<?php

namespace App\Support;

use Illuminate\Encryption\Encrypter;

class ProductionSecurity
{
    public function failures(): array
    {
        $key = (string) config('app.key');
        $key = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
        $checks = [
            'APP_ENV must be production.' => app()->environment('production'),
            'APP_DEBUG must be false.' => config('app.debug') === false,
            'APP_URL must be a valid HTTPS URL without credentials.' => filter_var(config('app.url'), FILTER_VALIDATE_URL)
                && parse_url(config('app.url'), PHP_URL_SCHEME) === 'https'
                && ! parse_url(config('app.url'), PHP_URL_USER) && ! parse_url(config('app.url'), PHP_URL_PASS),
            'A valid application encryption key is required.' => is_string($key) && Encrypter::supported($key, config('app.cipher')),
            'Secure session cookies must be enabled.' => config('session.secure') === true,
            'HTTP-only session cookies must be enabled.' => config('session.http_only') === true,
            'Session SameSite must be lax or strict.' => in_array(config('session.same_site'), ['lax', 'strict'], true),
            'Use persistent server-side sessions.' => in_array(config('session.driver'), ['file', 'database', 'redis'], true),
            'Session serialization must use JSON.' => config('session.serialization') === 'json',
            'Configure a production mail transport; log/array mail exposes reset links.' => $this->safeMailer(config('mail.default')),
        ];

        return array_keys(array_filter($checks, fn ($safe) => ! $safe));
    }

    private function safeMailer(?string $name, array $visited = []): bool
    {
        if (! $name || in_array($name, $visited, true)) {
            return false;
        }
        $mailer = config('mail.mailers.'.$name, []);
        $transport = $mailer['transport'] ?? null;
        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            $children = $mailer['mailers'] ?? [];

            return $children !== [] && collect($children)->every(fn ($child) => $this->safeMailer($child, [...$visited, $name]));
        }

        return in_array($transport, ['smtp', 'sendmail', 'ses', 'ses-v2', 'postmark', 'resend'], true);
    }
}
