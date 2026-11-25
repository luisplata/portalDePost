<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePublicacionEnviosTable extends Migration
{
    /**
     * Entrega de un producto a un canal (idempotencia por par).
     *
     * `producto_id` copia el tipo de `productos.id` (INT UNSIGNED) para que la
     * FK sea compatible (REQ-ENT-03, REG-07). Borrar un producto arrastra sus
     * entregas; borrar un canal con historial falla ruidosamente (RESTRICT).
     *
     * @return void
     */
    public function up()
    {
        Schema::create('publicacion_envios', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->unsignedInteger('producto_id');
            $table->unsignedBigInteger('publicacion_canal_id');
            $table->string('canal_tipo', 30);
            $table->string('estado', 20)->default('pendiente');
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->dateTime('proximo_intento_at')->nullable();
            $table->dateTime('enviado_at')->nullable();
            $table->string('referencia_externa', 64)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(
                ['producto_id', 'publicacion_canal_id'],
                'publicacion_envios_par_uniq'
            );
            $table->index(
                ['publicacion_canal_id', 'estado', 'proximo_intento_at'],
                'publicacion_envios_canal_estado_idx'
            );
            $table->index(
                ['estado', 'proximo_intento_at'],
                'publicacion_envios_estado_proximo_idx'
            );
            $table->index(
                ['producto_id', 'estado'],
                'publicacion_envios_producto_estado_idx'
            );
            $table->index('created_at', 'publicacion_envios_created_idx');

            $table->foreign('producto_id', 'publicacion_envios_producto_fk')
                ->references('id')
                ->on('productos')
                ->onDelete('cascade');

            $table->foreign('publicacion_canal_id', 'publicacion_envios_canal_fk')
                ->references('id')
                ->on('publicacion_canales')
                ->onDelete('restrict');
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
        Schema::dropIfExists('publicacion_envios');
        Schema::enableForeignKeyConstraints();
    }
}
