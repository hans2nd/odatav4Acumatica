<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiLearningCandidate extends Model
{
    protected $fillable = ['connection_id', 'domain', 'term', 'term_type', 'candidate_mapping', 'confidence', 'source_conversation_id', 'source_message_id', 'occurrence_count', 'status', 'reason', 'first_seen_at', 'last_seen_at'];
    protected $casts = ['candidate_mapping' => 'array', 'confidence' => 'float', 'first_seen_at' => 'datetime', 'last_seen_at' => 'datetime'];
}
