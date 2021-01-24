<?php

use Illuminate\Database\Seeder;

class ProductosQA extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        for ($i = 0; $i < 100; $i++) {
            $producto = new App\Producto();
            $producto->nombre = "Producto #" . $i;
            $producto->imagen = "/images/productos/1611518819.png";
            $producto->categoria = 1;
            $producto->estado = 1;
            $producto->save();
        }
    }
}
