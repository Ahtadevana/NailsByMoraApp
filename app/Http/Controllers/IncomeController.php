<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class IncomeController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50,100'],
        ]);
        $dateFrom = $filters['date_from'] ?? now()->startOfMonth()->toDateString();
        $dateTo = $filters['date_to'] ?? today()->toDateString();

        $query = DB::table('appointment_services')
            ->join('appointments', 'appointment_services.appointment_id', '=', 'appointments.id')
            ->join('customers', 'appointments.customer_id', '=', 'customers.id')
            ->join('services', 'appointment_services.service_id', '=', 'services.id')
            ->where('appointments.status', 'COMPLETED')
            ->whereBetween('appointments.appointment_date', [$dateFrom, $dateTo])
            ->when($filters['search'] ?? null, function (Builder $incomes, string $search): void {
                $incomes->where(function (Builder $matches) use ($search): void {
                    $matches->where('customers.name', 'like', '%'.$search.'%')
                        ->orWhere('services.name', 'like', '%'.$search.'%');
                });
            });
        $summaryQuery = clone $query;
        $perPage = (int) ($filters['per_page'] ?? 10);
        $incomes = $query
            ->select([
                'appointment_services.id',
                'appointments.id as appointment_id',
                'appointments.start_time',
                'customers.name as customer_name',
                'services.name as service_name',
                'appointment_services.price as amount',
            ])
            ->selectRaw('DATE(appointments.appointment_date) as appointment_date')
            ->orderByDesc('appointments.appointment_date')
            ->orderByDesc('appointments.start_time')
            ->orderBy('appointment_services.id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Incomes/Index', [
            'incomes' => $incomes,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'per_page' => $perPage,
            ],
            'summary' => [
                'amount' => (float) $summaryQuery->sum('appointment_services.price'),
                'count' => (int) $summaryQuery->count(),
            ],
        ]);
    }
}
