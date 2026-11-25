<?php

namespace Tests\Feature\Publicacion;

use App\Console\Kernel;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * Cadencia y exclusion mutua del scheduler (T-S5-09, REQ-NFR-01, NFR-08).
 */
class ScheduleTest extends TestCase
{
    public function test_productos_procesar_runs_every_fifteen_minutes_without_overlapping()
    {
        $evento = $this->evento('productos:procesar');

        $this->assertNotNull($evento);
        $this->assertSame('*/15 * * * *', $evento->expression);
        $this->assertTrue($evento->withoutOverlapping);
    }

    public function test_the_existing_jobs_stay_scheduled()
    {
        $this->assertNotNull($this->evento('ads:purge-clicks'));
        $this->assertNotNull($this->evento('analytics:purge-events'));
        $this->assertNotNull($this->evento('analytics:check-drift'));
    }

    private function evento(string $comando)
    {
        $kernel = $this->app->make(Kernel::class);
        $metodo = new \ReflectionMethod($kernel, 'schedule');
        $metodo->setAccessible(true);
        $schedule = new Schedule();
        $metodo->invoke($kernel, $schedule);

        foreach ($schedule->events() as $evento) {
            if (strpos((string) $evento->command, $comando) !== false) {
                return $evento;
            }
        }

        return null;
    }
}
