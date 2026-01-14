<?php

namespace Tests\Unit\Models;

use Adithwidhiantara\Audit\Models\Audit;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Tests\TestCase;

class AuditTest extends TestCase
{
    public function test_audit_model_configuration()
    {
        $audit = new Audit;

        $this->assertFalse($audit->timestamps);
        $this->assertEmpty($audit->getGuarded());
        $this->assertEquals([
            'old_values' => 'array',
            'new_values' => 'array',
            'id' => 'int', // Laravel default
        ], $audit->getCasts());
    }

    public function test_audit_auditable_relationship()
    {
        $audit = new Audit;
        $relation = $audit->auditable();

        $this->assertInstanceOf(MorphTo::class, $relation);
        $this->assertEquals('auditable_type', $relation->getMorphType());
        $this->assertEquals('auditable_id', $relation->getForeignKeyName());
    }

    public function test_audit_user_relationship()
    {
        $audit = new Audit;
        $relation = $audit->user();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertEquals('user_id', $relation->getForeignKeyName());
    }
}
