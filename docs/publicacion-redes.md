# Publicacion en redes (Telegram y Discord)

Esta capa publica cada producto nuevo en los canales configurados. Reemplaza al
n8n apagado y no usa colas, workers ni Redis: todo corre dentro del comando
`productos:procesar`, que se agenda cada 15 minutos.

## Canales y credenciales

Los **canales** se administran en `admin/canales`:

- Tipo: `telegram` o `discord` (inmutable al editar).
- Nombre unico, incluso entre canales eliminados.
- `habilitado`: un canal apagado no siembra ni envia.
- `vigente_desde`: su corte propio. Al pasar de apagado a encendido vuelve a
  "ahora" (0 a 1); las demás transiciones no lo mueven.
- Estado visible: `ok`, `invalido_credencial`, `invalido_config` o
  `tipo_desconocido`, con un detalle ya redactado.

Las credenciales se guardan cifradas con el cast `encrypted:array`, es decir,
dependen de `APP_KEY`. En el admin solo se muestra la mascara
(`123456789:****` o `https://discord.com/api/webhooks/1234/****`); el campo
arranca vacio y **vacio en edicion conserva el secreto**.

### Rotacion de APP_KEY

Si se rota `APP_KEY` sin recargar credenciales, esos canales pasan a
`invalido_credencial` con el detalle "Vuelva a cargar las credenciales."; la
corrida continua con los demás canales y las entregas ya enviadas se conservan.
Para recuperar un canal: editar, cargar token y chat_id (o el webhook) y usar
"Probar"; con una respuesta 2xx vuelve a `ok`.

## Interruptor y corte

La fuente de verdad es la tabla `publicacion_ajustes`, no el `.env`:

- `publicaciones_enabled`: interruptor maestro. Apagado no siembra, no envia,
  no toca `published` ni llama APIs. Es fail-closed.
- `publicaciones_desde`: corte global. Con el interruptor encendido es
  obligatorio y debe ser una fecha valida en UTC.
- El seeder solo crea las claves si no existen; nunca pisa al admin.

El **corte efectivo** de cada canal es
`max(publicaciones_desde, canal.vigente_desde)`. Si el canal nunca tuvo
entregas, se agrega el tope de la ventana de primer sembrado
(`ventana_sembrado_dias`, 7 dias). Un canal nuevo no arrastra el historico
viejo; un `vigente_desde` retroactivo puede generar un lote grande, acotado por
`lote_max` (10 por canal y por corrida).

El panel `admin/publicaciones/ajustes` muestra el interruptor, el corte y el
resumen de la última corrida (sembradas, enviadas, fallidas, inciertas y
duracion) o "Sin ejecuciones". Cambiar ajustes no dispara envíos por sí solo:
se aplica en la corrida siguiente.

## Entregas y estados

Cada par producto/canal tiene una sola entrega, en cualquier estado. Estados
de entrega:

- `pendiente`: candidata cuando `proximo_intento_at` es nulo o ya venció.
- `enviado`: 2xx confirmado. No se reenvía automáticamente.
- `fallido`: 4xx permanente o quinto fallo de servidor.
- `incierto`: timeout de lectura o respuesta vacía. No se reintenta
  automáticamente; se reintenta a mano.

El admin `admin/publicaciones` lista las entregas con filtros por estado,
canal, producto y fecha, paginadas de 20 en 20. El error guardado está
redactado y truncado a 500 caracteres. El reintento manual funciona desde
`fallido`, `incierto` y `enviado`; desde `incierto` o `enviado` la pantalla
advierte un posible duplicado. Una entrega `pendiente` se rechaza.

### Backoff y 429

- Fallo de servidor o de red: se incrementa el intento y el próximo se agenda
  con **backoff** de 1, 5, 15 y 60 minutos. Al quinto fallo la entrega queda
  `fallido`.
- **429**: no consume intento. La entrega sigue `pendiente` y el próximo
  intento es `now + clamp(espera, 1, 900)`, con 60 segundos por defecto.
  Telegram lo lee de `parameters.retry_after`; Discord de `retry_after` o del
  header `Retry-After`.
- **Timeout**: conexión rechazada, DNS o TLS son `fallo_servidor`; timeout de
  lectura o `errno` 52 son `incierto`. La clasificación usa `errno` y
  `pretransfer_time` porque Laravel colapsa ambos timeouts en la misma
  excepción. Ante cualquier duda, el default es `incierto`.

## Comandos

```bash
# Corrida real (agendada cada 15 minutos, sin solaparse)
php artisan productos:procesar

# Ensayo: cuenta lo que haria, no escribe ni llama APIs
php artisan productos:procesar --dry-run

# Tope total de envíos de la corrida (limit=0 sale con codigo 1)
php artisan productos:procesar --limit=5

# Prueba de un canal por id o nombre: texto fijo sin imagen, sin entregas
php artisan publicaciones:probar --canal=1
php artisan publicaciones:probar --canal="Telegram principal"
```

Todos los comandos leen los ajustes en caliente. La corrida usa un lock de
cache de 1800 segundos: si ya hay una en curso, la segunda sale con exito sin
efectos. El scheduler agrega `withoutOverlapping()` como segunda capa.

## Agregar una red nueva

1. Crear una clase que implemente `App\Services\Publicacion\CanalDriver`:
   `tipo()`, `validar(array $credenciales)` y
   `enviar(Producto $producto, array $credenciales, string $link)`.
   `validar` no hace red; `enviar` nunca lanza y devuelve `ResultadoEntrega`.
2. Agregar una linea en `config/publicaciones.php`, en el mapa `drivers`:
   `'nueva' => App\Services\Publicacion\NuevaDriver::class`.
3. Agregar su etiqueta en `etiquetas` si se quiere ver en el dashboard.

El formulario de alta ofrece exactamente las claves de ese mapa. Si se quita
un driver, el canal queda en `tipo_desconocido` y el barrido no se rompe.

## Limites conocidos

- Sin worker y sin colas: una corrida hace como maximo `lote_max` envíos por
  canal y pausa 800 ms entre envíos. Con dos canales y 10 envíos cada uno, el
  peor caso ronda los 415 segundos.
- `incierto` no se reintenta solo: un duplicado es peor que un reintento
  manual.
- Los timeouts no se simulan con `Http::fake`; la cobertura usa excepciones
  Guzzle construidas a mano. Un cambio de Guzzle degrada la clasificación a
  `incierto`, que es el default seguro.
- `published` significa "visible en todos los canales aplicables". Si no hay
  canales aplicables, no se toca. La columna "Redes" del listado de productos
  muestra el estado por red (`TG ok / DC fallo`).
- Los errores se guardan ya redactados y truncados a 500; no se guardan
  cuerpos de respuesta ni credenciales.
