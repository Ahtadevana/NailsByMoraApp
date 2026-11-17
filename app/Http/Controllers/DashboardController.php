<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'period' => ['nullable', Rule::in(['today', 'week', 'month', 'custom'])],
            'start_date' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);
        $period = $filters['period'] ?? 'month';
        [$start, $end] = $this->periodDates($period, $filters);
        $startAt = $start->startOfDay();
        $endAt = $end->endOfDay();

        $revenue = (float) DB::table('appointment_services')
            ->join('appointments', 'appointment_services.appointment_id', '=', 'appointments.id')
            ->where('appointments.status', 'COMPLETED')
            ->whereBetween('appointments.appointment_date', [$start->toDateString(), $end->toDateString()])
            ->sum('appointment_services.price');
        $expenses = (float) DB::table('expenses')
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->sum('amount');
        $appointmentStats = DB::table('appointments')
            ->whereBetween('appointment_date', [$start->toDateString(), $end->toDateString()])
            ->select('status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $lowStockItems = DB::table('inventory')
            ->join('inventory_categories', 'inventory.category_id', '=', 'inventory_categories.id')
            ->whereColumn('inventory.quantity', '<', 'inventory.minimum_stock')
            ->orderByRaw('(inventory.quantity / NULLIF(inventory.minimum_stock, 0))')
            ->orderBy('inventory.name')
            ->limit(5)
            ->get([
                'inventory.id',
                'inventory.name',
                'inventory.quantity',
                'inventory.minimum_stock',
                'inventory.unit',
                'inventory_categories.name as category',
            ]);

        $today = today()->toDateString();
        $nowTime = now()->format('H:i:s');
        $todayAppointments = Appointment::query()
            ->with(['customer:id,name', 'services:id,name'])
            ->whereDate('appointment_date', $today)
            ->orderBy('start_time')
            ->limit(8)
            ->get(['id', 'customer_id', 'start_time', 'status'])
            ->map(fn (Appointment $appointment): array => [
                'id' => $appointment->id,
                'start_time' => $appointment->start_time,
                'status' => $appointment->status,
                'customer' => $appointment->customer->name,
                'services' => $appointment->services->pluck('name')->all(),
            ]);

        return Inertia::render('Dashboard/Index', [
            'period' => [
                'value' => $period,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'label' => $start->isSameDay($end)
                    ? $start->locale('id')->translatedFormat('j F Y')
                    : $start->locale('id')->translatedFormat('j M Y').' – '.$end->locale('id')->translatedFormat('j M Y'),
            ],
            'financial' => [
                'revenue' => $revenue,
                'expenses' => $expenses,
                'net_income' => $revenue - $expenses,
                'appointments' => [
                    'total' => (int) $appointmentStats->sum(),
                    'scheduled' => (int) ($appointmentStats['SCHEDULED'] ?? 0),
                    'completed' => (int) ($appointmentStats['COMPLETED'] ?? 0),
                    'cancelled' => (int) ($appointmentStats['CANCELLED'] ?? 0),
                    'no_show' => (int) ($appointmentStats['NO_SHOW'] ?? 0),
                ],
            ],
            'chart' => $this->financialChart($start, $end),
            'todayAppointments' => $todayAppointments,
            'todayAppointmentCount' => Appointment::query()->whereDate('appointment_date', $today)->count(),
            'today' => $today,
            'attention' => [
                'low_stock_count' => DB::table('inventory')->whereColumn('quantity', '<', 'minimum_stock')->count(),
                'low_stock_items' => $lowStockItems,
            ],
            'activity' => [
                'customers' => DB::table('customers')->count(),
                'new_customers' => DB::table('customers')
                    ->whereBetween('created_at', [$startAt->toDateTimeString(), $endAt->toDateTimeString()])
                    ->count(),
                'active_services' => DB::table('services')->where('is_active', true)->count(),
                'inventory_items' => DB::table('inventory')->count(),
                'upcoming_appointments' => Appointment::query()
                    ->where('status', 'SCHEDULED')
                    ->where(function (Builder $appointments) use ($today, $nowTime): void {
                        $appointments->whereDate('appointment_date', '>', $today)
                            ->orWhere(function (Builder $todaysAppointments) use ($today, $nowTime): void {
                                $todaysAppointments->whereDate('appointment_date', $today)
                                    ->where('start_time', '>=', $nowTime);
                            });
                    })
                    ->count(),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function periodDates(string $period, array $filters): array
    {
        $today = CarbonImmutable::today();

        return match ($period) {
            'today' => [$today, $today],
            'week' => [$today->startOfWeek(CarbonImmutable::MONDAY), $today],
            'custom' => [
                CarbonImmutable::createFromFormat('!Y-m-d', $filters['start_date']),
                CarbonImmutable::createFromFormat('!Y-m-d', $filters['end_date']),
            ],
            default => [$today->startOfMonth(), $today],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function financialChart(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $days = (int) $start->diffInDays($end);
        $interval = $days <= 31 ? 'day' : ($days <= 120 ? 'week' : 'month');
        $series = [];
        $cursor = $this->bucketStart($start, $interval);
        $lastBucket = $this->bucketStart($end, $interval);

        while ($cursor <= $lastBucket) {
            $key = $cursor->toDateString();
            $series[$key] = [
                'label' => $interval === 'month'
                    ? $cursor->locale('id')->translatedFormat('M Y')
                    : $cursor->locale('id')->translatedFormat('j M'),
                'revenue' => 0.0,
                'expenses' => 0.0,
            ];
            $cursor = match ($interval) {
                'week' => $cursor->addWeek(),
                'month' => $cursor->addMonth(),
                default => $cursor->addDay(),
            };
        }

        $revenueByDate = DB::table('appointment_services')
            ->join('appointments', 'appointment_services.appointment_id', '=', 'appointments.id')
            ->where('appointments.status', 'COMPLETED')
            ->whereBetween('appointments.appointment_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('DATE(appointments.appointment_date) as activity_date')
            ->selectRaw('SUM(appointment_services.price) as amount')
            ->groupByRaw('DATE(appointments.appointment_date)')
            ->get();
        $expensesByDate = DB::table('expenses')
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('DATE(expense_date) as activity_date')
            ->selectRaw('SUM(amount) as amount')
            ->groupByRaw('DATE(expense_date)')
            ->get();

        foreach ($revenueByDate as $row) {
            $key = $this->bucketStart(CarbonImmutable::parse($row->activity_date), $interval)->toDateString();
            $series[$key]['revenue'] += (float) $row->amount;
        }
        foreach ($expensesByDate as $row) {
            $key = $this->bucketStart(CarbonImmutable::parse($row->activity_date), $interval)->toDateString();
            $series[$key]['expenses'] += (float) $row->amount;
        }

        return [
            'type' => 'line',
            'title' => 'Financial Overview',
            'currency' => true,
            'labels' => array_column(array_values($series), 'label'),
            'datasets' => [
                ['label' => 'Revenue', 'values' => array_column(array_values($series), 'revenue'), 'color' => '#7c2838'],
                ['label' => 'Expenses', 'values' => array_column(array_values($series), 'expenses'), 'color' => '#d89b43'],
            ],
        ];
    }

    private function bucketStart(CarbonImmutable $date, string $interval): CarbonImmutable
    {
        return match ($interval) {
            'week' => $date->startOfWeek(CarbonImmutable::MONDAY),
            'month' => $date->startOfMonth(),
            default => $date->startOfDay(),
        };
    }
}
