# Clínica Imagen — Fundamentación de diseño

## Tipografía

**Familia:** Poppins (Google Fonts), con `sans-serif` como respaldo.
Se define una sola vez en `assets/css/variables.css` (`--font-family`) y se carga globalmente desde `assets/css/styles.css`, de modo que el sitio institucional y el BackOffice comparten la misma tipografía.

**Por qué Poppins**

- **Legibilidad en pantalla:** es una sans-serif geométrica con altura de x amplia y formas abiertas, que se lee bien en tamaños chicos (tablas de agenda, badges de estado, formularios) y en pantallas móviles.
- **Identidad de salud y tecnología:** las formas circulares y limpias transmiten orden, higiene y precisión, valores asociados al sector odontológico e imagenológico, sin el tono frío de una tipografía técnica.
- **Una sola familia, varios pesos:** usar solo pesos de la misma familia mantiene la coherencia visual y reduce la cantidad de fuentes que se descargan.
- **Soporte del español:** incluye acentos y la ñ sin problemas.

**Pesos utilizados** (`--font-weight-*`)

| Variable | Peso | Uso |
|---|---|---|
| (por defecto) | 400 | Texto de párrafo y formularios |
| `--font-weight-medium` | 500 | Etiquetas y texto secundario destacado |
| `--font-weight-semibold` | 600 | Botones, encabezados de tarjetas y tablas |
| `--font-weight-bold` | 700 | Títulos de sección |
| `--font-weight-extrabold` | 800 | Título principal del sitio institucional |

**Escala de tamaños** (`--font-size-*`)

| Variable | Valor | Uso |
|---|---|---|
| `--font-size-xs` | 0.75rem | Badges, metadatos, ayudas de formulario |
| `--font-size-sm` | 0.85rem | Texto de tablas y agenda |
| `--font-size-md` | 0.95rem | Texto de formularios y menús |
| `--font-size-base` | 1rem | Texto base |
| `--font-size-lg` | 1.1rem | Subtítulos |
| `--font-size-xl` | 1.4rem | Títulos de tarjeta |

Los títulos principales usan `clamp()` para escalar de forma fluida entre móvil y escritorio.

## Roles del sistema

El sistema define cinco roles (menú en `includes/menu.php`, acceso en cada endpoint con `requerir_rol_json()`):

| Rol | Responsabilidades |
|---|---|
| `administrador` | Gestión completa: usuarios y roles, pacientes, profesionales, estudios, sucursales, turnos, solicitudes de cita, agenda, mensajes de contacto, envío y búsqueda de resultados, y eventos de seguridad |
| `recepcionista` | Atención en mostrador: panel, turnos, pacientes (alta y edición), solicitudes de cita, agenda y mensajes de contacto. No gestiona usuarios, catálogos ni resultados |
| `medico` | Médico derivante externo: agenda citas para sus pacientes y consulta sus resultados |
| `profesional` | Profesional de la clínica: ve su agenda, el historial de pacientes y carga observaciones y resultados |
| `paciente` | Agenda sus citas y consulta sus resultados |

El menú de cada rol se define en `includes/menu.php`. Las vistas son HTML estático sin lógica de acceso; cada endpoint en `api/` protege su acceso según el rol (`requerir_rol_json()`).

## Seguridad

- **Escape de salida.** Las vistas son HTML estático y la API devuelve solo JSON; el navegador inserta los datos con `textContent`, que los trata siempre como texto y nunca como marcado. Eso es equivalente a `htmlspecialchars` (que evita que `<script>` se interprete como etiqueta) y por eso no hace falta en el servidor. Como defensa adicional, la Content-Security-Policy de `.htaccess` prohíbe scripts y handlers inline.
- **Base de datos.** Todas las consultas usan sentencias preparadas (PDO); las contraseñas se guardan con `password_hash` (bcrypt).
- **Sesión y formularios.** Los POST exigen token CSRF; la sesión expira tras 25 minutos de inactividad.
- **Límite de intentos.** Login, registro, reseteo de contraseña, reenvío de verificación y contacto se limitan por IP (y por email en el login) contando eventos recientes en la tabla `eventos`, que además sirve de registro de auditoría.
- **Notas clínicas cifradas.** `notas_clinicas.texto` se guarda cifrado con AES-256-GCM (`includes/cifrado.php`): IV aleatorio por nota y etiqueta de autenticación, de modo que cualquier alteración del dato se detecta al descifrar. La clave (`APP_KEY`) vive solo en `includes/config.php` y se genera al instalar; si se pierde, las notas no se pueden recuperar. Las notas anteriores sin cifrar se siguen leyendo.
