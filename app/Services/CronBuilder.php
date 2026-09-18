<?php

namespace App\Services;

use InvalidArgumentException;
use Cron\CronExpression;

class CronBuilder
{
    public const PRESET_EVERY_5_MINUTES = 'Every 5 Minutes';
    public const PRESET_EVERY_10_MINUTES = 'Every 10 Minutes';
    public const PRESET_EVERY_15_MINUTES = 'Every 15 Minutes';
    public const PRESET_EVERY_30_MINUTES = 'Every 30 Minutes';
    public const PRESET_HOURLY = 'Hourly';
    public const PRESET_EVERY_2_HOURS = 'Every 2 Hours';
    public const PRESET_EVERY_6_HOURS = 'Every 6 Hours';
    public const PRESET_DAILY = 'Daily';
    public const PRESET_WEEKLY = 'Weekly';
    public const PRESET_MONTHLY = 'Monthly';
    public const PRESET_CUSTOM = 'Custom Cron';

    /**
     * Generate a valid cron expression from a preset and optional parameters.
     */
    public function fromPreset(
        string $preset, 
        ?string $time = null, 
        ?int $dayOfWeek = null, 
        ?int $dayOfMonth = null, 
        ?string $customCron = null
    ): string {
        if ($preset === self::PRESET_CUSTOM) {
            if (!$customCron || !$this->validate($customCron)) {
                throw new InvalidArgumentException("Invalid custom cron expression: {$customCron}");
            }
            return $customCron;
        }

        $hour = '*';
        $minute = '*';

        if ($time) {
            if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time)) {
                throw new InvalidArgumentException("Invalid time format. Expected HH:MM.");
            }
            [$hour, $minute] = explode(':', $time);
            $hour = (int)$hour;
            $minute = (int)$minute;
        } else {
            // Default time if not provided but required for daily+
            $hour = 0;
            $minute = 0;
        }

        return match ($preset) {
            self::PRESET_EVERY_5_MINUTES => '*/5 * * * *',
            self::PRESET_EVERY_10_MINUTES => '*/10 * * * *',
            self::PRESET_EVERY_15_MINUTES => '*/15 * * * *',
            self::PRESET_EVERY_30_MINUTES => '*/30 * * * *',
            self::PRESET_HOURLY => '0 * * * *',
            self::PRESET_EVERY_2_HOURS => '0 */2 * * *',
            self::PRESET_EVERY_6_HOURS => '0 */6 * * *',
            self::PRESET_DAILY => "{$minute} {$hour} * * *",
            self::PRESET_WEEKLY => "{$minute} {$hour} * * " . ($dayOfWeek ?? 1),
            self::PRESET_MONTHLY => "{$minute} {$hour} " . ($dayOfMonth ?? 1) . " * *",
            default => throw new InvalidArgumentException("Invalid preset: {$preset}")
        };
    }

    /**
     * Compile is an alias to fromPreset if passing a DTO/Array is preferred in the future.
     */
    public function compile(array $data): string
    {
        return $this->fromPreset(
            $data['preset'] ?? self::PRESET_DAILY,
            $data['time'] ?? null,
            $data['day_of_week'] ?? null,
            $data['day_of_month'] ?? null,
            $data['custom_cron'] ?? null
        );
    }

    /**
     * Check if a cron string is valid using the native Cron library.
     */
    public function validate(string $cron): bool
    {
        return CronExpression::isValidExpression($cron);
    }

    /**
     * Generate human-readable descriptions.
     */
    public function describe(string $cron): string
    {
        // For MVP, we simply map back common presets or return the raw string.
        // A full natural language parser is Enterprise scope.
        $map = [
            '*/5 * * * *' => 'Every 5 minutes',
            '*/10 * * * *' => 'Every 10 minutes',
            '*/15 * * * *' => 'Every 15 minutes',
            '*/30 * * * *' => 'Every 30 minutes',
            '0 * * * *' => 'Every hour',
            '0 */2 * * *' => 'Every 2 hours',
            '0 */6 * * *' => 'Every 6 hours',
        ];

        if (isset($map[$cron])) {
            return $map[$cron];
        }

        return "Custom schedule ({$cron})";
    }

    /**
     * Calculate preview execution times.
     */
    public function preview(string $cron, int $count = 3): array
    {
        if (!$this->validate($cron)) {
            throw new InvalidArgumentException("Cannot preview invalid cron expression.");
        }

        $expression = new CronExpression($cron);
        $runs = [];

        for ($i = 0; $i < $count; $i++) {
            $runs[] = $expression->getNextRunDate(null, $i)->format('Y-m-d H:i:s');
        }

        return $runs;
    }

    /**
     * Parse the cron into parts.
     */
    public function parse(string $cron): array
    {
        $parts = explode(' ', trim($cron));
        if (count($parts) !== 5) {
            return [];
        }

        return [
            'minute' => $parts[0],
            'hour' => $parts[1],
            'day_of_month' => $parts[2],
            'month' => $parts[3],
            'day_of_week' => $parts[4],
        ];
    }
}
