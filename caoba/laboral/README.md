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

Cada una de las siete respuestas incluye contador visible, aviso a las 100 palabras y validación inmediata si se excede el máximo. El contador contempla espacios y saltos de línea, se actualiza al escribir o pegar y recalcula respuestas conservadas tras un error. Se mantiene la validación del servidor.


Tipografía actual: Noto Sans SC (300 Light para lectura y 500 Medium para títulos y botones), licencia SIL OFL 1.1 incluida en brand/Noto-LICENSE.txt. Se alojan localmente los subconjuntos WOFF2 latino y latino extendido de Google Fonts. No se distribuye STHeiti.

Fondos de campaña aportados por el cliente: desktop 2560×1440 y móvil 1080×1920, optimizados a WebP. El encabezado cambia de archivo hasta 650 px y superpone una capa oscura para facilitar la lectura del texto blanco.

## Índice privado de candidaturas

El panel muestra nombre, fecha en Costa Rica, perfil y estado; permite buscar por nombre, correo o perfil y filtrar por estado. Orden descendente y paginación de 25 registros. Cada enlace abre un detalle privado con respuestas, descarga del CV, estado y notas; al guardar permanece en ese detalle. Las candidaturas históricas se leen sin migración.

Validación: LinkedIn admite Unicode en la ruta y lo codifica sin eliminar tildes ni duplicar escapes. Se mantienen las restricciones de dominio, HTTPS y ruta de perfil. Los errores se agrupan en un resumen con enlaces y aparecen junto al campo; se conservan texto, selecciones y consentimiento al fallar. El PDF debe seleccionarse de nuevo tras un error del servidor por seguridad del navegador. La validación PHP opera incluso sin JavaScript.
