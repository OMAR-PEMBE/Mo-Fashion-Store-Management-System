<?php

namespace App\Http\Controllers;

use App\Enums\ReferenceType;
use App\Services\ReferenceDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $counts = (clone $query)->toBase()->selectRaw('is_active, count(*) as total')->groupBy('is_active')->pluck('total', 'is_active');
        $counts = ['active' => (int) ($counts[1] ?? 0), 'inactive' => (int) ($counts[0] ?? 0)];
        if ($status = $filters['status'] ?? null) {
            $query->where('is_active', $status === 'active');
        }
        if ($type === ReferenceType::Sizes) {
            $query->orderBy('sort_order');
        }
        $records = $query->orderBy('name')->orderBy('id')->paginate(15)->withQueryString();

        // How many products (categories) or sizes and colours of products (sizes, colours) use each entry.
        [$table, $column] = match ($type) {
            ReferenceType::Categories => ['products', 'category_id'],
            ReferenceType::Sizes => ['product_variants', 'size_id'],
            ReferenceType::Colours => ['product_variants', 'colour_id'],
        };
        $usage = DB::table($table)->whereNull('deleted_at')->whereIn($column, $records->pluck('id'))
            ->selectRaw($column.' as ref, count(*) as total')->groupBy($column)->pluck('total', 'ref');

        return view('reference-data.index', compact('type', 'records', 'filters', 'counts', 'usage'));
    }

    public function create(ReferenceType $type): View
    {
        Gate::authorize('create', $type->model());
        $model = $type->model();

        $record = new $model(['is_active' => true]);
        if ($type === ReferenceType::Sizes) {
            // New sizes go to the end of the list unless the owner moves them.
            $record->sort_order = ((int) $model::max('sort_order')) + 10;
        }

        return view('reference-data.form', compact('type', 'record'));
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
