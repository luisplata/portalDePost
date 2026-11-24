<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Bootstrapea la app y valida la base configurada ANTES de `parent::setUp()`.
     *
     * `parent::setUp()` ejecuta `setUpTraits()`, que es donde `RefreshDatabase`
     * corre `migrate:fresh`. Por eso el guard tiene que correr antes: si la
     * configuracion apunta a la base de desarrollo, abortamos sin tocar una
     * sola tabla. Es la ultima defensa cuando existe `bootstrap/cache/config.php`
     * y los `<env>` de phpunit.xml quedan ignorados.
     */
    protected function setUp(): void
    {
        $this->refreshApplication();

        static::guardTestDatabaseName(
            (string) config('database.connections.mysql.database')
        );

        parent::setUp();
    }

    /**
     * Aborta si el nombre de la base no termina en `_test`.
     *
     * @param  string  $database
     * @return void
     *
     * @throws \RuntimeException
     */
    public static function guardTestDatabaseName(string $database): void
    {
        if (substr($database, -5) !== '_test') {
            throw new \RuntimeException(
                "Refusing to run tests against '{$database}': name must end with '_test'."
            );
        }
    }
}
