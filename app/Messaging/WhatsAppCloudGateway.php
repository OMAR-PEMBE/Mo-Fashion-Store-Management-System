<?php

namespace App\Messaging;

use App\Models\Message;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Meta's WhatsApp Cloud API. Business-started messages (receipts, marketing) must use a
 * template approved in WhatsApp Manager; its body values are sent in order.
 * https://developers.facebook.com/docs/whatsapp/cloud-api/guides/send-message-templates
 */
class WhatsAppCloudGateway implements Gateway
{
    public function __construct(private array $config) {}

    public function name(): string
    {
        return 'whatsapp_cloud';
    }

    public function send(Message $message): ?string
    {
        if (blank($this->config['phone_number_id'] ?? null) || blank($this->config['access_token'] ?? null)) {
            throw new PermanentFailure('WhatsApp is not connected yet: set WHATSAPP_PHONE_NUMBER_ID and WHATSAPP_ACCESS_TOKEN.');
        }
        $payload = ['messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $message->recipient];
        $payload += $message->template
            ? ['type' => 'template', 'template' => [
                'name' => $message->template,
                'language' => ['code' => config('messaging.whatsapp.receipt_language')],
                'components' => [['type' => 'body', 'parameters' => array_map(fn ($value) => ['type' => 'text', 'text' => (string) $value], $message->parameters ?? [])]],
            ]]
            : ['type' => 'text', 'text' => ['body' => $message->body, 'preview_url' => true]];
        $url = rtrim($this->config['graph_url'], '/').'/'.$this->config['api_version'].'/'.$this->config['phone_number_id'].'/messages';

        try {
            $response = Http::withToken($this->config['access_token'])->acceptJson()->timeout($this->config['timeout'] ?? 15)->post($url, $payload);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Could not reach WhatsApp: '.$exception->getMessage(), 0, $exception);
        }
        if ($response->successful()) {
            return $response->json('messages.0.id');
        }
        $error = $response->json('error.message') ?? $response->body();
        // 429 and 5xx are worth retrying; other 4xx (bad number, unapproved template, bad token) are not.
        if ($response->status() === 429 || $response->serverError()) {
            throw new RuntimeException('WhatsApp is busy ('.$response->status().'): '.$error);
        }
        throw new PermanentFailure('WhatsApp refused the message ('.$response->status().'): '.$error);
    }
}
