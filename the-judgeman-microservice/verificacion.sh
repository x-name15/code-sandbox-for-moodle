#!/bin/bash
# verificacion.sh - Script de verificación post-refactorización

set -e

echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║         JUDGEMAN v2.0 - VERIFICACIÓN DE INTEGRIDAD           ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

check_ok() {
    echo -e "${GREEN}✓${NC} $1"
}

check_fail() {
    echo -e "${RED}✗${NC} $1"
    exit 1
}

check_warn() {
    echo -e "${YELLOW}⚠${NC} $1"
}

# 1. Verificar estructura de archivos
echo "1. Verificando estructura de archivos..."
[ -f "main.py" ] && check_ok "main.py existe" || check_fail "main.py falta"
[ -f "src/__init__.py" ] && check_ok "src/__init__.py existe" || check_fail "src/__init__.py falta"
[ -f "src/config.py" ] && check_ok "src/config.py existe" || check_fail "src/config.py falta"
[ -f "src/docker_manager.py" ] && check_ok "src/docker_manager.py existe" || check_fail "src/docker_manager.py falta"
[ -f "src/callback_handler.py" ] && check_ok "src/callback_handler.py existe" || check_fail "src/callback_handler.py falta"
[ -f "src/worker.py" ] && check_ok "src/worker.py existe" || check_fail "src/worker.py falta"
[ -f "src/main.py" ] && check_ok "src/main.py existe" || check_fail "src/main.py falta"
echo ""

# 2. Verificar backup
echo "2. Verificando código original respaldado..."
if [ -f "judgeman.py.backup" ]; then
    check_ok "judgeman.py respaldado como judgeman.py.backup"
else
    check_warn "judgeman.py.backup no encontrado (puede ser normal)"
fi

if [ -f "judgeman.py" ]; then
    check_warn "judgeman.py todavía existe (debería ser .backup)"
fi
echo ""

# 3. Verificar .env
echo "3. Verificando configuración..."
if [ -f ".env" ]; then
    check_ok ".env existe"
    
    # Verificar variables críticas
    grep -q "RABBIT_HOST" .env && check_ok "RABBIT_HOST configurado" || check_fail "RABBIT_HOST falta"
    grep -q "SHARED_VOLUME_NAME" .env && check_ok "SHARED_VOLUME_NAME configurado" || check_fail "SHARED_VOLUME_NAME falta"
    grep -q "LOG_LEVEL" .env && check_ok "LOG_LEVEL configurado" || check_fail "LOG_LEVEL falta"
else
    check_fail ".env no existe - copiar desde .env.example"
fi
echo ""

# 4. Verificar config.json
echo "4. Verificando config.json..."
if [ -f "config.json" ]; then
    check_ok "config.json existe"
    
    # Verificar que sea JSON válido
    if python3 -c "import json; json.load(open('config.json'))" 2>/dev/null; then
        check_ok "config.json es JSON válido"
    else
        check_fail "config.json tiene errores de sintaxis"
    fi
else
    check_fail "config.json falta"
fi
echo ""

# 5. Verificar Dockerfile
echo "5. Verificando Dockerfile..."
if grep -q "COPY src/ ./src/" Dockerfile && grep -q "CMD \[\"python\", \"main.py\"\]" Dockerfile; then
    check_ok "Dockerfile actualizado para v2.0"
else
    check_fail "Dockerfile no actualizado - revisar COPY y CMD"
fi
echo ""

# 6. Verificar docker-compose.yml
echo "6. Verificando docker-compose.yml..."
if grep -q "env_file:" docker-compose.yml; then
    check_ok "docker-compose.yml usa env_file"
else
    check_warn "docker-compose.yml no tiene env_file (puede ser intencional)"
fi

if grep -q "judgeman_data:" docker-compose.yml; then
    check_ok "Volumen judgeman_data declarado"
else
    check_fail "Volumen judgeman_data no encontrado"
fi
echo ""

# 7. Verificar requirements.txt
echo "7. Verificando dependencias..."
if grep -q "python-dotenv" requirements.txt; then
    check_ok "python-dotenv en requirements.txt"
else
    check_fail "python-dotenv falta en requirements.txt"
fi

if grep -q "pika" requirements.txt; then
    check_ok "pika en requirements.txt"
else
    check_fail "pika falta en requirements.txt"
fi

if grep -q "docker" requirements.txt; then
    check_ok "docker SDK en requirements.txt"
else
    check_fail "docker SDK falta en requirements.txt"
fi
echo ""

# 8. Verificar imports en módulos
echo "8. Verificando imports entre módulos..."
if grep -q "from .config import" src/worker.py; then
    check_ok "worker.py importa config correctamente"
else
    check_fail "worker.py imports incorrectos"
fi

if grep -q "from .config import" src/docker_manager.py; then
    check_ok "docker_manager.py importa config correctamente"
else
    check_fail "docker_manager.py imports incorrectos"
fi
echo ""

# 9. Verificar documentación
echo "9. Verificando documentación..."
[ -f "README.md" ] && check_ok "README.md existe" || check_warn "README.md falta"
[ -f "CHANGELOG.md" ] && check_ok "CHANGELOG.md existe" || check_warn "CHANGELOG.md falta"
[ -f "MIGRATION.md" ] && check_ok "MIGRATION.md existe" || check_warn "MIGRATION.md falta"
echo ""

# 10. Test de sintaxis Python
echo "10. Verificando sintaxis Python..."
for file in src/*.py main.py; do
    if python3 -m py_compile "$file" 2>/dev/null; then
        check_ok "$file - sintaxis válida"
    else
        check_fail "$file - errores de sintaxis"
    fi
done
echo ""

echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║                   VERIFICACIÓN COMPLETADA                     ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo ""
echo "Siguiente paso: ./deploy.sh"
echo ""
