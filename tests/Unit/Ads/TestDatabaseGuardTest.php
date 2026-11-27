<?php

namespace Tests\Unit\Ads;

use Tests\TestCase;

/**
 * T-00-02: el guard del harness DEBE abortar cuando la base apuntada no
 * termina en `_test`.
 *
 * Los casos de nombre son puros (solo evalúan el predicado), no abren
 * conexión ni levantan migraciones: corren sin tocar la base de desarrollo.
 */
class TestDatabaseGuardTest extends TestCase
{
    public function test_guard_rejects_the_development_database_name()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Refusing to run tests against 'laravel': name must end with '_test'.");

        TestCase::guardTestDatabaseName('laravel');
    }

    public function test_guard_rejects_any_name_without_the_test_suffix()
    {
        foreach (['laravel_test_2', 'laravel_test_x', 'testing', 'laravel_', ''] as $name) {
            try {
                TestCase::guardTestDatabaseName($name);
                $this->fail("Guard accepted unsafe database name '{$name}'.");
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('must end with', $e->getMessage());
            }
        }
    }

    public function test_guard_accepts_test_database_names()
    {
        foreach (['laravel_test', 'portal_test', 'x_test'] as $name) {
            TestCase::guardTestDatabaseName($name);
            $this->addToAssertionCount(1);
        }
    }

    public function test_configured_database_is_isolated_from_the_development_one()
    {
        $database = (string) config('database.connections.mysql.database');

        $this->assertSame('laravel_test', $database);
    }
}
