<?php

namespace App\Console\Commands;

use App\Auto;
use App\Producto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ProcesarProductos extends Command
{
    protected $signature = 'productos:procesar';
    protected $description = 'Procesa los productos pendientes y los envía al webhook de n8n';

    public function handle()
    {
        $productos = Producto::where('publication_date', '<=', now())
            ->where('published', false)
            ->get();

        $allAutos = Auto::all();

        $this->info("Procesando productos");

        foreach ($productos as $producto) {
            $ok = true;

            foreach ($allAutos as $auto) {
                $response = null;

                switch ($auto->method) {
                    case 'POST':
                        $response = Http::post($auto->webhook, [
                            'id' => $producto->id,
                            'nombre' => $producto->nombre,
                            'precio' => $producto->precio,
                            'publication_date' => $producto->publication_date,
                        ]);
                        break;

                    case 'GET':
                        $response = Http::get($auto->webhook, [
                            'id' => $producto->id,
                            'nombre' => $producto->nombre,
                            'precio' => $producto->precio,
                            'publication_date' => $producto->publication_date,
                        ]);
                        break;
                }

                if (!$response || !$response->successful()) {
                    $ok = false;
                    $this->error("Error enviando producto {$producto->id} a {$auto->webhook}: " . ($response ? $response->body() : 'sin respuesta'));
                } else {
                    $this->info("Producto {$producto->id} enviado a {$auto->webhook}.");
                }
            }

            // Si se envió a todos los autos, recién ahí se marca publicado
            if ($ok) {
                $producto->published = true;
                $producto->save();
                $this->info("Producto {$producto->id} marcado como publicado.");
            }
        }

    }
}