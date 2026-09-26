<?php

namespace App\Services;

use App\Jobs\SendMessage;
use App\Models\Message;
use App\Models\Sale;
use App\Models\User;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Puts customer messages in the outbox and hands them to the queue. Nothing here talks to a
 * provider directly, so a sale never waits on WhatsApp or SMS.
 */
class MessageService
{
    /** Digits for WhatsApp/SMS ("0755 123 456" → "255755123456"), or null when it cannot be a phone number. */
    public function recipient(?string $number): ?string
    {
        $digits = Phone::international($number);

        return $digits !== null && preg_match('/^[1-9][0-9]{9,14}$/', $digits) ? $digits : null;
    }

    /** A private, expiring link to the customer's receipt; no sign-in needed. */
    public function receiptLink(Sale $sale): string
    {
        return URL::temporarySignedRoute('receipts.public', now()->addDays(config('messaging.receipt_link_days')), ['sale' => $sale->id]);
    }

    /**
     * Queue the WhatsApp receipt for a sale. The automatic send after a sale is de-duplicated,
     * so a double-tapped "Complete sale" never sends two receipts; $resend always sends again.
     */
    public function queueReceipt(Sale $sale, ?string $number, ?User $actor, bool $resend = false, string $field = 'receipt_whatsapp'): Message
    {
        $recipient = $this->recipient($number);
        if ($recipient === null) {
            throw ValidationException::withMessages([$field => 'Enter a WhatsApp number such as 0755 123 456.']);
        }
        $key = $resend ? null : 'receipt:sale:'.$sale->id;
        if ($key && ($existing = Message::where('dedupe_key', $key)->first())) {
            return $existing;
        }
        $sale->loadMissing('customer');
        $name = Str::of($sale->customer?->full_name ?? '')->before(' ')->trim()->toString() ?: 'Customer';
        $summary = $sale->sale_number.' · '.Money::format($sale->total_amount);
        $link = $this->receiptLink($sale);
        try {
            $message = Message::create([
                'channel' => 'whatsapp', 'purpose' => 'receipt', 'recipient' => $recipient,
                'customer_id' => $sale->customer_id, 'sale_id' => $sale->id,
                'template' => config('messaging.whatsapp.receipt_template'), 'parameters' => [$name, $summary, $link],
                // Same words as the approved template (docs/MESSAGING.md), so the log shows what the customer sees.
                'body' => "Asante $name! Your receipt $summary is ready: $link Thank you for shopping with us.",
                'status' => 'queued', 'provider' => config('messaging.whatsapp.driver'), 'dedupe_key' => $key, 'created_by' => $actor?->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            return Message::where('dedupe_key', $key)->firstOrFail(); // the same receipt was queued a moment ago
        }
        SendMessage::dispatch($message->id)->afterCommit();

        return $message;
    }
}
