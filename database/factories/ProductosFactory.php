<?php

use Faker\Generator as Faker;

$factory->define(App\Producto::class, function (Faker $faker) {
    return [
        'nombre' => $faker->name,
        'imagen' => "/images/productos/1611518819.png",
        'categorias_id' => 1,
        'estado' => 1,
    ];
});
