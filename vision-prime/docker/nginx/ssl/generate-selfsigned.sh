#!/bin/bash
# Generate self-signed SSL certificate for development/testing
# For production, replace with Let's Encrypt or proper CA certificates.
#
# Writes fullchain.pem + privkey.pem into this script's directory
# (docker/nginx/ssl), which the compose stack mounts at /etc/nginx/ssl.

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

DOMAIN="${APP_DOMAIN:-localhost}"

echo "Generating self-signed SSL certificate for $DOMAIN..."

# MSYS_NO_PATHCONV keeps '/CN=...' from being mangled on Git Bash/Windows;
# relative output paths keep native openssl (Windows) working.
MSYS_NO_PATHCONV=1 openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout privkey.pem \
    -out fullchain.pem \
    -subj "/CN=$DOMAIN" \
    -addext "subjectAltName=DNS:$DOMAIN,DNS:www.$DOMAIN"

# Set proper permissions
chmod 600 privkey.pem
chmod 644 fullchain.pem

echo "SSL certificates generated in $SCRIPT_DIR"
echo "For production, replace with Let's Encrypt certificates:"
echo "  certbot certonly --nginx -d $DOMAIN -d www.$DOMAIN"
echo "  Then update ssl_certificate paths in the nginx template"
