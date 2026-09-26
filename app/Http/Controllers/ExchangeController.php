<?php

namespace App\Http\Controllers;

use App\Enums\ExchangeStatus;
use App\Models\Exchange;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Services\ExchangeService;
use App\Services\ReturnService;
use App\Support\RecentSales;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ExchangeController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:191'], 'status' => ['nullable', Rule::enum(ExchangeStatus::class)]]);
        $visible = Exchange::query()->when(! $request->user()->hasPermission('sales.view_all'), fn ($q) => $q->whereHas('sale', fn ($q) => $q->where('salesperson_id', $request->user()->id)))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q->where('exchange_number', 'like', '%'.$term.'%')->orWhereHas('sale', fn ($q) => $q->where('sale_number', 'like', '%'.$term.'%'))));
        $counts = (clone $visible)->toBase()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')->map(fn ($n) => (int) $n);
        $exchanges = (clone $visible)->with(['sale.customer' => fn ($q) => $q->withTrashed()])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))->latest('id')->paginate(15)->withQueryString();

        return view('exchanges.index', compact('exchanges', 'filters', 'counts'));
    }

    public function create(Request $request, ExchangeService $service)
    {
        $data = $request->validate(['sale_number' => ['nullable', 'string', 'max:100']]);
        $sale = null;
        $remaining = [];
        $lines = [];
        $deadline = null;
        if ($data['sale_number'] ?? null) {
            $sale = Sale::where('sale_number', $data['sale_number'])->when(! $request->user()->hasPermission('sales.view_all'), fn ($q) => $q->where('salesperson_id', $request->user()->id))->firstOrFail();
            $service->authorize($request->user(), $sale);
            $service->eligible($sale);
            $sale->load(['items.variant.product', 'items.variant.size', 'items.variant.colour']);
            $remaining = app(ReturnService::class)->remaining($sale);
            $deadline = $service->deadline($sale);
            $raw = old('replacement_items', []);
            foreach (is_array($raw) ? array_slice($raw, 0, 100) : [] as $item) {
                if (! is_array($item) || ! is_scalar($item['product_variant_id'] ?? null) || ! ctype_digit((string) $item['product_variant_id'])) {
                    continue;
                }
                $variant = ProductVariant::withTrashed()->with(['product', 'size', 'colour'])->find($item['product_variant_id']);
                if ($variant) {
                    $lines[] = ['product_variant_id' => $variant->id, 'label' => $variant->sku.' · '.$variant->product->name, 'name' => $variant->product->name, 'variant' => collect([$variant->size?->name, $variant->colour?->name])->filter()->join(' · '), 'sku' => $variant->sku, 'quantity' => is_scalar($item['quantity'] ?? null) ? (string) $item['quantity'] : '1', 'unit_price' => $variant->selling_price];
                }
            }
        }
        $requestKey = old('request_key', (string) Str::uuid());

        $recentSales = $sale ? collect() : RecentSales::for($request->user(), (int) config('exchanges.window_days') + 1, fn ($recent) => $service->deadline($recent));

        return view('exchanges.create', compact('sale', 'remaining', 'deadline', 'lines', 'requestKey', 'recentSales'));
    }

    public function store(Request $request, ExchangeService $service)
    {
        $exchange = $service->create($request->all(), $request->user());

        return redirect()->route('exchanges.show', $exchange)->with('status', 'Exchange saved for review. Stock has not changed.');
    }

    public function show(Request $request, Exchange $exchange, ExchangeService $service)
    {
        $service->authorize($request->user(), $exchange->sale);
        $exchange->load(['items.variant.product', 'items.variant.size', 'items.variant.colour', 'refund']);
        $deadline = $service->deadline($exchange->sale);

        return view('exchanges.show', compact('exchange', 'deadline'));
    }

    public function complete(Request $request, Exchange $exchange, ExchangeService $service)
    {
        $service->complete($exchange, $request->all(), $request->user());

        return back()->with('status', 'Exchange completed. Stock and settlement history recorded.');
    }

    public function cancel(Request $request, Exchange $exchange, ExchangeService $service)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $service->cancel($exchange, $request->user(), $data['reason']);

        return back()->with('status', 'Pending exchange cancelled. Stock unchanged.');
    }
}
