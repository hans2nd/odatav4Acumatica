<?php

namespace App\Services\Reliability\Classification\Enums;

class FailureCategory
{
    public const TRANSIENT = 'TRANSIENT_FAILURE';
    public const PERMANENT = 'PERMANENT_FAILURE';
    public const INFRASTRUCTURE = 'INFRASTRUCTURE_FAILURE';
    public const DATABASE = 'DATABASE_FAILURE';
    public const NETWORK = 'NETWORK_FAILURE';
    public const VALIDATION = 'VALIDATION_FAILURE';
    public const CONFIGURATION = 'CONFIGURATION_FAILURE';
    public const AUTHORIZATION = 'AUTHORIZATION_FAILURE';
    public const UNKNOWN = 'UNKNOWN_FAILURE';
}
