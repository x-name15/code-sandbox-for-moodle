# Code Sandbox para Moodle

> **⚠️ Disclaimer:** Proyecto experimental hecho por curiosidad. Úsalo bajo tu propio riesgo.

**Un entorno de ejecución de código seguro y escalable diseñado para la educación en programación moderna.**

## 🎯 Descripción General

Code Sandbox es un módulo de actividad para Moodle que transforma tu LMS en un entorno de desarrollo profesional para estudiantes y una plataforma eficiente de revisión de código para instructores. Piensa en él como un híbrido entre una tarea de programación y un desafío de código interactivo: los estudiantes escriben, prueban y entregan código directamente en el navegador, mientras que los instructores pueden revisar los resultados de la ejecución real del código y asignar calificaciones.

## 🏗️ Arquitectura

Este módulo es parte de una **arquitectura de microservicios desacoplada** diseñada para seguridad y escalabilidad:

```
┌─────────────────────┐
│   Moodle (este mod) │  ← El estudiante escribe código en el Editor Monaco
└──────────┬──────────┘
           │
           ▼
    ┌──────────────┐
    │   RabbitMQ   │  ← Cola de trabajos (procesamiento asíncrono)
    └──────┬───────┘
           │
           ▼
    ┌────────────────┐
    │  Worker Process│  ← Ejecuta código en contenedores Docker aislados
    │ (judgeman.py)  │
    └────────┬───────┘
             │
             ▼
    ┌──────────────────┐
    │ Callback Moodle  │  ← Los resultados se envían de vuelta para actualizar los intentos
    └──────────────────┘
```

### Componentes Clave:

1. **Plugin de Moodle (este repositorio)**: Gestiona la experiencia del estudiante, almacena intentos y maneja las calificaciones.
2. **RabbitMQ**: Asegura la escalabilidad al encolar las solicitudes de ejecución de código.
3. **Servicio Worker**: Servicio externo en Python que lee de la cola, genera contenedores Docker y ejecuta el código del estudiante.
4. **Contenedores Docker**: Sandboxes aislados y seguros donde el código no confiable se ejecuta de forma segura.
5. **Bloque CodeSandbox** (`/blocks/codesandbox`): Bloque lateral complementario que muestra monitoreo de ejecución en tiempo real y notas de scratchpad persistentes.

## ✨ Características

### Para Estudiantes:
- **Editor de Código Profesional**: Impulsado por Monaco Editor (el mismo motor detrás de VS Code)
- **Múltiples Lenguajes de Programación**: Python, JavaScript, Java, C++, y más
- **Retroalimentación Instantánea**: Prueba el código antes de la entrega final
- **Modo Solo Lectura**: Después de la entrega, el código se vuelve de solo lectura para garantizar la integridad

### Para Instructores:
- **Panel de Entregas**: Revisa todas las entregas de estudiantes con marcas de tiempo
- **Revisión de Código en Vivo**: Ve exactamente lo que escribieron los estudiantes y los resultados de ejecución
- **Calificación Integrada**: Las calificaciones se sincronizan directamente con el libro de calificaciones de Moodle
- **Información de Ejecución**: Visualiza stdout, stderr, códigos de salida y tiempo de ejecución

### Seguridad y Rendimiento:
- **Ejecución Aislada**: El código se ejecuta en contenedores Docker efímeros, no en el servidor principal
- **Procesamiento Basado en Colas**: RabbitMQ previene la sobrecarga del servidor en momentos de pico (ej. exámenes)
- **Callbacks Basados en Tokens**: Comunicación segura entre el worker y Moodle
- **Límites de Recursos**: Límites configurables de CPU/memoria por ejecución

## � Integración con block_codesandbox

El módulo de actividad trabaja perfectamente con el plugin complementario **Bloque CodeSandbox**:

### Cómo se Conectan

Cuando se completa la ejecución de código, el endpoint de callback (`callback.php`) actualiza **ambos**:
- **Tablas del módulo** (`codesandbox_attempts`) - Almacena resultados completos de ejecución
- **Tablas del bloque** (`block_codesandbox_snap`, `block_codesandbox_hist`) - Proporciona datos de monitoreo en tiempo real

### Lo que Proporciona el Bloque

1. **📸 Snapshot del Último Run**: Vista previa de la ejecución de código más reciente con estado y tiempo de ejecución
2. **📊 Historial de Ejecuciones**: Línea de tiempo de las últimas 5 ejecuciones con indicadores de éxito/fallo  
3. **📝 Scratchpad Persistente**: Bloc de notas con autoguardado vinculado a cada instancia de actividad

### Flujo de Datos

```
[Estudiante hace clic en "Run"] 
    ↓
[mod_codesandbox/run.php crea intento & publica a RabbitMQ]
    ↓
[Worker ejecuta código en contenedor Docker]
    ↓
[Worker envía POST con resultados a callback.php]
    ↓
[callback.php actualiza:]
    • codesandbox_attempts (datos del módulo)
    • block_codesandbox_snap (snapshot para el bloque)
    • block_codesandbox_hist (entrada de historial para el bloque)
    ↓
[Frontend recarga automáticamente la página después de 800ms]
    ↓
[Bloque muestra snapshot + historial actualizados]
```

### Instalación

El bloque es **opcional** pero recomendado. Para instalarlo:
```bash
cd /ruta/a/moodle/blocks
cp -r block_codesandbox codesandbox
php admin/cli/upgrade.php
```

Luego agrega el bloque a cualquier página de actividad Code Sandbox.

Para documentación detallada del bloque, ver `/blocks/codesandbox/README.es.md`

## 📦 Instalación

### Requisitos Previos

1. **Moodle 4.0+** (probado en Moodle 4.0-4.5)
2. **PHP 7.4+** con extensiones `php-bcmath` y `php-xml`
3. **Composer** (para dependencias de PHP)
4. **Servidor RabbitMQ** (en ejecución y accesible)
5. **Servicio Worker** (despliegue separado, ver [Configuración del Worker](#configuración-del-worker))
6. **block_codesandbox** (opcional, recomendado para UI mejorada)

### Pasos

1. **Clonar o descargar** este repositorio en tu instalación de Moodle:
   ```bash
   cd /ruta/a/moodle/mod
   git clone <url-del-repositorio> codesandbox
   ```

2. **Instalar las dependencias de PHP**:
   ```bash
   cd codesandbox
   composer install --no-dev
   ```

3. **Instalar el plugin** a través de la interfaz web de Moodle:
   - Navega a **Administración del sitio → Notificaciones**
   - Sigue las instrucciones de actualización

4. **Configurar los ajustes de RabbitMQ**:
   - Ve a **Administración del sitio → Plugins → Módulos de actividad → Code Sandbox**
   - Configura:
     - Host de RabbitMQ (ej. `localhost` o `rabbitmq.example.com`)
     - Puerto de RabbitMQ (por defecto: `5672`)
     - Usuario de RabbitMQ (ej. `moodle`)
     - Contraseña de RabbitMQ
     - Nombre de la Cola (ej. `moodle_code_jobs`)

5. **Desplegar el Servicio Worker** (ver siguiente sección)

## 🔧 Configuración del Worker

El worker es un **servicio Python separado** que ejecuta el código del estudiante. No está incluido en este repositorio.

**Lo que necesitas:**

- Un servidor con Docker instalado
- Python 3.8+ con `pika` (biblioteca cliente de RabbitMQ)
- Acceso de red tanto a RabbitMQ como al endpoint de callback de Moodle

**Cómo funciona:**

1. El worker escucha la cola de RabbitMQ (`moodle_code_jobs`)
2. Cuando llega un trabajo:
   - Descarga la imagen Docker apropiada (ej. `python:3.11-slim`)
   - Ejecuta el código del estudiante dentro del contenedor con límites de recursos
   - Captura stdout, stderr y código de salida
   - Envía los resultados de vuelta a Moodle vía `/mod/codesandbox/callback.php`

**Ejemplo de Arquitectura del Worker:**
```bash
# Demonio worker (ej. judgeman.py)
while true:
    job = rabbitmq.consume()
    result = docker.run(job.language, job.code, limits)
    http.post(job.callback_url, result, token=job.callback_token)
```

Para una implementación completa del worker, consulta el repositorio separado del worker o contacta a los mantenedores.

## 🚀 Uso

### Crear una Actividad Code Sandbox

1. Activa el modo de edición en tu curso de Moodle
2. Haz clic en **Añadir una actividad o recurso → Code Sandbox**
3. Configura:
   - **Nombre**: ej. "Tarea de Python 1"
   - **Lenguaje**: Selecciona el lenguaje de programación
   - **Calificación Máxima**: Establece el valor del libro de calificaciones
   - **Fecha de Entrega**: Fecha límite opcional
   - **Instrucciones**: Proporciona contexto y requisitos

4. Guarda la actividad

### Flujo de Trabajo del Estudiante

1. Abre la actividad Code Sandbox
2. Escribe código en el editor Monaco
3. Haz clic en **Probar** para ejecutar el código (crea un intento borrador)
4. Ve la salida en el panel de terminal
5. Cuando esté satisfecho, haz clic en **Entregar** (bloquea el código)

### Flujo de Trabajo del Instructor

1. Abre la actividad Code Sandbox
2. Haz clic en **Ver Entregas de Estudiantes** (visible solo para instructores)
3. Revisa el código enviado y los resultados de ejecución
4. Asigna calificaciones directamente desde el panel de entregas
5. Las calificaciones se sincronizan automáticamente con el libro de calificaciones de Moodle

## 📊 Esquema de Base de Datos

### `mdl_codesandbox`
Instancias de actividad principales.

| Campo         | Tipo          | Descripción                               |
|---------------|---------------|-------------------------------------------|
| id            | BIGINT        | Clave primaria                            |
| course        | BIGINT        | ID del curso                              |
| name          | VARCHAR(255)  | Nombre de la actividad                    |
| intro         | TEXT          | Descripción de la actividad               |
| language      | VARCHAR(50)   | Lenguaje de programación (python, java…)  |
| grade         | DECIMAL(10,5) | Calificación máxima                       |
| duedate       | BIGINT        | Fecha de entrega (timestamp Unix)         |
| timecreated   | BIGINT        | Timestamp de creación                     |
| timemodified  | BIGINT        | Timestamp de última modificación          |

### `mdl_codesandbox_attempts`
Entregas de código de estudiantes y resultados de ejecución.

| Campo          | Tipo          | Descripción                                  |
|----------------|---------------|----------------------------------------------|
| id             | BIGINT        | Clave primaria                               |
| codesandboxid  | BIGINT        | Clave foránea a mdl_codesandbox              |
| userid         | BIGINT        | ID del usuario estudiante                    |
| code           | TEXT          | Código del estudiante                        |
| status         | VARCHAR(20)   | pending, completed, failed, submitted        |
| job_id         | VARCHAR(100)  | Identificador único de trabajo para RabbitMQ |
| stdout         | TEXT          | Salida estándar de la ejecución              |
| stderr         | TEXT          | Error estándar de la ejecución               |
| exitcode       | INT           | Código de salida del proceso                 |
| execution_time | DECIMAL(10,3) | Tiempo de ejecución en segundos              |
| timecreated    | BIGINT        | Timestamp de creación del intento            |
| timemodified   | BIGINT        | Timestamp de última actualización            |

## 🔐 Consideraciones de Seguridad

- **Sin ejecución directa de código en el servidor Moodle**: Todo el código se ejecuta en contenedores Docker aislados
- **Autenticación de callback basada en tokens**: El worker debe proporcionar un token HMAC válido
- **Límites de recursos**: Los contenedores Docker aplican restricciones de CPU, memoria y tiempo
- **Entregas de solo lectura**: Una vez entregado, el código del estudiante no puede ser modificado
- **Acceso basado en capacidades**: Profesores y estudiantes tienen permisos distintos

## 🛠️ Opciones de Configuración

| Ajuste               | Por Defecto       | Descripción                                    |
|----------------------|-------------------|------------------------------------------------|
| `rabbitmq_host`      | `localhost`       | Nombre del host del servidor RabbitMQ          |
| `rabbitmq_port`      | `5672`            | Puerto AMQP de RabbitMQ                        |
| `rabbitmq_user`      | `moodle`          | Nombre de usuario de RabbitMQ                  |
| `rabbitmq_pass`      | `securepass`      | Contraseña de RabbitMQ                         |
| `rabbitmq_queue`     | `moodle_code_jobs`| Nombre de la cola para trabajos de código      |

Accede a estos vía: **Administración del sitio → Plugins → Módulos de actividad → Code Sandbox**

## 🌐 Internacionalización

Este plugin soporta múltiples idiomas usando el sistema de paquetes de idioma de Moodle:

- **Inglés** (`lang/en/codesandbox.php`)
- **Español** (`lang/es/codesandbox.php`)

Para añadir más idiomas, crea un nuevo archivo de idioma en `lang/<código-idioma>/codesandbox.php` siguiendo la misma estructura.

## 📝 Hoja de Ruta de Desarrollo

- [x] **Fase 1 (MVP)**: Integración de editor Monaco, soporte multi-lenguaje, intentos borrador
- [x] **Fase 2 (Conectividad)**: Integración de RabbitMQ, procesamiento asíncrono de trabajos
- [x] **Fase 3 (Worker)**: Servicio worker externo con ejecución en Docker
- [x] **Fase 4 (Evaluación)**: Panel de instructor para revisar y calificar entregas
- [ ] **Fase 5 (Avanzado)**: Soporte de pruebas unitarias, casos de prueba personalizados, detección de plagio

## 🤝 Contribuir

¡Las contribuciones son bienvenidas! Por favor:

1. Haz un fork del repositorio
2. Crea una rama de característica (`git checkout -b feature/caracteristica-increible`)
3. Confirma tus cambios (`git commit -m 'Añadir característica increíble'`)
4. Sube a la rama (`git push origin feature/caracteristica-increible`)
5. Abre un Pull Request

## 📄 Licencia

Este proyecto está licenciado bajo **GNU GPL v3** (o posterior) para cumplir con los requisitos de licencia de Moodle.

## 🙏 Créditos

- **Monaco Editor**: Microsoft Corporation
- **php-amqplib**: Mantenido por la comunidad PHP AMQP
- **Moodle**: Martin Dougiamas y la comunidad Moodle

## 📧 Soporte

Para reportes de errores, solicitudes de características o preguntas generales:

- Abre un issue en GitHub
- Contacto: [tu-email@ejemplo.com]

---

**Hecho con ❤️ para la comunidad educativa**
