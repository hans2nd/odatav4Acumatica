<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncSchedule extends Model
{
    protected $fillable = [
        'entity_key',
        'cron_expression',
        'is_active',
        'last_run_at',
        'next_run_at',
        'custom_filters',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
        'custom_filters' => 'array',
    ];

    public function getFrequencyAttribute()
    {
        if (!$this->is_active || empty($this->cron_expression)) return '';
        
        $parts = explode(' ', $this->cron_expression);
        if (count($parts) !== 5) return '';

        [$min, $hour, $dom, $month, $dow] = $parts;

        if ($min === '*' || str_starts_with($min, '*/')) return 'minute';
        if ($hour === '*' || str_starts_with($hour, '*/')) return 'hour';
        if ($dow !== '*') return 'weekly';
        if ($dom !== '*') return 'monthly';
        return 'daily';
    }

    public function getIntervalAttribute()
    {
        if (!$this->is_active || empty($this->cron_expression)) return null;
        
        $parts = explode(' ', $this->cron_expression);
        if (count($parts) !== 5) return null;

        [$min, $hour] = $parts;
        
        if (str_starts_with($min, '*/')) return (int) substr($min, 2);
        if (str_starts_with($hour, '*/')) return (int) substr($hour, 2);
        return 1;
    }

    public function getTimeAttribute()
    {
        if (!$this->is_active || empty($this->cron_expression)) return null;
        
        $parts = explode(' ', $this->cron_expression);
        if (count($parts) !== 5) return null;

        [$min, $hour, $dom, $month, $dow] = $parts;

        // If it's minute or hour frequency, there's no specific time
        if ($min === '*' || str_starts_with($min, '*/') || $hour === '*' || str_starts_with($hour, '*/')) {
            return null;
        }

        return sprintf('%02d:%02d', (int)$hour, (int)$min);
    }

    public function getDayOfWeekAttribute()
    {
        if (!$this->is_active || empty($this->cron_expression)) return null;
        
        $parts = explode(' ', $this->cron_expression);
        if (count($parts) !== 5) return null;

        return $parts[4] !== '*' ? (int)$parts[4] : 1;
    }

    public function getDayOfMonthAttribute()
    {
        if (!$this->is_active || empty($this->cron_expression)) return null;
        
        $parts = explode(' ', $this->cron_expression);
        if (count($parts) !== 5) return null;

        return $parts[2] !== '*' ? (int)$parts[2] : 1;
    }
}
