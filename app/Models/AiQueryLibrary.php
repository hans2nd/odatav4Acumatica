<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiQueryLibrary extends Model
{
    use HasFactory;

    protected $fillable = [
        'connection_id',
        'signature_hash',
        'canonical_plan_json',
        'query_repository_id',
        'repository_version',
        'hit_count',
        'last_hit_at',
        'is_active',
    ];

    protected $casts = [
        'canonical_plan_json' => 'array',
        'repository_version'  => 'integer',
        'hit_count'           => 'integer',
        'last_hit_at'         => 'datetime',
        'is_active'           => 'boolean',
    ];

    /**
     * Database connection associated with this library entry.
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class, 'connection_id');
    }

    /**
     * Authoritative saved query definition in QueryRepository.
     */
    public function queryRepository(): BelongsTo
    {
        return $this->belongsTo(QueryRepository::class, 'query_repository_id');
    }

    /**
     * Determine whether the library entry is currently active, linked,
     * and matching the authoritative repository version.
     */
    public function isValid(): bool
    {
        if (!$this->is_active || empty($this->query_repository_id)) {
            return false;
        }

        $repo = $this->queryRepository;
        if (!$repo || !$repo->is_active) {
            return false;
        }

        return (int)$repo->version === (int)$this->repository_version;
    }

    /**
     * Increment the hit counter and update the last hit timestamp.
     */
    public function recordHit(): void
    {
        $this->timestamps = false;
        $this->increment('hit_count');
        $this->update(['last_hit_at' => now()]);
        $this->timestamps = true;
    }
}
