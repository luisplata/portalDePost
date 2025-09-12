<?php

namespace App\Console\Commands;

use App\Auto;
use App\Producto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        Log::info("Procesando productos: {$productos->count()} pendientes.");

        foreach ($productos as $producto) {
            $ok = true;

            foreach ($allAutos as $auto) {
                try {
                    $response = null;

                    switch ($auto->method) {
                        case 'POST':
                            $response = Http::timeout(5)->post($auto->webhook, $producto->toArray());
                            break;

                        case 'GET':
                            $response = Http::timeout(5)->get($auto->webhook, $producto->toArray());
                            break;
                    }

                    if (!$response || !$response->successful()) {
                        $ok = false;
                        $errorMsg = "Error HTTP enviando producto {$producto->id} a {$auto->webhook}: " . ($response ? $response->status() : 'sin respuesta');
                        $this->error($errorMsg);
                        Log::error($errorMsg);
                    } else {
                        $msg = "Producto {$producto->id} enviado a {$auto->webhook}.";
                        $this->info($msg);
                        Log::info($msg);
                    }

                } catch (\Exception $e) {
                    $ok = false;
                    $errorMsg = "No se pudo conectar a {$auto->webhook}: " . $e->getMessage();

                    // Detectar cURL error 7 (no se puede conectar)
                    if (strpos($e->getMessage(), 'cURL error 7') !== false) {
                        $errorMsg .= " — Probablemente el puerto está bloqueado por firewall o el servidor no responde.";
                    }

                    $this->error($errorMsg);
                    Log::error($errorMsg);
                }
            }

            if ($ok) {
                $producto->published = true;
                $producto->save();
                $msg = "Producto {$producto->id} marcado como publicado.";
                $this->info($msg);
                Log::info($msg);
            }
        }

        Log::info("Fin del procesamiento de productos.");
    }
}
