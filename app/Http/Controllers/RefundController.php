<?php

namespace App\Http\Controllers;

use App\Enums\RefundStatus;
use App\Models\Refund;
use App\Models\Sale;
use App\Services\RefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RefundController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:191'], 'status' => ['nullable', Rule::enum(RefundStatus::class)]]);
        $refunds = Refund::with('sale')->when(! $request->user()->hasPermission('sales.view_all'), fn ($q) => $q->whereHas('sale', fn ($q) => $q->where('salesperson_id', $request->user()->id)))
            ->when($filters['q'] ?? null, fn ($q, $value) => $q->where(fn ($q) => $q->where('refund_number', 'like', '%'.$value.'%')->orWhereHas('sale', fn ($q) => $q->where('sale_number', 'like', '%'.$value.'%'))))
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value))->latest('id')->paginate(15)->withQueryString();

        return view('refunds.index', compact('refunds', 'filters'));
    }

    public function create(Request $request, RefundService $service)
    {
        $data = $request->validate(['sale_number' => ['nullable', 'string', 'max:100'], 'return_id' => ['nullable', 'integer', 'min:1']]);
        $sale = null;
        $limits = [];
        $returnId = empty($data['return_id']) ? null : (int) $data['return_id'];
        if ($data['sale_number'] ?? null) {
            $sale = Sale::where('sale_number', $data['sale_number'])->when(! $request->user()->hasPermission('sales.view_all'), fn ($q) => $q->where('salesperson_id', $request->user()->id))->firstOrFail();
            $service->authorize($request->user(), $sale);
            $sale->load(['items.variant.product', 'returns' => fn ($q) => $q->where('status', 'COMPLETED')]);
            $limits = $service->available($sale, $returnId);
        }
        $requestKey = old('request_key', (string) Str::uuid());

        return view('refunds.create', compact('sale', 'limits', 'returnId', 'requestKey'));
    }

    public function store(Request $request, RefundService $service)
    {
        $refund = $service->create($request->all(), $request->user());

        return redirect()->route('refunds.show', $refund)->with('status', 'Refund requested. Administrator approval is required before payment.');
    }

    public function show(Request $request, Refund $refund, RefundService $service)
    {
        $service->authorize($request->user(), $refund->sale);
        $refund->load(['items.saleItem.variant.product', 'requester', 'saleReturn']);
        $limits = $service->available($refund->sale, $refund->return_id, $refund->id);

        return view('refunds.show', compact('refund', 'limits'));
    }

    public function approve(Request $request, Refund $refund, RefundService $service)
    {
        $service->approve($refund, $request->all(), $request->user());

        return back()->with('status', 'Refund approved. Return the money using the approved method, then record completion.');
    }

    public function complete(Request $request, Refund $refund, RefundService $service)
    {
        $service->complete($refund, $request->all(), $request->user());

        return back()->with('status', 'Refund completion recorded. Stock unchanged.');
    }

    public function close(Request $request, Refund $refund, RefundService $service)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['REJECTED', 'CANCELLED'])], 'reason' => ['required', 'string', 'max:255']]);
        $service->close($refund, $request->user(), $data['status'], $data['reason']);

        return back()->with('status', 'Refund closed without recording a payment.');
    }
}
