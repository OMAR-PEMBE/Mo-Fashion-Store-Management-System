<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use App\Services\ExpenseService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'status' => ['nullable', Rule::in(['active', 'inactive'])]]);
        $categories = ExpenseCategory::when($filters['q'] ?? null, fn ($q, $value) => $q->where('name', 'like', '%'.$value.'%'))
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->where('is_active', $value === 'active'))
            ->orderBy('name')->orderBy('id')->paginate(15)->withQueryString();

        return view('expense-categories.index', compact('categories', 'filters'));
    }

    public function create()
    {
        return view('expense-categories.form', ['category' => new ExpenseCategory]);
    }

    public function edit(ExpenseCategory $expenseCategory)
    {
        return view('expense-categories.form', ['category' => $expenseCategory]);
    }

    public function store(Request $request, ExpenseService $service)
    {
        $service->saveCategory($request->all(), $request->user());

        return redirect()->route('expense-categories.index')->with('status', 'Expense category created.');
    }

    public function update(Request $request, ExpenseCategory $expenseCategory, ExpenseService $service)
    {
        $service->saveCategory($request->all(), $request->user(), $expenseCategory);

        return redirect()->route('expense-categories.index')->with('status', 'Expense category updated.');
    }
}
