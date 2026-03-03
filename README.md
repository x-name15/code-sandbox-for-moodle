# 📦 Moodle CodeSandbox - Sistema Completo

> ## ⚠️ **Disclaimer**
> 
> Esto es un experimento personal que hice por aburrimiento para ver hasta dónde llegaba. No es un producto comercial. Úsalo bajo tu propio riesgo en ambientes de desarrollo/prueba.

---

## 🎯 ¿Qué es esto?

**CodeSandbox** es un sistema completo que transforma Moodle en un entorno de desarrollo integrado (IDE) para enseñar programación. Permite a estudiantes escribir, ejecutar y entregar código en tiempo real, mientras los profesores revisan y califican las entregas.

### Lo que hace diferente a esto:
- **Ejecuta código REAL** en contenedores Docker aislados (no es solo un editor de texto)
- **Arquitectura asíncrona** con colas de mensajes (RabbitMQ) para manejar cientos de ejecuciones simultáneas
- **Feedback inmediato** con resultados de ejecución (stdout/stderr)
- **Calificación automática** mediante casos de prueba input/output
- **Monitoreo en tiempo real** con historial de ejecuciones

## 📦 Componentes del Proyecto

Este repositorio contiene **3 componentes principales** que trabajan juntos:

### 1️⃣ **mod_codesandbox** - Actividad Principal
Plugin de actividad para Moodle que proporciona:
- Editor Monaco (VS Code en el navegador)
- Gestión de entregas y estados (draft/submitted/graded)
- Sistema de calificación automática (test cases)
- Panel de revisión para profesores
- Soporte multi-lenguaje (Python, JavaScript, Java, C++, etc.)

📂 **Directorio:** `mod_codesandbox/`  
📖 **Docs:** Ver [mod_codesandbox/docs/README.md](mod_codesandbox/docs/README.md)

### 2️⃣ **block_codesandbox** - Panel de Monitoreo
Bloque lateral complementario que muestra:
- 📸 **Snapshot del último run** (código, estado, tiempo de ejecución)
- 📊 **Historial de ejecuciones** (últimas 5 con indicadores de éxito/fallo)
- 📝 **Scratchpad persistente** (notas con autoguardado por actividad)

📂 **Directorio:** `block_codesandbox/`  
📖 **Docs:** Ver [block_codesandbox/docs/README.md](block_codesandbox/docs/README.md)

### 3️⃣ **the-judgeman-microservice** - Worker de Ejecución
Microservicio Python que:
- Consume trabajos de RabbitMQ
- Ejecuta código en contenedores Docker efímeros
- Aplica límites de seguridad (timeout, memoria, CPU)
- Devuelve resultados a Moodle vía callback HTTP

📂 **Directorio:** `the-judgeman-microservice/`  
📖 **Docs:** Ver [the-judgeman-microservice/README.md](the-judgeman-microservice/README.md)

---

## 🚀 Instalación Rápida

### Prerequisitos
- Moodle 3.9+ (probado en 4.x)
- Docker y Docker Compose
- RabbitMQ (puede ejecutarse vía Docker)
- PHP 7.4+ con extensión `php-amqplib`

### Paso a Paso

1. **Instalar los plugins de Moodle**
```bash
cd /var/www/html/moodle

# Módulo de actividad
cp -r mod_codesandbox mod/codesandbox

# Bloque de monitoreo
cp -r block_codesandbox blocks/codesandbox

# Actualizar BD desde interfaz de Moodle
# Admin -> Notificaciones -> Upgrade
```

2. **Configurar RabbitMQ**
```bash
docker run -d --name rabbitmq \
  -p 5672:5672 -p 15672:15672 \
  rabbitmq:3-management
```

3. **Desplegar el Worker**
```bash
cd the-judgeman-microservice
cp .env.example .env
# Editar .env con las credenciales de RabbitMQ y Moodle
docker-compose up -d
```

4. **Configurar el plugin en Moodle**
- Ir a: Administración del sitio → Plugins → Actividades → Code Sandbox
- Configurar:
  - RabbitMQ host/port/credentials
  - Callback token (secreto compartido con el worker)
  - Lenguajes permitidos

Ver documentación detallada en cada componente.

---

## ✨ Características Destacadas

### Para Estudiantes
- ✅ **Editor profesional** (Monaco - mismo que VS Code)
- ✅ **Sintaxis highlighting** para múltiples lenguajes
- ✅ **Ejecución instantánea** con feedback en tiempo real
- ✅ **Historial de runs** visible en el sidebar
- ✅ **Scratchpad persistente** para tomar notas
- ✅ **Modo solo-lectura** después de entregar

### Para Profesores
- ✅ **Dashboard de entregas** con filtros y búsqueda
- ✅ **Revisión de código** con resultados de ejecución
- ✅ **Calificación automática** mediante test cases
- ✅ **Calificación manual** opcional con comentarios
- ✅ **Estadísticas de ejecuciones** por estudiante
- ✅ **Exportación de entregas** (código + resultados)

### Técnicas/Seguridad
- ✅ **Arquitectura asíncrona** con message queue
- ✅ **Contenedores efímeros** (se destruyen tras cada ejecución)
- ✅ **Límites de recursos** (CPU, memoria, timeout)
- ✅ **Aislamiento de red** (sin acceso a internet desde contenedores)
- ✅ **Usuario non-root** en contenedores
- ✅ **Token-based callbacks** (webhook seguro)

---

## 🏗️ Arquitectura del Sistema

El sistema utiliza una arquitectura **desacoplada y asíncrona** para garantizar la seguridad del servidor Moodle y la escalabilidad ante cientos de ejecuciones simultáneas.

### Diagrama de Flujo de Datos
`[Estudiante (Navegador)]` -> `[Moodle (Plugin)]` -> `[RabbitMQ (Cola)]` -> `[Worker (Consumer)]` -> `[Docker (Sandbox)]`

### 🧩 Componentes Principales

#### 1. Frontend (Moodle Plugin)
* **Tecnología:** PHP (Moodle API), JavaScript (AMD Modules), Monaco Editor.
* **Responsabilidad:** * Renderizar el editor de código (VS Code style).
    * Gestionar estados (`draft`, `submitted`, `graded`).
    * Enviar peticiones de ejecución a la cola.
    * Recibir los resultados via Webhook/Callback.

#### 2. Message Broker (RabbitMQ)
* **Rol:** Buffer de peticiones.
* **Responsabilidad:** Desacoplar la petición web de la ejecución pesada. Si 500 alumnos envían código a la vez, RabbitMQ los encola y los entrega ordenadamente a los Workers, evitando que el servidor web de Moodle colapse.

#### 3. The Worker (El Ejecutor)
* **Tecnología:** Python / Node.js (Daemon).
* **Responsabilidad:** * Escuchar la cola `moodle_code_jobs`.
    * Orquestar el ciclo de vida de los contenedores Docker.
    * **Seguridad:** Aplicar límites de tiempo (timeout) y memoria.
    * Reportar el resultado de vuelta a Moodle.

#### 4. The Sandbox (Docker)
* **Rol:** Entorno de ejecución efímero.
* **Funcionamiento:**
    * Se crea un contenedor **nuevo** por cada ejecución (`docker run`).
    * Se inyecta el código del alumno.
    * Se ejecuta el script.
    * Se captura `STDOUT` y `STDERR`.
    * El contenedor se destruye inmediatamente (`--rm`).
    * **Aislamiento:** Sin acceso a red (opcional), usuario sin privilegios (non-root), sistema de archivos de solo lectura.

---

## 🔄 Flujo de Ejecución Detallado (The Lifecycle)

1.  **Submit/Run:** El usuario pulsa "Ejecutar". El JS envía el código a `run.php`.
2.  **Encolado:** Moodle crea un registro en la tabla `mdl_codesandbox_attempts` con estado `pending` y un `job_id` único. Envía el payload a RabbitMQ.
3.  **Consumo:** El Worker detecta un nuevo mensaje.
4.  **Aislamiento:** El Worker instancia un contenedor Docker específico para el lenguaje (ej: `python:3.9-alpine`).
5.  **Ejecución:** El código corre dentro del contenedor.
    * *Si tarda > 5s:* El Worker mata el contenedor (Timeout).
    * *Si usa mucha RAM:* Docker lo mata (OOM Kill).
6.  **Callback:** El Worker envía el resultado (JSON) a Moodle mediante una API interna (`callback.php`).
7.  **Feedback:** Moodle actualiza la BD y notifica al usuario (vía WebSocket o Polling AJAX) que su resultado está listo.

---

## 🛡️ Medidas de Seguridad

Dado que permitimos ejecutar código arbitrario, la seguridad es la prioridad #1:

* **Contenedores Efímeros:** Ningún estado se guarda. Cada ejecución es "limpia".
* **Usuario Non-Root:** El código del alumno corre como usuario `nobody` o `sandbox` dentro del contenedor.
* **Límites de Recursos:** Flags de Docker `--memory="128m" --cpus="0.5"`.
* **Timeouts:** Hard limit de ejecución (ej. 5 segundos) para evitar bucles infinitos (`while True: pass`).
* **Network Ban:** Los contenedores se lanzan con `--network none` para evitar ataques a la red local o descargas maliciosas.

---

## 🛠️ Stack Tecnológico Recomendado para el Backend
* **Broker:** RabbitMQ (imagen oficial `rabbitmq:3-management`).
* **Worker:** Python 3 con librería `pika` (cliente RabbitMQ) y `docker` (SDK Docker).
* **Contenedores:** Alpine Linux (ligero y rápido).




## 🧠 Sistema de Evaluación Automática (I/O Matching)

El plugin implementa una estrategia de calificación de **Nivel 2: Comparación de Entrada/Salida (Black Box Testing)**. Esta metodología evalúa el comportamiento del código sin analizar su sintaxis interna, garantizando objetividad y soporte para múltiples lenguajes.

### ⚙️ Lógica de Calificación
El profesor define una serie de **Casos de Prueba (Test Cases)**. Cada caso consta de:
* **Input (stdin):** Datos que se inyectarán al programa (ej: números, textos).
* **Expected Output (stdout):** La respuesta exacta que debe imprimir el programa.

### 🔄 Flujo Técnico de Evaluación

1.  **Preparación (Moodle):**
    * El profesor crea la actividad y añade 3 casos de prueba.
    * Al enviar la tarea, Moodle construye un payload JSON que incluye:
        * `source_code`: El script del alumno.
        * `test_cases`: `[{ "in": "2 2", "out": "4" }, { "in": "10 5", "out": "15" }]`.

2.  **Ejecución Iterativa (Worker):**
    * El Worker recibe el trabajo y levanta el contenedor Docker.
    * **Bucle de Pruebas:** Por cada caso de prueba, el Worker ejecuta el código inyectando el `input` a través del flujo de entrada estándar (`stdin`).
    * **Captura:** Se captura el `stdout` generado por el contenedor.

3.  **Validación y Puntuación:**
    * **Normalización:** Se limpian espacios en blanco y saltos de línea extra (`trim()`).
    * **Comparación:** Se compara `Output Real === Output Esperado`.
    * **Cálculo:** Si el alumno pasa 2 de 3 tests, el Worker calcula: `Score = (2/3) * 100 = 66.6%`.

4.  **Feedback (Moodle):**
    * El Worker devuelve a Moodle el puntaje final y el detalle de cada test (cuáles pasaron y cuáles fallaron).
    * Moodle actualiza automáticamente el **Libro de Calificaciones (Gradebook)** con la nota calculada.

### 📊 Ejemplo de Payload (RabbitMQ)
```json
{
  "job_id": "abc-123",
  "language": "python",
  "code": "a = int(input())\nb = int(input())\nprint(a + b)",
  "test_cases": [
    { "id": 1, "input": "5\n5", "expected_output": "10" },
    { "id": 2, "input": "-1\n1", "expected_output": "0" }
  ]
```
---

## 📁 Estructura del Repositorio

```
codesandbox/
├── mod_codesandbox/          # Módulo de actividad principal
│   ├── view.php              # Vista del estudiante
│   ├── run.php               # Endpoint ejecución
│   ├── callback.php          # Receptor de resultados
│   ├── report.php            # Dashboard profesor
│   ├── amd/src/              # JavaScript (AMD modules)
│   ├── classes/              # PHP Classes
│   │   ├── grader.php        # Lógica de calificación
│   │   └── rabbitmq_client.php
│   ├── db/                   # Database schema
│   ├── lang/                 # Strings i18n
│   └── vendor/               # Dependencies (Composer)
│
├── block_codesandbox/        # Bloque lateral de monitoreo
│   ├── block_codesandbox.php # Lógica del bloque
│   ├── externallib.php       # Web services
│   ├── amd/src/notes.js      # Scratchpad logic
│   ├── db/                   # DB tables (snapshot, history, notes)
│   └── lang/                 # Translations
│
└── the-judgeman-microservice/ # Worker de ejecución
    ├── src/
    │   ├── main.py           # Entry point
    │   ├── worker.py         # RabbitMQ consumer
    │   ├── docker_manager.py # Docker orchestration
    │   ├── callback_handler.py
    │   └── config.py
    ├── config.json           # Language configs
    ├── docker-compose.yml    # Multi-container setup
    └── requirements.txt      # Python dependencies
```

---

## 🛠️ Stack Tecnológico

| Componente | Tecnología | Propósito |
|------------|-----------|-----------|
| **Frontend** | Monaco Editor (TypeScript) | Editor de código tipo VS Code |
| **Backend** | PHP 7.4+ (Moodle API) | Lógica de negocio y BD |
| **Message Queue** | RabbitMQ 3.x | Cola de trabajos asíncrona |
| **Worker** | Python 3.9+ (pika, docker-py) | Consumidor y executor |
| **Sandbox** | Docker 20.10+ | Contenedores efímeros Linux |
| **Base de Datos** | PostgreSQL / MySQL | Storage de Moodle |
| **Comunicación** | HTTP (webhooks) | Callbacks worker→Moodle |

---

## 🧪 Casos de Uso

### Caso 1: Clase de Introducción a Python
Un profesor crea una actividad "Suma de dos números" con 5 test cases. 100 estudiantes entregan código simultáneamente. RabbitMQ encola las ejecuciones, el worker procesa cada una en ~2 segundos, y todos reciben su calificación automática sin saturar el servidor Moodle.

### Caso 2: Examen de Algoritmos en Vivo
Examen con 3 problemas de programación. Los estudiantes tienen 90 minutos. El sistema permite múltiples intentos (runs) pero solo la entrega final cuenta para la nota. El profesor revisa después el código de cada estudiante con el historial completo de ejecuciones.

### Caso 3: Taller de JavaScript
Actividad de práctica sin calificación automática. Los estudiantes usan el editor para experimentar con código JavaScript. El bloque lateral muestra su historial de runs para que vean su progreso. El profesor puede revisar manualmente y dar retroalimentación.

---

## 🤝 Contribuciones

Este es un proyecto personal experimental. Si encuentras bugs o quieres mejorarlo:
1. Fork el repo
2. Crea una branch con tu feature
3. Haz un PR


---

## 📝 Licencia y Créditos

**Licencia:** MIT (úsalo como quieras, pero sin garantías)  
**Autor:** Mr Jacket
**Estado:** Experimento funcional, no producto terminado  

**Librerías usadas:**
- Monaco Editor (Microsoft)
- php-amqplib (Videla/Alvarez)
- Docker SDK for Python
- RabbitMQ (Pivotal/VMware)

---

**⚠️ Recordatorio final:** Esto es un proyecto experimental. Úsalo bajo tu propio riesgo.




