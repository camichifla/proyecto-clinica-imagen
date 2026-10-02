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

La rúbrica menciona los roles "administrador" y "recepcionista". En el sistema, las tareas de recepción las cubre el rol **administrador**, y se agregan tres roles más que surgen del análisis del sector:

| Rol | Responsabilidades |
|---|---|
| `administrador` | Tareas de recepción y gestión: usuarios, solicitudes de cita, agenda, envío y búsqueda de resultados |
| `medico` | Médico derivante externo: agenda citas para sus pacientes y consulta sus resultados |
| `profesional` | Profesional de la clínica: ve su agenda, el historial de pacientes y carga observaciones y resultados |
| `paciente` | Agenda sus citas y consulta sus resultados |

El menú de cada rol se define en `includes/menu.php`. Las vistas son HTML estático sin lógica de acceso; cada endpoint en `api/` protege su acceso según el rol (`requerir_rol_json()`).
