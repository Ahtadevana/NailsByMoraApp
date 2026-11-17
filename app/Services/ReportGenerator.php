<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ReportGenerator
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function generate(array $filters, int $page = 1, int $perPage = 25, bool $allRows = false): array
    {
        $type = $filters['report_type'];
        $start = $filters['start_date'] ?? null;
        $end = $filters['end_date'] ?? null;

        $report = match ($type) {
            'financial_summary' => $this->financialSummary($filters, $page, $perPage, $allRows),
            'revenue' => $this->revenueReport($filters, $page, $perPage, $allRows),
            'expenses' => $this->expenseReport($filters, $page, $perPage, $allRows),
            'appointments' => $this->appointmentReport($filters, $page, $perPage, $allRows),
            'service_performance' => $this->servicePerformanceReport($filters, $page, $perPage, $allRows),
            'customers' => $this->customerReport($filters, $page, $perPage, $allRows),
            'inventory_status' => $this->inventoryStatusReport($filters, $page, $perPage, $allRows),
            'stock_movements' => $this->stockMovementReport($filters, $page, $perPage, $allRows),
        };

        $report['type'] = $type;
        $report['title'] = $this->reportTitle($type);
        $report['period'] = $type === 'inventory_status'
            ? 'Stok saat ini'
            : $this->periodLabel($start, $end);
        $report['generated_at'] = now()->toIso8601String();

        return $report;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function financialSummary(array $filters, int $page, int $perPage, bool $allRows): array
    {
        $revenueByDate = $this->revenueByDate($filters['start_date'], $filters['end_date']);
        $expensesByDate = $this->expensesByDate($filters['start_date'], $filters['end_date'], $filters);
        $timeline = $this->mergeTimeline($revenueByDate, $expensesByDate, $filters['start_date'], $filters['end_date']);
        $totalRevenue = array_sum(array_column($timeline, 'revenue'));
        $totalExpenses = array_sum(array_column($timeline, 'expenses'));

        return [
            'metrics' => [
                ['label' => 'Total Pendapatan', 'value' => $totalRevenue, 'format' => 'currency'],
                ['label' => 'Total Pengeluaran', 'value' => $totalExpenses, 'format' => 'currency'],
                ['label' => 'Laba Bersih', 'value' => $totalRevenue - $totalExpenses, 'format' => 'currency'],
            ],
            'charts' => [[
                'type' => 'line',
                'title' => 'Pendapatan, pengeluaran & laba bersih',
                'currency' => true,
                'labels' => array_column($timeline, 'label'),
                'datasets' => [
                    $this->dataset('Pendapatan', $timeline, 'revenue', '#7c2838'),
                    $this->dataset('Pengeluaran', $timeline, 'expenses', '#d89b43'),
                    $this->dataset('Laba bersih', $timeline, 'net_income', '#31826b'),
                ],
            ]],
            'tables' => [$this->arrayTable(
                'Ringkasan keuangan per periode',
                [
                    ['key' => 'report_date', 'label' => 'Periode'],
                    ['key' => 'revenue', 'label' => 'Pendapatan', 'format' => 'currency'],
                    ['key' => 'expenses', 'label' => 'Pengeluaran', 'format' => 'currency'],
                    ['key' => 'net_income', 'label' => 'Laba bersih', 'format' => 'currency'],
                ],
                array_map(fn (array $row): array => [
                    'report_date' => $row['label'],
                    'revenue' => $row['revenue'],
                    'expenses' => $row['expenses'],
                    'net_income' => $row['net_income'],
                ], $timeline),
            )],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function revenueReport(array $filters, int $page, int $perPage, bool $allRows): array
    {
        $lines = $this->revenueLineQuery($filters);
        $summary = (clone $lines)
            ->selectRaw('COUNT(*) as service_count')
            ->selectRaw('COUNT(DISTINCT appointments.id) as appointment_count')
            ->selectRaw('COALESCE(SUM(appointment_services.price), 0) as total_revenue')
            ->first();
        $byDate = $this->revenueByDate($filters['start_date'], $filters['end_date'], $filters['service_id'] ?? null);
        $serviceQuery = $this->serviceRevenueQuery($filters);
        $serviceRows = (clone $serviceQuery)->get();
        $totalRevenue = (float) $summary->total_revenue;
        $revenueSeries = $this->bucketDateMap($byDate, 'revenue', $filters['start_date'], $filters['end_date']);

        return [
            'metrics' => [
                ['label' => 'Total Pendapatan', 'value' => $totalRevenue, 'format' => 'currency'],
                ['label' => 'Janji Temu Selesai', 'value' => (int) $summary->appointment_count, 'format' => 'number'],
                ['label' => 'Layanan Digunakan', 'value' => (int) $summary->service_count, 'format' => 'number'],
                ['label' => 'Rata-rata per Janji Temu', 'value' => $summary->appointment_count ? $totalRevenue / $summary->appointment_count : 0, 'format' => 'currency'],
            ],
            'charts' => [
                [
                    'type' => 'line',
                    'title' => 'Pendapatan dari waktu ke waktu',
                    'currency' => true,
                    'labels' => array_column($revenueSeries, 'label'),
                    'datasets' => [$this->dataset('Pendapatan', $revenueSeries, 'revenue', '#7c2838')],
                ],
                [
                    'type' => 'bar',
                    'title' => 'Pendapatan per layanan',
                    'currency' => true,
                    'labels' => array_column(array_slice($serviceRows->all(), 0, 12), 'name'),
                    'datasets' => [['label' => 'Pendapatan', 'values' => array_map('floatval', array_column(array_slice($serviceRows->all(), 0, 12), 'revenue')), 'color' => '#7c2838']],
                ],
            ],
            'tables' => [$this->table(
                'Kinerja layanan',
                [
                    ['key' => 'name', 'label' => 'Layanan'],
                    ['key' => 'times_performed', 'label' => 'Jumlah penggunaan'],
                    ['key' => 'revenue', 'label' => 'Pendapatan', 'format' => 'currency'],
                    ['key' => 'average_price', 'label' => 'Harga rata-rata', 'format' => 'currency'],
                ],
                $serviceQuery->orderByDesc('revenue')->orderBy('services.name'),
                $page,
                $perPage,
                $allRows,
            )],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function expenseReport(array $filters, int $page, int $perPage, bool $allRows): array
    {
        $query = DB::table('expenses')
            ->join('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
            ->whereBetween('expenses.expense_date', [$filters['start_date'], $filters['end_date']]);
        $this->applyExpenseFilters($query, $filters);

        $stats = (clone $query)
            ->selectRaw('COUNT(*) as expense_count')
            ->selectRaw('COALESCE(SUM(expenses.amount), 0) as total_amount')
            ->first();
        $byCategory = (clone $query)
            ->select('expense_categories.name')
            ->selectRaw('SUM(expenses.amount) as amount')
            ->groupBy('expense_categories.id', 'expense_categories.name')
            ->orderByDesc('amount')
            ->get();
        $byPayment = (clone $query)
            ->select('expenses.payment_method')
            ->selectRaw('SUM(expenses.amount) as amount, COUNT(*) as transactions')
            ->groupBy('expenses.payment_method')
            ->orderBy('expenses.payment_method')
            ->get();
        $byDate = (clone $query)
            ->selectRaw('DATE(expenses.expense_date) as report_date')
            ->selectRaw('SUM(expenses.amount) as amount')
            ->groupByRaw('DATE(expenses.expense_date)')
            ->orderBy('report_date')
            ->get()
            ->map(fn (object $row): array => [
                'date' => (string) $row->report_date,
                'amount' => (float) $row->amount,
            ])->all();
        $expenseSeries = $this->bucketRows($byDate, 'date', ['amount'], $filters['start_date'], $filters['end_date']);
        $totalAmount = (float) $stats->total_amount;
        $categoryRows = $byCategory->map(fn (object $row): array => [
            'name' => $row->name,
            'amount' => (float) $row->amount,
            'percentage' => $totalAmount > 0 ? (float) $row->amount / $totalAmount * 100 : 0,
        ])->all();
        $paymentRows = $byPayment->map(fn (object $row): array => [
            'payment_method' => $this->paymentMethodLabel($row->payment_method),
            'amount' => (float) $row->amount,
            'transactions' => (int) $row->transactions,
        ])->all();
        $expenseRowsQuery = (clone $query)
            ->select([
                'expenses.id',
                'expenses.name',
                'expense_categories.name as category',
                'expenses.amount',
                'expenses.payment_method',
                'expenses.expense_date',
                'expenses.note',
            ]);

        return [
            'metrics' => [
                ['label' => 'Total Pengeluaran', 'value' => $totalAmount, 'format' => 'currency'],
                ['label' => 'Jumlah Pengeluaran', 'value' => (int) $stats->expense_count, 'format' => 'number'],
                ['label' => 'Rata-rata Pengeluaran', 'value' => $stats->expense_count ? $totalAmount / $stats->expense_count : 0, 'format' => 'currency'],
            ],
            'charts' => [
                [
                    'type' => 'line',
                    'title' => 'Pengeluaran dari waktu ke waktu',
                    'currency' => true,
                    'labels' => array_column($expenseSeries, 'label'),
                    'datasets' => [['label' => 'Pengeluaran', 'values' => array_column($expenseSeries, 'amount'), 'color' => '#d89b43']],
                ],
                [
                    'type' => 'donut',
                    'title' => 'Pengeluaran per kategori',
                    'currency' => true,
                    'labels' => array_column($categoryRows, 'name'),
                    'datasets' => [['label' => 'Jumlah', 'values' => array_column($categoryRows, 'amount')]],
                ],
                [
                    'type' => 'bar',
                    'title' => 'Pengeluaran per metode pembayaran',
                    'currency' => true,
                    'labels' => array_column($paymentRows, 'payment_method'),
                    'datasets' => [['label' => 'Jumlah', 'values' => array_column($paymentRows, 'amount'), 'color' => '#7c2838']],
                ],
            ],
            'tables' => [
                $this->table(
                    'Transaksi pengeluaran',
                    [
                        ['key' => 'name', 'label' => 'Pengeluaran'],
                        ['key' => 'category', 'label' => 'Kategori'],
                        ['key' => 'amount', 'label' => 'Jumlah', 'format' => 'currency'],
                        ['key' => 'payment_method', 'label' => 'Metode pembayaran'],
                        ['key' => 'expense_date', 'label' => 'Tanggal', 'format' => 'date'],
                        ['key' => 'note', 'label' => 'Catatan'],
                    ],
                    $expenseRowsQuery->orderByDesc('expenses.expense_date')->orderByDesc('expenses.id'),
                    $page,
                    $perPage,
                    $allRows,
                ),
                $this->arrayTable('Pengeluaran per kategori', [
                    ['key' => 'name', 'label' => 'Kategori'],
                    ['key' => 'amount', 'label' => 'Jumlah', 'format' => 'currency'],
                    ['key' => 'percentage', 'label' => 'Persentase', 'format' => 'percent'],
                ], $categoryRows),
                $this->arrayTable('Pengeluaran per metode pembayaran', [
                    ['key' => 'payment_method', 'label' => 'Metode pembayaran'],
                    ['key' => 'amount', 'label' => 'Jumlah', 'format' => 'currency'],
                    ['key' => 'transactions', 'label' => 'Transaksi'],
                ], $paymentRows),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function appointmentReport(array $filters, int $page, int $perPage, bool $allRows): array
    {
        $query = DB::table('appointments')
            ->whereBetween('appointment_date', [$filters['start_date'], $filters['end_date']]);
        if (($filters['appointment_status'] ?? 'all') !== 'all') {
            $query->where('status', $filters['appointment_status']);
        }

        $stats = (clone $query)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'SCHEDULED' THEN 1 ELSE 0 END) as scheduled")
            ->selectRaw("SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) as completed")
            ->selectRaw("SUM(CASE WHEN status = 'CANCELLED' THEN 1 ELSE 0 END) as cancelled")
            ->selectRaw("SUM(CASE WHEN status = 'NO_SHOW' THEN 1 ELSE 0 END) as no_show")
            ->first();
        $daily = (clone $query)
            ->selectRaw('DATE(appointment_date) as date')
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) as completed, SUM(CASE WHEN status = 'SCHEDULED' THEN 1 ELSE 0 END) as scheduled, SUM(CASE WHEN status = 'CANCELLED' THEN 1 ELSE 0 END) as cancelled, SUM(CASE WHEN status = 'NO_SHOW' THEN 1 ELSE 0 END) as no_show")
            ->groupBy('appointment_date')
            ->orderBy('appointment_date');
        $dailyRows = (clone $daily)->get()->map(fn (object $row): array => [
            'date' => (string) $row->date,
            'total' => (int) $row->total,
            'completed' => (int) $row->completed,
            'scheduled' => (int) $row->scheduled,
            'cancelled' => (int) $row->cancelled,
            'no_show' => (int) $row->no_show,
        ])->all();
        $dailySeries = $this->bucketRows($dailyRows, 'date', ['total'], $filters['start_date'], $filters['end_date']);
        $statuses = [
            ['label' => 'Terjadwal', 'value' => (int) $stats->scheduled, 'color' => '#d89b43'],
            ['label' => 'Selesai', 'value' => (int) $stats->completed, 'color' => '#31826b'],
            ['label' => 'Dibatalkan', 'value' => (int) $stats->cancelled, 'color' => '#8d8b8c'],
            ['label' => 'Tidak hadir', 'value' => (int) $stats->no_show, 'color' => '#bd4b4b'],
        ];

        return [
            'metrics' => [
                ['label' => 'Total janji temu', 'value' => (int) $stats->total, 'format' => 'number'],
                ['label' => 'Terjadwal', 'value' => (int) $stats->scheduled, 'format' => 'number'],
                ['label' => 'Selesai', 'value' => (int) $stats->completed, 'format' => 'number'],
                ['label' => 'Dibatalkan', 'value' => (int) $stats->cancelled, 'format' => 'number'],
                ['label' => 'Tidak hadir', 'value' => (int) $stats->no_show, 'format' => 'number'],
            ],
            'charts' => [
                [
                    'type' => 'donut',
                    'title' => 'Janji temu per status',
                    'labels' => array_column(array_values(array_filter($statuses, fn ($status): bool => $status['value'] > 0)), 'label'),
                    'datasets' => [['label' => 'Janji temu', 'values' => array_values(array_map(fn ($status) => $status['value'], array_filter($statuses, fn ($status): bool => $status['value'] > 0))), 'colors' => array_values(array_map(fn ($status) => $status['color'], array_filter($statuses, fn ($status): bool => $status['value'] > 0)))]],
                ],
                [
                    'type' => 'bar',
                    'title' => 'Jumlah janji temu dari waktu ke waktu',
                    'labels' => array_column($dailySeries, 'label'),
                    'datasets' => [['label' => 'Janji temu', 'values' => array_column($dailySeries, 'total'), 'color' => '#7c2838']],
                ],
            ],
            'tables' => [$this->table(
                'Janji temu per tanggal',
                [
                    ['key' => 'date', 'label' => 'Tanggal', 'format' => 'date'],
                    ['key' => 'total', 'label' => 'Total'],
                    ['key' => 'completed', 'label' => 'Selesai'],
                    ['key' => 'scheduled', 'label' => 'Terjadwal'],
                    ['key' => 'cancelled', 'label' => 'Dibatalkan'],
                    ['key' => 'no_show', 'label' => 'Tidak hadir'],
                ],
                $daily,
                $page,
                $perPage,
                $allRows,
            )],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function servicePerformanceReport(array $filters, int $page, int $perPage, bool $allRows): array
    {
        $serviceQuery = $this->serviceRevenueQuery($filters);
        $allServices = (clone $serviceQuery)->get();
        $chartServices = $allServices->take(12)->values();
        $performed = (int) $allServices->sum('times_performed');
        $totalRevenue = (float) $allServices->sum('revenue');
        $mostFrequent = $allServices->sortByDesc('times_performed')->first();
        $highestRevenue = $allServices->sortByDesc('revenue')->first();

        return [
            'metrics' => [
                ['label' => 'Layanan digunakan', 'value' => $performed, 'format' => 'number'],
                ['label' => 'Total pendapatan layanan', 'value' => $totalRevenue, 'format' => 'currency'],
                ['label' => 'Layanan paling sering', 'value' => $mostFrequent->name ?? '—', 'format' => 'text'],
                ['label' => 'Pendapatan layanan tertinggi', 'value' => $highestRevenue->name ?? '—', 'format' => 'text'],
            ],
            'charts' => [
                [
                    'type' => 'bar',
                    'title' => 'Layanan berdasarkan frekuensi',
                    'labels' => $chartServices->pluck('name')->all(),
                    'datasets' => [['label' => 'Jumlah penggunaan', 'values' => $chartServices->pluck('times_performed')->map(fn ($value): int => (int) $value)->all(), 'color' => '#7c2838']],
                ],
                [
                    'type' => 'bar',
                    'title' => 'Pendapatan layanan',
                    'currency' => true,
                    'labels' => $chartServices->pluck('name')->all(),
                    'datasets' => [['label' => 'Pendapatan', 'values' => $chartServices->pluck('revenue')->map(fn ($value): float => (float) $value)->all(), 'color' => '#31826b']],
                ],
            ],
            'tables' => [$this->table(
                'Kinerja layanan selesai',
                [
                    ['key' => 'name', 'label' => 'Service'],
                    ['key' => 'times_performed', 'label' => 'Jumlah penggunaan'],
                    ['key' => 'revenue', 'label' => 'Pendapatan', 'format' => 'currency'],
                    ['key' => 'average_price', 'label' => 'Average price', 'format' => 'currency'],
                ],
                $serviceQuery->orderByDesc('times_performed')->orderBy('services.name'),
                $page,
                $perPage,
                $allRows,
            )],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function customerReport(array $filters, int $page, int $perPage, bool $allRows): array
    {
        $appointmentStats = DB::table('appointments')
            ->whereBetween('appointment_date', [$filters['start_date'], $filters['end_date']])
            ->select('customer_id')
            ->selectRaw('COUNT(*) as appointments_count')
            ->selectRaw("SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) as completed_count")
            ->groupBy('customer_id');
        $spending = DB::table('appointments')
            ->join('appointment_services', 'appointments.id', '=', 'appointment_services.appointment_id')
            ->whereBetween('appointments.appointment_date', [$filters['start_date'], $filters['end_date']])
            ->where('appointments.status', 'COMPLETED')
            ->select('appointments.customer_id')
            ->selectRaw('SUM(appointment_services.price) as total_spent')
            ->groupBy('appointments.customer_id');

        $query = DB::table('customers')
            ->leftJoinSub($appointmentStats, 'period_appointments', fn ($join) => $join->on('customers.id', '=', 'period_appointments.customer_id'))
            ->leftJoinSub($spending, 'period_spending', fn ($join) => $join->on('customers.id', '=', 'period_spending.customer_id'))
            ->when(! empty($filters['customer_id']), fn (Builder $customers) => $customers->where('customers.id', $filters['customer_id']));
        $stats = DB::table('customers')
            ->selectRaw('COUNT(*) as total_customers')
            ->selectRaw('SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as new_customers', [
                CarbonImmutable::parse($filters['start_date'])->startOfDay()->toDateTimeString(),
                CarbonImmutable::parse($filters['end_date'])->endOfDay()->toDateTimeString(),
            ])
            ->first();
        $activity = DB::table('appointments')
            ->whereBetween('appointment_date', [$filters['start_date'], $filters['end_date']])
            ->select('appointment_date')
            ->selectRaw('COUNT(DISTINCT customer_id) as active_customers')
            ->groupBy('appointment_date')
            ->orderBy('appointment_date')
            ->get();
        $activitySeries = $this->bucketRows($activity->map(fn (object $row): array => [
            'date' => (string) $row->appointment_date,
            'active_customers' => (int) $row->active_customers,
        ])->all(), 'date', ['active_customers'], $filters['start_date'], $filters['end_date']);
        $activeCustomers = (clone $query)->whereNotNull('period_appointments.customer_id')->count();
        $completedAppointments = DB::table('appointments')
            ->when(! empty($filters['customer_id']), fn (Builder $appointments) => $appointments->where('customer_id', $filters['customer_id']))
            ->whereBetween('appointment_date', [$filters['start_date'], $filters['end_date']])
            ->where('status', 'COMPLETED')
            ->count();

        return [
            'metrics' => [
                ['label' => 'Total pelanggan', 'value' => (int) $stats->total_customers, 'format' => 'number'],
                ['label' => 'Pelanggan baru', 'value' => (int) $stats->new_customers, 'format' => 'number'],
                ['label' => 'Pelanggan dengan janji temu', 'value' => $activeCustomers, 'format' => 'number'],
                ['label' => 'Janji temu selesai', 'value' => $completedAppointments, 'format' => 'number'],
            ],
            'charts' => [[
                'type' => 'line',
                'title' => 'Aktivitas pelanggan',
                'labels' => array_column($activitySeries, 'label'),
                'datasets' => [['label' => 'Pelanggan aktif', 'values' => array_column($activitySeries, 'active_customers'), 'color' => '#7c2838']],
            ]],
            'tables' => [$this->table(
                'Aktivitas pelanggan',
                [
                    ['key' => 'name', 'label' => 'Pelanggan'],
                    ['key' => 'appointments', 'label' => 'Janji temu'],
                    ['key' => 'completed', 'label' => 'Selesai'],
                    ['key' => 'total_spent', 'label' => 'Total belanja', 'format' => 'currency'],
                ],
                $query->select([
                    'customers.id',
                    'customers.name',
                    DB::raw('COALESCE(period_appointments.appointments_count, 0) as appointments'),
                    DB::raw('COALESCE(period_appointments.completed_count, 0) as completed'),
                    DB::raw('COALESCE(period_spending.total_spent, 0) as total_spent'),
                ])->orderByDesc('appointments')->orderBy('customers.name'),
                $page,
                $perPage,
                $allRows,
            )],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function inventoryStatusReport(array $filters, int $page, int $perPage, bool $allRows): array
    {
        $query = DB::table('inventory')
            ->join('inventory_categories', 'inventory.category_id', '=', 'inventory_categories.id')
            ->when(! empty($filters['inventory_category_id']), fn (Builder $items) => $items->where('inventory.category_id', $filters['inventory_category_id']))
            ->when(($filters['stock_status'] ?? 'all') === 'low_stock', fn (Builder $items) => $items->whereColumn('inventory.quantity', '<', 'inventory.minimum_stock'))
            ->when(($filters['stock_status'] ?? 'all') === 'in_stock', fn (Builder $items) => $items->whereColumn('inventory.quantity', '>=', 'inventory.minimum_stock'));
        $summary = (clone $query)
            ->selectRaw('COUNT(*) as total_items')
            ->selectRaw('SUM(CASE WHEN inventory.quantity < inventory.minimum_stock THEN 1 ELSE 0 END) as low_stock_items')
            ->selectRaw('COALESCE(SUM(inventory.quantity * inventory.unit_cost), 0) as inventory_value')
            ->first();
        $items = (clone $query)->select([
            'inventory.id',
            'inventory.name',
            'inventory_categories.name as category',
            'inventory.quantity',
            'inventory.unit',
            'inventory.minimum_stock',
            'inventory.unit_cost',
            DB::raw('(inventory.quantity * inventory.unit_cost) as inventory_value'),
            DB::raw("CASE WHEN inventory.quantity < inventory.minimum_stock THEN 'Stok menipis' ELSE 'Stok tersedia' END as status"),
        ]);

        return [
            'metrics' => [
                ['label' => 'Total barang inventaris', 'value' => (int) $summary->total_items, 'format' => 'number'],
                ['label' => 'Stok menipis', 'value' => (int) $summary->low_stock_items, 'format' => 'number'],
                ['label' => 'Nilai total inventaris', 'value' => (float) $summary->inventory_value, 'format' => 'currency'],
            ],
            'charts' => [],
            'tables' => [$this->table(
                'Inventaris saat ini',
                [
                    ['key' => 'name', 'label' => 'Barang'],
                    ['key' => 'category', 'label' => 'Kategori'],
                    ['key' => 'quantity', 'label' => 'Stok saat ini'],
                    ['key' => 'unit', 'label' => 'Satuan'],
                    ['key' => 'minimum_stock', 'label' => 'Stok minimum'],
                    ['key' => 'unit_cost', 'label' => 'Biaya per satuan', 'format' => 'currency'],
                    ['key' => 'inventory_value', 'label' => 'Nilai inventaris', 'format' => 'currency'],
                    ['key' => 'status', 'label' => 'Status'],
                ],
                $items->orderBy('inventory.name'),
                $page,
                $perPage,
                $allRows,
            )],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function stockMovementReport(array $filters, int $page, int $perPage, bool $allRows): array
    {
        $query = DB::table('inventory_movements')
            ->join('inventory', 'inventory_movements.inventory_item_id', '=', 'inventory.id')
            ->join('inventory_categories', 'inventory.category_id', '=', 'inventory_categories.id')
            ->whereBetween('inventory_movements.created_at', [
                CarbonImmutable::parse($filters['start_date'])->startOfDay()->toDateTimeString(),
                CarbonImmutable::parse($filters['end_date'])->endOfDay()->toDateTimeString(),
            ])
            ->when(! empty($filters['inventory_category_id']), fn (Builder $movements) => $movements->where('inventory.category_id', $filters['inventory_category_id']))
            ->when(! empty($filters['inventory_item_id']), fn (Builder $movements) => $movements->where('inventory.id', $filters['inventory_item_id']))
            ->when(($filters['movement_type'] ?? 'all') !== 'all', fn (Builder $movements) => $movements->where('inventory_movements.type', $filters['movement_type']));

        $summary = (clone $query)
            ->selectRaw("SUM(CASE WHEN inventory_movements.type = 'IN' THEN 1 ELSE 0 END) as in_movements")
            ->selectRaw("SUM(CASE WHEN inventory_movements.type = 'OUT' THEN 1 ELSE 0 END) as out_movements")
            ->first();
        $quantityByUnit = (clone $query)
            ->select('inventory_movements.type', 'inventory.unit')
            ->selectRaw('SUM(inventory_movements.quantity) as total_quantity')
            ->groupBy('inventory_movements.type', 'inventory.unit')
            ->orderBy('inventory_movements.type')
            ->orderBy('inventory.unit')
            ->get();
        $activity = (clone $query)
            ->selectRaw('DATE(inventory_movements.created_at) as movement_date')
            ->selectRaw("SUM(CASE WHEN inventory_movements.type = 'IN' THEN 1 ELSE 0 END) as in_count, SUM(CASE WHEN inventory_movements.type = 'OUT' THEN 1 ELSE 0 END) as out_count")
            ->groupByRaw('DATE(inventory_movements.created_at)')
            ->orderBy('movement_date')
            ->get();
        $activitySeries = $this->bucketRows($activity->map(fn (object $row): array => [
            'date' => (string) $row->movement_date,
            'in_count' => (int) $row->in_count,
            'out_count' => (int) $row->out_count,
        ])->all(), 'date', ['in_count', 'out_count'], $filters['start_date'], $filters['end_date']);
        $movementRows = (clone $query)->select([
            'inventory_movements.id',
            'inventory_movements.created_at',
            'inventory.name as item',
            'inventory_categories.name as category',
            'inventory_movements.type',
            'inventory_movements.quantity',
            'inventory.unit',
            'inventory_movements.reason',
            'inventory_movements.note',
        ]);
        $quantityInByUnit = $quantityByUnit->where('type', 'IN')->map(fn (object $row): array => [
            'unit' => $row->unit,
            'quantity' => (float) $row->total_quantity,
        ])->values()->all();
        $quantityOutByUnit = $quantityByUnit->where('type', 'OUT')->map(fn (object $row): array => [
            'unit' => $row->unit,
            'quantity' => (float) $row->total_quantity,
        ])->values()->all();

        return [
            'metrics' => [
                ['label' => 'Total barang masuk', 'value' => (int) $summary->in_movements, 'format' => 'number'],
                ['label' => 'Total barang keluar', 'value' => (int) $summary->out_movements, 'format' => 'number'],
                ['label' => 'Jumlah masuk (per satuan)', 'value' => $quantityInByUnit, 'format' => 'quantities'],
                ['label' => 'Jumlah keluar (per satuan)', 'value' => $quantityOutByUnit, 'format' => 'quantities'],
            ],
            'charts' => [[
                'type' => 'bar',
                'title' => 'Aktivitas pergerakan stok',
                'subtitle' => 'Movement record counts; quantities remain separated by unit below.',
                'labels' => array_column($activitySeries, 'label'),
                'datasets' => [
                    ['label' => 'Barang masuk', 'values' => array_column($activitySeries, 'in_count'), 'color' => '#31826b'],
                    ['label' => 'Barang keluar', 'values' => array_column($activitySeries, 'out_count'), 'color' => '#d89b43'],
                ],
            ]],
            'tables' => [$this->table(
                'Riwayat pergerakan stok',
                [
                    ['key' => 'created_at', 'label' => 'Tanggal', 'format' => 'datetime'],
                    ['key' => 'item', 'label' => 'Barang'],
                    ['key' => 'category', 'label' => 'Kategori'],
                    ['key' => 'type', 'label' => 'Jenis'],
                    ['key' => 'quantity', 'label' => 'Jumlah'],
                    ['key' => 'unit', 'label' => 'Satuan'],
                    ['key' => 'reason', 'label' => 'Alasan'],
                    ['key' => 'note', 'label' => 'Catatan'],
                ],
                $movementRows->orderByDesc('inventory_movements.created_at')->orderByDesc('inventory_movements.id'),
                $page,
                $perPage,
                $allRows,
            )],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function revenueLineQuery(array $filters): Builder
    {
        return DB::table('appointment_services')
            ->join('appointments', 'appointment_services.appointment_id', '=', 'appointments.id')
            ->join('services', 'appointment_services.service_id', '=', 'services.id')
            ->where('appointments.status', 'COMPLETED')
            ->whereBetween('appointments.appointment_date', [$filters['start_date'], $filters['end_date']])
            ->when(! empty($filters['service_id']), fn (Builder $query) => $query->where('services.id', $filters['service_id']));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function serviceRevenueQuery(array $filters): Builder
    {
        return $this->revenueLineQuery($filters)
            ->select('services.id', 'services.name')
            ->selectRaw('COUNT(*) as times_performed, SUM(appointment_services.price) as revenue, AVG(appointment_services.price) as average_price')
            ->groupBy('services.id', 'services.name');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, float>
     */
    private function revenueByDate(string $start, string $end, ?int $serviceId = null): array
    {
        return DB::table('appointment_services')
            ->join('appointments', 'appointment_services.appointment_id', '=', 'appointments.id')
            ->when($serviceId, fn (Builder $query) => $query->where('appointment_services.service_id', $serviceId))
            ->where('appointments.status', 'COMPLETED')
            ->whereBetween('appointments.appointment_date', [$start, $end])
            ->selectRaw('DATE(appointments.appointment_date) as report_date')
            ->selectRaw('SUM(appointment_services.price) as amount')
            ->groupByRaw('DATE(appointments.appointment_date)')
            ->orderBy('report_date')
            ->get()
            ->mapWithKeys(fn (object $row): array => [(string) $row->report_date => (float) $row->amount])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, float>
     */
    private function expensesByDate(string $start, string $end, array $filters): array
    {
        $query = DB::table('expenses')
            ->whereBetween('expense_date', [$start, $end]);
        $this->applyExpenseFilters($query, $filters);

        return $query->selectRaw('DATE(expense_date) as report_date')
            ->selectRaw('SUM(amount) as amount')
            ->groupByRaw('DATE(expense_date)')
            ->orderBy('report_date')
            ->get()
            ->mapWithKeys(fn (object $row): array => [(string) $row->report_date => (float) $row->amount])
            ->all();
    }

    private function applyExpenseFilters(Builder $query, array $filters): void
    {
        $query
            ->when(! empty($filters['expense_category_id']), fn (Builder $expenses) => $expenses->where('expenses.expense_category_id', $filters['expense_category_id']))
            ->when(($filters['payment_method'] ?? 'all') !== 'all', fn (Builder $expenses) => $expenses->where('expenses.payment_method', $filters['payment_method']));
    }

    /**
     * @param  array<string, float>  $revenue
     * @param  array<string, float>  $expenses
     * @return array<int, array<string, mixed>>
     */
    private function mergeTimeline(array $revenue, array $expenses, string $start, string $end): array
    {
        $values = [];
        foreach ($revenue as $date => $amount) {
            $key = $this->bucketKey($date, $start, $end);
            $values[$key]['date'] = $key;
            $values[$key]['revenue'] = ($values[$key]['revenue'] ?? 0) + $amount;
        }
        foreach ($expenses as $date => $amount) {
            $key = $this->bucketKey($date, $start, $end);
            $values[$key]['date'] = $key;
            $values[$key]['expenses'] = ($values[$key]['expenses'] ?? 0) + $amount;
        }
        ksort($values);

        return array_values(array_map(function (array $row) use ($start, $end): array {
            $row['revenue'] = (float) ($row['revenue'] ?? 0);
            $row['expenses'] = (float) ($row['expenses'] ?? 0);
            $row['net_income'] = $row['revenue'] - $row['expenses'];
            $row['label'] = $this->bucketLabel($row['date'], $start, $end);

            return $row;
        }, $values));
    }

    private function bucketKey(string $date, string $start, string $end): string
    {
        $days = CarbonImmutable::parse($start)->diffInDays(CarbonImmutable::parse($end));
        $period = CarbonImmutable::parse($date);

        return $days <= 31
            ? $period->toDateString()
            : ($days <= 120 ? $period->startOfWeek()->toDateString() : $period->startOfMonth()->toDateString());
    }

    /**
     * @param  array<string, float|int>  $values
     * @return array<int, array<string, mixed>>
     */
    private function bucketDateMap(array $values, string $field, string $start, string $end): array
    {
        return $this->bucketRows(array_map(
            fn (string $date, float|int $value): array => ['date' => $date, $field => $value],
            array_keys($values),
            array_values($values),
        ), 'date', [$field], $start, $end);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $fields
     * @return array<int, array<string, mixed>>
     */
    private function bucketRows(array $rows, string $dateKey, array $fields, string $start, string $end): array
    {
        $buckets = [];
        foreach ($rows as $row) {
            $key = $this->bucketKey((string) $row[$dateKey], $start, $end);
            $buckets[$key]['date'] = $key;
            foreach ($fields as $field) {
                $buckets[$key][$field] = ($buckets[$key][$field] ?? 0) + (float) $row[$field];
            }
        }
        ksort($buckets);

        return array_map(function (array $row) use ($start, $end): array {
            $row['label'] = $this->bucketLabel($row['date'], $start, $end);
            foreach ($row as $key => $value) {
                if ($key !== 'date' && $key !== 'label') {
                    $row[$key] = (float) $value;
                }
            }

            return $row;
        }, array_values($buckets));
    }

    private function bucketLabel(string $date, string $start, string $end): string
    {
        $days = CarbonImmutable::parse($start)->diffInDays(CarbonImmutable::parse($end));
        $period = CarbonImmutable::parse($date);

        return $days <= 31
            ? $period->locale('id')->translatedFormat('j M')
            : ($days <= 120
                ? $period->locale('id')->translatedFormat('j M')
                : $period->locale('id')->translatedFormat('M Y'));
    }

    private function periodLabel(string $start, string $end): string
    {
        return CarbonImmutable::parse($start)->locale('id')->translatedFormat('j F Y').' – '.CarbonImmutable::parse($end)->locale('id')->translatedFormat('j F Y');
    }

    private function reportTitle(string $type): string
    {
        return match ($type) {
            'financial_summary' => 'Ringkasan Keuangan',
            'revenue' => 'Laporan Pendapatan',
            'expenses' => 'Laporan Pengeluaran',
            'appointments' => 'Laporan Janji Temu',
            'service_performance' => 'Kinerja Layanan',
            'customers' => 'Laporan Pelanggan',
            'inventory_status' => 'Status Inventaris',
            'stock_movements' => 'Pergerakan Stok',
        };
    }

    private function paymentMethodLabel(string $method): string
    {
        return match ($method) {
            'CASH' => 'Tunai',
            'BANK TRANSFER' => 'Transfer bank',
            'QRIS' => 'QRIS',
            'OTHER' => 'Lainnya',
            default => $method,
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function dataset(string $label, array $rows, string $field, string $color): array
    {
        return [
            'label' => $label,
            'values' => array_map(fn (array $row): float => (float) $row[$field], $rows),
            'color' => $color,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<string, mixed>
     */
    private function table(string $title, array $columns, Builder $query, int $page, int $perPage, bool $allRows): array
    {
        $count = (int) DB::query()->fromSub(clone $query, 'report_count')->count();
        $pageRows = $allRows
            ? $query->get()
            : $query->forPage($page, $perPage)->get();
        $from = $count === 0 ? null : ($allRows ? 1 : (($page - 1) * $perPage) + 1);

        return [
            'title' => $title,
            'columns' => $columns,
            'rows' => $pageRows->map(function (object $row): array {
                $values = (array) $row;

                if (isset($values['payment_method'])) {
                    $values['payment_method'] = $this->paymentMethodLabel((string) $values['payment_method']);
                }
                if (($values['type'] ?? null) === 'IN') {
                    $values['type'] = 'Masuk';
                } elseif (($values['type'] ?? null) === 'OUT') {
                    $values['type'] = 'Keluar';
                }

                return $values;
            })->all(),
            'pagination' => [
                'current_page' => $allRows ? 1 : $page,
                'per_page' => $allRows ? $count : $perPage,
                'last_page' => $allRows ? 1 : max(1, (int) ceil($count / $perPage)),
                'from' => $from,
                'to' => $count === 0 ? null : min($allRows ? $count : $page * $perPage, $count),
                'total' => $count,
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function arrayTable(string $title, array $columns, array $rows): array
    {
        return [
            'title' => $title,
            'columns' => $columns,
            'rows' => $rows,
            'pagination' => [
                'current_page' => 1,
                'per_page' => count($rows),
                'last_page' => 1,
                'from' => $rows === [] ? null : 1,
                'to' => count($rows) ?: null,
                'total' => count($rows),
            ],
        ];
    }
}
