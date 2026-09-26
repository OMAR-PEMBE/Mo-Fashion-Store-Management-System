<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One WhatsApp message or SMS in the outbox, with its delivery state. */
class Message extends Model
{
    public const STATUS_LABELS = ['queued' => 'Waiting to send', 'sent' => 'Sent', 'delivered' => 'Delivered', 'read' => 'Read', 'failed' => 'Not sent'];

    protected $fillable = ['channel', 'purpose', 'recipient', 'customer_id', 'sale_id', 'template', 'parameters', 'body',
        'status', 'provider', 'provider_message_id', 'dedupe_key', 'attempts', 'error', 'created_by', 'sent_at', 'delivered_at', 'failed_at'];

    protected function casts(): array
    {
        return ['parameters' => 'array', 'attempts' => 'integer', 'sent_at' => 'datetime', 'delivered_at' => 'datetime', 'failed_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            'sent', 'delivered', 'read' => 'success',
            'failed' => 'danger',
            default => 'warning',
        };
    }

    /** "+255 755 123 456" for screens. */
    public function recipientDisplay(): string
    {
        return \App\Support\Phone::display('+'.$this->recipient) ?? $this->recipient;
    }
}
