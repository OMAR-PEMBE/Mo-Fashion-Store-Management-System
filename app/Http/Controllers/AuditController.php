<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditService;
use App\Support\AuditLabels;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditController extends Controller
{
    public function index(Request $request, AuditService $service)
    {
        $service->authorize($request->user());
        $filters = $request->validate([
            'user_id' => ['nullable', 'integer', 'min:1'], 'action' => ['nullable', 'string', 'max:100'],
            'entity_type' => ['nullable', 'string', 'max:150'], 'entity_id' => ['nullable', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date_format:Y-m-d'], 'date_to' => array_filter(['nullable', 'date_format:Y-m-d', $request->filled('date_from') ? 'after_or_equal:date_from' : null]),
        ]);
        $query = DB::table('audit_logs')->leftJoin('users', 'users.id', '=', 'audit_logs.user_id');
        foreach (['user_id', 'action', 'entity_type', 'entity_id'] as $field) {
            $query->when($filters[$field] ?? null, fn ($q, $value) => $q->where('audit_logs.'.$field, $value));
        }
        $query->when($filters['date_from'] ?? null, fn ($q, $value) => $q->where('audit_logs.created_at', '>=', $value.' 00:00:00'));
        $query->when($filters['date_to'] ?? null, fn ($q, $value) => $q->where('audit_logs.created_at', '<', CarbonImmutable::parse($value)->addDay()->format('Y-m-d').' 00:00:00'));

        return view('audit.index', ['entries' => $query->select('audit_logs.id', 'audit_logs.action', 'audit_logs.entity_type', 'audit_logs.entity_id', 'audit_logs.created_at', 'users.name as actor_name')->orderByDesc('audit_logs.id')->paginate(30)->withQueryString(),
            'filters' => $filters, 'users' => User::orderBy('name')->get(['id', 'name']),
            'actions' => DB::table('audit_logs')->distinct()->orderBy('action')->pluck('action'),
            'types' => DB::table('audit_logs')->distinct()->orderBy('entity_type')->pluck('entity_type')]);
    }

    public function show(Request $request, int $audit, AuditService $service)
    {
        $service->authorize($request->user());
        $entry = DB::table('audit_logs')->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')->where('audit_logs.id', $audit)->select('audit_logs.*', 'users.name as actor_name')->first();
        abort_unless($entry, 404);
        $decoded = [];
        foreach (['old_values', 'new_values'] as $field) {
            $value = json_decode($entry->$field ?? 'null', true);
            $decoded[$field] = is_array($value) ? $service->redact($value) : null;
        }
        // One row per field, before and after side by side, so a change reads at a glance.
        $show = fn ($value) => match (true) {
            $value === null => null,
            is_bool($value) => $value ? 'Yes' : 'No',
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            default => (string) $value,
        };
        $rows = [];
        foreach (array_unique(array_merge(array_keys($decoded['old_values'] ?? []), array_keys($decoded['new_values'] ?? []))) as $key) {
            $before = $show($decoded['old_values'][$key] ?? null);
            $after = $show($decoded['new_values'][$key] ?? null);
            $rows[] = ['field' => (string) $key, 'before' => AuditLabels::value((string) $key, $before), 'after' => AuditLabels::value((string) $key, $after),
                'changed' => $decoded['old_values'] !== null && $before !== $after];
        }
        $hasBefore = $decoded['old_values'] !== null;

        return view('audit.show', compact('entry', 'rows', 'hasBefore'));
    }
}
