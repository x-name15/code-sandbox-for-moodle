FROM python:3.9-slim

# Evita que Python genere archivos .pyc y bufferee el output
ENV PYTHONDONTWRITEBYTECODE=1
ENV PYTHONUNBUFFERED=1

WORKDIR /app

# Instalar dependencias
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copiar código fuente
COPY config.json .
COPY src/ ./src/
COPY main.py .

# Ejecutar
CMD ["python", "main.py"]