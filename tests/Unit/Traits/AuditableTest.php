<?php

namespace Tests\Unit\Traits;

use Adithwidhiantara\Audit\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuditableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('test_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    protected function enableRedisDriver()
    {
        Config::set('audit.driver', 'redis');
        Config::set('audit.redis.connection', 'default');
        Config::set('audit.redis.queue_key', 'audit_pkg:buffer');
    }

    public function test_it_triggers_audit_on_created()
    {
        $this->enableRedisDriver();

        Redis::shouldReceive('connection')
            ->with('default')
            ->once()
            ->andReturnSelf();

        Redis::shouldReceive('rpush')
            ->once()
            ->withArgs(function ($key, $payload) {
                $data = json_decode($payload, true);

                return $key === 'audit_pkg:buffer'
                    && $data['event'] === 'created'
                    && $data['auditable_type'] === TestModel::class
                    && $data['new_values'] !== '[]'
                    && $data['old_values'] === '[]';
            });

        TestModel::create(['name' => 'test']);
    }

    public function test_it_triggers_audit_on_updated()
    {
        // Initial create with default driver (file/array) so it doesn't trigger redis logic
        Config::set('audit.driver', 'array');
        $model = TestModel::create(['name' => 'test']);

        // Now enable redis for update test
        $this->enableRedisDriver();

        Redis::shouldReceive('connection')
            ->with('default')
            ->once()
            ->andReturnSelf();

        Redis::shouldReceive('rpush')
            ->once()
            ->withArgs(function ($key, $payload) {
                $data = json_decode($payload, true);

                return $key === 'audit_pkg:buffer'
                    && $data['event'] === 'updated'
                    && $data['auditable_type'] === TestModel::class
                    && json_decode($data['old_values'], true)['name'] === 'test'
                    && json_decode($data['new_values'], true)['name'] === 'updated'
                    && !isset(json_decode($data['new_values'], true)['updated_at']);
            });

        $model->update(['name' => 'updated']);
    }

    public function test_it_triggers_audit_on_deleted()
    {
        Config::set('audit.driver', 'array');
        $model = TestModel::create(['name' => 'test']);

        $this->enableRedisDriver();

        Redis::shouldReceive('connection')
            ->with('default')
            ->once()
            ->andReturnSelf();

        Redis::shouldReceive('rpush')
            ->once()
            ->withArgs(function ($key, $payload) {
                $data = json_decode($payload, true);

                return $key === 'audit_pkg:buffer'
                    && $data['event'] === 'deleted'
                    && $data['auditable_type'] === TestModel::class
                    && $data['new_values'] === '[]'
                    && $data['old_values'] !== '[]';
            });

        $model->delete();
    }

    public function test_audits_relationship()
    {
        $model = new TestModel();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphMany::class, $model->audits());
    }
}

class TestModel extends Model
{
    use Auditable;

    protected $guarded = [];
}
