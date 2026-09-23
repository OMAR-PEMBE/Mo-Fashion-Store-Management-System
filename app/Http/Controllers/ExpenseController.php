<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\ExpenseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:191'], 'category_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date_format:Y-m-d'], 'date_to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('date_from') ? ['after_or_equal:date_from'] : [])],
            'recorded_by' => ['nullable', 'integer']]);
        $expenses = Expense::with(['category', 'recorder'])
            ->when($filters['q'] ?? null, fn ($q, $value) => $q->where(fn ($q) => $q->where('expense_number', 'like', '%'.$value.'%')->orWhere('description', 'like', '%'.$value.'%')))
            ->when($filters['category_id'] ?? null, fn ($q, $value) => $q->where('expense_category_id', $value))
            ->when($filters['date_from'] ?? null, fn ($q, $value) => $q->where('expense_date', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($q, $value) => $q->where('expense_date', '<=', $value))
            ->when($filters['recorded_by'] ?? null, fn ($q, $value) => $q->where('recorded_by', $value))
            ->orderByDesc('expense_date')->orderByDesc('id')->paginate(15)->withQueryString();

        return view('expenses.index', ['expenses' => $expenses, 'filters' => $filters,
            'categories' => ExpenseCategory::orderBy('name')->get(),
            'recorders' => User::whereIn('id', Expense::select('recorded_by'))->orderBy('name')->get()]);
    }

    public function create()
    {
        return $this->form(new Expense);
    }

    public function edit(Expense $expense)
    {
        return $this->form($expense);
    }

    private function form(Expense $expense)
    {
        return view('expenses.form', ['expense' => $expense, 'requestKey' => (string) Str::uuid(),
            'categories' => ExpenseCategory::where(fn ($q) => $q->where('is_active', true)->orWhere('id', $expense->expense_category_id))->orderBy('name')->get()]);
    }

    public function store(Request $request, ExpenseService $service)
    {
        $expense = $service->save($request->all(), $request->user());

        return redirect()->route('expenses.show', $expense)->with('status', 'Expense recorded.');
    }

    public function update(Request $request, Expense $expense, ExpenseService $service)
    {
        $service->save($request->all(), $request->user(), $expense);

        return redirect()->route('expenses.show', $expense)->with('status', 'Expense updated. The previous values remain in audit history.');
    }

    public function show(Expense $expense)
    {
        $expense->load(['category', 'recorder']);
        $history = DB::table('audit_logs')->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
            ->where('entity_type', 'expense')->where('entity_id', $expense->id)
            ->select('audit_logs.*', 'users.name as actor_name')->orderByDesc('audit_logs.id')->paginate(10);

        return view('expenses.show', compact('expense', 'history'));
    }
}
