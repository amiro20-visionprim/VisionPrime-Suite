#!/bin/bash
# Generate self-signed SSL certificate for development/testing
# For production, replace with Let's Encrypt or proper CA certificates

set -e

SSL_DIR="$(dirname "$0")/ssl"
mkdir -p "$SSL_DIR"

DOMAIN="${APP_DOMAIN:-localhost}"

echo "Generating self-signed SSL certificate for $DOMAIN..."

openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout "$SSL_DIR/privkey.pem" \
    -out "$SSL_DIR/fullchain.pem" \
    -subj "/CN=$DOMAIN" \
    -addext "subjectAltName=DNS:$DOMAIN,DNS:www.$DOMAIN"

# Set proper permissions
chmod 600 "$SSL_DIR/privkey.pem"
chmod 644 "$SSL_DIR/fullchain.pem"

echo "SSL certificates generated in $SSL_DIR"
echo "For production, replace with Let's Encrypt certificates:"
echo "  certbot certonly --nginx -d $DOMAIN -d www.$DOMAIN"
echo "  Then update ssl_certificate paths in nginx config"