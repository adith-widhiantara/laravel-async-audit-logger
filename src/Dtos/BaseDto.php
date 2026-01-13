<?php

namespace Adithwidhiantara\Audit\Dtos;

abstract readonly class BaseDto
{
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
