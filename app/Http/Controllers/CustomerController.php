<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Colour;
use App\Models\Customer;
use App\Models\Size;
use App\Services\CustomerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:191'], 'status' => ['nullable', Rule::in(['active', 'inactive'])]]);
        $search = $filters['q'] ?? '';
        $digits = preg_replace('/\D/', '', $search);
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        $customers = Customer::query()->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('full_name', 'like', '%'.$search.'%')->orWhere('customer_code', 'like', '%'.$search.'%')
            ->when($digits !== '', fn ($q) => $q->orWhere('phone', 'like', '%'.$digits.'%')->orWhere('whatsapp_number', 'like', '%'.$digits.'%'))))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('is_active', $status === 'active'))
            ->orderBy('full_name')->orderBy('id')->paginate(15)->withQueryString();

        return view('customers.index', compact('customers', 'filters'));
    }

    public function create()
    {
        return $this->form(new Customer);
    }

    public function edit(Customer $customer)
    {
        Gate::authorize('customers.manage');

        return $this->form($customer);
    }

    private function form(Customer $customer)
    {
        $customer->load('categories');

        return view('customers.form', ['customer' => $customer,
            'sizes' => Size::where(fn ($q) => $q->where('is_active', true)->orWhere('id', $customer->preferred_size_id))->orderBy('sort_order')->get(),
            'colours' => Colour::where(fn ($q) => $q->where('is_active', true)->orWhere('id', $customer->preferred_colour_id))->orderBy('name')->get(),
            'categories' => Category::withTrashed()->where(fn ($q) => $q->where(fn ($q) => $q->where('is_active', true)->whereNull('deleted_at'))->orWhereIn('id', $customer->categories->modelKeys()))->orderBy('name')->get()]);
    }

    public function store(Request $request, CustomerService $service)
    {
        $customer = $service->save($request->all(), $request->user());
        // The POS registers customers inline and selects them without leaving the sale.
        if ($request->expectsJson()) {
            return response()->json(['id' => $customer->id, 'label' => $customer->full_name.' · '.$customer->customer_code,
                'name' => $customer->full_name, 'detail' => $customer->customer_code], 201);
        }

        return redirect()->route('customers.show', $customer)->with('status', 'Customer created.');
    }

    public function update(Request $request, Customer $customer, CustomerService $service)
    {
        $service->save($request->all(), $request->user(), $customer);

        return redirect()->route('customers.show', $customer)->with('status', 'Customer updated.');
    }

    public function show(Customer $customer)
    {
        $customer->load(['categories', 'preferredSize', 'preferredColour']);

        $sales = $customer->sales()->when(! auth()->user()->hasPermission('sales.view_all'), fn ($q) => $q->where('salesperson_id', auth()->id()))->orderByDesc('id')->paginate(10);

        return view('customers.show', compact('customer', 'sales'));
    }
}
