<?php

namespace App\Services\Admin;

use App\Models\AdminSetting;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class BookingSchedule
{
    public function state(): array
    {
        $stored = AdminSetting::getValue('booking.schedule', [
            'today_open' => true,
            'tomorrow_open' => true,
            'hours' => $this->defaultHours(),
            'closed_dates' => [],
            'closed_until' => null,
        ]);
        $stored['hours'] = array_replace($this->defaultHours(), $stored['hours'] ?? []);

        return $stored;
    }

    public function save(array $data): array
    {
        $hours = $this->defaultHours();
        foreach (array_keys($hours) as $hour) {
            $hours[$hour] = in_array((string) $hour, array_map('strval', $data['open_hours'] ?? []), true);
        }
        $closed = collect($data['closed_dates'] ?? [])
            ->filter(fn ($date) => is_string($date) && $date !== '')
            ->map(fn (string $date) => Carbon::parse($date)->toDateString())
            ->unique()
            ->values()
            ->all();

        $state = [
            'today_open' => (bool) ($data['today_open'] ?? false),
            'tomorrow_open' => (bool) ($data['tomorrow_open'] ?? false),
            'hours' => $hours,
            'closed_dates' => $closed,
            'closed_until' => ($data['closed_until'] ?? null) ?: null,
        ];
        AdminSetting::putValue('booking.schedule', $state);

        return $state;
    }

    public function assertBookable(mixed $date, mixed $arrivalTime = null): void
    {
        if ($date === null || $date === '') {
            return;
        }

        $day = Carbon::parse($date)->startOfDay();
        $state = $this->state();
        $errors = [];

        if (! empty($state['closed_until']) && $day->lessThanOrEqualTo(Carbon::parse($state['closed_until'])->startOfDay())) {
            $errors['service_date'] = 'That service date is inside the closed-until period.';
        }

        $closed = collect($state['closed_dates'] ?? [])
            ->map(fn ($closedDate) => Carbon::parse($closedDate)->toDateString())
            ->all();
        if (in_array($day->toDateString(), $closed, true)) {
            $errors['service_date'] = 'That service date is closed.';
        }

        if ($day->isToday() && empty($state['today_open'])) {
            $errors['service_date'] = 'Bookings are closed today.';
        }

        if ($day->isTomorrow() && empty($state['tomorrow_open'])) {
            $errors['service_date'] = 'Bookings are closed tomorrow.';
        }

        if ($arrivalTime) {
            $hour = (string) (int) substr((string) $arrivalTime, 0, 2);
            $hours = $state['hours'] ?? [];
            $open = $hours[$hour] ?? false;
            if (! in_array($open, [true, 1, '1'], true)) {
                $errors['arrival_time'] = 'That arrival hour is closed.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function defaultHours(): array
    {
        $hours = [];
        for ($hour = 8; $hour <= 20; $hour++) {
            $hours[(string) $hour] = true;
        }

        return $hours;
    }
}
