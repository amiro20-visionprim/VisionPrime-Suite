#!/bin/bash
# Vision Prime - PostgreSQL Backup Script
# Runs daily via cron, encrypts and uploads to S3

set -euo pipefail

# Configuration
BACKUP_DIR="/backups/postgres"
RETENTION_DAYS=30
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="${BACKUP_DIR}/vision_prime_${DATE}.sql.gz.enc"
LOG_FILE="${BACKUP_DIR}/backup_${DATE}.log"

# Load environment
if [[ -f "/app/.env.production" ]]; then
    set -a
    source /app/.env.production
    set +a
fi

# S3 Configuration (optional)
S3_BUCKET="${AWS_BUCKET:-}"
S3_PREFIX="postgres/"

mkdir -p "$BACKUP_DIR"

exec > >(tee -a "$LOG_FILE") 2>&1

log() { echo "[$(date +'%Y-%m-%d %H:%M:%S')] $1"; }

log "Starting PostgreSQL backup..."

# Check required variables
: "${DB_DATABASE:?DB_DATABASE not set}"
: "${DB_USERNAME:?DB_USERNAME not set}"
: "${DB_PASSWORD:?DB_PASSWORD not set}"
: "${DB_HOST:?DB_HOST not set}"
: "${DB_PORT:?DB_PORT not set}"

# Create dump
log "Creating database dump..."
PGPASSWORD="$DB_PASSWORD" pg_dump \
    -h "$DB_HOST" \
    -p "$DB_PORT" \
    -U "$DB_USERNAME" \
    -d "$DB_DATABASE" \
    --no-owner --no-privileges --no-tablespaces \
    --clean --if-exists \
    | gzip -9 \
    | openssl enc -aes-256-cbc -salt -pbkdf2 -iter 100000 \
        -pass pass:"${BACKUP_ENCRYPTION_KEY:-$APP_KEY}" \
    > "$BACKUP_FILE"

if [[ ! -s "$BACKUP_FILE" ]]; then
    log "ERROR: Backup file is empty!"
    exit 1
fi

BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
log "Backup created: $BACKUP_FILE ($BACKUP_SIZE)"

# Verify backup can be decrypted
log "Verifying backup integrity..."
openssl enc -aes-256-cbc -d -pbkdf2 -iter 100000 \
    -pass pass:"${BACKUP_ENCRYPTION_KEY:-$APP_KEY}" \
    -in "$BACKUP_FILE" | gzip -t

if [[ $? -ne 0 ]]; then
    log "ERROR: Backup verification failed!"
    exit 1
fi
log "Backup verification passed"

# Upload to S3 if configured
if [[ -n "$S3_BUCKET" && -n "$AWS_ACCESS_KEY_ID" && -n "$AWS_SECRET_ACCESS_KEY" ]]; then
    log "Uploading to S3: s3://$S3_BUCKET/$S3_PREFIX"
    
    aws s3 cp "$BACKUP_FILE" "s3://$S3_BUCKET/$S3_PREFIX$(basename "$BACKUP_FILE")" \
        --storage-class STANDARD_IA \
        --metadata "database=$DB_DATABASE,date=$DATE"
    
    if [[ $? -eq 0 ]]; then
        log "Upload to S3 successful"
    else
        log "WARNING: S3 upload failed"
    fi
fi

# Cleanup old local backups
log "Cleaning up backups older than $RETENTION_DAYS days..."
find "$BACKUP_DIR" -name "vision_prime_*.sql.gz.enc" -mtime +$RETENTION_DAYS -delete
find "$BACKUP_DIR" -name "backup_*.log" -mtime +$RETENTION_DAYS -delete

log "Backup completed successfully"
log "Backup file: $BACKUP_FILE"
log "Size: $BACKUP_SIZE"