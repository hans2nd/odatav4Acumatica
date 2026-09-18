<?php

declare(strict_types=1);

namespace App\Services\Connections;

use App\Contracts\Connections\ConnectionProviderInterface;
use App\Exceptions\Connections\ProviderNotFoundException;
use App\Exceptions\Connections\ProviderDisabledException;
use InvalidArgumentException;

class ConnectionProviderFactory
{
    /**
     * Resolves a connection type into its concrete provider instance.
     *
     * @param string $type The connection type (e.g., mysql, sqlsrv, odata)
     * @return ConnectionProviderInterface
     *
     * @throws ProviderNotFoundException
     * @throws ProviderDisabledException
     * @throws InvalidArgumentException
     */
    public function make(string $type): ConnectionProviderInterface
    {
        $registry = config('providers.registry', []);
        $aliases = config('providers.aliases', []);

        // Resolve alias if present
        if (isset($aliases[$type])) {
            $type = $aliases[$type];
        }

        if (!isset($registry[$type])) {
            throw new ProviderNotFoundException("Connection provider type [{$type}] is not registered.");
        }

        $providerConfig = $registry[$type];
        
        if (empty($providerConfig['enabled'])) {
            throw new ProviderDisabledException("Connection provider type [{$type}] is disabled.");
        }

        $className = $providerConfig['class'];

        if (!class_exists($className)) {
            throw new InvalidArgumentException("Connection provider class [{$className}] does not exist.");
        }

        $instance = app($className);

        if (!$instance instanceof ConnectionProviderInterface) {
            throw new InvalidArgumentException("Class [{$className}] must implement ConnectionProviderInterface.");
        }

        return $instance;
    }

    /**
     * @return array<string>
     */
    public static function getDatabaseTypes(): array
    {
        return array_keys(array_filter(config('providers.registry', []), function ($p) {
            return ($p['kind'] ?? '') === 'database' && !empty($p['enabled']);
        }));
    }

    /**
     * @return array<string>
     */
    public static function getApiTypes(): array
    {
        return array_keys(array_filter(config('providers.registry', []), function ($p) {
            return ($p['kind'] ?? '') === 'api' && !empty($p['enabled']);
        }));
    }
}
