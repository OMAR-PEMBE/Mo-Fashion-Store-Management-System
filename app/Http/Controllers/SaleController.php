<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Message;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Services\BusinessSettingsService;
use App\Services\MessageService;
use App\Services\SaleService;
use App\Support\Phone;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:191'], 'payment_method' => ['nullable', Rule::in(array_keys(SaleService::PAYMENT_METHODS))],
            'date_from' => ['nullable', 'date_format:Y-m-d'], 'date_to' => ['nullable', 'date_format:Y-m-d']]);
        $query = Sale::query()->when(! $request->user()->hasPermission('sales.view_all'), fn ($q) => $q->where('salesperson_id', $request->user()->id))
            ->when($filters['q'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->where('sale_number', 'like', '%'.$search.'%')->orWhereHas('customer', fn ($q) => $q->where('full_name', 'like', '%'.$search.'%'))))
            ->when($filters['payment_method'] ?? null, fn ($q, $value) => $q->where('payment_method', $value))
            ->when($filters['date_from'] ?? null, fn ($q, $value) => $q->where('sale_date', '>=', $value.' 00:00:00'))
            ->when($filters['date_to'] ?? null, fn ($q, $value) => $q->where('sale_date', '<=', $value.' 23:59:59'));
        // Headline for whatever is filtered: "12 sales · TZS 1,245,000". Only completed sales count toward money.
        $summary = ['count' => (clone $query)->count(), 'total' => (string) BigDecimal::of((string) ((clone $query)->where('status', 'COMPLETED')->sum('total_amount') ?: '0'))->toScale(2, RoundingMode::HalfUp)];
        $sales = (clone $query)->with(['customer', 'salesperson'])->orderByDesc('id')->paginate(15)->withQueryString();

        return view('sales.index', compact('sales', 'filters', 'summary'));
    }

    public function create()
    {
        $raw = old('items', []);
        $raw = is_array($raw) ? array_slice($raw, 0, 100) : [];
        $ids = collect($raw)->filter(fn ($i) => is_array($i))->pluck('product_variant_id')->filter(fn ($id) => is_scalar($id) && ctype_digit((string) $id));
        $variants = ProductVariant::withTrashed()->with(['product', 'size', 'colour', 'inventory'])->whereIn('id', $ids)->get()->keyBy('id');
        $lines = [];
        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }
            $id = is_scalar($item['product_variant_id'] ?? null) ? (string) $item['product_variant_id'] : '';
            $variant = $variants[$id] ?? null;
            $line = ['product_variant_id' => $id, 'label' => $variant ? $variant->sku.' · '.$variant->product->name : 'Unknown variant',
                'name' => $variant?->product->name ?? 'Unknown item', 'variant' => collect([$variant?->size?->name, $variant?->colour?->name])->filter()->join(' · '),
                'sku' => $variant?->sku ?? '', 'catalogue_price' => $variant?->selling_price ?? '0.00', 'available' => $variant?->inventory?->available_quantity ?? 0];
            foreach (['quantity', 'unit_price', 'discount_amount'] as $field) {
                $line[$field] = is_scalar($item[$field] ?? null) ? (string) $item[$field] : '0';
            }
            $lines[] = $line;
        }
        // "New sale for this customer" links pass ?customer=ID; a returning cart's own choice wins.
        $customerId = old('customer_id', request()->query('customer'));
        $customer = is_scalar($customerId) ? Customer::where('is_active', true)->find($customerId) : null;
        $selectedCustomer = ['id' => $customer?->id ?? '', 'label' => $customer?->full_name ?? 'Walk-in customer', 'detail' => $customer?->customer_code ?? ''];
        $requestKey = old('request_key', (string) Str::uuid());

        return view('sales.pos', compact('lines', 'selectedCustomer', 'requestKey') + ['canOverridePrice' => auth()->user()->hasPermission('sales.override_price')]);
    }

    public function lookup(Request $request)
    {
        $data = $request->validate(['kind' => ['required', Rule::in(['variant', 'customer'])], 'q' => ['nullable', 'string', 'max:191']]);
        $search = $data['q'] ?? '';
        if ($data['kind'] === 'customer') {
            $digits = preg_replace('/\D/', '', $search);

            return Customer::where('is_active', true)->where(fn ($q) => $q->where('full_name', 'like', '%'.$search.'%')->orWhere('customer_code', 'like', '%'.$search.'%')
                ->when($digits !== '', fn ($q) => $q->orWhere('phone', 'like', '%'.$digits.'%')->orWhere('whatsapp_number', 'like', '%'.$digits.'%')))
                ->orderBy('full_name')->limit(20)->get()->map(fn ($customer) => ['id' => $customer->id, 'label' => $customer->full_name.' · '.$customer->customer_code,
                    'name' => $customer->full_name, 'detail' => collect([$customer->customer_code, $customer->phone])->filter()->join(' · ')]);
        }

        return ProductVariant::available()->whereHas('product', fn ($q) => $q->available()->whereNull('deleted_at'))
            ->with(['product', 'size', 'colour', 'inventory'])->where(fn ($q) => $q->where('sku', 'like', '%'.$search.'%')->orWhereHas('product', fn ($q) => $q->where('name', 'like', '%'.$search.'%')))
            ->orderBy('sku')->limit(20)->get()->map(fn ($v) => ['id' => $v->id, 'label' => $v->sku.' · '.$v->product->name.' · '.($v->size?->name ?? 'One size').' / '.($v->colour?->name ?? 'No colour'),
                'name' => $v->product->name, 'variant' => collect([$v->size?->name, $v->colour?->name])->filter()->join(' · '), 'sku' => $v->sku,
                'unit_price' => $v->selling_price, 'available' => $available = $v->inventory?->available_quantity ?? 0, 'low' => $available > 0 && $available <= $v->low_stock_threshold]);
    }

    public function review(Request $request, SaleService $service)
    {
        $data = $service->validate($request->all());
        $variants = ProductVariant::withTrashed()->with(['product', 'size', 'colour'])->whereIn('id', array_column($data['items'], 'product_variant_id'))->get()->keyBy('id');
        abort_unless($variants->count() === count($data['items']), 422, 'A cart variant no longer exists.');
        // Early feedback on the cart; completion enforces the same policy under lock.
        $service->enforcePricePolicy($data, $request->user(), $variants);
        $customer = $data['customer_id'] ? Customer::where('is_active', true)->find($data['customer_id']) : null;
        abort_if($data['customer_id'] && ! $customer, 422, 'Choose an active customer.');
        $totals = $service->calculateTotals($data['items']);
        // Preserve the reviewed cart when returning to edit; completion still validates it anew.
        $request->session()->flashInput($data);

        // WhatsApp receipt: prefilled from the customer, ticked when the shop sends receipts automatically.
        $receiptNumber = Phone::display($customer?->whatsapp_number ?: $customer?->phone);
        $autoReceipt = app(BusinessSettingsService::class)->values()['whatsapp_receipts'] === '1';

        return view('sales.review', compact('data', 'variants', 'customer', 'totals', 'receiptNumber', 'autoReceipt'));
    }

    public function store(Request $request, SaleService $service, MessageService $messages)
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

        $status = 'Sale completed. Stock and customer history updated.';
        if ($request->boolean('send_receipt')) {
            try {
                $message = $messages->queueReceipt($sale, (string) $request->input('receipt_whatsapp'), $request->user());
                $status .= ' WhatsApp receipt '.($message->status === 'failed' ? 'could not be sent; see below.' : 'on its way to '.$message->recipientDisplay().'.');
            } catch (ValidationException) {
                // The sale is done either way; the number can be fixed and the receipt resent from the sale page.
                $status .= ' The WhatsApp number looked wrong, so no receipt was sent; fix it below and send again.';
            }
        }

        return redirect()->route('sales.show', $sale)->with('status', $status);
    }

    public function show(Request $request, Sale $sale)
    {
        abort_unless($request->user()->hasPermission('sales.view_all') || $sale->salesperson_id === $request->user()->id, 403);
        $sale->load(['customer', 'salesperson', 'items.variant.product', 'items.variant.size', 'items.variant.colour', 'returns', 'refunds', 'exchanges']);

        $messages = Message::where('sale_id', $sale->id)->latest('id')->limit(5)->get();
        $receiptNumber = $messages->first()?->recipientDisplay() ?? Phone::display($sale->customer?->whatsapp_number ?: $sale->customer?->phone);

        return view('sales.show', compact('sale', 'messages', 'receiptNumber'));
    }

    public function sendReceipt(Request $request, Sale $sale, MessageService $messages)
    {
        abort_unless($request->user()->hasPermission('sales.view_all') || $sale->salesperson_id === $request->user()->id, 403);
        $request->validate(['receipt_whatsapp' => ['required', 'string', 'max:30']], ['receipt_whatsapp.required' => 'Enter the WhatsApp number to send the receipt to.']);
        $message = $messages->queueReceipt($sale, $request->input('receipt_whatsapp'), $request->user(), resend: true);

        return redirect()->route('sales.show', $sale)->with('status', $message->status === 'failed' ? 'The receipt could not be sent. See the reason below.' : 'Receipt sent to '.$message->recipientDisplay().' on WhatsApp.');
    }

    public function cancel(Request $request, Sale $sale, SaleService $service)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $service->cancelSale($sale, $request->user(), $data['reason']);
    }
}
