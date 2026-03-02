#!/bin/bash
# JudgeMan Build and Deploy Script

set -e  # Exit on error

echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║          THE JUDGEMAN MICROSERVICE - BUILD SCRIPT             ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo ""

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Functions
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if .env exists
if [ ! -f .env ]; then
    log_warn ".env file not found"
    if [ -f .env.example ]; then
        log_info "Copying .env.example to .env"
        cp .env.example .env
        log_warn "Please review .env file before production deployment"
    else
        log_error ".env.example not found!"
        exit 1
    fi
fi

# Stop existing containers
log_info "Stopping existing containers..."
docker-compose down 2>/dev/null || true

# Build images
log_info "Building Docker images..."
docker-compose build

# Start services
log_info "Starting services..."
docker-compose up -d

# Wait for RabbitMQ to be ready
log_info "Waiting for RabbitMQ to be ready..."
sleep 10

# Check container status
log_info "Checking container status..."
docker-compose ps

echo ""
log_info "╔═══════════════════════════════════════════════════════════╗"
log_info "║                    DEPLOYMENT COMPLETE                    ║"
log_info "╚═══════════════════════════════════════════════════════════╝"
echo ""
log_info "Services:"
log_info "  - RabbitMQ Management: http://localhost:15672 (guest/guest)"
log_info "  - Worker: Running and listening to queue"
echo ""
log_info "To view logs:"
log_info "  docker-compose logs -f worker"
echo ""
log_info "To test:"
log_info "  python test.py"
echo ""
