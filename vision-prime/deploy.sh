#!/bin/bash
# Vision Prime - Production Deployment Script
# Usage: ./deploy.sh [staging|production]

set -euo pipefail

ENVIRONMENT="${1:-production}"
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
COMPOSE_FILE="docker-compose.production.yml"
ENV_FILE=".env.${ENVIRONMENT}"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log() { echo -e "${BLUE}[$(date +'%H:%M:%S')]${NC} $1"; }
success() { echo -e "${GREEN}✓${NC} $1"; }
warn() { echo -e "${YELLOW}⚠${NC} $1"; }
error() { echo -e "${RED}✗${NC} $1"; exit 1; }

# Check requirements
check_requirements() {
    log "Checking requirements..."
    
    command -v docker >/dev/null 2>&1 || error "Docker not installed"
    command -v docker-compose >/dev/null 2>&1 || error "Docker Compose not installed"
    
    if [[ ! -f "$ENV_FILE" ]]; then
        warn "Environment file $ENV_FILE not found"
        warn "Copy .env.production.template to $ENV_FILE and configure"
        error "Missing environment file"
    fi
    
    success "Requirements check passed"
}

# Build images
build_images() {
    log "Building Docker images..."
    docker-compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" build --no-cache --pull
    success "Images built"
}

# Run database migrations
run_migrations() {
    log "Running database migrations..."
    docker-compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" run --rm app \
        php artisan migrate --force --no-interaction
    success "Migrations completed"
}

# Clear and cache config
optimize_app() {
    log "Optimizing application..."
    docker-compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" run --rm app bash -c "
        php artisan config:clear &&
        php artisan route:clear &&
        php artisan view:clear &&
        php artisan config:cache &&
        php artisan route:cache &&
        php artisan view:cache &&
        php artisan event:cache
    "
    success "Application optimized"
}

# Restart services
restart_services() {
    log "Restarting services..."
    docker-compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" up -d --remove-orphans
    
    # Wait for health checks
    log "Waiting for services to be healthy..."
    sleep 10
    
    # Check health
    if docker-compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" ps | grep -q "unhealthy"; then
        error "Some services are unhealthy"
    fi
    
    success "Services restarted and healthy"
}

# Run post-deployment tasks
post_deploy() {
    log "Running post-deployment tasks..."
    
    # Warm up Horizon
    docker-compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" exec -T horizon \
        php artisan horizon:terminate || true
    
    # Clear opcode cache
    docker-compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" exec -T app \
        php -r "opcache_reset();" || true
    
    success "Post-deployment tasks completed"
}

# Health check
health_check() {
    log "Running health checks..."
    
    local max_attempts=30
    local attempt=1
    
    while [[ $attempt -le $max_attempts ]]; do
        if curl -sf "https://${APP_DOMAIN:-localhost}/up" >/dev/null 2>&1; then
            success "Health check passed"
            return 0
        fi
        
        log "Attempt $attempt/$max_attempts - waiting for application..."
        sleep 2
        ((attempt++))
    done
    
    error "Health check failed after $max_attempts attempts"
}

# Rollback function
rollback() {
    warn "Rolling back to previous version..."
    
    # This would typically involve:
    # 1. Restore previous docker image tags
    # 2. Restore database from backup
    # 3. Restart services
    
    log "Manual rollback required - check DEPLOYMENT-RUNBOOK.md"
}

# Main deployment flow
main() {
    log "Starting Vision Prime deployment to $ENVIRONMENT"
    
    # Load environment
    set -a
    source "$ENV_FILE"
    set +a
    
    check_requirements
    build_images
    run_migrations
    optimize_app
    restart_services
    post_deploy
    health_check
    
    success "Deployment to $ENVIRONMENT completed successfully!"
    log "Application available at: https://${APP_DOMAIN:-localhost}"
    log "Horizon dashboard: https://${HORIZON_DOMAIN:-horizon.localhost}"
}

# Trap errors for rollback
trap 'error "Deployment failed! Check logs and consider rollback."' ERR

main "$@"