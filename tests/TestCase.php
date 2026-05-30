<?php

namespace Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * @param  iterable<Model>|Model|class-string<Model>|string  $table
     * @param  array<string, mixed>  $data
     */
    public function assertDatabaseHas($table, array $data = [], $connection = null): static
    {
        parent::assertDatabaseHas($table, $data, $connection);

        return $this;
    }

    /**
     * @param  iterable<Model>|Model|class-string<Model>|string  $table
     * @param  array<string, mixed>  $data
     */
    public function assertDatabaseMissing($table, array $data = [], $connection = null): static
    {
        parent::assertDatabaseMissing($table, $data, $connection);

        return $this;
    }

    /**
     * @param  iterable<Model>|Model|class-string<Model>|string  $table
     * @param  array<string, mixed>  $data
     */
    public function assertSoftDeleted($table, array $data = [], $connection = null, $deletedAtColumn = 'deleted_at'): static
    {
        parent::assertSoftDeleted($table, $data, $connection, $deletedAtColumn);

        return $this;
    }

    public function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    public function withoutVite(): static
    {
        parent::withoutVite();

        return $this;
    }
}
