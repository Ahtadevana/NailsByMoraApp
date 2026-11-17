<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

#[Signature('appointments:cancel-overdue')]
class CancelOverdueAppointments extends Command
{
    public function handle(): int
    {
        $now = CarbonImmutable::now();
        $cancelledCount = 0;

        Appointment::query()
            ->where('status', 'SCHEDULED')
            ->whereDate('appointment_date', '<=', $now->toDateString())
            ->with('services')
            ->chunkById(100, function (Collection $appointments) use ($now, &$cancelledCount): void {
                foreach ($appointments as $appointment) {
                    $startsAt = CarbonImmutable::parse(
                        $appointment->appointment_date->toDateString().' '.$appointment->start_time,
                    );
                    $durationMinutes = (int) $appointment->services->sum(
                        fn (Service $service): int => (int) $service->pivot->duration_minutes,
                    );

                    if ($startsAt->addMinutes($durationMinutes)->isAfter($now)) {
                        continue;
                    }

                    $cancelledCount += Appointment::query()
                        ->whereKey($appointment->id)
                        ->where('status', 'SCHEDULED')
                        ->whereDate('appointment_date', $appointment->appointment_date->toDateString())
                        ->whereTime('start_time', $appointment->start_time)
                        ->update([
                            'status' => 'CANCELLED',
                            'updated_at' => $now,
                        ]);
                }
            });

        $this->info("{$cancelledCount} janji temu terlewat otomatis dibatalkan.");

        return self::SUCCESS;
    }
}
