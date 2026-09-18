<?php

namespace App\Enums;

enum MetadataSource: string
{
    case CACHE = 'CACHE';
    case DATABASE = 'DATABASE';
    case PROVIDER = 'PROVIDER';
}
