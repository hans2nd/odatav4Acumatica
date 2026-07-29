<?php

namespace App\Services\Alerting\Dispatchers;

use App\Services\Alerting\Contracts\AlertDispatcherInterface;
use App\Services\Alerting\Models\AlertDefinition;
use App\Services\Alerting\Models\NotificationMessage;
use App\Services\Alerting\Models\NotificationResult;
use App\Services\Alerting\AlertRegistry;

class StandardAlertDispatcher implements AlertDispatcherInterface
{
    public function __construct(
        private readonly AlertRegistry $registry
    ) {}

    public function dispatch(AlertDefinition $definition, NotificationMessage $message): array
    {
        $results = [];

        $providers = $this->registry->getProviders();

        foreach ($definition->channels as $channel) {
            foreach ($providers as $provider) {
                if ($provider->supports($channel)) {
                    // Coba kirim pesan melalui provider
                    try {
                        $result = $provider->send($message);
                        $results[] = $result;
                    } catch (\Throwable $e) {
                        $results[] = new NotificationResult(
                            false, 
                            get_class($provider), 
                            "Gagal memproses pengiriman: " . $e->getMessage()
                        );
                    }
                }
            }
        }

        return $results;
    }
}
