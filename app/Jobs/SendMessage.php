<?php

namespace App\Jobs;

use App\Messaging\Messenger;
use App\Messaging\PermanentFailure;
use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends one outbox message. Temporary problems are retried (see messaging.retry_backoff);
 * a permanent refusal, or running out of retries, marks the message "failed" so staff can
 * fix the number and resend. The sale itself is never affected.
 */
class SendMessage implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $messageId) {}

    public function tries(): int
    {
        return count(config('messaging.retry_backoff')) + 1;
    }

    public function backoff(): array
    {
        return config('messaging.retry_backoff');
    }

    public function handle(Messenger $messenger): void
    {
        $message = Message::find($this->messageId);
        if (! $message || $message->status !== 'queued') {
            return; // Already sent, failed for good, or removed: never send twice.
        }
        $gateway = $messenger->gateway($message->channel);
        $message->forceFill(['attempts' => $message->attempts + 1, 'provider' => $gateway->name()])->save();

        try {
            $providerId = $gateway->send($message);
        } catch (PermanentFailure $exception) {
            $this->markFailed($message, $exception);

            return;
        } catch (Throwable $exception) {
            $message->forceFill(['error' => Str::limit($exception->getMessage(), 1000)])->save();
            // The sync queue (tests, or a server without a worker) cannot retry, and must not break the page.
            if ($this->job === null || $this->job->getConnectionName() === 'sync' || $this->attempts() >= $this->tries()) {
                $this->markFailed($message, $exception);

                return;
            }
            throw $exception;
        }
        $message->forceFill(['status' => 'sent', 'provider_message_id' => $providerId, 'sent_at' => now(), 'error' => null])->save();
    }

    public function failed(?Throwable $exception): void
    {
        if (($message = Message::find($this->messageId)) && $message->status === 'queued') {
            $this->markFailed($message, $exception);
        }
    }

    private function markFailed(Message $message, ?Throwable $exception): void
    {
        $message->forceFill(['status' => 'failed', 'failed_at' => now(), 'error' => Str::limit($exception?->getMessage() ?? 'Unknown error', 1000)])->save();
    }
}
