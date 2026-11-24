<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdClicksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ad_clicks', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->unsignedBigInteger('creative_id');
            $table->unsignedBigInteger('placement_id');
            $table->string('anonymous_user_uuid', 64)->nullable();
            $table->string('referer', 255)->nullable();
            // sha256(ip . '|' . app.key) en hex: la IP cruda nunca se persiste.
            $table->char('ip_hash', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            // Solo created_at, en UTC (D-11): no hay updated_at.
            $table->timestamp('created_at')->nullable();

            $table->index(['creative_id', 'created_at'], 'ad_clicks_creative_created_idx');
            $table->index('anonymous_user_uuid', 'ad_clicks_uuid_idx');
            $table->index('created_at', 'ad_clicks_created_idx');
            $table->index('placement_id', 'ad_clicks_placement_idx');

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
        Schema::dropIfExists('ad_clicks');
        Schema::enableForeignKeyConstraints();
    }
}
