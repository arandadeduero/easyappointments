# Revisión de la personalización de Aranda de Duero

Revisión realizada el 20-10-2026 sobre la rama `aranda` comparada con Easy!Appointments 1.6.0. Qué cambia y por qué
se explica en [aranda-cambios.md](aranda-cambios.md). Las referencias `fichero:línea` corresponden al estado de la
rama en esa fecha.

Los puntos marcados "no ejecutado" se dedujeron leyendo el código; conviene reproducirlos antes de darlos por ciertos.

## Seguridad y datos

1. **Contraseña SMTP en el repositorio del contratista.** `application/config/email.php` contenía tres contraseñas
   reales sucesivas de `citaprevia@arandadeduero.es`. Se eliminaron del historial de `aranda`, pero siguen en el
   repositorio del contratista. **Hay que cambiar la contraseña** y guardarla solo en `.env`.
2. **Autorización del kiosko por IP.** Si solo hay un dispositivo emparejado, cualquier equipo que salga por la misma IP
   pública obtiene acceso al kiosko sin cookie ni código (`Kiosk_devices_model::authorize_by_ip`,
   `application/models/Kiosk_devices_model.php:155-185`, usado en `In_person_appointments.php:506`). Tras un NAT
   municipal o una wifi pública eso incluye a cualquier visitante.
3. **Token de impresión expuesto.** El token del servicio de impresión llega en claro al navegador de la página pública
   del kiosko (`In_person_appointments.php:208-219`, `In_person_confirmation.php:44-55`). La migración 076 inserta el
   valor por defecto `change-this-print-token`. El servicio local acepta `/health` y `/printers` sin token y lanza un
   proceso PowerShell en cada llamada a `/printers` (`local-print-service/server.js:497-512`).
4. **Websocket sin autenticación de suscriptores.** Cualquiera que alcance el puerto de `realtime-server` recibe todos
   los eventos del calendario (`realtime-server/server.js:121-132`). No usa TLS; en producción necesita un proxy
   `wss://`. Los eventos no llevan datos personales. No hay unidad `systemd`/`pm2` ni guía de despliegue.
5. **Clientes ficticios por turno.** Cada turno presencial crea una fila en `users` con email
   `walkin-...@example.com` (`In_person_appointments.php:299-312`). Con las notificaciones activas se intentará
   enviar correo a esa dirección. Conviene revisar la retención de datos de estas filas.
6. **El GET público `turnos/data` escribe en base de datos** en cada refresco de la pantalla de sala
   (`Turnos.php:96`, `Appointments_model::ensure_daily_queue_numbers`). Un cliente recargando la página fuerza
   escrituras continuas.
7. **Límite global de peticiones eliminado.** `EA_Controller.php:90` ya no aplica el límite por IP de 1.6.0. Se
   sustituye por límites por operación (reserva 20/120 s, recuperación 5/600 s, API 10/300 s, emparejamiento
   10/600 s). Las páginas públicas no cubiertas quedan sin protección genérica. Los ficheros
   `storage/cache/rate_limit_*` nunca se borran.
8. `finish_and_call_next` renombra estados en **todas** las citas de la instalación, fuera de la transacción
   (`Calendar.php:720-731` y `1002`).

## Funcionamiento

9. **Instalación nueva probablemente rota (no ejecutado).** `Providers_model::validate` exige
   `settings.attendants_number >= 1` (`application/models/Providers_model.php:152-160`), pero el instalador crea la
   proveedora de ejemplo sin ese campo (`application/controllers/Installation.php:183-192`), igual que `console seed`
   y la API REST `POST/PUT /api/v1/providers`.
10. **Pérdida de control de solapamientos.** Se eliminó `has_provider_conflict`/`force_save` del calendario del
    backend (`Calendar.php`, bloque 760-777 original). Dos citas al mismo proveedor y hora se guardan sin aviso salvo
    que `attendants_number` esté configurado.
11. **Migración 081 destruye el valor configurado** de `book_advance_timeout`: lo fija a 1 sin convertir minutos a
    días, y su `down` lo pone a 30.
12. **Número de turno aleatorio.** `get_random_queue_number` puede hacer hasta 200 consultas por cita
    (`Appointments_model.php:143-155`).
13. Las migraciones no crean claves foráneas para `kiosk_devices.id_users_provider` ni `appointments.id_users_caller`.
14. En el primer commit del contratista había dos migraciones con el número 071; CodeIgniter aborta en ese caso. Se
    corrigió en el commit siguiente, así que la rama actual migra bien.
15. `Calendar::get_active_calls` compara ids con `in_array(..., true)` sobre valores que pueden llegar como texto
    (`Calendar.php:897`); posible lista vacía para secretarías (no ejecutado).
16. En la confirmación de reserva online desaparecen los botones "Ir a reservas" y "Añadir a Google Calendar", no
    solo en modo kiosko (`application/views/pages/booking_confirmation.php`).
17. El servicio de impresión se llama por `http://127.0.0.1:8787`; si el kiosko se sirve por HTTPS el navegador
    bloqueará la petición como contenido mixto.

## Calidad y mantenimiento

18. **Estados de cita en castellano a fuego** ("Reservado", "En proceso", "Finalizada"/"Finalizado") en 36 puntos de
    controllers, models y libraries, y en el JS del calendario (`calendar_default_view.js:177-210`,
    `calendar_table_view.js:153-186`). Si se renombra un estado o se cambia el idioma, el coloreado y la detección de
    walk-ins dejan de funcionar. Hay dos tablas de colores distintas (`Booking`/`In_person_appointments` frente a
    `Calendar`).
19. **Traducciones.** 126 claves nuevas solo en inglés y español; los otros idiomas muestran la clave en bruto. Textos
    sin clave de idioma: pie de la página pública, `app.js:96`, `local_printing.js:43,50`, `Calendar.php:1436`.
20. **Código duplicado** entre `Booking.php` e `In_person_appointments.php` (estado por defecto, colores, búsqueda de
    hueco walk-in) y `is_walkin_appointment` duplicado en `Calendar.php` y `Reports.php` con heurísticas frágiles
    (prefijo de notas, nombre, email).
21. **Código muerto**: `assets/js/pages/turnos-slider.js` y `assets/img/aranda.gif` no se usan en ninguna vista. El
    slider además carga GIF desde `www.arandadeduero.es`.
22. Consultas de esquema (`field_exists`) en cada petición en `Turnos`, `Calendar` y varios modelos para tolerar
    bases de datos sin migrar.
23. Estilos inline extensos duplicados en `in_person_appointments.php` y `booking_confirmation.php` en vez de SCSS.
24. **Sin tests** para el código nuevo y **`openapi.yml` sin actualizar**. El contratista había eliminado `tests/`,
    `docs/`, la CI y `openapi.yml` al partir del zip; están restaurados en la rama.
25. Sin aviso `aria-live` en la pantalla de sala; el aviso de llamada es solo sonoro.

## Lo que está bien

- No se tocó el framework (`system/`) ni los hooks.
- No se detectó XSS: el HTML de "documentación" pasa por un saneador con lista blanca
  (`assets/js/utils/string.js:49-143`) y el resto del render usa `e()` o `.text()`.
- Las citas se guardan con bloqueo por proveedor y `finish_and_call_next` usa transacción con `SELECT ... FOR UPDATE`.
- Google Sync pasa a verificar el certificado TLS (`verify => true`), y las llamadas externas tienen tiempo máximo.
- Los ficheros compilados commiteados corresponden a las fuentes del último commit.

## Pasos recomendados

1. Rotar la contraseña SMTP y ponerla en `.env`.
2. Pedir al contratista que corrija los puntos 2, 3, 6, 9, 10 y 11 antes de ponerlo en producción.
3. Fusionar `main` (1.6.1) en `aranda`.
4. Añadir tests para disponibilidad con capacidad compartida, kiosko y llamadas, y documentar las rutas nuevas en
   `openapi.yml`.
5. Documentar el despliegue de `realtime-server` y `local-print-service` (servicio del sistema, proxy `wss://`,
   rotación de logs).
