<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiMetadataRelationship extends Model
{
    protected $fillable = ['connection_id', 'source_table', 'target_table', 'source_key', 'target_key', 'relationship_type', 'cardinality', 'allowed_for_chatedp', 'business_context', 'description'];
    protected $casts = ['allowed_for_chatedp' => 'boolean', 'business_context' => 'array'];
}
