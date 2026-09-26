<?php

namespace App\Http\Controllers;

use App\Enums\ReturnStatus;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Services\ReturnService;
use App\Support\RecentSales;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ReturnController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:191'], 'status' => ['nullable', Rule::enum(ReturnStatus::class)]]);
        $visible = SaleReturn::query()->when(! $request->user()->hasPermission('sales.view_all'), fn ($q) => $q->whereHas('sale', fn ($q) => $q->where('salesperson_id', $request->user()->id)))
            ->when($filters['q'] ?? null, fn ($q, $value) => $q->where(fn ($q) => $q->where('return_number', 'like', '%'.$value.'%')->orWhereHas('sale', fn ($q) => $q->where('sale_number', 'like', '%'.$value.'%'))));
        $counts = (clone $visible)->toBase()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')->map(fn ($n) => (int) $n);
        $returns = (clone $visible)->with(['sale.customer' => fn ($q) => $q->withTrashed()])->withSum('items', 'quantity')
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value))->latest('id')->paginate(15)->withQueryString();

        return view('returns.index', compact('returns', 'filters', 'counts'));
    }

    public function create(Request $request, ReturnService $service)
    {
        $data = $request->validate(['sale_number' => ['nullable', 'string', 'max:100']]);
        $sale = null;
        $remaining = [];
        $deadline = null;
        if ($data['sale_number'] ?? null) {
            $sale = Sale::where('sale_number', $data['sale_number'])->when(! $request->user()->hasPermission('sales.view_all'), fn ($q) => $q->where('salesperson_id', $request->user()->id))->firstOrFail();
            $service->authorize($request->user(), $sale);
            $service->checkEligibility($sale);
            $sale->load(['items.variant.product', 'items.variant.size', 'items.variant.colour']);
            $remaining = $service->remaining($sale);
            $deadline = $service->deadline($sale);
        }
        $requestKey = old('request_key', (string) Str::uuid());

        $recentSales = $sale ? collect() : RecentSales::for($request->user(), (int) config('returns.window_days') + 1, fn ($recent) => $service->deadline($recent));

        return view('returns.create', compact('sale', 'remaining', 'deadline', 'requestKey', 'recentSales'));
    }

    public function store(Request $request, ReturnService $service)
    {
        $return = $service->create($request->all(), $request->user());

        return redirect()->route('returns.show', $return)->with('status', 'Return saved for review. Stock has not changed.');
    }

    public function show(Request $request, SaleReturn $return, ReturnService $service)
    {
        $service->authorize($request->user(), $return->sale);
        $return->load(['items.variant.product', 'items.variant.size', 'items.variant.colour', 'processor', 'refunds']);
        $deadline = $service->deadline($return->sale);
        $eligible = $deadline && now()->lte($deadline);

        return view('returns.show', compact('return', 'deadline', 'eligible'));
    }

    public function approve(Request $request, SaleReturn $return, ReturnService $service)
    {
        $service->approve($return, $request->user());

        return back()->with('status', 'Return approved. Complete it to process the returned items.');
    }

    public function complete(Request $request, SaleReturn $return, ReturnService $service)
    {
        $service->complete($return, $request->user());
        // Say what actually happened: damaged or faulty items stay out of stock.
        $items = $return->items()->get();
        $restocked = (int) $items->where('returned_to_stock', true)->sum('quantity');
        $kept = (int) $items->where('returned_to_stock', false)->sum('quantity');
        $parts = array_filter([
            $restocked ? $restocked.' '.str('item')->plural($restocked).' back in stock' : null,
            $kept ? $kept.' '.str('item')->plural($kept).' kept out of stock (not sellable)' : null,
        ]);

        return back()->with('status', 'Return completed: '.implode('; ', $parts).'. No refund issued yet.');
    }

    public function reject(Request $request, SaleReturn $return, ReturnService $service)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $service->reject($return, $request->user(), $data['reason']);

        return back()->with('status', 'Return rejected. Stock unchanged.');
    }
}
