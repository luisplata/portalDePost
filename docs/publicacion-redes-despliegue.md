# Publicacion en redes: despliegue en QA y PDN

Estado al escribir esto: implementado, verificado y mergeado en `develop`. Falta acceso a los
servidores para configurar canales reales. Este documento es el checklist para ese dia.

---

## 1. Datos necesarios antes de tocar nada

| Dato | Por que |
|---|---|
| Dominio de QA | Confirmar el vhost y que apunte a la app correcta |
| Ruta de la app en el servidor | Para el cron y el `git pull` |
| Base de datos de QA y de PDN | **Tienen que ser distintas**. Confirmarlo antes de migrar |
| Acceso SSH por entorno | Migraciones y comandos artisan |
| Entrada de cron disponible | Sin `schedule:run` no hay barrido automatico |
| Token de bot de Telegram + `chat_id` | Canal de Telegram |
| URL de webhook de Discord | Canal de Discord |
| Si QA y PDN comparten directorio de codigo | Define si hay uno o dos `.env` |

---

## 2. Aislamiento entre QA y PDN

Los dos entornos viven en la misma maquina. Eso no los hace iguales. Reglas:

1. **Base de datos separada.** Si comparten base, comparten interruptor, canales, entregas y
   `published`. Un producto de QA saldria por el canal real.
2. **`APP_KEY` propia por entorno.** Las credenciales de canales se guardan cifradas con esa
   clave. Si copias la base de un entorno a otro, los canales quedan `invalido_credencial` y
   hay que recargarlos.
3. **QA no publica en los canales reales.** Dos opciones validas:
   - Interruptor apagado en QA y probar solo con `--dry-run` y el boton "Probar".
   - Un canal de prueba exclusivo de QA (grupo privado de Telegram, canal privado de Discord).
4. **Cron por entorno.** Una entrada por vhost, con `cd` a su propio directorio. Dos entradas
   sobre el mismo directorio ejecutan el barrido dos veces, aunque el lock lo evita, pero
   ensucia el resumen de corrida.
5. **`publicaciones_desde` distinto por entorno.** En PDN el corte define desde que dia se
   publica. En QA conviene un corte reciente para no arrastrar pruebas.

---

## 3. Checklist QA

- [ ] Confirmar `DB_DATABASE` de QA y que no sea la de PDN.
- [ ] Confirmar `APP_KEY` propia y distinta de PDN.
- [ ] `git pull` en la ruta de QA.
- [ ] `php artisan migrate --force` (solo agrega 3 tablas, sin `ALTER`).
- [ ] Crear el canal de prueba en **Publicaciones > Canales**.
- [ ] Boton **Probar** del canal y confirmar que el mensaje llega al canal de prueba.
- [ ] Crear un producto de prueba con fecha de publicacion de hoy.
- [ ] `php artisan productos:procesar --dry-run` y revisar el corte efectivo por canal.
- [ ] `php artisan productos:procesar` y confirmar entrega `enviado`.
- [ ] Revisar **Publicaciones > Entregas** y la columna **Redes** en Productos.
- [ ] Confirmar que el resto de la web sigue igual: home, ficha, redirects, dashboard.
- [ ] Borrar el producto de prueba y las entregas que genero.

---

## 4. Checklist PDN

- [ ] Base y `APP_KEY` confirmadas como propias de PDN.
- [ ] Backup de la base antes de migrar.
- [ ] `git pull` y `php artisan migrate --force`.
- [ ] Definir `publicaciones_desde` en **Publicaciones > Ajustes**. Los productos creados
      antes de esa fecha **no se envian**, por diseño.
- [ ] Crear los canales reales y probar cada uno con el boton **Probar**.
- [ ] Dejar el interruptor **apagado** hasta tener el cron verificado.
- [ ] Configurar el cron:
      `* * * * * cd /ruta/pdn && php artisan schedule:run >> /dev/null 2>&1`
- [ ] Verificar que el scheduler corre una vez y deja `corrida_at` en Ajustes.
- [ ] Encender el interruptor.
- [ ] Crear un producto real y confirmar el mensaje en cada canal.
- [ ] Revisar entregas y `published`.

---

## 5. Limites conocidos

| Limite | Efecto |
|---|---|
| Sin worker ni colas | El envio es sincrono dentro del barrido. Maximo 10 entregas por canal y por corrida, con pausa de 800 ms |
| Timeout de lectura | La entrega queda `incierto` y **no** se reintenta sola. Se reintenta a mano desde Entregas, con aviso de posible duplicado |
| Zona horaria | `config/app.php` sigue en UTC. `publication_date` se compara en UTC: lo programado a las 13:43 de Bogota sale a las 08:43 locales |
| Sin historico | No hay backfill de publicaciones. Lo anterior al corte no se envia nunca |
| Rate limit | Telegram y Discord pueden responder 429. La entrega queda `pendiente` con espera, sin consumir intento |
| Rotacion de `APP_KEY` | Invalida las credenciales guardadas. El canal pasa a `invalido_credencial` y hay que recargarlo |

---

## 6. Comandos utiles

```bash
# Ensayo sin escribir nada
php artisan productos:procesar --dry-run

# Una corrida manual
php artisan productos:procesar

# Probar un canal especifico sin crear entregas
php artisan publicaciones:probar {id}

# Estado de migraciones
php artisan migrate:status

# Si el scheduler no corre, probarlo a mano
php artisan schedule:run
```

Rollback del cambio completo:

1. Interruptor apagado en **Ajustes**.
2. Revertir el codigo al commit anterior.
3. Las tablas nuevas pueden quedarse: no afectan a nada si estan vacias. Si se quieren quitar,
   `php artisan migrate:rollback --step=3`.
