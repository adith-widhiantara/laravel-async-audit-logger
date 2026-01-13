<?php

namespace Adithwidhiantara\Audit\Dtos;

use ReflectionClass;
use ReflectionException;

abstract readonly class BaseDto
{
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
