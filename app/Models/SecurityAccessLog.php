<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SecurityAccessLog extends Model
{
    public $timestamps = false;

    protected $table = 'security_access_logs';

    protected $fillable = [
        'event_type',
        'user_id',
        'user_identifier',
        'endpoint',
        'method',
        'status_code',
        'is_anonymous',
        'ip_address',
        'user_agent',
        'client_type',
        'details',
        'created_at',
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'status_code'  => 'integer',
        'created_at'   => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Detect human-readable client/application type from User-Agent string.
     */
    public static function detectClientType(?string $userAgent): string
    {
        if (!$userAgent) {
            return 'Unknown / Empty UA';
        }

        $ua = strtolower($userAgent);

        if (str_contains($ua, 'powerbi') || str_contains($ua, 'mashup') || str_contains($ua, 'microsoft.data.mashup')) {
            return 'Power BI';
        }
        if (str_contains($ua, 'excel') || str_contains($ua, 'microsoft office')) {
            return 'Microsoft Excel';
        }
        if (str_contains($ua, 'postman')) {
            return 'Postman';
        }
        if (str_contains($ua, 'insomnia')) {
            return 'Insomnia';
        }
        if (str_contains($ua, 'curl')) {
            return 'cURL CLI';
        }
        if (str_contains($ua, 'python') || str_contains($ua, 'requests') || str_contains($ua, 'urllib') || str_contains($ua, 'aiohttp')) {
            return 'Python Script';
        }
        if (str_contains($ua, 'go-http-client') || str_contains($ua, 'axios') || str_contains($ua, 'node-fetch') || str_contains($ua, 'guzzle')) {
            return 'API Script / Bot';
        }
        if (str_contains($ua, 'edg/')) {
            return 'Microsoft Edge';
        }
        if (str_contains($ua, 'opr/') || str_contains($ua, 'opera')) {
            return 'Opera';
        }
        if (str_contains($ua, 'chrome/') && !str_contains($ua, 'edg/')) {
            return 'Google Chrome';
        }
        if (str_contains($ua, 'firefox/')) {
            return 'Mozilla Firefox';
        }
        if (str_contains($ua, 'safari/') && !str_contains($ua, 'chrome/')) {
            return 'Apple Safari';
        }

        return 'Web / HTTP Client';
    }

    /**
     * Detect OS platform from User-Agent string.
     */
    public static function detectPlatform(?string $userAgent): string
    {
        if (!$userAgent) {
            return 'Unknown OS';
        }

        $ua = strtolower($userAgent);
        if (str_contains($ua, 'windows')) return 'Windows';
        if (str_contains($ua, 'macintosh') || str_contains($ua, 'mac os')) return 'macOS';
        if (str_contains($ua, 'android')) return 'Android';
        if (str_contains($ua, 'iphone') || str_contains($ua, 'ipad')) return 'iOS';
        if (str_contains($ua, 'linux')) return 'Linux';

        return 'Other';
    }

    /**
     * Safely record a security or API access event without breaking the request flow if table is not migrated yet.
     */
    public static function recordEvent(
        Request $request,
        string $eventType,
        int $statusCode,
        bool $isAnonymous = false,
        ?int $userId = null,
        ?string $userIdentifier = null,
        ?string $endpoint = null,
        ?string $details = null
    ): void {
        try {
            if (!Schema::hasTable('security_access_logs')) {
                return;
            }

            $ua = $request->userAgent();

            self::create([
                'event_type'      => $eventType,
                'user_id'         => $userId,
                'user_identifier' => $userIdentifier,
                'endpoint'        => $endpoint ?? '/' . ltrim($request->path(), '/'),
                'method'          => $request->method(),
                'status_code'     => $statusCode,
                'is_anonymous'    => $isAnonymous,
                'ip_address'      => $request->ip(),
                'user_agent'      => $ua ? mb_substr($ua, 0, 1000) : null,
                'client_type'     => self::detectClientType($ua),
                'details'         => $details ? mb_substr($details, 0, 500) : null,
                'created_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            // Never interrupt request execution on audit log error
        }
    }
}

