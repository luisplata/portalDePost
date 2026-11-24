<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePostStatsDailyTable extends Migration
{
    /**
     * Hecho diario por producto. `date` es fecha local (America/Bogota) y se
     * calcula en PHP. `unique_visits` nulo marca un dia sin captura de
     * identidad (backfill o solo click).
     *
     * @return void
     */
    public function up()
    {
        Schema::create('post_stats_daily', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('producto_id')->unsigned();
            $table->date('date');
            $table->integer('visits')->unsigned()->default(0);
            $table->integer('unique_visits')->unsigned()->nullable();
            $table->integer('clicks')->unsigned()->default(0);
            $table->timestamps();

            $table->unique(['producto_id', 'date'], 'post_stats_daily_uniq');
            $table->index('date', 'post_stats_daily_date_idx');

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
        Schema::dropIfExists('post_stats_daily');
        Schema::enableForeignKeyConstraints();
    }
}
