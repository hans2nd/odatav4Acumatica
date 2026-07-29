<?php

namespace App\Services\Monitoring\Enums;

class HealthStatus
{
    public const HEALTHY = 'HEALTHY';
    public const WARNING = 'WARNING';
    public const DEGRADED = 'DEGRADED';
    public const CRITICAL = 'CRITICAL';
    public const FAILED = 'FAILED';
    public const UNKNOWN = 'UNKNOWN';

    /**
     * Skor tingkat keparahan untuk kompilasi (Semakin tinggi semakin parah).
     * Digunakan saat mengagregasi aturan (Worst wins).
     */
    public static function getSeverity(string $status): int
    {
        return match ($status) {
            self::HEALTHY => 0,
            self::UNKNOWN => 1,
            self::WARNING => 2,
            self::DEGRADED => 3,
            self::FAILED => 4,
            self::CRITICAL => 5,
            default => 1,
        };
    }
}
