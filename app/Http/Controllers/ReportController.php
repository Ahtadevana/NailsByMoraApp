<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\Inventory;
use App\Models\InventoryCategory;
use App\Models\Service;
use App\Services\ReportGenerator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    private const REPORT_TYPES = [
        'financial_summary',
        'revenue',
        'expenses',
        'appointments',
        'service_performance',
        'customers',
        'inventory_status',
        'stock_movements',
    ];

    public function index(Request $request, ReportGenerator $reports): Response
    {
        $filters = $request->validate($this->rules());
        $hasGeneratedReport = ! empty($filters['report_type']);
        $report = $hasGeneratedReport
            ? $reports->generate(
                $filters,
                (int) ($filters['page'] ?? 1),
                (int) ($filters['per_page'] ?? 25),
            )
            : null;

        return Inertia::render('Reports/Index', [
            'report' => $report,
            'generatedFilters' => $hasGeneratedReport ? $filters : null,
            'filters' => [
                'report_type' => $filters['report_type'] ?? '',
                'start_date' => $filters['start_date'] ?? now()->startOfMonth()->toDateString(),
                'end_date' => $filters['end_date'] ?? today()->toDateString(),
                'expense_category_id' => $filters['expense_category_id'] ?? '',
                'payment_method' => $filters['payment_method'] ?? 'all',
                'service_id' => $filters['service_id'] ?? '',
                'appointment_status' => $filters['appointment_status'] ?? 'all',
                'customer_id' => $filters['customer_id'] ?? '',
                'inventory_category_id' => $filters['inventory_category_id'] ?? '',
                'stock_status' => $filters['stock_status'] ?? 'all',
                'inventory_item_id' => $filters['inventory_item_id'] ?? '',
                'movement_type' => $filters['movement_type'] ?? 'all',
                'per_page' => (int) ($filters['per_page'] ?? 25),
                'page' => (int) ($filters['page'] ?? 1),
            ],
            'options' => [
                'services' => Service::query()->orderBy('name')->get(['id', 'name']),
                'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
                'expense_categories' => ExpenseCategory::query()->orderBy('name')->get(['id', 'name']),
                'inventory_categories' => InventoryCategory::query()->orderBy('name')->get(['id', 'name']),
                'inventory_items' => Inventory::query()->orderBy('name')->get(['id', 'category_id', 'name', 'unit']),
            ],
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(bool $requireReportType = false): array
    {
        return [
            'report_type' => [
                $requireReportType ? 'required' : 'nullable',
                Rule::in(self::REPORT_TYPES),
            ],
            'start_date' => [
                'nullable',
                'date_format:Y-m-d',
                Rule::requiredIf(fn (): bool => request()->filled('report_type')
                    && request()->input('report_type') !== 'inventory_status'),
            ],
            'end_date' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:start_date',
                Rule::requiredIf(fn (): bool => request()->filled('report_type')
                    && request()->input('report_type') !== 'inventory_status'),
            ],
            'expense_category_id' => ['nullable', 'integer', 'exists:expense_categories,id'],
            'payment_method' => ['nullable', Rule::in(['all', 'CASH', 'BANK TRANSFER', 'QRIS', 'OTHER'])],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'appointment_status' => ['nullable', Rule::in(['all', 'SCHEDULED', 'COMPLETED', 'CANCELLED', 'NO_SHOW'])],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'inventory_category_id' => ['nullable', 'integer', 'exists:inventory_categories,id'],
            'stock_status' => ['nullable', Rule::in(['all', 'low_stock', 'in_stock'])],
            'inventory_item_id' => ['nullable', 'integer', 'exists:inventory,id'],
            'movement_type' => ['nullable', Rule::in(['all', 'IN', 'OUT'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ];
    }
}
