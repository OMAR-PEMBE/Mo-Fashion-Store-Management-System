<?php

namespace App\Messaging;

use App\Models\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Development and testing: writes the message to the application log instead of sending it. */
class LogGateway implements Gateway
{
    public function __construct(private string $channel) {}

    public function name(): string
    {
        return 'log';
    }

    public function send(Message $message): ?string
    {
        Log::info('['.$this->channel.':log] message #'.$message->id.' to +'.$message->recipient, ['body' => $message->body, 'template' => $message->template]);

        return 'log-'.Str::lower(Str::random(12));
    }
}
