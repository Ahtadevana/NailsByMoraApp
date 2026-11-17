<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    private const STATUSES = ['SCHEDULED', 'COMPLETED', 'CANCELLED', 'NO_SHOW'];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'date_from' => ['nullable', 'required_with:date_to', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'required_with:date_from', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['all', ...self::STATUSES])],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'sort' => ['nullable', Rule::in(['soonest', 'latest', 'customer_asc', 'customer_desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ]);

        $month = CarbonImmutable::createFromFormat('!Y-m', $filters['month'] ?? now()->format('Y-m'));
        $monthStart = $month->startOfMonth();
        $monthEnd = $month->endOfMonth();

        $query = Appointment::query()
            ->with([
                'customer:id,name,phone,email',
                'services:id,name,is_active',
            ]);

        if (! empty($filters['date'])) {
            $query->whereDate('appointment_date', $filters['date']);
        } elseif (! empty($filters['date_from']) && ! empty($filters['date_to'])) {
            $query->whereBetween('appointment_date', [$filters['date_from'], $filters['date_to']]);
        } else {
            $query->whereBetween('appointment_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $appointments) use ($search): void {
                $appointments->whereHas('customer', fn (Builder $customers) => $customers->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('services', fn (Builder $services) => $services->where('name', 'like', '%'.$search.'%'))
                    ->orWhere('notes', 'like', '%'.$search.'%');
            });
        }

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (($filters['status'] ?? 'all') !== 'all') {
            $query->where('status', $filters['status']);
        }

        match ($filters['sort'] ?? 'soonest') {
            'latest' => $query->orderByDesc('appointment_date')->orderByDesc('start_time'),
            'customer_asc' => $query->join('customers as appointment_customers', 'appointments.customer_id', '=', 'appointment_customers.id')
                ->orderBy('appointment_customers.name')->orderBy('appointments.appointment_date')->orderBy('appointments.start_time')
                ->select('appointments.*'),
            'customer_desc' => $query->join('customers as appointment_customers', 'appointments.customer_id', '=', 'appointment_customers.id')
                ->orderByDesc('appointment_customers.name')->orderBy('appointments.appointment_date')->orderBy('appointments.start_time')
                ->select('appointments.*'),
            default => $query->orderBy('appointment_date')->orderBy('start_time'),
        };

        $perPage = (int) ($filters['per_page'] ?? 10);
        $appointments = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Appointment $appointment): array => $this->appointmentRow($appointment));

        $calendarCounts = Appointment::query()
            ->whereBetween('appointment_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->selectRaw('appointment_date, COUNT(*) as total')
            ->groupBy('appointment_date')
            ->orderBy('appointment_date')
            ->get()
            ->mapWithKeys(fn ($entry): array => [
                CarbonImmutable::parse($entry->appointment_date)->toDateString() => (int) $entry->total,
            ]);

        $monthAppointments = Appointment::query()->whereBetween(
            'appointment_date',
            [$monthStart->toDateString(), $monthEnd->toDateString()],
        );
        $stats = (clone $monthAppointments)
            ->select('status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $selectedCustomer = ! empty($filters['customer_id'])
            ? Customer::query()->find($filters['customer_id'], ['id', 'name', 'phone', 'email'])
            : null;

        return Inertia::render('Appointments/Index', [
            'appointments' => $appointments,
            'calendar' => [
                'month' => $month->format('Y-m'),
                'counts' => $calendarCounts,
            ],
            'filters' => [
                'month' => $month->format('Y-m'),
                'date' => $filters['date'] ?? '',
                'date_from' => $filters['date_from'] ?? '',
                'date_to' => $filters['date_to'] ?? '',
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? 'all',
                'customer_id' => $filters['customer_id'] ?? '',
                'sort' => $filters['sort'] ?? 'soonest',
                'per_page' => $perPage,
            ],
            'selectedCustomer' => $selectedCustomer,
            'serviceCatalog' => Service::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'price', 'duration_minutes']),
            'summary' => [
                'today' => Appointment::query()->whereDate('appointment_date', today())->count(),
                'scheduled' => (int) ($stats['SCHEDULED'] ?? 0),
                'completed' => (int) ($stats['COMPLETED'] ?? 0),
                'no_show' => (int) ($stats['NO_SHOW'] ?? 0),
            ],
        ]);
    }

    public function lookups(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $search = $validated['search'] ?? '';

        $customers = Customer::query()
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $customers) use ($search): void {
                $customers->where('name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            }))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'phone', 'email']);

        return response()->json(['customers' => $customers]);
    }

    public function show(Appointment $appointment): JsonResponse
    {
        $appointment->load(['customer:id,name,phone,email', 'services:id,name,is_active']);
        $totalDuration = (int) $appointment->services->sum('pivot.duration_minutes');
        $startAt = CarbonImmutable::parse($appointment->appointment_date->toDateString().' '.$appointment->start_time);

        return response()->json([
            'appointment' => [
                'id' => $appointment->id,
                'customer' => $appointment->customer,
                'date' => $appointment->appointment_date->toDateString(),
                'start_time' => substr($appointment->start_time, 0, 5),
                'end_time' => $startAt->addMinutes($totalDuration)->format('H:i'),
                'status' => $appointment->status,
                'notes' => $appointment->notes,
                'services' => $appointment->services->map(fn (Service $service): array => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'is_active' => $service->is_active,
                    'price' => (float) $service->pivot->price,
                    'duration_minutes' => (int) $service->pivot->duration_minutes,
                ]),
                'total_price' => (float) $appointment->services->sum(fn (Service $service): float => (float) $service->pivot->price),
                'total_duration' => $totalDuration,
                'created_at' => $appointment->created_at,
                'updated_at' => $appointment->updated_at,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateAppointment($request);
        $services = $this->selectedServices($validated['services']);
        $this->ensureServicesCanBeBooked($services);

        DB::transaction(function () use ($validated, $services): void {
            if ($validated['status'] === 'SCHEDULED') {
                $this->ensureScheduleIsAvailable(
                    $validated['appointment_date'],
                    $validated['start_time'],
                    (int) $services->sum('duration_minutes'),
                );
            }

            $appointment = Appointment::query()->create([
                'customer_id' => $validated['customer_id'],
                'appointment_date' => $validated['appointment_date'],
                'start_time' => $validated['start_time'],
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
            ]);
            $appointment->services()->sync($services->mapWithKeys(fn (Service $service): array => [
                $service->id => [
                    'price' => $service->price,
                    'duration_minutes' => $service->duration_minutes,
                ],
            ])->all());
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Janji temu berhasil dibuat.']);
    }

    public function update(Request $request, Appointment $appointment): RedirectResponse
    {
        $validated = $this->validateAppointment($request);
        $existingServices = $appointment->services()->get()->keyBy('id');
        $services = $this->selectedServices($validated['services']);
        $this->ensureServicesCanBeBooked($services, $existingServices->keys()->all());
        $durationMinutes = (int) $services->sum(fn (Service $service): int => (int) (
            $existingServices->get($service->id)?->pivot->duration_minutes ?? $service->duration_minutes
        ));

        DB::transaction(function () use ($appointment, $validated, $services, $existingServices, $durationMinutes): void {
            if ($validated['status'] === 'SCHEDULED') {
                $this->ensureScheduleIsAvailable(
                    $validated['appointment_date'],
                    $validated['start_time'],
                    $durationMinutes,
                    $appointment->id,
                );
            }

            $appointment->update([
                'customer_id' => $validated['customer_id'],
                'appointment_date' => $validated['appointment_date'],
                'start_time' => $validated['start_time'],
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
            ]);

            $appointment->services()->sync($services->mapWithKeys(function (Service $service) use ($existingServices): array {
                $previous = $existingServices->get($service->id);

                return [$service->id => [
                    'price' => $previous?->pivot->price ?? $service->price,
                    'duration_minutes' => $previous?->pivot->duration_minutes ?? $service->duration_minutes,
                ]];
            })->all());
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Janji temu berhasil diperbarui.']);
    }

    public function updateStatus(Request $request, Appointment $appointment): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
        ]);

        DB::transaction(function () use ($appointment, $validated): void {
            if ($validated['status'] === 'SCHEDULED') {
                $this->validateScheduledAppointmentTime(
                    $appointment->appointment_date->toDateString(),
                    $appointment->start_time,
                    'status',
                );
                $durationMinutes = (int) $appointment->services()->sum('appointment_services.duration_minutes');
                $this->ensureScheduleIsAvailable(
                    $appointment->appointment_date->toDateString(),
                    $appointment->start_time,
                    $durationMinutes,
                    $appointment->id,
                    'status',
                );
            }

            $appointment->update(['status' => $validated['status']]);
        });

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Status janji temu diubah menjadi '.$this->statusLabel($validated['status']).'.',
        ]);
    }

    public function destroy(Appointment $appointment): RedirectResponse
    {
        DB::transaction(function () use ($appointment): void {
            $appointment->services()->detach();
            $appointment->delete();
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Janji temu berhasil dihapus.']);
    }

    public function destroySelected(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:appointments,id'],
        ]);

        $deletedCount = DB::transaction(function () use ($validated): int {
            $appointments = Appointment::query()
                ->whereIn('id', $validated['ids'])
                ->lockForUpdate()
                ->get();
            $appointmentIds = $appointments->modelKeys();

            if ($appointmentIds === []) {
                return 0;
            }

            DB::table('appointment_services')
                ->whereIn('appointment_id', $appointmentIds)
                ->delete();

            return Appointment::query()->whereIn('id', $appointmentIds)->delete();
        });

        return back()->with('toast', [
            'type' => 'success',
            'message' => $deletedCount.' janji temu berhasil dihapus.',
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function appointmentRules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'appointment_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'notes' => ['nullable', 'string', 'max:10000'],
            'services' => ['required', 'array', 'min:1', 'max:50'],
            'services.*.id' => ['required', 'integer', 'distinct', 'exists:services,id'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAppointment(Request $request): array
    {
        $validated = $request->validate($this->appointmentRules());

        if ($validated['status'] === 'SCHEDULED') {
            $this->validateScheduledAppointmentTime(
                $validated['appointment_date'],
                $validated['start_time'],
                'appointment_date',
            );
        }

        return $validated;
    }

    private function validateScheduledAppointmentTime(string $date, string $time, string $errorField): void
    {
        if (CarbonImmutable::parse($date.' '.$time)->isPast()) {
            throw ValidationException::withMessages([
                $errorField => 'Janji temu berstatus terjadwal harus dijadwalkan pada waktu mendatang.',
            ]);
        }
    }

    private function ensureScheduleIsAvailable(
        string $date,
        string $time,
        int $durationMinutes,
        ?int $ignoreAppointmentId = null,
        string $errorField = 'start_time',
    ): void {
        if ($durationMinutes < 1) {
            return;
        }

        $requestedStart = CarbonImmutable::parse($date.' '.$time);
        $requestedEnd = $requestedStart->addMinutes($durationMinutes);
        $appointments = Appointment::query()
            ->with('services')
            ->whereDate('appointment_date', $date)
            ->where('status', 'SCHEDULED');

        if ($ignoreAppointmentId !== null) {
            $appointments->where('id', '!=', $ignoreAppointmentId);
        }

        foreach ($appointments->lockForUpdate()->get() as $scheduledAppointment) {
            $scheduledStart = CarbonImmutable::parse(
                $scheduledAppointment->appointment_date->toDateString().' '.$scheduledAppointment->start_time,
            );
            $scheduledEnd = $scheduledStart->addMinutes(
                (int) $scheduledAppointment->services->sum('pivot.duration_minutes'),
            );

            if ($requestedStart->lt($scheduledEnd) && $scheduledStart->lt($requestedEnd)) {
                throw ValidationException::withMessages([
                    $errorField => 'Waktu ini bertabrakan dengan jadwal janji temu lainnya. Pilih waktu lain.',
                ]);
            }
        }
    }

    private function selectedServices(array $lines)
    {
        $ids = collect($lines)->pluck('id');

        return Service::query()->whereIn('id', $ids)->get()->keyBy('id')->sortBy(
            fn (Service $service): int => $ids->search($service->id),
        )->values();
    }

    private function ensureServicesCanBeBooked($services, array $existingIds = []): void
    {
        if ($services->count() === 0) {
            throw ValidationException::withMessages(['services' => 'Pilih minimal satu layanan.']);
        }

        if ($services->contains(fn (Service $service): bool => ! $service->is_active && ! in_array($service->id, $existingIds))) {
            throw ValidationException::withMessages(['services' => 'Layanan nonaktif tidak dapat ditambahkan ke janji temu.']);
        }
    }

    private function appointmentRow(Appointment $appointment): array
    {
        return [
            'id' => $appointment->id,
            'customer' => $appointment->customer,
            'date' => $appointment->appointment_date->toDateString(),
            'start_time' => substr($appointment->start_time, 0, 5),
            'status' => $appointment->status,
            'notes' => $appointment->notes,
            'services' => $appointment->services->map(fn (Service $service): array => [
                'id' => $service->id,
                'name' => $service->name,
                'is_active' => $service->is_active,
                'price' => (float) $service->pivot->price,
                'duration_minutes' => (int) $service->pivot->duration_minutes,
            ]),
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'NO_SHOW' => 'tidak hadir',
            'SCHEDULED' => 'terjadwal',
            'COMPLETED' => 'selesai',
            'CANCELLED' => 'dibatalkan',
        };
    }
}
