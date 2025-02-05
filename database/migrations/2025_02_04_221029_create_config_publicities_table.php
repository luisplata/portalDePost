<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateConfigPublicitiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('config_publicities', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('value');
            $table->timestamps();
        });

        DB::table('config_publicities')->insert([
            'key' => 'game',
            'value' => '{"url":"https://bellseboss.itch.io/?utm_source=onlysfree.com&utm_medium=referral", "image":"https://www.bellseboss.com/assets/Graficos/BellsebossLogo.png", "name":"Bellseboss Studio"}'
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('config_publicities');
    }
}
