# The JudgeMan Microservice 🔒

> **⚠️ Disclaimer:** Proyecto experimental que ejecuta código arbitrario en Docker. Úsalo solo en ambientes de desarrollo/prueba.

Microservicio de ejecución segura de código en sandbox para integración con Moodle y otras plataformas educativas.

## 🎯 Características

- **Ejecución Segura**: Código ejecutado en contenedores Docker aislados
- **Múltiples Lenguajes**: Python, JavaScript, C++ (extensible)
- **Límites de Recursos**: Memoria, CPU, PIDs, timeout configurables
- **Cola de Mensajes**: Procesamiento asíncrono con RabbitMQ
- **Callbacks HTTP**: Notificaciones de resultados a sistemas externos
- **Arquitectura Modular**: Diseño profesional con separación de responsabilidades

## 🏗️ Arquitectura

```
┌─────────────┐      ┌──────────────┐      ┌─────────────────┐
│   Moodle    │─────▶│  RabbitMQ    │◀─────│  JudgeMan       │
│  (Cliente)  │      │  (Cola)      │      │  (Worker)       │
└─────────────┘      └──────────────┘      └────────┬────────┘
                                                     │
                                                     ▼
                                            ┌─────────────────┐
                                            │ Docker Sandboxes│
                                            │  (python)       │
                                            │  (node)         │
                                            │  (gcc)          │
                                            └─────────────────┘
```

### Estructura del Proyecto

```
the-judgeman-microservice/
├── src/
│   ├── __init__.py           # Package initialization
│   ├── config.py             # Configuration management
│   ├── docker_manager.py     # Docker sandbox orchestration
│   ├── callback_handler.py   # HTTP callbacks
│   ├── worker.py             # RabbitMQ consumer
│   └── main.py               # Application entry point
├── main.py                   # Root entry point
├── config.json               # Language configurations
├── docker-compose.yml        # Multi-container setup
├── Dockerfile                # Worker container
├── requirements.txt          # Python dependencies
├── .env.example              # Environment template
└── test.py                   # Test client
```

## 🚀 Inicio Rápido

### Prerequisitos

- Docker >= 20.10
- Docker Compose >= 2.0

### Instalación

1. **Clonar el repositorio**
```bash
git clone <repository-url>
cd the-judgeman-microservice
```

2. **Configurar variables de entorno**
```bash
cp .env.example .env
# Editar .env según necesidades
```

3. **Iniciar servicios**
```bash
docker-compose up --build
```

4. **Verificar funcionamiento**
```bash
python test.py
```

## ⚙️ Configuración

### Variables de Entorno (.env)

```bash
# RabbitMQ
RABBIT_HOST=rabbitmq
RABBIT_PORT=5672
RABBIT_USER=guest
RABBIT_PASS=guest
RABBIT_QUEUE_NAME=moodle_code_jobs

# Docker
SHARED_VOLUME_NAME=judgeman_data
SHARED_MOUNT_PATH=/shared_data

# Resource Limits
MEMORY_LIMIT=128m
CPU_QUOTA=50000
PIDS_LIMIT=50
TIMEOUT_SECONDS=5
MAX_OUTPUT_SIZE=10000

# Application
LOG_LEVEL=INFO
ENVIRONMENT=production
```

### Lenguajes Soportados (config.json)

```json
{
  "languages": {
    "python": {
      "image": "python:3.9-alpine",
      "filename": "main.py",
      "command": "python /jail/main.py"
    },
    "javascript": {
      "image": "node:18-alpine",
      "filename": "main.js",
      "command": "node /jail/main.js"
    },
    "cpp": {
      "image": "frolvlad/alpine-gxx",
      "filename": "main.cpp",
      "command": "g++ -o /tmp/out /jail/main.cpp && /tmp/out"
    }
  }
}
```

## 📡 Formato de Mensajes

### Job Request (RabbitMQ)

```json
{
  "language": "python",
  "code": "print('Hello, World!')",
  "callback_url": "http://moodle.example.com/api/judge/callback",
  "job_id": "unique-job-id-123",
  "input": "optional input data"
}
```

### Callback Response (HTTP POST)

```json
{
  "job_id": "unique-job-id-123",
  "success": true,
  "exit_code": 0,
  "output": "Hello, World!\n",
  "error": "",
  "execution_time": 0.234
}
```

## 🧪 Testing

### Enviar Jobs de Prueba

```bash
python test.py
```

### Ejemplo Programático

```python
import pika
import json

connection = pika.BlockingConnection(
    pika.ConnectionParameters('localhost')
)
channel = connection.channel()
channel.queue_declare(queue='moodle_code_jobs')

job = {
    "language": "python",
    "code": "print('Hello from Python!')",
    "callback_url": "http://localhost:4000/callback",
    "job_id": "test-001"
}

channel.basic_publish(
    exchange='',
    routing_key='moodle_code_jobs',
    body=json.dumps(job)
)

print(f"Job {job['job_id']} sent!")
connection.close()
```

## 🔒 Seguridad

### Medidas Implementadas

- ✅ **Aislamiento por Contenedor**: Cada ejecución en sandbox separado
- ✅ **Límites de Recursos**: Memoria, CPU, procesos controlados
- ✅ **Timeouts**: Ejecuciones limitadas en tiempo
- ✅ **Network Isolation**: Sin acceso a red desde sandboxes
- ✅ **Read-Only Filesystem**: Archivos de código en modo lectura
- ✅ **User Namespaces**: Ejecución sin privilegios

### Recomendaciones

- No exponer RabbitMQ públicamente
- Usar credenciales fuertes en producción
- Implementar autenticación en callbacks
- Monitorear uso de recursos
- Mantener imágenes Docker actualizadas

## 📊 Monitoreo

### Logs

```bash
# Ver logs del worker
docker-compose logs -f worker

# Ver logs de RabbitMQ
docker-compose logs -f rabbitmq
```

### RabbitMQ Management UI

```
URL: http://localhost:15672
User: guest
Pass: guest
```

## 🛠️ Desarrollo

### Estructura Modular

- **config.py**: Gestión de configuración y variables de entorno
- **docker_manager.py**: Orquestación de contenedores Docker
- **callback_handler.py**: Envío de resultados HTTP
- **worker.py**: Consumidor de RabbitMQ y lógica principal
- **main.py**: Punto de entrada con manejo de señales

### Agregar Nuevo Lenguaje

1. Editar `config.json`:
```json
"rust": {
  "image": "rust:alpine",
  "filename": "main.rs",
  "command": "rustc /jail/main.rs -o /tmp/out && /tmp/out"
}
```

2. Reiniciar worker:
```bash
docker-compose restart worker
```

### Testing Local (sin Docker)

```bash
# Crear virtual environment
python -m venv venv
source venv/bin/activate  # Linux/Mac
# o
venv\Scripts\activate     # Windows

# Instalar dependencias
pip install -r requirements.txt

# Configurar .env
cp .env.example .env

# Ejecutar
python main.py
```

## 🐛 Troubleshooting

### Worker no conecta a RabbitMQ

```bash
# Verificar que RabbitMQ esté corriendo
docker-compose ps

# Revisar logs
docker-compose logs rabbitmq
```

### Sandboxes no ejecutan

```bash
# Verificar socket Docker montado
docker exec -it judgeman-worker ls -la /var/run/docker.sock

# Verificar volumen compartido
docker volume inspect judgeman_data
```

### Callbacks fallan

```bash
# Verificar host.docker.internal
docker exec -it judgeman-worker ping host.docker.internal

# Revisar extra_hosts en docker-compose.yml
```

## 📝 Licencia

MIT License (úsalo como quieras, pero sin garantías)

## 🎓 Sobre este Proyecto

> **RECORDATORIO FINAL:** Este microservicio es parte de un experimento personal que hice por aburrimiento para ver si era capaz de construir un sistema de ejecución segura de código.
>
> **Lo desarrollé para aprender, no para vender.** Funciona, pero es experimental.
> 
> Si decides usarlo:
> - ✅ Revísalo bien antes de implementarlo
> - ✅ Úsalo en ambientes de desarrollo/prueba  
> - ❌ No lo pongas en producción sin auditoría de seguridad
> - ❌ No esperes soporte tipo empresa
>
> **Es un experimento funcional, no un producto terminado.**

---

**Estado:** Experimento personal - Funcional pero no production-ready  
**Creado por:** Un desarrollador curioso que quería ver hasta dónde llegaba
