<?php

namespace App\Http\Controllers;

use App\Enums\PurchaseStatus;
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
        $counts = (clone $query)->toBase()->selectRaw('is_active, count(*) as total')->groupBy('is_active')->pluck('total', 'is_active');
        $counts = ['active' => (int) ($counts[1] ?? 0), 'inactive' => (int) ($counts[0] ?? 0)];
        if ($status = $filters['status'] ?? null) {
            $query->where('is_active', $status === 'active');
        }
        // Received purchases only: drafts and cancelled drafts never reached stock.
        $received = fn ($q) => $q->where('status', PurchaseStatus::Confirmed);
        $suppliers = $query->withCount(['purchases as received_count' => $received])
            ->withSum(['purchases as received_total' => $received], 'total_amount')
            ->withMax(['purchases as last_purchase_date' => $received], 'purchase_date')
            ->orderBy('name')->orderBy('id')->paginate(15)->withQueryString();

        return view('suppliers.index', compact('suppliers', 'filters', 'counts'));
    }

    public function create(): View
    {
        Gate::authorize('create', Supplier::class);

        return view('suppliers.form', ['supplier' => new Supplier(['is_active' => true, 'supplier_code' => $this->nextCode()])]);
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

        $purchases = $summary = null;
        if (Gate::allows('purchases.manage')) {
            $purchases = $supplier->purchases()->withSum('items', 'quantity')->orderByDesc('purchase_date')->orderByDesc('id')->paginate(10);
            $received = $supplier->purchases()->where('status', PurchaseStatus::Confirmed);
            $summary = ['total' => (clone $received)->sum('total_amount'), 'count' => (clone $received)->count(),
                'last' => (clone $received)->max('purchase_date'), 'drafts' => $supplier->purchases()->where('status', PurchaseStatus::Draft)->count()];
        }

        return view('suppliers.show', compact('supplier', 'purchases', 'summary'));
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

    /** Suggests the next SUP-001 style code; the owner can still type their own. */
    private function nextCode(): string
    {
        $numbers = Supplier::withTrashed()->where('supplier_code', 'like', 'SUP-%')->pluck('supplier_code')
            ->map(fn ($code) => preg_match('/^SUP-(\d+)$/', $code, $m) ? $m[1] : null)->filter();
        // Keep the store's own padding (SUP-0001 stays four digits), at least three.
        $width = max(3, (int) $numbers->map(fn ($digits) => strlen($digits))->max());

        return 'SUP-'.str_pad((string) ((int) $numbers->map(fn ($digits) => (int) $digits)->max() + 1), $width, '0', STR_PAD_LEFT);
    }
}
