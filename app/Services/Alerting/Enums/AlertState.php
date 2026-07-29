<?php

namespace App\Services\Alerting\Enums;

class AlertState
{
    /**
     * Kondisi aman, alarm tidak berbunyi.
     */
    public const RESOLVED = 'RESOLVED';

    /**
     * Kondisi anomali terdeteksi, alarm harus dibunyikan.
     */
    public const FIRING = 'FIRING';
}
