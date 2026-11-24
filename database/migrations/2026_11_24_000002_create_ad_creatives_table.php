<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdCreativesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ad_creatives', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->string('campaign_key', 64)->unique('ad_creatives_campaign_key_uniq');
            $table->string('name', 100);
            $table->unsignedBigInteger('placement_id');
            $table->string('status', 20)->default('draft');
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->smallInteger('priority')->default(0);
            $table->string('image_url', 2048);
            $table->string('alt_text', 150)->nullable();
            $table->string('headline', 120);
            $table->text('body')->nullable();
            $table->string('cta_label', 40)->nullable();
            $table->string('target_url', 2048);
            // productos.id es INT UNSIGNED (increments), no bigint (H-1).
            $table->unsignedInteger('producto_id')->nullable();
            $table->timestamps();

            $table->index(['placement_id', 'status', 'priority'], 'ad_creatives_sel_idx');
            $table->index('producto_id', 'ad_creatives_producto_idx');

            $table->foreign('placement_id')
                ->references('id')
                ->on('ad_placements')
                ->onDelete('cascade');

            $table->foreign('producto_id')
                ->references('id')
                ->on('productos')
                ->onDelete('set null');
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
        Schema::dropIfExists('ad_creatives');
        Schema::enableForeignKeyConstraints();
    }
}
