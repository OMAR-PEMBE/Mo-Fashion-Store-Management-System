<?php

namespace App\Messaging;

use App\Models\Message;

/**
 * A way of delivering a message (Meta's WhatsApp Cloud API, an SMS provider, or the log).
 * send() returns the provider's message id. It throws PermanentFailure when retrying cannot
 * help (a wrong number, a rejected template) and any other exception for temporary problems.
 */
interface Gateway
{
    public function name(): string;

    public function send(Message $message): ?string;
}
