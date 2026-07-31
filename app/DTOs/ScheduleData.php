<?php

namespace App\DTOs;

use Illuminate\Http\Request;

class ScheduleData
{
    public function __construct(
        public readonly bool $isActive,
        public readonly ?string $preset = null,
        public readonly ?string $time = null,
        public readonly ?int $dayOfWeek = null,
        public readonly ?int $dayOfMonth = null,
        public readonly ?string $customCron = null,
        public readonly ?string $cronExpression = null,
        public readonly string $timezone = 'UTC'
    ) {}

    public static function fromRequest(Request $request): self
    {
        $frequency = $request->input('frequency');
        $interval = (int) $request->input('interval', 1);
        $time = $request->input('time', '00:00');
        
        $cronExpression = $request->input('cron_expression');
        $isActive = $request->has('is_active') ? $request->boolean('is_active') : !empty($frequency);
        
        if (empty($cronExpression) && !empty($frequency)) {
            $hour = '*';
            $minute = '*';
            if ($time && preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time)) {
                [$hour, $minute] = explode(':', $time);
                $hour = (int)$hour;
                $minute = (int)$minute;
            } else {
                $hour = 0;
                $minute = 0;
            }

            if ($frequency === 'minute') {
                $cronExpression = "*/{$interval} * * * *";
            } elseif ($frequency === 'hour') {
                $cronExpression = "0 */{$interval} * * *";
            } elseif ($frequency === 'daily') {
                $cronExpression = "{$minute} {$hour} * * *";
            } elseif ($frequency === 'weekly') {
                $dow = (int) $request->input('day_of_week', 1);
                $cronExpression = "{$minute} {$hour} * * {$dow}";
            } elseif ($frequency === 'monthly') {
                $dom = (int) $request->input('day_of_month', 1);
                $cronExpression = "{$minute} {$hour} {$dom} * *";
            }
        }

        return new self(
            isActive: $isActive,
            preset: $request->input('preset'),
            time: $time,
            dayOfWeek: $request->has('day_of_week') ? (int) $request->input('day_of_week') : null,
            dayOfMonth: $request->has('day_of_month') ? (int) $request->input('day_of_month') : null,
            customCron: $request->input('custom_cron'),
            cronExpression: $cronExpression,
            timezone: $request->input('timezone', 'UTC')
        );
    }
}
