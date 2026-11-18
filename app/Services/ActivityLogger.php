<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ActivityLogger
{
    /**
     * Write an immutable audit entry. Callers must pass explicit, non-sensitive
     * snapshots; a second deny-list layer prevents credentials/tokens entering
     * the JSON columns if a future caller accidentally includes them.
     */
    public static function record(
        string $category,
        string $eventKey,
        string $summary,
        ?Model $subject = null,
        ?array $before = null,
        ?array $after = null,
        ?Model $actor = null,
        ?string $subjectLabel = null,
    ): ?ActivityLog {
        $actor ??= Auth::user();

        if (! $actor) {
            return null;
        }

        $request = app()->bound('request') ? request() : null;
        $safeBefore = self::sanitize($before ?? []);
        $safeAfter = self::sanitize($after ?? []);

        return ActivityLog::create([
            'actor_user_id' => $actor->getKey(),
            'actor_name' => Str::limit((string) $actor->getAttribute('name'), 255, ''),
            'actor_email' => Str::limit((string) $actor->getAttribute('email'), 255, '') ?: null,
            'actor_role' => Str::limit((string) $actor->getAttribute('role'), 40, ''),
            'category' => $category,
            'event_key' => $eventKey,
            'summary' => Str::limit($summary, 255, ''),
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'subject_label' => $subjectLabel ? Str::limit($subjectLabel, 255, '') : null,
            'before_data' => $safeBefore === [] ? null : $safeBefore,
            'after_data' => $safeAfter === [] ? null : $safeAfter,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent() ? Str::limit($request->userAgent(), 500, '') : null,
            'created_at' => now(),
        ]);
    }

    /**
     * Record a security event when no authenticated actor can safely be
     * attributed (for example, a rejected or throttled login attempt).
     * Raw email addresses and credentials must never be passed by callers.
     */
    public static function recordAnonymous(
        string $category,
        string $eventKey,
        string $summary,
        array $after = [],
        ?string $subjectLabel = null,
    ): ActivityLog {
        $request = app()->bound('request') ? request() : null;
        $safeAfter = self::sanitize($after);

        return ActivityLog::create([
            'actor_user_id' => null,
            'actor_name' => 'Pengguna tidak terautentikasi',
            'actor_email' => null,
            'actor_role' => null,
            'category' => $category,
            'event_key' => $eventKey,
            'summary' => Str::limit($summary, 255, ''),
            'subject_type' => null,
            'subject_id' => null,
            'subject_label' => $subjectLabel ? Str::limit($subjectLabel, 255, '') : null,
            'before_data' => null,
            'after_data' => $safeAfter === [] ? null : $safeAfter,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent() ? Str::limit($request->userAgent(), 500, '') : null,
            'created_at' => now(),
        ]);
    }

    private static function sanitize(array $values): array
    {
        $safe = [];

        foreach ($values as $key => $value) {
            $keyString = (string) $key;
            if (preg_match('/password|token|secret|activation.?link|activation.?code|reset.?link|recovery.?code|remember.?token|private.?key|api.?key|authorization|cookie/i', $keyString)) {
                continue;
            }

            if (is_array($value)) {
                $safe[$keyString] = self::sanitize($value);
                continue;
            }

            if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $safe[$keyString] = $value;
                continue;
            }

            if (is_string($value)) {
                $safe[$keyString] = Str::limit($value, 1000, '');
            }
        }

        return $safe;
    }
}
