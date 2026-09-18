<?php

namespace App\Enums;

enum RefreshReason: string
{
    case MANUAL = 'MANUAL';
    case AUTO = 'AUTO';
    case CACHE_MISS = 'CACHE_MISS';
    case FORCE_REFRESH = 'FORCE_REFRESH';
    case QUEUE = 'QUEUE';
}
