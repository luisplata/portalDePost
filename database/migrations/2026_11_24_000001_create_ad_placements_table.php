<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateAdPlacementsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ad_placements', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->string('key', 64)->unique('ad_placements_key_uniq');
            $table->string('name', 100);
            $table->unsignedTinyInteger('max_items')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $this->seedPlacements();
    }

    /**
     * Siembra los 4 placements contractuales con insert-if-missing.
     *
     * No es updateOrInsert a proposito: una re-ejecucion no debe pisar el
     * name/max_items que el dueño edito (REQ-PLC-01, Scenario 2).
     *
     * @return void
     */
    public function seedPlacements()
    {
        foreach ((array) config('ads.placements', []) as $key => $placement) {
            if (DB::table('ad_placements')->where('key', $key)->exists()) {
                continue;
            }

            DB::table('ad_placements')->insert([
                'key' => $key,
                'name' => $placement['name'],
                'max_items' => $placement['max_items'],
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('ad_placements');
        Schema::enableForeignKeyConstraints();
    }
}
