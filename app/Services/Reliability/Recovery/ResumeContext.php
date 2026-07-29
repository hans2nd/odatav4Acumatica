<?php

namespace App\Services\Reliability\Recovery;

class ResumeContext
{
    /**
     * @param string $policy Kebijakan pelanjutan (Lihat ResumePolicy)
     * @param array $additionalContext Opsi tambahan
     */
    public function __construct(
        public readonly string $policy = 'FROM_LAST_SUCCESSFUL_BATCH',
        public readonly array $additionalContext = []
    ) {}
}
