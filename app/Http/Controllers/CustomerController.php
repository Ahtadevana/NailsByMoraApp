<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'contact' => ['nullable', Rule::in(['all', 'has_phone', 'no_phone', 'has_email', 'no_email'])],
            'activity' => ['nullable', Rule::in(['all', 'appointments', 'no_appointments', 'recent', 'no_recent'])],
            'created' => ['nullable', Rule::in(['all', 'today', 'week', 'month', 'year', 'custom'])],
            'created_from' => ['nullable', 'required_if:created,custom', 'date'],
            'created_to' => ['nullable', 'required_if:created,custom', 'date', 'after_or_equal:created_from'],
            'period' => ['nullable', Rule::in(['month', '30days', '90days', 'year'])],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'name_asc', 'name_desc', 'most_appointments', 'least_appointments', 'last_visit'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ]);

        $period = $filters['period'] ?? 'month';
        $periodStart = match ($period) {
            '30days' => today()->subDays(29)->startOfDay(),
            '90days' => today()->subDays(89)->startOfDay(),
            'year' => today()->startOfYear(),
            default => today()->startOfMonth(),
        };

        $query = Customer::query()
            ->withCount('appointments')
            ->withMax([
                'appointments as last_visit' => fn (Builder $appointments) => $appointments
                    ->where('status', 'COMPLETED'),
            ], 'appointment_date')
            ->selectSub(
                DB::table('appointments')
                    ->join('appointment_services', 'appointments.id', '=', 'appointment_services.appointment_id')
                    ->whereColumn('appointments.customer_id', 'customers.id')
                    ->where('appointments.status', 'COMPLETED')
                    ->selectRaw('COALESCE(SUM(appointment_services.price), 0)'),
                'total_spent',
            );

        if (! empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function (Builder $customers) use ($search): void {
                $customers->where('name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        match ($filters['contact'] ?? 'all') {
            'has_phone' => $query->whereNotNull('phone')->where('phone', '!=', ''),
            'no_phone' => $query->where(fn (Builder $customers) => $customers->whereNull('phone')->orWhere('phone', '')),
            'has_email' => $query->whereNotNull('email')->where('email', '!=', ''),
            'no_email' => $query->where(fn (Builder $customers) => $customers->whereNull('email')->orWhere('email', '')),
            default => null,
        };

        match ($filters['activity'] ?? 'all') {
            'appointments' => $query->has('appointments'),
            'no_appointments' => $query->doesntHave('appointments'),
            'recent' => $query->whereHas('appointments', fn (Builder $appointments) => $appointments
                ->where('status', 'COMPLETED')
                ->whereDate('appointment_date', '>=', today()->subDays(30))),
            'no_recent' => $query->whereDoesntHave('appointments', fn (Builder $appointments) => $appointments
                ->where('status', 'COMPLETED')
                ->whereDate('appointment_date', '>=', today()->subDays(30))),
            default => null,
        };

        $created = $filters['created'] ?? 'all';

        if ($created !== 'all') {
            [$createdFrom, $createdTo] = match ($created) {
                'today' => [today()->startOfDay(), today()->endOfDay()],
                'week' => [today()->startOfWeek(), today()->endOfWeek()],
                'month' => [today()->startOfMonth(), today()->endOfMonth()],
                'year' => [today()->startOfYear(), today()->endOfYear()],
                'custom' => [
                    CarbonImmutable::parse($filters['created_from'])->startOfDay(),
                    CarbonImmutable::parse($filters['created_to'])->endOfDay(),
                ],
            };

            $query->whereBetween('customers.created_at', [$createdFrom, $createdTo]);
        }

        match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->orderBy('customers.created_at'),
            'name_asc' => $query->orderBy('customers.name'),
            'name_desc' => $query->orderByDesc('customers.name'),
            'most_appointments' => $query->orderByDesc('appointments_count')->orderByDesc('customers.created_at'),
            'least_appointments' => $query->orderBy('appointments_count')->orderByDesc('customers.created_at'),
            'last_visit' => $query->orderByDesc('last_visit')->orderByDesc('customers.created_at'),
            default => $query->orderByDesc('customers.created_at'),
        };

        $perPage = (int) ($filters['per_page'] ?? 10);

        $customers = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Customer $customer): array => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'appointments_count' => $customer->appointments_count,
                'total_spent' => $customer->total_spent,
                'last_visit' => $customer->last_visit,
                'created_at' => $customer->created_at,
            ]);

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'contact' => $filters['contact'] ?? 'all',
                'activity' => $filters['activity'] ?? 'all',
                'created' => $created,
                'created_from' => $filters['created_from'] ?? '',
                'created_to' => $filters['created_to'] ?? '',
                'period' => $period,
                'sort' => $filters['sort'] ?? 'newest',
                'per_page' => $perPage,
            ],
            'summary' => [
                'total' => Customer::query()->count(),
                'new' => Customer::query()->whereBetween('created_at', [$periodStart, now()])->count(),
                'has_email' => Customer::query()->whereNotNull('email')->where('email', '!=', '')->count(),
                'has_phone' => Customer::query()->whereNotNull('phone')->where('phone', '!=', '')->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = Customer::query()->create($this->validateCustomer($request));

        return back()->with('toast', [
            'type' => 'success',
            'message' => $customer->name.' berhasil ditambahkan.',
        ]);
    }

    public function quickStore(Request $request): JsonResponse
    {
        $customer = Customer::query()->create($this->validateCustomer($request));

        return response()->json([
            'customer' => $customer->only(['id', 'name', 'phone', 'email']),
        ], 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        $appointments = DB::table('appointments')
            ->leftJoin('appointment_services', 'appointments.id', '=', 'appointment_services.appointment_id')
            ->leftJoin('services', 'appointment_services.service_id', '=', 'services.id')
            ->where('appointments.customer_id', $customer->id)
            ->select([
                'appointments.id',
                'appointments.appointment_date',
                'appointments.status',
                'services.name as service_name',
            ])
            ->orderByDesc('appointments.appointment_date')
            ->orderByDesc('appointments.start_time')
            ->get()
            ->groupBy('id')
            ->map(fn ($rows): array => [
                'id' => $rows->first()->id,
                'date' => $rows->first()->appointment_date,
                'status' => $rows->first()->status,
                'services' => $rows->pluck('service_name')->filter()->unique()->values(),
            ])
            ->values();

        $totalSpent = DB::table('appointments')
            ->join('appointment_services', 'appointments.id', '=', 'appointment_services.appointment_id')
            ->where('appointments.customer_id', $customer->id)
            ->where('appointments.status', 'COMPLETED')
            ->sum('appointment_services.price');

        $lastVisit = DB::table('appointments')
            ->where('customer_id', $customer->id)
            ->where('status', 'COMPLETED')
            ->max('appointment_date');

        return response()->json([
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'notes' => $customer->notes,
                'created_at' => $customer->created_at,
            ],
            'summary' => [
                'appointments' => $appointments->count(),
                'total_spent' => $totalSpent,
                'last_visit' => $lastVisit,
            ],
            'appointments' => $appointments,
        ]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $customer->update($this->validateCustomer($request));

        return back()->with('toast', [
            'type' => 'success',
            'message' => $customer->name.' berhasil diperbarui.',
        ]);
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        if ($customer->appointments()->exists()) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Pelanggan dengan riwayat janji temu tidak dapat dihapus.',
            ]);
        }

        $customer->delete();

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Pelanggan berhasil dihapus.',
        ]);
    }

    public function destroySelected(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:customers,id'],
        ]);

        $deletableCustomers = Customer::query()
            ->whereIn('id', $validated['ids'])
            ->doesntHave('appointments');

        $deletedCount = (clone $deletableCustomers)->count();
        $deletableCustomers->delete();

        if ($deletedCount < count($validated['ids'])) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => $deletedCount > 0
                    ? $deletedCount.' pelanggan dihapus. Pelanggan dengan riwayat janji temu tetap disimpan.'
                    : 'Pelanggan dengan riwayat janji temu tidak dapat dihapus.',
            ]);
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => $deletedCount.' pelanggan berhasil dihapus.',
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function customerRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'regex:/\A[\p{L}\p{M} ]+\z/u'],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/\A[0-9]+\z/'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCustomer(Request $request): array
    {
        return $request->validate($this->customerRules(), [
            'name.regex' => 'Nama hanya boleh berisi huruf dan spasi; angka dan simbol tidak diperbolehkan.',
            'phone.regex' => 'Nomor telepon hanya boleh berisi angka.',
            'email.email' => 'Masukkan alamat email dengan format yang valid.',
        ]);
    }
}
