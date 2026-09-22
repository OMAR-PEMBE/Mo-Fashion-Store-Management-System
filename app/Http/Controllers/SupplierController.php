<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Services\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Supplier::class);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:191'], 'status' => ['nullable', Rule::in(['active', 'inactive'])]]);
        $query = Supplier::query();
        $search = $filters['q'] ?? '';
        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                foreach (['name', 'supplier_code', 'contact_person', 'phone', 'email'] as $field) {
                    $query->orWhere($field, 'like', '%'.$search.'%');
                }
            });
        }
        if ($status = $filters['status'] ?? null) {
            $query->where('is_active', $status === 'active');
        }
        $suppliers = $query->orderBy('name')->orderBy('id')->paginate(15)->withQueryString();

        return view('suppliers.index', compact('suppliers', 'filters'));
    }

    public function create(): View
    {
        Gate::authorize('create', Supplier::class);

        return view('suppliers.form', ['supplier' => new Supplier]);
    }

    public function store(Request $request, SupplierService $service): RedirectResponse
    {
        Gate::authorize('create', Supplier::class);
        $supplier = $service->save($request->all());

        return redirect()->route('suppliers.show', $supplier)->with('status', 'Supplier created.');
    }

    public function show(Supplier $supplier): View
    {
        Gate::authorize('view', $supplier);

        $purchases = Gate::allows('purchases.manage') ? $supplier->purchases()->orderByDesc('id')->paginate(10) : null;

        return view('suppliers.show', compact('supplier', 'purchases'));
    }

    public function edit(Supplier $supplier): View
    {
        Gate::authorize('update', $supplier);

        return view('suppliers.form', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier, SupplierService $service): RedirectResponse
    {
        Gate::authorize('update', $supplier);
        $service->save($request->all(), $supplier);

        return redirect()->route('suppliers.show', $supplier)->with('status', 'Supplier updated.');
    }
}
