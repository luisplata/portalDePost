<?php

namespace Database\Seeders;

use App\Auto;
use Illuminate\Database\Seeder;

class AutoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        $auto = new Auto();
        $auto->name = "Test";
        $auto->webhook = "http://143.198.98.210:5678/webhook/6bb6c78a-49b4-48fe-b0cd-bed957e64210";
        $auto->method = "POST";
        $auto->save();
    }
}
