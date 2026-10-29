<?php

namespace App\Http\Controllers;

use App\Models\SecurityAccessLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SecurityMonitorController extends Controller
{
    /**
     * Render the Security & Access Monitor page.
     */
    public function index(Request $request): View
    {
        $payload = $this->buildMonitorPayload($request);

        return view('security.monitor', $payload);
    }

    /**
     * Return live JSON snapshot for AJAX polling / filtering.
     */
    public function data(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            ...$this->buildMonitorPayload($request),
        ]);
    }

    /**
     * Build the full session & API access payload.
     */
    protected function buildMonitorPayload(Request $request): array
    {
        $currentSessionId = $request->hasSession() ? $request->session()->getId() : '';
        $sessionLifetimeMinutes = (int) config('session.lifetime', 120);
        $cutoffTimestamp = now()->subMinutes($sessionLifetimeMinutes)->timestamp;
        $onlineThresholdTimestamp = now()->subMinutes(5)->timestamp;

        $authenticatedSessions = [];
        $anonymousSessions = [];

        if (Schema::hasTable('sessions')) {
            $rawSessions = DB::table('sessions')
                ->where('last_activity', '>=', $cutoffTimestamp)
                ->orderByDesc('last_activity')
                ->get();

            $userIds = $rawSessions->pluck('user_id')->filter()->unique()->values()->all();
            $usersById = User::with('roles')->whereIn('id', $userIds)->get()->keyBy('id');

            foreach ($rawSessions as $sess) {
                $lastActiveCarbon = Carbon::createFromTimestamp($sess->last_activity);
                $isOnlineNow = $sess->last_activity >= $onlineThresholdTimestamp;
                $clientType = SecurityAccessLog::detectClientType($sess->user_agent);
                $platform = SecurityAccessLog::detectPlatform($sess->user_agent);

                if ($sess->user_id && isset($usersById[$sess->user_id])) {
                    $u = $usersById[$sess->user_id];
                    $authenticatedSessions[] = [
                        'session_id' => $sess->id,
                        'short_id' => substr($sess->id, 0, 10) . '…',
                        'is_current' => $sess->id === $currentSessionId,
                        'user_id' => $u->id,
                        'user_name' => $u->name,
                        'user_email' => $u->email,
                        'user_is_active' => (bool) ($u->is_active ?? true),
                        'roles' => $u->roles->pluck('name')->values()->all(),
                        'ip_address' => $sess->ip_address ?: 'Unknown',
                        'user_agent' => $sess->user_agent ?: '',
                        'client_type' => $clientType,
                        'platform' => $platform,
                        'is_online' => $isOnlineNow,
                        'last_activity_ts' => $sess->last_activity,
                        'last_activity' => $lastActiveCarbon->diffForHumans(),
                        'last_activity_at' => $lastActiveCarbon->format('d M Y H:i:s'),
                    ];
                } else {
                    $anonymousSessions[] = [
                        'session_id' => $sess->id,
                        'short_id' => substr($sess->id, 0, 10) . '…',
                        'is_current' => $sess->id === $currentSessionId,
                        'user_id' => $sess->user_id,
                        'ip_address' => $sess->ip_address ?: 'Unknown',
                        'user_agent' => $sess->user_agent ?: '',
                        'client_type' => $clientType,
                        'platform' => $platform,
                        'is_online' => $isOnlineNow,
                        'last_activity_ts' => $sess->last_activity,
                        'last_activity' => $lastActiveCarbon->diffForHumans(),
                        'last_activity_at' => $lastActiveCarbon->format('d M Y H:i:s'),
                    ];
                }
            }
        }

        $accessLogs = [];
        $stats = [
            'active_user_sessions' => count($authenticatedSessions),
            'unique_online_users' => count(array_unique(array_column($authenticatedSessions, 'user_id'))),
            'anonymous_sessions' => count($anonymousSessions),
            'api_requests_24h' => 0,
            'suspicious_24h' => 0,
        ];

        if (Schema::hasTable('security_access_logs')) {
            $since24h = now()->subHours(24);

            $stats['api_requests_24h'] = SecurityAccessLog::where('created_at', '>=', $since24h)
                ->where('event_type', 'api_access')
                ->count();

            $stats['suspicious_24h'] = SecurityAccessLog::where('created_at', '>=', $since24h)
                ->where(function ($q) {
                    $q->where('is_anonymous', true)
                        ->orWhereIn('event_type', ['api_unauthorized', 'login_failed'])
                        ->orWhereIn('status_code', [401, 403, 422]);
                })
                ->count();

            $filterType = $request->query('filter', 'all');
            $search = trim((string) $request->query('search', ''));

            $query = SecurityAccessLog::with('user')->orderByDesc('id');

            if ($filterType === 'api') {
                $query->whereIn('event_type', ['api_access', 'api_unauthorized']);
            } elseif ($filterType === 'suspicious') {
                $query->where(function ($q) {
                    $q->where('is_anonymous', true)
                        ->orWhereIn('event_type', ['api_unauthorized', 'login_failed'])
                        ->orWhereIn('status_code', [401, 403, 422]);
                });
            } elseif ($filterType === 'login') {
                $query->whereIn('event_type', ['login_success', 'login_failed', 'session_revoked']);
            }

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('user_identifier', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhere('endpoint', 'like', "%{$search}%")
                        ->orWhere('client_type', 'like', "%{$search}%")
                        ->orWhere('details', 'like', "%{$search}%");
                });
            }

            $accessLogs = $query->limit(150)->get()->map(function (SecurityAccessLog $log) {
                return [
                    'id' => $log->id,
                    'event_type' => $log->event_type,
                    'user_id' => $log->user_id,
                    'user_name' => $log->user?->name,
                    'user_identifier' => $log->user_identifier ?: ($log->user?->email ?? 'Anonymous'),
                    'endpoint' => $log->endpoint,
                    'method' => $log->method,
                    'status_code' => $log->status_code,
                    'is_anonymous' => (bool) $log->is_anonymous,
                    'ip_address' => $log->ip_address ?: 'Unknown',
                    'user_agent' => $log->user_agent ?: '',
                    'client_type' => $log->client_type ?: SecurityAccessLog::detectClientType($log->user_agent),
                    'details' => $log->details,
                    'time_ago' => $log->created_at ? $log->created_at->diffForHumans() : '-',
                    'created_at' => $log->created_at ? $log->created_at->format('d M Y H:i:s') : '-',
                ];
            })->all();
        }

        return [
            'stats' => $stats,
            'authenticatedSessions' => $authenticatedSessions,
            'anonymousSessions' => $anonymousSessions,
            'accessLogs' => $accessLogs,
            'sessionLifetime' => $sessionLifetimeMinutes,
        ];
    }

    /**
     * Force logout / terminate a specific session ID.
     */
    public function revokeSession(Request $request, string $sessionId): JsonResponse
    {
        $currentSessionId = $request->hasSession() ? $request->session()->getId() : '';
        if ($currentSessionId !== '' && $sessionId === $currentSessionId) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat memutus sesi Anda sendiri yang sedang aktif.',
            ], 422);
        }

        $sess = DB::table('sessions')->where('id', $sessionId)->first();
        if (!$sess) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi sudah berakhir atau tidak ditemukan.',
            ], 404);
        }

        $targetUser = $sess->user_id ? User::find($sess->user_id) : null;
        DB::table('sessions')->where('id', $sessionId)->delete();

        SecurityAccessLog::recordEvent(
            $request,
            'session_revoked',
            200,
            $targetUser === null,
            $targetUser?->id,
            $targetUser?->email ?? "Guest ({$sess->ip_address})",
            '/security-monitor',
            'Session forcefully terminated by ' . ($request->user()?->email ?? 'Admin') . " (Target IP: {$sess->ip_address})"
        );

        return response()->json([
            'success' => true,
            'message' => $targetUser
                ? "Sesi milik {$targetUser->name} ({$sess->ip_address}) berhasil dihentikan (Force Logout)."
                : "Sesi anonymous dari IP {$sess->ip_address} berhasil dihapus.",
        ]);
    }

    /**
     * Terminate all sessions belonging to a specific user.
     */
    public function revokeUserSessions(Request $request, int $userId): JsonResponse
    {
        $currentSessionId = $request->hasSession() ? $request->session()->getId() : '';
        $targetUser = User::find($userId);

        if (!$targetUser) {
            return response()->json(['success' => false, 'message' => 'User tidak ditemukan.'], 404);
        }

        $deleted = DB::table('sessions')
            ->where('user_id', $userId)
            ->where('id', '!=', $currentSessionId)
            ->delete();

        SecurityAccessLog::recordEvent(
            $request,
            'session_revoked',
            200,
            false,
            $targetUser->id,
            $targetUser->email,
            '/security-monitor',
            "Revoked {$deleted} session(s) for {$targetUser->email} by " . ($request->user()?->email ?? 'Admin')
        );

        return response()->json([
            'success' => true,
            'message' => "{$deleted} sesi aktif milik {$targetUser->name} berhasil dihentikan.",
        ]);
    }

    /**
     * Purge all anonymous (unauthenticated) and expired sessions.
     */
    public function purgeGuestSessions(Request $request): JsonResponse
    {
        $currentSessionId = $request->hasSession() ? $request->session()->getId() : '';
        $sessionLifetimeMinutes = (int) config('session.lifetime', 120);
        $cutoffTimestamp = now()->subMinutes($sessionLifetimeMinutes)->timestamp;

        $deleted = DB::table('sessions')
            ->where('id', '!=', $currentSessionId)
            ->where(function ($q) use ($cutoffTimestamp) {
                $q->whereNull('user_id')
                    ->orWhere('last_activity', '<', $cutoffTimestamp);
            })
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "{$deleted} sesi anonymous / kedaluwarsa berhasil dibersihkan.",
        ]);
    }

    /**
     * Clear security & API access logs.
     */
    public function clearAccessLogs(Request $request): JsonResponse
    {
        if (Schema::hasTable('security_access_logs')) {
            SecurityAccessLog::query()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Riwayat log akses berhasil dibersihkan.',
        ]);
    }
}