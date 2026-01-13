<?php

namespace Adithwidhiantara\Audit\Traits;

use Adithwidhiantara\Audit\Dtos\DataDto;
use Adithwidhiantara\Audit\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
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

            if (!empty($new)) {
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
            url: Request::fullUrl(),
            ip_address: Request::ip(),
            user_agent: Request::userAgent(),
            old_values: json_encode($old),
            new_values: json_encode($new),
            created_at: now()->toDateTimeString(),
        ))->toArray();

        AuditLogger::push($data);
    }
}
