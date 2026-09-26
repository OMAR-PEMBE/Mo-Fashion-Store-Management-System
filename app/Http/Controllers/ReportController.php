<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use App\Services\SaleService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    public function index(Request $request, ReportService $service)
    {
        Gate::authorize('reports.view');
        $types = array_filter(ReportService::TYPES, fn ($type) => $service->allowed($request->user(), $type));

        return response()->view('reports.index', compact('types'))->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, string $type, ReportService $service)
    {
        $report = $service->prepare($request->user(), $type, $request->query());
        $rows = $report['query']?->paginate(25)->withQueryString();
        // Totals across every match, not just this page, for each money column.
        $totals = [];
        foreach ($report['query'] ? $report['money'] : [] as $column) {
            $totals[$column] = Money::round(DB::query()->fromSub((clone $report['query'])->reorder(), 'report_rows')->sum($column));
        }
        if ($rows) {
            $rows->through(fn ($row) => ['id' => $row->id, 'values' => $service->row($row, $report['columns'], $report['money'])]);
        }
        $options = [];
        foreach ($service->fields($type) as $field) {
            $options[$field] = match ($field) {
                'category_id' => DB::table($type === 'expenses' ? 'expense_categories' : 'categories')->orderBy('name')->pluck('name', 'id'),
                'size_id' => DB::table('sizes')->orderBy('sort_order')->pluck('name', 'id'),
                'colour_id' => DB::table('colours')->orderBy('name')->pluck('name', 'id'),
                'salesperson_id', 'recorded_by' => DB::table('users')->orderBy('name')->pluck('name', 'id'),
                'status' => array_combine($service->statuses($type), $service->statuses($type)),
                'payment_method' => SaleService::PAYMENT_METHODS,
                'stock_status' => ['low' => 'Low stock (positive)', 'out' => 'Out of stock', 'available' => 'Available stock'],
                default => null,
            };
        }
        unset($report['query']);

        return response()->view('reports.show', $report + compact('type', 'rows', 'options', 'totals'))->header('Cache-Control', 'private, no-store');
    }

    public function export(Request $request, string $type, ReportService $service)
    {
        $report = $service->prepare($request->user(), $type, $request->query());
        if ($type === 'profit') {
            $rows = collect($report['summary'])->map(fn ($value, $key) => ['metric' => ReportService::FINANCIAL_LABELS[$key], 'amount' => $value])->values();
        } else {
            $rows = $report['query']->limit(5001)->get();
            if ($rows->count() > 5000) {
                throw ValidationException::withMessages(['export' => 'This download exceeds 5,000 rows. Narrow the filters before exporting.']);
            }
            $rows = $rows->map(fn ($row) => $service->row($row, $report['columns'], $report['money']));
        }

        return response()->streamDownload(function () use ($rows, $report) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, array_values($report['columns']), ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($stream, array_map([ReportService::class, 'csvCell'], array_values($row)), ',', '"', '');
            }
            fclose($stream);
        }, $type.'-'.($report['filters']['date_from'] ?? now()->toDateString()).'-'.($report['filters']['date_to'] ?? 'snapshot').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
