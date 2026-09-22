<?php

namespace App\Http\Controllers;

use App\Enums\ReferenceType;
use App\Services\ReferenceDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReferenceDataController extends Controller
{
    public function index(Request $request, ReferenceType $type): View
    {
        Gate::authorize('viewAny', $type->model());
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);
        $query = $type->model()::query();
        if ($search = $filters['q'] ?? null) {
            $query->where(fn ($query) => $query->where('name', 'like', '%'.$search.'%')
                ->orWhere($type->identifier(), 'like', '%'.$search.'%'));
        }
        if ($status = $filters['status'] ?? null) {
            $query->where('is_active', $status === 'active');
        }
        if ($type === ReferenceType::Sizes) {
            $query->orderBy('sort_order');
        }
        $records = $query->orderBy('name')->orderBy('id')->paginate(15)->withQueryString();

        return view('reference-data.index', compact('type', 'records', 'filters'));
    }

    public function create(ReferenceType $type): View
    {
        Gate::authorize('create', $type->model());
        $model = $type->model();

        return view('reference-data.form', ['type' => $type, 'record' => new $model]);
    }

    public function store(Request $request, ReferenceType $type, ReferenceDataService $service): RedirectResponse
    {
        Gate::authorize('create', $type->model());
        $service->save($type, $request->all());

        return redirect()->route('reference.index', $type->value)->with('status', ucfirst($type->singular()).' created.');
    }

    public function edit(ReferenceType $type, int $record): View
    {
        $record = $type->model()::findOrFail($record);
        Gate::authorize('update', $record);

        return view('reference-data.form', compact('type', 'record'));
    }

    public function update(Request $request, ReferenceType $type, int $record, ReferenceDataService $service): RedirectResponse
    {
        $record = $type->model()::findOrFail($record);
        Gate::authorize('update', $record);
        $service->save($type, $request->all(), $record);

        return redirect()->route('reference.index', $type->value)->with('status', ucfirst($type->singular()).' updated.');
    }
}
