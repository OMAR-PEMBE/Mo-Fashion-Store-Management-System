<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AuditService
{
    public function authorize(User $actor): void
    {
        $actor = $actor->fresh();
        abort_unless($actor?->canAccessWorkspace() && $actor->role->slug === 'administrator' && ! $actor->must_change_password, 403);
        Gate::forUser($actor)->authorize('audit.view');
    }

    public function record(?User $actor, string $action, string $type, ?int $id, ?array $before, array $after): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $actor?->id, 'action' => $action, 'entity_type' => $type, 'entity_id' => $id,
            'old_values' => $before === null ? null : json_encode($this->redact($before), JSON_THROW_ON_ERROR),
            'new_values' => json_encode($this->redact($after), JSON_THROW_ON_ERROR),
            'ip_address' => app()->runningInConsole() ? null : request()->ip(), 'created_at' => now(),
        ]);
    }

    public function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            if (preg_match('/password|token|secret|authorization|cookie|api.?key/i', (string) $key)) {
                $values[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $values[$key] = $this->redact($value);
            }
        }

        return $values;
    }
}
