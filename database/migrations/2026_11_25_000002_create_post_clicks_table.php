<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePostClicksTable extends Migration
{
    /**
     * Evento de click saliente. `date` es la fecha local del evento y
     * `created_at` queda en UTC para retencion y auditoria.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('post_clicks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('producto_id')->unsigned();
            $table->date('date');
            $table->string('identity', 80);
            $table->string('source', 8);
            $table->string('referer', 255)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->char('ip_hash', 64);
            $table->timestamp('created_at')->nullable();

            $table->index(['producto_id', 'date'], 'post_clicks_product_date_idx');
            $table->index(['date', 'producto_id', 'identity'], 'post_clicks_date_identity_idx');
            $table->index('created_at', 'post_clicks_created_idx');

            $table->foreign('producto_id')
                ->references('id')
                ->on('productos')
                ->onDelete('cascade');
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
        Schema::dropIfExists('post_clicks');
        Schema::enableForeignKeyConstraints();
    }
}
