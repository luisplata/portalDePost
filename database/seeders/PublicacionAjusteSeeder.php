<?php

namespace Database\Seeders;

use App\PublicacionAjuste;
use Illuminate\Database\Seeder;

/**
 * Siembra inicial de los ajustes de publicaciones (REQ-AJUS-04).
 *
 * `firstOrCreate` por clave: nunca pisa un valor editado desde el admin.
 */
class PublicacionAjusteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        PublicacionAjuste::firstOrCreate(
            ['clave' => PublicacionAjuste::CLAVE_ACTIVO],
            ['valor' => config('publicaciones.sembrado_enabled') ? '1' : '0']
        );

        PublicacionAjuste::firstOrCreate(
            ['clave' => PublicacionAjuste::CLAVE_DESDE],
            ['valor' => config('publicaciones.sembrado_desde')]
        );
    }
}
