<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:191'], 'status' => ['nullable', Rule::enum(OrderStatus::class)]]);
        $orders = Order::with('customer')->when(! $request->user()->hasPermission('orders.manage'), fn ($q) => $q->where('salesperson_id', $request->user()->id))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q->where('order_number', 'like', '%'.$term.'%')->orWhereHas('customer', fn ($q) => $q->where('full_name', 'like', '%'.$term.'%'))))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))->latest('id')->paginate(15)->withQueryString();

        return view('orders.index', compact('orders', 'filters'));
    }

    public function create()
    {
        $view = app(SaleController::class)->create()->with('isOrder', true);
        if (! $view->getData()['selectedCustomer']['id']) {
            $view->with('selectedCustomer', ['id' => '', 'label' => 'Choose a registered customer']);
        }

        return $view;
    }

    public function store(Request $request, OrderService $service)
    {
        $order = $service->create($request->all(), $request->user());

        return redirect()->route('orders.show', $order)->with('status', 'Order saved. Confirm it to reserve stock.');
    }

    public function show(Request $request, Order $order, OrderService $service)
    {
        $service->authorize($request->user(), $order);
        $order->load(['customer', 'salesperson', 'items.variant.product', 'reservations', 'sale']);
        $timeline = DB::table('audit_logs')->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')->where('entity_type', 'order')->where('entity_id', $order->id)
            ->orderBy('audit_logs.id')->get(['audit_logs.action', 'audit_logs.new_values', 'audit_logs.created_at', 'users.name']);

        return view('orders.show', compact('order', 'timeline'));
    }

    public function confirm(Request $request, Order $order, OrderService $service)
    {
        $service->confirm($order, $request->user());

        return back()->with('status', 'Order confirmed. Stock reserved.');
    }

    public function paid(Request $request, Order $order, OrderService $service)
    {
        $service->markPaid($order, $request->all(), $request->user());

        return back()->with('status', 'Full payment recorded. Convert the order to a sale.');
    }

    public function convert(Request $request, Order $order, OrderService $service)
    {
        $service->convertToSale($order, $request->user());

        return redirect()->route('orders.show', $order)->with('status', 'Sale completed. Reserved stock deducted.');
    }

    public function cancel(Request $request, Order $order, OrderService $service)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $service->cancel($order, $request->user(), $data['reason']);

        return back()->with('status', 'Order cancelled. Any active reservations released.');
    }

    public function status(Request $request, Order $order, OrderService $service)
    {
        $data = $request->validate(['status' => ['required', Rule::enum(OrderStatus::class)]]);
        $service->changeStatus($order, $data['status'], $request->user());

        return back()->with('status', 'Fulfilment status updated.');
    }
}
