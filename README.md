# AutoPill

Esta es una plataforma web para gestionar el Pastillero Automatico Autopill, basado en ESP32. Permite a sus usuarios crear y administrar alarmas de medicación, registrar la ejecución de las alarmas, y recibir notificaciones de cuando tomar las pastillas. El firmware del ESP32 consulta periódicamente la API para obtener instrucciones y reporta eventos de dispensado.

## ¿Qué hace el proyecto?

- Gestión de usuarios con roles de usuario "común" y cuidador (registro, login tradicional y Google OAuth).
- Recuperación de contraseña por email con enlaces seguros y expiración.
- Creación, edición, eliminación y listado de alarmas por usuario y módulo.
- Notificaciones en tiempo real en la web y envío hacia dispositivos vinculados.
- Vinculación y administración de módulos ESP32 por usuario.
- Paneles de control para usuario "comúnes" y cuidadores.
- API HTTP utilizada por ESP32 para obtener próximas alarmas y reportar eventos.
- Servicios de monitoreo/cron para tareas de mantenimiento y seguimiento.

Tecnologías principales:
- PHP 8, MySQL/MariaDB
- Web server (Apache/IIS)
- SendGrid para email transaccional
  
## Rol de usuario común
El usuario común es el usuario dueño del Pastillero Autopill, quien toma las pastillas.

## Rol de cuidador
Un usuario no dueño del pastillero, quien esta vinculado con un usuario común, y administra las pastillas de este de manera remota, con previa autorización del mismo. 

## ¿Por qué es útil?

- Es el software principal para manejar un hardware pensado para ayudar al usuario a adherirse a su tratamiento farmacológico.
- Centraliza el manejo de tratamientos: el usuario ve y recibe recordatorios; el cuidador puede supervisar estados y alarmas activas, además de modificarlas.
- Permite la configuración del Pastillero Autopill con una interfaz web y hardware simple.
- Incluye un flujo de recuperación de contraseña robusto y prácticas de seguridad básicas.

## Arquitectura y archivos clave

- Autenticación y sesiones: `session_init.php`, `login.php`, `login_process.php`, `logout.php`, `register.php`, `register_process.php`.
- Recuperación de contraseña: `forgot-password.php`, `forgot_password_start.php`, `verificar-codigo.php`, `validate_reset_link.php`, `nueva-password.php`, `set_new_password.php`.
- Alarmas: `agregar-alarma.php`, `guardar_alarma.php`, `editar-alarma.php`, `modificar-alarma.php`, `eliminar-alarma.php`, `eliminar_alarma_multiple.php`, `get_all_user_alarms.php`, `listar_alarmas_activas.php`, `guardar_nombre_alarma.php`, `modificar-nombre-alarma.php`, `modificar-cantidad-pastillas.php`.
- Módulos/ESP32: `configurar-modulo.php`, `guardar_modulo.php`, `obtener_modulos.php`, `obtener_modulos_paciente.php`, `vincular_esp.php`, `vincular_esp32_manual.php`, `procesar_vinculacion_esp.php`, `eliminar_vinculo_esp.php`, `unlink_vinculo.php`, `notify_esp_unlink.php`.
- Notificaciones: `notifications.php`, `notifications_count.php`, `notification_details.php`, `mark_notification_read.php`, `delete_notifications.php`, utilidades en `notificaciones_utils.php` y `notificaciones_dispensado_utils.php`.
- Dashboards: `dashboard.php`, `dashboard_paciente.php`, `dashboard_cuidador.php`, `monitor_alarmas.php`, `panel_monitor_alarmas.php`.
- API para ESP32: `get_notifications_esp.php` (principal), `send_notification_esp.php`, `report_alarm_execution.php`, `activate_servo.php`.
- Infraestructura: `conexion.php` (BD), `logger.php`, `email_config.php`/`email_config.local.php`, `email_service.php`, `styles.css`, `notifications.js`.
- Google OAuth: `google_login.php`, `google_callback.php`, `google_oauth_config.php`, `google_role_select.php`, `google_finalize_signup.php`.

Base de datos de referencia: `bg03.sql` (y `bg03_duplicado.sql`).

## Requisitos

- PHP 8.0 o superior
- MySQL/MariaDB 5.7+ / 10.4+
- Web server (Apache con mod_php o IIS con FastCGI)
- Clave API de correo (por ejemplo SendGrid)
- Clave API de Google OAuth

## Configuración

### Base de datos

El archivo `conexion.php` soporta tres métodos, en este orden de prioridad:
1. Variables de entorno: `BG03_DB_HOST`, `BG03_DB_USER`, `BG03_DB_PASS`, `BG03_DB_NAME`.
2. Bloques manuales en el propio archivo para uso local o en servidor.
3. Fallback a `localhost`/`root`/`` base `bg03`.

Sugerido en producción: definir variables de entorno del sistema o del virtual host.


### Email

Configura `email_config.local.php` (no versionado) o variables de entorno:
- `EMAIL_API_PROVIDER` (por defecto: `sendgrid`)
- `EMAIL_API_KEY` (token de la API)
- `EMAIL_FROM` (remitente), `EMAIL_FROM_NAME`
- `EMAIL_BASE_URL` URL pública base del sitio (usada para enlaces de recuperación)

Si no configuras un proveedor, el sistema intentará `mail()` como fallback.

### Google OAuth

1. Crea credenciales OAuth 2.0 en Google Cloud Console (tipo aplicación web).
2. Configura URIs de redirección autorizadas apuntando a `google_callback.php`.
3. Completa `google_oauth_config.php` y secretos en `google_oauth_secrets.php`.

### Variables adicionales

- API key del ESP32: definida en `get_notifications_esp.php`. Ajusta según tu despliegue.

## Puesta en marcha local

1. Clona este repositorio en el directorio servido por tu web server.
2. Crea la base de datos y credenciales; importa `bg03.sql` si aplica.
3. Configura `conexion.php` vía variables de entorno o bloque manual.
4. Configura `email_config.local.php` o variables de entorno para email y `EMAIL_BASE_URL`.
5. (Opcional) Configura Google OAuth si se usará.
6. Abre `http://localhost/index.php` y realiza un registro o login.
7. Vincula un dispositivo ESP32 y crea tus primeras alarmas.

## Endpoints para ESP32

- Obtener instrucciones/notificaciones:
  `GET /get_notifications_esp.php?api_key=...&code=ESP32_XXX&user_id=...&module=1`
- Reportar ejecución de alarma: `POST /report_alarm_execution.php`
- Activar servo de prueba/control: `POST /activate_servo.php`

Revisa el código del endpoint principal `get_notifications_esp.php` para ver el contrato exacto y los campos que devuelve según tu esquema de BD.

## Resolución de problemas

- Respuestas JSON inválidas: evita cerrar archivos PHP con `?>` y no generes salida antes de `header('Content-Type: application/json')`.
- Sesiones: asegúrate de que `session_init.php` se incluya primero y que no haya salidas previas.
- Email: si usas SendGrid, confirma que `EMAIL_API_KEY` es válido y que el servidor puede salir a internet.
- Conexión a BD: valida variables `BG03_DB_*` o ajusta el bloque manual en `conexion.php`.

## Cómo recibir ayuda

- Abre un issue en GitHub con pasos para reproducir, logs relevantes y capturas.
- Desde la propia aplicación web, puedes usar los formularios de contacto/reporte: `report_problem.php` / `enviar_reporte_problema.php` / `enviar_contacto_index.php`.
- Si trabajas en una red interna, coordina con el administrador del servidor o de la base de datos para revisar conectividad y permisos.

## Seguridad

- No subas `email_config.local.php` ni `google_oauth_secrets.php` al repositorio público.
- Cambia la API key del ESP32 y las credenciales por valores propios en producción.
- Revisa permisos de archivos y de la cuenta de MySQL en el servidor.

## Licencia

Autopill 2025. Todos los derechos reservados.


