<?php

namespace App\Enums;

enum MetadataStatus: string
{
    case PENDING = 'PENDING';
    case REFRESHING = 'REFRESHING';
    case STALE = 'STALE';
    case READY = 'READY';
    case FAILED = 'FAILED';
}
