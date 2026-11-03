# Caoba Legal — reclutamiento

Landing y registro privado de candidaturas en PHP 8.2+, PDO SQLite y Fileinfo.

- Campaña: https://whatcat.studio/Caoba
- Instalación: https://assets.whatcatstudio.com/caoba/laboral/
- Panel: añadir `?admin=1` (autenticación HTTP Basic por HTTPS).

## Configuración privada

`dirname(DOCUMENT_ROOT)/caoba-private/config.php` debe devolver un array con `open` (booleano), `storage` (ruta absoluta fuera del directorio público), `admin_user`, `admin_hash` (password_hash), `rate_secret` (aleatorio), `privacy_contact` y `retention`.

Crear directorio privado con permisos 0700 y archivos 0600. La configuración, contraseñas, SQLite y PDF nunca se versionan ni se copian al directorio público. `preview_secret` es opcional para pruebas cerradas; retirarlo antes de abrir. Las pruebas deben utilizar almacenamiento separado de producción.

## Privacidad y operación

Contacto: info@caobalegal.com. Conservación: seis meses. Al acceder a la aplicación se eliminan los registros y PDF anteriores a seis meses, antes de mostrar el panel. No existe tarea cron en esta instalación: si el sitio queda sin tráfico, ejecutar una visita periódica para activar la limpieza. Los respaldos de infraestructura deben tener una política de expiración compatible; esta aplicación no controla los respaldos de SiteGround.

Se registra consentimiento y fecha, sin puntuación ni decisiones automatizadas. El equipo de selección revisa y decide. El panel permite descargar PDF, cambiar estado y guardar notas internas.

## Validación

Pruebas HTTPS con datos ficticios: siete respuestas obligatorias (incluidas referencias), formulario cerrado, acceso privado de prueba, CSRF, consentimiento, rechazo de PDF falso y de más de 5 MB, máximo de 100 palabras, envío y persistencia, límite de frecuencia, autenticación del panel, descarga íntegra de PDF, cambios de estado, escape de notas y denegación de acceso al almacenamiento privado.

No se envían notificaciones de correo. Consultar el panel para revisar nuevas candidaturas.

## Identidad visual

Se reutiliza la línea de los materiales de Caoba en `/caoba/decision/`: terracota #ca662d, tinta #594544, marrón profundo #3a2c2b, Jost para títulos y controles y Literata para lectura. Logo de aniversario (10 años), procedente de /caoba/logo/caoba-10anos-static.png y usado en las firmas actuales; motivo de anillos de los materiales existentes. Fuentes alojadas localmente para mantener CSP y evitar peticiones de candidatos a Google Fonts.
