<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePostVisitIdentitiesTable extends Migration
{
    /**
     * Identidad anonima que visito un producto en un dia local. Materializa el
     * dedupe diario exacto y la interseccion con clicks (conversion principal).
     *
     * @return void
     */
    public function up()
    {
        Schema::create('post_visit_identities', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('producto_id')->unsigned();
            $table->date('date');
            $table->string('identity', 80);
            $table->timestamp('created_at')->nullable();

            $table->unique(['producto_id', 'date', 'identity'], 'post_visit_identities_uniq');
            $table->index(['date', 'producto_id', 'identity'], 'post_visit_identities_date_identity_idx');
            $table->index('created_at', 'post_visit_identities_created_idx');

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
        Schema::dropIfExists('post_visit_identities');
        Schema::enableForeignKeyConstraints();
    }
}
