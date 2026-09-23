<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:191'], 'payment_method' => ['nullable', Rule::in(array_keys(SaleService::PAYMENT_METHODS))],
            'date_from' => ['nullable', 'date_format:Y-m-d'], 'date_to' => ['nullable', 'date_format:Y-m-d']]);
        $sales = Sale::with(['customer', 'salesperson'])->when(! $request->user()->hasPermission('sales.view_all'), fn ($q) => $q->where('salesperson_id', $request->user()->id))
            ->when($filters['q'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->where('sale_number', 'like', '%'.$search.'%')->orWhereHas('customer', fn ($q) => $q->where('full_name', 'like', '%'.$search.'%'))))
            ->when($filters['payment_method'] ?? null, fn ($q, $value) => $q->where('payment_method', $value))
            ->when($filters['date_from'] ?? null, fn ($q, $value) => $q->where('sale_date', '>=', $value.' 00:00:00'))
            ->when($filters['date_to'] ?? null, fn ($q, $value) => $q->where('sale_date', '<=', $value.' 23:59:59'))
            ->orderByDesc('id')->paginate(15)->withQueryString();

        return view('sales.index', compact('sales', 'filters'));
    }

    public function create()
    {
        $raw = old('items', []);
        $raw = is_array($raw) ? array_slice($raw, 0, 100) : [];
        $ids = collect($raw)->filter(fn ($i) => is_array($i))->pluck('product_variant_id')->filter(fn ($id) => is_scalar($id) && ctype_digit((string) $id));
        $variants = ProductVariant::withTrashed()->with('product')->whereIn('id', $ids)->get()->keyBy('id');
        $lines = [];
        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }
            $id = is_scalar($item['product_variant_id'] ?? null) ? (string) $item['product_variant_id'] : '';
            $line = ['product_variant_id' => $id, 'label' => isset($variants[$id]) ? $variants[$id]->sku.' · '.$variants[$id]->product->name : 'Unknown variant'];
            foreach (['quantity', 'unit_price', 'discount_amount'] as $field) {
                $line[$field] = is_scalar($item[$field] ?? null) ? (string) $item[$field] : '0';
            }
            $lines[] = $line;
        }
        $customerId = old('customer_id');
        $customer = is_scalar($customerId) ? Customer::find($customerId) : null;
        $selectedCustomer = ['id' => $customer?->id ?? '', 'label' => $customer?->full_name ?? 'Walk-in'];
        $requestKey = old('request_key', (string) Str::uuid());

        return view('sales.pos', compact('lines', 'selectedCustomer', 'requestKey'));
    }

    public function lookup(Request $request)
    {
        $data = $request->validate(['kind' => ['required', Rule::in(['variant', 'customer'])], 'q' => ['nullable', 'string', 'max:191']]);
        $search = $data['q'] ?? '';
        if ($data['kind'] === 'customer') {
            $digits = preg_replace('/\D/', '', $search);

            return Customer::where('is_active', true)->where(fn ($q) => $q->where('full_name', 'like', '%'.$search.'%')->orWhere('customer_code', 'like', '%'.$search.'%')
                ->when($digits !== '', fn ($q) => $q->orWhere('phone', 'like', '%'.$digits.'%')->orWhere('whatsapp_number', 'like', '%'.$digits.'%')))
                ->orderBy('full_name')->limit(20)->get()->map(fn ($customer) => ['id' => $customer->id, 'label' => $customer->full_name.' · '.$customer->customer_code]);
        }

        return ProductVariant::available()->whereHas('product', fn ($q) => $q->available()->whereNull('deleted_at'))
            ->with(['product', 'size', 'colour', 'inventory'])->where(fn ($q) => $q->where('sku', 'like', '%'.$search.'%')->orWhereHas('product', fn ($q) => $q->where('name', 'like', '%'.$search.'%')))
            ->orderBy('sku')->limit(20)->get()->map(fn ($v) => ['id' => $v->id, 'label' => $v->sku.' · '.$v->product->name.' · '.($v->size?->name ?? 'One size').' / '.($v->colour?->name ?? 'No colour'), 'unit_price' => $v->selling_price, 'available' => $v->inventory?->available_quantity ?? 0]);
    }

    public function review(Request $request, SaleService $service)
    {
        $data = $service->validate($request->all());
        $variants = ProductVariant::withTrashed()->with('product')->whereIn('id', array_column($data['items'], 'product_variant_id'))->get()->keyBy('id');
        abort_unless($variants->count() === count($data['items']), 422, 'A cart variant no longer exists.');
        $customer = $data['customer_id'] ? Customer::where('is_active', true)->find($data['customer_id']) : null;
        abort_if($data['customer_id'] && ! $customer, 422, 'Choose an active customer.');
        $totals = $service->calculateTotals($data['items']);
        // Preserve the reviewed cart when returning to edit; completion still validates it anew.
        $request->session()->flashInput($data);

        return view('sales.review', compact('data', 'variants', 'customer', 'totals'));
    }

    public function store(Request $request, SaleService $service)
    {
        try {
            $sale = $service->completeSale($request->all(), $request->user());
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 409 || $request->expectsJson()) {
                throw $exception;
            }
            $request->session()->flashInput($request->except('_token'));

            return response()->view('sales.conflict', ['message' => $exception->getMessage()], 409);
        }

        return redirect()->route('sales.show', $sale)->with('status', 'Sale completed. Stock and customer history updated.');
    }

    public function show(Request $request, Sale $sale)
    {
        abort_unless($request->user()->hasPermission('sales.view_all') || $sale->salesperson_id === $request->user()->id, 403);
        $sale->load(['customer', 'salesperson', 'items.variant.product', 'returns', 'refunds', 'exchanges']);

        return view('sales.show', compact('sale'));
    }

    public function cancel(Request $request, Sale $sale, SaleService $service)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $service->cancelSale($sale, $request->user(), $data['reason']);
    }
}
