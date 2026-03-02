# Bloque CodeSandbox

> **⚠️ Disclaimer:** Proyecto experimental. Úsalo bajo tu propio riesgo.

**Panel lateral de monitoreo en tiempo real para actividades Code Sandbox**

## Descripción General

El Bloque CodeSandbox es un plugin complementario para el módulo de actividad `mod_codesandbox`. Proporciona a los estudiantes un panel lateral persistente que muestra:

- 📸 **Snapshot del Último Run**: Vista previa de la ejecución de código más reciente con estado y tiempo de ejecución
- 📊 **Historial de Ejecuciones**: Línea de tiempo de ejecuciones recientes con indicadores de éxito/fallo
- 📝 **Scratchpad**: Bloc de notas con autoguardado vinculado a cada instancia de actividad

Este bloque mejora la experiencia de aprendizaje al dar a los estudiantes visibilidad continua de su progreso de codificación sin salir de la página de actividad.

## Características

### 1. Snapshot del Último Run
Muestra una vista compacta del código ejecutado más recientemente:
- Vista previa del código (primeras 10 líneas)
- Estado de ejecución (éxito/error)
- Tiempo de ejecución en milisegundos
- Marca de tiempo de la ejecución

### 2. Historial de Ejecuciones
Muestra las últimas 5 ejecuciones de código con:
- Iconos de estado éxito/fallo
- Marcas de tiempo de ejecución
- Métricas de tiempo de ejecución
- Fondos codificados por colores para escaneo visual rápido

### 3. Scratchpad Persistente
Un área de toma de notas que:
- Se autoguarda mientras escribes (con debounce)
- Almacena notas por instancia de actividad (`cmid`)
- Persiste entre sesiones
- Muestra el estado de guardado en tiempo real

## Cómo Funciona

### Integración con mod_codesandbox

El bloque se conecta a la actividad Code Sandbox a través de un flujo de trabajo integrado:

```
┌─────────────────┐
│ Estudiante      │
│ escribe código  │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Click en "Run"  │
│                 │
└────────┬────────┘
         │
         ▼
┌─────────────────────────┐
│ mod_codesandbox/run.php │
│ - Crea intento          │
│ - Envía a RabbitMQ      │
└────────┬────────────────┘
         │
         ▼
┌─────────────────────────┐
│ Worker ejecuta código   │
│ en contenedor Docker    │
└────────┬────────────────┘
         │
         ▼
┌──────────────────────────────┐
│ mod_codesandbox/callback.php │
│ - Actualiza registro attempt│
│ - Actualiza tablas bloque✨ │
│   • block_codesandbox_snap  │
│   • block_codesandbox_hist  │
└──────────┬──────────────────┘
         │
         ▼
┌─────────────────────┐
│ Página se recarga   │
│ Bloque muestra datos│
└─────────────────────┘
```

### Tablas de Base de Datos

El bloque crea tres tablas:

#### `block_codesandbox_notes`
Almacena las notas del scratchpad por usuario por actividad.

| Campo         | Tipo    | Descripción                    |
|---------------|---------|--------------------------------|
| id            | int     | Clave primaria                 |
| userid        | int     | ID del usuario                 |
| cmid          | int     | ID del módulo de curso         |
| note_text     | text    | Contenido del scratchpad       |
| timemodified  | int     | Timestamp última modificación  |

**Índice único**: `(userid, cmid)`

#### `block_codesandbox_snap`
Almacena el snapshot de ejecución más reciente por usuario por actividad.

| Campo         | Tipo    | Descripción                    |
|---------------|---------|--------------------------------|
| id            | int     | Clave primaria                 |
| userid        | int     | ID del usuario                 |
| cmid          | int     | ID del módulo de curso         |
| code_preview  | text    | Primeras líneas del código     |
| status        | char(20)| Estado de ejecución (success/error)|
| runtime_ms    | int     | Tiempo de ejecución en ms      |
| timestamp     | int     | Timestamp de ejecución         |

**Índice único**: `(userid, cmid)`

#### `block_codesandbox_hist`
Almacena el historial de ejecuciones (últimas 5 por usuario por actividad).

| Campo         | Tipo    | Descripción                    |
|---------------|---------|--------------------------------|
| id            | int     | Clave primaria                 |
| userid        | int     | ID del usuario                 |
| cmid          | int     | ID del módulo de curso         |
| status        | char(20)| Estado de ejecución            |
| timestamp     | int     | Timestamp de ejecución         |
| runtime_ms    | int     | Tiempo de ejecución en ms      |

**Índice**: `(userid, cmid, timestamp)`

## Instalación

1. **Copiar archivos** a `blocks/codesandbox/`

2. **Instalar dependencias** (ya incluidas en mod_codesandbox):
   - Requiere `mod_codesandbox` instalado
   - RabbitMQ con php-amqplib

3. **Ejecutar actualización de Moodle**:
   ```bash
   php admin/cli/upgrade.php
   ```

4. **Agregar bloque al curso/actividad**:
   - Navegar a una actividad Code Sandbox
   - Activar edición
   - Agregar el bloque "CodeSandbox Monitor"
   - El bloque detectará automáticamente el contexto de la actividad

## Requisitos

- **Moodle**: 4.1 o superior
- **mod_codesandbox**: v1.0-alpha o superior (dependencia)
- **PHP**: 7.4+
- **RabbitMQ**: Configurado y ejecutándose (vía mod_codesandbox)

## Detección de Contexto

El bloque detecta inteligentemente su contexto:

- ✅ **En actividad Code Sandbox**: Muestra interfaz completa con datos de ejecución
- ℹ️ **Fuera de actividad**: Muestra un mensaje de ayuda
- 🔒 **Aislamiento de datos**: Cada instancia de actividad tiene datos separados (por `cmid`)

## Configuración

No se necesita configuración adicional. El bloque automáticamente:
- Detecta el contexto de la actividad actual
- Carga datos específicos del usuario conectado
- Se conecta a la misma instancia de RabbitMQ que mod_codesandbox

## Detalles Técnicos

### Mecanismo de Auto-guardado
El scratchpad usa JavaScript AMD (`amd/src/notes.js`) con:
- Debounce de 1 segundo al escribir
- Llamadas AJAX al servicio web `block_codesandbox_save_notes`
- Retroalimentación visual ("Escribiendo...", "Guardado")

### Actualizaciones en Tiempo Real
Cuando se completa la ejecución de código:
1. `mod_codesandbox/callback.php` recibe resultados del worker
2. Actualiza `codesandbox_attempts` (tabla del módulo)
3. **Actualiza tablas del bloque** (snapshot + historial)
4. Frontend recarga la página automáticamente (delay 800ms)
5. Bloque muestra datos actualizados

### Formatos Aplicables
El bloque puede agregarse a:
- Páginas de curso (`course-view`)
- Páginas de actividad Code Sandbox (`mod-codesandbox-view`)

## Soporte de Idiomas

- Inglés (`lang/en/`)
- Español (`lang/es/`)

## Historial de Versiones

- **1.0.0** (2026-02-13)
  - Lanzamiento inicial
  - Monitoreo de ejecución en tiempo real
  - Scratchpad persistente
  - Integración con RabbitMQ
  - Auto-recarga al completar

## Licencia

Igual que Moodle (GPL v3)

## Autores

Desarrollado para el ecosistema Moodle Code Sandbox.

---

Para más información sobre el módulo de actividad principal, ver `mod/codesandbox/README.md`.
