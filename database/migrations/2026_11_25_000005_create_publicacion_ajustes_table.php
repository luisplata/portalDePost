<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePublicacionAjustesTable extends Migration
{
    /**
     * Clave/valor plano con las claves cerradas de publicaciones: interruptor,
     * corte global y resumen de la ultima corrida (REQ-AJUS-01..06, OBS-07).
     *
     * @return void
     */
    public function up()
    {
        Schema::create('publicacion_ajustes', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->string('clave', 60);
            $table->text('valor')->nullable();
            $table->timestamps();

            $table->unique('clave', 'publicacion_ajustes_clave_uniq');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('publicacion_ajustes');
        Schema::enableForeignKeyConstraints();
    }
}
