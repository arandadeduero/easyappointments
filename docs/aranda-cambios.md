# Personalización del Ayuntamiento de Aranda de Duero

Este documento describe en qué se diferencia la rama `aranda` del proyecto original Easy!Appointments y cómo se
construyó. La revisión del código, con los problemas detectados, está en [aranda-revision.md](aranda-revision.md).

## Origen de la rama

- El contratista descargó el paquete de la release **1.6.0** (27-05-2026), inicializó un repositorio nuevo y trabajó
  sobre él entre el 15-06-2026 y el 24-09-2026 (19 commits). No partió del historial git del proyecto.
- La rama `aranda` se reconstruyó el 20-10-2026 a partir de la etiqueta `1.6.0` del repositorio original:
  1. Un commit de importación con el contenido exacto de su primer commit.
  2. Sus 18 commits posteriores, reproducidos uno a uno conservando autor y fecha.
  3. Un commit que restaura los ficheros de desarrollo que el paquete de release no incluye y que nunca se
     modificaron (tests, docs, docker, CI, `composer.lock`, `config-sample.php`, `openapi.yml`, SCSS de los temas,
     permisos de fichero).
  4. La configuración de secretos descrita más abajo.
- La contraseña SMTP que el contratista había guardado en `application/config/email.php` se eliminó de **todos** los
  commits antes de publicar la rama. No existe en el historial de `aranda`.
- El 20-10-2026 se fusionó `main` (release **1.6.1**, 12-10-2026) en `aranda`. En los conflictos se mantuvo la
  funcionalidad del contratista y se incorporaron todas las correcciones de seguridad de la 1.6.1: verificación del
  hash de la cita al modificarla desde la página pública, control de acceso a clientes y confirmación de solapamientos
  en el calendario, filtrado de los datos sensibles de los proveedores, permisos en la conexión OAuth de Google y
  restricción de las peticiones CalDAV al mismo servidor. Detalles en el commit de fusión.

Para ver todas las diferencias respecto al original:

```bash
git diff main aranda --stat
git diff main aranda -- application    # solo backend
git log 1.6.0..aranda --no-merges      # commits del contratista y posteriores
```

## Configuración

### Secretos en `.env`

La contraseña SMTP ya no está en el código. Se lee de la variable `SMTP_PASSWORD` del fichero `.env` situado junto a
`index.php`. El fichero está ignorado por git; `.env.example` documenta las variables disponibles.

```bash
cp .env.example .env
# editar .env y rellenar SMTP_PASSWORD
```

Las variables definidas en el entorno real (servidor web, shell, contenedor) tienen prioridad sobre el fichero. El
resto de la configuración SMTP (servidor, usuario, puerto, cifrado) sigue en `application/config/email.php`.

### Constantes nuevas en `config.php`

El contratista añadió constantes opcionales a la clase `Config` del fichero raíz `config.php`. No están en
`config-sample.php`; si no se definen, el tiempo real queda desactivado.

| Constante                  | Uso                                                                    |
| -------------------------- | ---------------------------------------------------------------------- |
| `REALTIME_WS_ENABLED`      | Activa la conexión websocket del calendario y de la pantalla de sala.  |
| `REALTIME_WS_URL`          | URL `ws://` o `wss://` a la que se conectan los navegadores.           |
| `REALTIME_WS_PUBLISH_URL`  | URL HTTP del endpoint `/publish` de `realtime-server`.                 |
| `REALTIME_WS_SECRET`       | Secreto compartido con `realtime-server` (cabecera `X-Realtime-Secret`).|

`application/config/app.php` define además tiempos máximos para servicios externos (Google, CalDAV, webhooks, SMTP) y
un umbral para registrar llamadas lentas.

### Ajustes nuevos en base de datos

Se crean mediante migraciones y se editan desde el backend:

- `company_working_plan_walkin`: plan de trabajo para citas presenciales.
- `disable_booking_appointments` y `disable_walkin_appointments`: sustituyen a `disable_booking`.
- `allow_same_day_booking`: permite reservar para el mismo día (por defecto, solo desde mañana).
- `walkin_advance_margin`: margen mínimo en minutos para asignar un turno presencial.
- `local_printing_enabled`, `local_printing_endpoint`, `local_printing_token`: impresión de tickets, también por
  proveedor.
- `book_advance_timeout` pasa a expresarse en **días naturales** de antelación para cancelar o reprogramar (antes eran
  minutos). La migración 081 fija el valor a 1.

## Funcionalidades añadidas

### Citas presenciales (kiosko)

- Pantalla pública `in_person_appointments?departamento=<id_proveedor>` para que el ciudadano saque turno en el
  momento. Elige servicio, ve la documentación que debe presentar y recibe la hora estimada y un número de turno.
- El kiosko solo funciona en dispositivos emparejados. El emparejamiento se hace desde Ajustes → Reservas con un código
  de 6 dígitos que caduca a los 5 minutos. Los dispositivos se guardan en las tablas `kiosk_devices` y
  `kiosk_pairing_codes` y se pueden revocar.
- Cada turno crea una cita de tipo `walkin` y un cliente ficticio ("Presencial" + marca de tiempo).
- Confirmación en `in_person_confirmation/of/<hash>` con el número de turno en grande y reimpresión del ticket.
- Ficheros principales: `application/controllers/In_person_appointments.php`, `In_person_confirmation.php`,
  `application/models/Kiosk_devices_model.php`, `assets/js/pages/in_person_appointments.js`.

### Pantalla de sala

- Página pública `turnos?departamento=<id_proveedor>` pensada para un monitor: reloj, turnos llamados con mostrador
  y turnos pendientes. Suena una campana al llamar.
- Datos por `turnos/data` cada 5 segundos y, si está configurado, por websocket.
- Ficheros: `application/controllers/Turnos.php`, `assets/js/pages/turnos.js`, `assets/css/turnos.css`.

### Gestión de llamadas en el calendario

- Barra inferior fija con las citas "En proceso" del día. Permite llamar a una cita, finalizarla y "finalizar y llamar
  al siguiente".
- Cada empleado tiene un **número de mostrador** (`desk_number`) en su cuenta, admin o secretaría.
- Se guarda quién llamó (`appointments.id_users_caller`) y cuándo (`appointments.call_datetime`).
- Endpoints: `calendar/call_appointment`, `calendar/get_active_calls`, `calendar/finish_appointment`,
  `calendar/finish_and_call_next`.

### Capacidad compartida por departamento

- Un proveedor (departamento) puede atender varias citas a la vez según `attendants_number`, acotado además por el
  mismo campo del servicio. Se valida al guardar con un bloqueo por proveedor.
- Ficheros: `application/libraries/Availability.php`, `application/models/Appointments_model.php`.

### Informes

- Página `reports` (botón "Informes" del calendario) con contadores por servicio, estado, proveedor y día, filtrables
  por periodo, fecha, proveedor y servicio. Solo muestra totales, no exporta.
- Ficheros: `application/controllers/Reports.php`, `application/views/pages/reports.php`.

### Documentación por servicio

- Campo `documentation_requirements` (HTML saneado) en cada servicio. Se muestra como paso adicional en la reserva
  pública y en el kiosko.

### Impresión local de tickets

- Servicio Node para Windows en `local-print-service/` que recibe el ticket por HTTP en `127.0.0.1:8787` y lo imprime
  con PowerShell. Se autentica con un token que debe coincidir con el configurado en el proveedor.
- El navegador del kiosko llama al servicio directamente (`assets/js/utils/local_printing.js`).

### Eventos en tiempo real

- Servicio Node en `realtime-server/` (dependencia `ws`). Easy!Appointments publica eventos de citas e
  indisponibilidades por HTTP (`application/libraries/Realtime_events.php`, disparado desde `Webhooks_client`) y el
  servicio los reenvía por websocket a los calendarios abiertos y a las pantallas de sala.
- Sustituye al refresco cada 60 segundos del calendario; si no hay websocket se mantiene el sondeo.

### Otros

- Renovación automática del token CSRF (`csrf/refresh`) y cabecera `X-CSRF` en todas las peticiones del backend.
- Límite de peticiones por operación (reserva, recuperación de contraseña, API, emparejamiento) en lugar del límite
  global por IP.
- Emails con cabecera de color corporativo y logo; se retira el pie "Powered by Easy!Appointments".

## Cambios de comportamiento sobre el original

- En la reserva pública se elige **primero el departamento y después el servicio**. Desaparece "cualquier
  proveedor". Admite `?departamento=<id>` en la URL.
- Por defecto solo se puede reservar a partir de mañana (`allow_same_day_booking`).
- El intervalo del calendario pasa de 15 a 5 minutos.
- El color de cada cita depende de su **estado** ("Reservado", "En proceso", "Finalizada"), no del servicio. Los
  estados están escritos en castellano en el código.
- El contratista había eliminado la detección de solapamientos al crear citas desde el calendario del backend; la
  fusión de la 1.6.1 la recupera como pregunta de confirmación, compatible con la capacidad compartida.
- Todas las citas, también las online, reciben número de turno y una nota "Cita #N".
- El tema visual queda fijado a `minty` y desaparece el selector de tema.
- Se eliminan del backend el menú "Acerca de", el enlace "Premium" y el contenido del pie. En la página pública el pie
  muestra "Desarrollado por San-Cha Informática".
- Se eliminan el selector de color y la obligatoriedad del email en el modal de citas.
- En la confirmación de reserva desaparecen los botones "Ir a reservas" y "Añadir a Google Calendar".
- Las traducciones nuevas solo existen en inglés y español.

## Migraciones añadidas

| Nº  | Contenido                                                                        |
| --- | -------------------------------------------------------------------------------- |
| 070 | Tipo de cita (`booking`/`walkin`), número de turno, plan de trabajo presencial.  |
| 071 | Usuario que llama (`id_users_caller`) y documentación por servicio.              |
| 072 | Ajustes para deshabilitar citas online o presenciales.                           |
| 073 | `attendants_number` por proveedor.                                               |
| 074 | `allow_same_day_booking`.                                                        |
| 075 | Tablas `kiosk_devices` y `kiosk_pairing_codes`.                                  |
| 076 | Ajustes globales de impresión local.                                             |
| 077 | Ajustes de impresión local por proveedor.                                        |
| 078 | `appointments.call_datetime`.                                                    |
| 079 | `user_settings.desk_number`.                                                     |
| 080 | `walkin_advance_margin`.                                                         |
| 081 | `book_advance_timeout` en días.                                                  |

## Rutas nuevas

| Ruta                                          | Acceso                 |
| --------------------------------------------- | ---------------------- |
| `in_person_appointments` (+ `preview`, `store`, `authorize_device`) | Público, dispositivo emparejado |
| `in_person_confirmation/of/<hash>`            | Público                |
| `turnos`, `turnos/data`                       | Público                |
| `csrf/refresh`                                | Público                |
| `reports`                                     | Sesión, ver citas      |
| `calendar/call_appointment`, `get_active_calls`, `finish_appointment`, `finish_and_call_next` | Sesión, editar citas |
| `booking_settings/generate_kiosk_pairing_code`, `revoke_kiosk_device` | Sesión, ajustes del sistema |

Ninguna de estas rutas está documentada en `openapi.yml`. La API REST (`/api/v1`) no se modificó.

## Ficheros compilados

El contratista commitea los ficheros `*.min.js`, `*.css` y `assets/vendor/`, que el proyecto original ignora, porque el
servidor sirve el repositorio sin paso de construcción. Tras tocar un fuente hay que ejecutar `npm run build` y
commitear también el fichero generado. `gulpfile.js` incluye una tarea `cssFiles` para los CSS escritos a mano
(`turnos.css`, `calendar_active_calls.css`).
