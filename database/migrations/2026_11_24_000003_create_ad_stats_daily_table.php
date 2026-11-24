<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdStatsDailyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ad_stats_daily', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->unsignedBigInteger('creative_id');
            $table->unsignedBigInteger('placement_id');
            // Fecha de calendario LOCAL (America/Bogota): la calcula PHP, nunca MySQL.
            $table->date('date');
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamps();

            $table->unique(['creative_id', 'placement_id', 'date'], 'ad_stats_daily_uniq');
            $table->index('date', 'ad_stats_daily_date_idx');
            $table->index('placement_id', 'ad_stats_daily_placement_idx');

            $table->foreign('creative_id')
                ->references('id')
                ->on('ad_creatives')
                ->onDelete('cascade');

            $table->foreign('placement_id')
                ->references('id')
                ->on('ad_placements')
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
        Schema::dropIfExists('ad_stats_daily');
        Schema::enableForeignKeyConstraints();
    }
}
