#!/bin/bash
# Vision Prime - Production Deployment Script
# Usage: ./deploy.sh [staging|production]
# Requires: Docker + Docker Compose (v2 recommended), .env.production configured.

set -euo pipefail

ENVIRONMENT="${1:-production}"
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
COMPOSE_FILE="docker-compose.production.yml"
ENV_FILE=".env.${ENVIRONMENT}"
DC=""

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

dc() { $DC -f "$COMPOSE_FILE" --env-file "$ENV_FILE" "$@"; }

# Check requirements
check_requirements() {
    log "Checking requirements..."

    command -v docker >/dev/null 2>&1 || error "Docker not installed"
    if docker compose version >/dev/null 2>&1; then
        DC="docker compose"
    elif command -v docker-compose >/dev/null 2>&1; then
        DC="docker-compose"
        warn "docker-compose v1 detected - consider upgrading to the 'docker compose' plugin"
    else
        error "Docker Compose not installed (install the 'docker compose' plugin)"
    fi

    if [[ ! -f "$ENV_FILE" ]]; then
        warn "Environment file $ENV_FILE not found"
        warn "Copy .env.production.template to $ENV_FILE and configure it"
        error "Missing environment file"
    fi

    success "Requirements check passed ($DC)"
}

# Ensure TLS certificates exist (nginx refuses to start without them)
ensure_certificates() {
    if [[ -f docker/nginx/ssl/fullchain.pem && -f docker/nginx/ssl/privkey.pem ]]; then
        success "TLS certificates found in docker/nginx/ssl"
        return
    fi

    warn "TLS certificates missing - generating self-signed fallback for ${APP_DOMAIN:-localhost}"
    warn "Replace docker/nginx/ssl/*.pem with a Let's Encrypt certificate for production"
    ( cd "$PROJECT_DIR" && APP_DOMAIN="${APP_DOMAIN:-localhost}" bash docker/nginx/ssl/generate-selfsigned.sh )
}

# Build images
build_images() {
    log "Building Docker images..."
    dc build --pull
    success "Images built"
}

# Run database migrations and seed base roles
run_migrations() {
    log "Running database migrations..."
    dc run --rm app php artisan migrate --force --no-interaction
    log "Seeding base roles and permissions..."
    dc run --rm app php artisan db:seed --class=RolePermissionSeeder --force --no-interaction
    success "Migrations and base seed completed"
}

# Clear and cache config
optimize_app() {
    log "Optimizing application..."
    dc run --rm app bash -c "
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
    dc up -d --remove-orphans

    # Wait for health checks
    log "Waiting for services to be healthy..."
    sleep 10

    if dc ps | grep -q "unhealthy"; then
        error "Some services are unhealthy"
    fi

    success "Services restarted and healthy"
}

# Run post-deployment tasks
post_deploy() {
    log "Running post-deployment tasks..."

    # Warm up Horizon
    dc exec -T horizon php artisan horizon:terminate || true

    # Clear opcode cache
    dc exec -T app php -r "opcache_reset();" || true

    success "Post-deployment tasks completed"
}

# Health check
health_check() {
    log "Running health checks..."

    local domain="${APP_DOMAIN:-localhost}"
    local max_attempts=30
    local attempt=1

    while [[ $attempt -le $max_attempts ]]; do
        if curl -sfk "https://${domain}/up" >/dev/null 2>&1; then
            success "Health check passed (https://${domain}/up)"
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
    # shellcheck disable=SC1090
    source "$ENV_FILE"
    set +a

    check_requirements
    ensure_certificates
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
