<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePublicacionCanalesTable extends Migration
{
    /**
     * Canales externos administrables (Telegram, Discord).
     *
     * `credenciales` es TEXT porque el cast `encrypted:array` guarda base64
     * con IV y crece; no se filtra ni se ordena por esa columna. El unique de
     * nombre incluye las filas con `deleted_at` a proposito (REQ-CAN-12).
     *
     * @return void
     */
    public function up()
    {
        Schema::create('publicacion_canales', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->string('tipo', 30);
            $table->string('nombre', 60);
            $table->boolean('habilitado')->default(false);
            $table->dateTime('vigente_desde');
            $table->text('credenciales');
            $table->json('config')->nullable();
            $table->string('estado', 30)->default('ok');
            $table->string('estado_detalle', 255)->nullable();
            $table->dateTime('estado_verificado_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique('nombre', 'publicacion_canales_nombre_uniq');
            $table->index(
                ['habilitado', 'estado', 'deleted_at'],
                'publicacion_canales_operativo_idx'
            );
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
        Schema::dropIfExists('publicacion_canales');
        Schema::enableForeignKeyConstraints();
    }
}
