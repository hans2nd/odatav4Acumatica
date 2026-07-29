<?php

namespace App\Services\Alerting\Enums;

class NotificationChannel
{
    public const EMAIL = 'EMAIL';
    public const SLACK = 'SLACK';
    public const MICROSOFT_TEAMS = 'MICROSOFT_TEAMS';
    public const DISCORD = 'DISCORD';
    public const WEBHOOK = 'WEBHOOK';
    public const SMS = 'SMS';
    public const PUSH_NOTIFICATION = 'PUSH_NOTIFICATION';
    public const PAGER_DUTY = 'PAGER_DUTY';
    public const LOG = 'LOG'; // Untuk purwarupa / dry-run
}
