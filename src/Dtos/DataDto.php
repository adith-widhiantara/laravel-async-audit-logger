<?php

namespace Adithwidhiantara\Audit\Dtos;

final readonly class DataDto extends BaseDto
{
    public function __construct(
        public string $id,
        public string $event,
        public string $auditable_type,
        public string $auditable_id,
        public int|string|null $user_id,
        public string $url,
        public string $ip_address,
        public string|null $user_agent,
        public string|false $old_values,
        public string|false $new_values,
        public string $created_at,
    )
    {
        //
    }
}
