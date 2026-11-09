# Powerbull — acceso optimizado y contraseña para cuentas de Google

## Abrir en XAMPP

1. Guardá una copia de tu carpeta actual de Powerbull.
2. Reemplazala por la carpeta **powerbull** incluida en este ZIP, dentro de `C:\xampp\htdocs\`.
3. Iniciá Apache y abrí **http://localhost/powerbull/**.

Requiere PHP 8.1 o superior, sesiones habilitadas y conexión a Internet.
Se recomienda habilitar la extensión cURL en PHP; también se admite el transporte HTTPS de PHP.
La conexión con Supabase ya está configurada para el proyecto original.

**No importes otra vez la base de datos.** Las correcciones del alta de perfiles y del contexto de sesión ya fueron aplicadas al proyecto conectado. No se borraron las cuentas ni se cambiaron sus roles.

## Iniciar sesión y crear una cuenta

- Correo y contraseña: usa Supabase Auth y carga el perfil de la tabla `usuarios`.
- Crear cuenta: usa la función existente `powerbull-registro` y abre la sesión cuando el alta termina correctamente.
- Continuar con Google: usa Supabase OAuth con PKCE y permite que Google reutilice su sesión cuando corresponde, sin forzar el selector de cuentas en cada ingreso. Si la persona es nueva, Supabase crea la cuenta y el disparador de la base crea su perfil con rol **comprador**.
- El perfil toma nombre, apellido y correo disponibles. Si Google solo entrega el nombre completo, se usa como respaldo. La dirección queda vacía hasta que se disponga de ella. Google no comparte la contraseña.
- Cuando Supabase vincula una identidad de Google a una cuenta existente, se conserva el perfil y su rol.
- Los tokens se guardan en la sesión PHP del servidor. La sesión se renueva automáticamente. Al ingresar o cambiar la contraseña se renuevan el identificador de sesión y el token del formulario.
- Las cuentas que ingresan solamente con Google muestran **Crear contraseña** en la tarjeta de Mi cuenta. La contraseña creada pertenece a Powerbull y permite ingresar también con el mismo correo.
- Si pasó un tiempo desde el ingreso con Google, la pantalla pide **Confirmar con Google** antes de crear la contraseña. La cuenta confirmada debe ser la misma que está abierta en Powerbull.
- Cuando ya existe una contraseña de Powerbull, la tarjeta muestra **Cambiar contraseña** y el formulario solicita la contraseña actual.
- Si el formulario de correo/contraseña rechaza el acceso, orienta a quienes se registraron con Google para que entren con ese botón y creen su contraseña desde Mi cuenta. No consulta públicamente los métodos de acceso de otros correos.
- Las contraseñas siguen gestionadas por Supabase Auth. No se agregan a la tabla pública `usuarios`.

El botón de Google ocupa el 100 % del ancho de los campos, admite cambios de texto y conserva el tamaño en celulares. No depende del ancho del botón personalizado que Google dibuja dentro de un iframe.

## Optimización del acceso

- Después del intercambio válido con Auth, se usa el usuario incluido en su respuesta, evitando pedirlo nuevamente.
- Una sola llamada autenticada carga el perfil, las opciones de acceso y el resumen de pedidos. La base filtra siempre por el usuario de la sesión; no devuelve hashes ni contraseñas.
- El acceso y la carga inicial de Mi cuenta pasan de cuatro peticiones a Supabase a dos. La tarjeta y el resumen se conservan en la sesión durante un minuto y se actualizan después de una compra.
- Las peticiones consecutivas dentro de una solicitud PHP reutilizan la conexión HTTPS cuando cURL está disponible.
- El acceso normal no fuerza a elegir nuevamente la cuenta de Google. La selección se solicita al confirmar la cuenta para crear una contraseña.

La selección de cuenta, el consentimiento y la verificación de Google dependen de Google y de la conexión del navegador.

## Configuración de Google

En el proyecto conectado se verificaron el proveedor Google habilitado, el Client ID existente y el retorno hacia Powerbull en localhost.

La URL de devolución de Google hacia Supabase es:

`https://mbzenzxffillbpmybuak.supabase.co/auth/v1/callback`

La devolución de Supabase hacia esta aplicación es:

`http://localhost/powerbull/google_callback.php`

El código añade un identificador temporal `flow` en la URL y lo valida contra la sesión del navegador.

Para publicarla con otro dominio o moverla a otra dirección:

1. Configurá `POWERBULL_APP_URL` con la URL HTTPS completa de la carpeta de la tienda, sin barra final. También podés indicar ese valor en `includes/config.php`, propiedad `app_url`.
2. En Supabase, Authentication → URL Configuration, autorizá la nueva dirección de retorno. El patrón debe admitir la consulta `flow`, por ejemplo `https://tudominio.com/powerbull/google_callback.php**`.
3. Conservá en Google Cloud la devolución hacia Supabase indicada arriba.

En localhost, `app_url` puede quedar vacío: se reconoce la dirección y la carpeta automáticamente.
Si Google limita la aplicación a usuarios de prueba o a una organización, la cuenta utilizada también debe estar autorizada por esa configuración de Google.

## Archivos principales

- `includes/bootstrap.php`: sesión, errores, protección de formularios y carga de módulos.
- `includes/supabase.php`: peticiones HTTPS a Supabase y respuestas de error controladas.
- `includes/auth.php`: perfil del usuario, sesión, permisos y renovación de tokens.
- `api.php`: operaciones de los formularios, registro, contraseña, carrito y administración.
- `google.php` y `google_callback.php`: inicio y finalización de OAuth con PKCE.
- `login.php`, `registro.php` e `includes/layout.php`: formularios y botón de Google.
- `supabase/actualizar_perfil_google.sql`: copia de la corrección aplicada al alta de perfiles.
- `supabase/optimizar_sesion.sql`: contexto de sesión y opciones de acceso, limitado al usuario autenticado; ya aplicado.
- `supabase/functions/powerbull-registro/`: fuente de la función de registro que ya está desplegada.

`includes/config.php` conserva las claves públicas del proyecto. La clave secreta de servicio permanece únicamente en Supabase.
Si tu instalación de XAMPP requiere un archivo de certificados, podés indicar su ruta en `POWERBULL_CA_BUNDLE`; la verificación HTTPS se mantiene habilitada.

## Portada y demás pantallas

La portada conserva la plantilla de ocho espacios de Imagen del artículo / Nombre del artículo y la sección Sobre nosotros. Las fotos y los productos de ejemplo no se reincorporaron a la portada.

Se restauraron las funciones de PHP que necesitaban las pantallas de cuenta, pedidos, carrito, productos y administración. Las imágenes nuevas del administrador se guardan en `uploads/`, con nombres aleatorios y tipos limitados a JPG, PNG y WebP; esa carpeta debe permitir escritura al servidor PHP.

## Verificación

Pasaron 33 comprobaciones funcionales y de permisos. Se revisó la sintaxis de los 19 archivos PHP y de JavaScript. Las pruebas HTTP contra Supabase verificaron el registro, el acceso y la carga de la cuenta. Se comparó la versión anterior con la optimizada y se confirmó la reducción de cuatro llamadas a dos, y cero llamadas en las visitas siguientes a Mi cuenta mientras el contexto está vigente.

La creación de contraseña se comprobó con una cuenta temporal propia configurada con una identidad de Google, sin contraseña de Powerbull, y con una sesión válida. La confirmación reciente del callback se simula únicamente en esa sesión de prueba; no se usan credenciales de Google de una persona. Pasaron las comprobaciones de creación de contraseña, acceso posterior con el mismo correo, cambio de contraseña y protecciones del formulario. La cuenta temporal conservó un solo perfil y ambos métodos de ingreso. También se verificó que la función de contexto rechace llamadas anónimas y no devuelva contraseñas ni sus hashes.

El consentimiento y el ingreso final con una cuenta real de Google se completan en el navegador de la persona que ingresa. La corrección de los datos del perfil de Google fue comprobada anteriormente con una transacción de prueba revertida.
