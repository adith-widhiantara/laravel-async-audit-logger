<?php

namespace Adithwidhiantara\Audit\Traits;

use Adithwidhiantara\Audit\Dtos\DataDto;
use Adithwidhiantara\Audit\Models\Audit;
use Adithwidhiantara\Audit\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

trait Auditable
{
    public static function bootAuditable(): void
    {
        // 1. CREATE
        static::created(function (Model $model) {
            self::audit('created', $model, [], $model->toArray());
        });

        // 2. UPDATE
        static::updated(function (Model $model) {
            $new = $model->getChanges();

            $old = [];
            foreach ($new as $key => $val) {
                $old[$key] = $model->getOriginal($key);
            }

            unset($new['updated_at'], $old['updated_at']);

            if (! empty($new)) {
                self::audit('updated', $model, $old, $new);
            }
        });

        // 3. DELETE
        static::deleted(function (Model $model) {
            self::audit('deleted', $model, $model->toArray(), []);
        });
    }

    protected static function audit(string $event, Model $model, $old = [], $new = []): void
    {
        $data = (new DataDto(
            id: (string) Str::uuid(),
            event: $event,
            auditable_type: get_class($model),
            auditable_id: $model->getKey(),
            user_id: Auth::id() ?? null,
            url: app()->runningInConsole() ? 'console' : request()->fullUrl(),
            ip_address: app()->runningInConsole() ? '127.0.0.1' : request()->ip(),
            user_agent: app()->runningInConsole() ? 'CLI' : request()->userAgent(),
            old_values: json_encode($old),
            new_values: json_encode($new),
            created_at: now()->toDateTimeString(),
        ))->toArray();

        AuditLogger::push($data);
    }

    public function audits(): MorphToMany
    {
        return $this
            ->morphMany(Audit::class, 'auditable')
            ->orderBy('created_at', 'desc');
    }
}
