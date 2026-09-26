<?php

namespace App\Http\Controllers;

use App\Enums\PurchaseStatus;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\PurchaseService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:191'], 'status' => ['nullable', Rule::enum(PurchaseStatus::class)],
            'supplier_id' => ['nullable', 'integer'], 'payment_status' => ['nullable', Rule::in(['PAID', 'PARTIALLY_PAID', 'UNPAID'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'], 'date_to' => ['nullable', 'date_format:Y-m-d']]);
        $visible = Purchase::query()
            ->when($filters['q'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->where('purchase_number', 'like', '%'.$search.'%')->orWhere('supplier_invoice_number', 'like', '%'.$search.'%')->orWhereHas('supplier', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))))
            ->when($filters['supplier_id'] ?? null, fn ($q, $value) => $q->where('supplier_id', $value))
            ->when($filters['payment_status'] ?? null, fn ($q, $value) => $q->where('payment_status', $value))
            ->when($filters['date_from'] ?? null, fn ($q, $value) => $q->where('purchase_date', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($q, $value) => $q->where('purchase_date', '<=', $value));
        $counts = (clone $visible)->toBase()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')->map(fn ($n) => (int) $n);
        $purchases = (clone $visible)->with('supplier')->withSum('items', 'quantity')
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value))->orderByDesc('id')->paginate(15)->withQueryString();

        return view('purchases.index', compact('purchases', 'filters', 'counts'));
    }

    public function lookup(Request $request)
    {
        $data = $request->validate(['kind' => ['required', Rule::in(['supplier', 'variant'])], 'q' => ['nullable', 'string', 'max:191']]);
        $search = $data['q'] ?? '';
        if ($data['kind'] === 'supplier') {
            return Supplier::where('is_active', true)->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('supplier_code', 'like', '%'.$search.'%'))
                ->orderBy('name')->orderBy('id')->limit(20)->get()->map(fn ($s) => ['id' => $s->id, 'label' => $s->name.' · '.$s->supplier_code]);
        }

        return ProductVariant::available()->whereHas('product', fn ($q) => $q->available()->whereNull('deleted_at'))->with(['product', 'size', 'colour'])
            ->where(fn ($q) => $q->where('sku', 'like', '%'.$search.'%')->orWhereHas('product', fn ($q) => $q->where('name', 'like', '%'.$search.'%')))
            ->orderBy('sku')->limit(20)->get()->map(fn ($v) => ['id' => $v->id, 'label' => $this->variantLabel($v)]);
    }

    public function create(Request $request)
    {
        return $this->form($request, new Purchase);
    }

    public function edit(Request $request, Purchase $purchase)
    {
        abort_unless($purchase->status === PurchaseStatus::Draft, 409, 'Only drafts can be edited.');

        return $this->form($request, $purchase);
    }

    private function form(Request $request, Purchase $purchase)
    {
        $purchase->load('items.variant.product', 'items.variant.size', 'items.variant.colour', 'supplier');
        $rawItems = old('items', $purchase->items->map(fn ($i) => $i->only(['product_variant_id', 'quantity', 'unit_cost']))->all());
        $rawItems = is_array($rawItems) ? array_slice($rawItems, 0, 100) : [];
        $ids = collect($rawItems)->filter(fn ($i) => is_array($i))->pluck('product_variant_id')->filter(fn ($id) => is_scalar($id) && ctype_digit((string) $id));
        $variants = ProductVariant::withTrashed()->with(['product', 'size', 'colour'])->whereIn('id', $ids)->get()->keyBy('id');
        $lines = [];
        foreach ($rawItems as $item) {
            if (! is_array($item)) {
                continue;
            }
            $id = is_scalar($item['product_variant_id'] ?? null) ? (string) $item['product_variant_id'] : '';
            $variant = $variants->get($id);
            $lines[] = ['product_variant_id' => $id, 'label' => $variant ? $this->variantLabel($variant) : '',
                'quantity' => is_scalar($item['quantity'] ?? null) ? (string) $item['quantity'] : '',
                'unit_cost' => is_scalar($item['unit_cost'] ?? null) ? (string) $item['unit_cost'] : ''];
        }
        $supplierId = old('supplier_id', $purchase->supplier_id);
        $supplier = is_scalar($supplierId) ? Supplier::withTrashed()->find($supplierId) : null;
        $selectedSupplier = ['id' => $supplier?->id ?? '', 'label' => $supplier ? $supplier->name.' · '.$supplier->supplier_code : ''];

        return view('purchases.form', compact('purchase', 'lines', 'selectedSupplier'));
    }

    public function store(Request $request, PurchaseService $service)
    {
        $purchase = $service->createDraft($request->all(), $request->user());

        return redirect()->route('purchases.show', $purchase)->with('status', 'Draft saved. Check it, then receive the stock when the goods arrive.');
    }

    public function update(Request $request, Purchase $purchase, PurchaseService $service)
    {
        $service->updateDraft($purchase, $request->all(), $request->user(), $this->revision($request));

        return redirect()->route('purchases.show', $purchase)->with('status', 'Draft updated.');
    }

    public function show(Purchase $purchase)
    {
        $purchase->load(['supplier', 'items.variant.product', 'items.variant.size', 'items.variant.colour', 'creator', 'confirmer']);

        return view('purchases.show', compact('purchase'));
    }

    public function confirm(Request $request, Purchase $purchase, PurchaseService $service)
    {
        $service->confirm($purchase, $request->user(), $this->revision($request));

        return redirect()->route('purchases.show', $purchase)->with('status', 'Stock received. Quantities and average costs are updated.');
    }

    public function cancel(Request $request, Purchase $purchase, PurchaseService $service)
    {
        $service->cancelDraft($purchase, $request->user(), $this->revision($request));

        return redirect()->route('purchases.show', $purchase)->with('status', 'Draft cancelled. No stock was changed.');
    }

    private function revision(Request $request): int
    {
        return (int) $request->validate(['revision' => ['required', 'integer', 'min:1']])['revision'];
    }

    private function variantLabel(ProductVariant $variant): string
    {
        return $variant->sku.' · '.$variant->product->name.' · '.($variant->size?->name ?? 'One size').' / '.($variant->colour?->name ?? 'No colour');
    }
}
