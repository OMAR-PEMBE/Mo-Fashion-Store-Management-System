<?php

namespace App\Messaging;

use InvalidArgumentException;

/** Picks the gateway for a channel from config/messaging.php, so going live is an .env change. */
class Messenger
{
    public function gateway(string $channel): Gateway
    {
        $driver = config("messaging.$channel.driver");

        return match ([$channel, $driver]) {
            ['whatsapp', 'cloud'] => new WhatsAppCloudGateway(config('messaging.whatsapp.cloud')),
            ['whatsapp', 'log'], ['sms', 'log'] => new LogGateway($channel),
            default => throw new InvalidArgumentException("No \"$driver\" driver for $channel messages."),
        };
    }
}
