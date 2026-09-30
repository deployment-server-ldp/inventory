<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ActivityLogger
{
    private const HIDDEN = ['password', 'remember_token', 'idempotency_key', 'updated_at', 'created_at'];

    public static function log(
        string $action,
        string $description,
        ?Model $subject = null,
        array $old = [],
        array $new = [],
        ?string $module = null,
        ?string $reference = null,
        $user = null,
    ): ActivityLog {
        $user ??= Auth::user();
        $request = app()->runningInConsole() ? null : request();

        return ActivityLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'action' => $action,
            'module' => $module,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'reference' => $reference ?? ($subject->reference_no ?? null),
            'description' => Str::limit($description, 495),
            'old_values' => $old ? self::clean($old) : null,
            'new_values' => $new ? self::clean($new) : null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250) : null,
        ]);
    }

    /** Log a model update with only the changed attributes. Call after save(). */
    public static function logChanges(string $action, string $description, Model $model, array $original, ?string $module = null): void
    {
        $changes = $model->getChanges();
        $old = array_intersect_key($original, $changes);
        if (array_diff(array_keys($changes), self::HIDDEN) === []) {
            return;
        }
        self::log($action, $description, $model, $old, $changes, $module);
    }

    private static function clean(array $values): array
    {
        return array_diff_key($values, array_flip(self::HIDDEN));
    }
}
