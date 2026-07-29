<?php

namespace App\Services\ChangeDetection\Strategies;

use App\Services\ChangeDetection\Contracts\ComparisonStrategyInterface;
use App\Services\ChangeDetection\Contracts\FingerprintGeneratorInterface;
use App\Services\ChangeDetection\ChangeOperation;
use App\Services\ChangeDetection\ChangeDetectionContext;
use App\Services\Mappers\NormalizedRecord;

class FingerprintComparisonStrategy implements ComparisonStrategyInterface
{
    public function __construct(
        private readonly FingerprintGeneratorInterface $fingerprintGenerator
    ) {}

    public function compare(NormalizedRecord $record, ChangeDetectionContext $context): ChangeOperation
    {
        if ($context->snapshot === null) {
            return new ChangeOperation(
                ChangeOperation::INSERT, 
                "Snapshot tujuan tidak ditemukan (Rekaman baru)."
            );
        }

        $excludedFields = $context->options['excludedFields'] ?? [];

        $sourceFingerprint = $this->fingerprintGenerator->generate($record, $excludedFields);
        $snapshotFingerprint = $this->fingerprintGenerator->generate($context->snapshot, $excludedFields);

        if ($sourceFingerprint->hash === $snapshotFingerprint->hash) {
            return new ChangeOperation(
                ChangeOperation::UNCHANGED, 
                "Hash identik (Sidik jari cocok sempurna)."
            );
        }

        return new ChangeOperation(
            ChangeOperation::UPDATE, 
            "Hash tidak identik (Terdeteksi perubahan nilai data)."
        );
    }
}
