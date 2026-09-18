<?php

namespace App\Services\AI;

use App\Models\AiMetadataRelationship;
use App\Models\Connection;

class RelationshipValidationService
{
    /** @return array<int, array<string, mixed>> */
    public function validate(Connection $connection, array $tables): array
    {
        $tables = array_values(array_unique($tables));
        if (count($tables) < 2) return [];
        $relationships = [];
        for ($i = 0; $i < count($tables); $i++) for ($j = $i + 1; $j < count($tables); $j++) {
            $relationship = AiMetadataRelationship::where('connection_id', $connection->id)->where('allowed_for_chatedp', true)
                ->where(fn ($q) => $q->where(fn ($q) => $q->where('source_table', $tables[$i])->where('target_table', $tables[$j]))
                    ->orWhere(fn ($q) => $q->where('source_table', $tables[$j])->where('target_table', $tables[$i])))
                ->first();
            if (!$relationship) throw new \App\Exceptions\AI\SemanticAmbiguityException('Hubungan antar tabel yang diminta belum diizinkan dalam AI Metadata.');
            $relationships[] = ['id' => $relationship->id, 'source_table' => $relationship->source_table, 'target_table' => $relationship->target_table, 'source_key' => $relationship->source_key, 'target_key' => $relationship->target_key];
        }
        return $relationships;
    }
}
