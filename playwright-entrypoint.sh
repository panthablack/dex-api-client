#!/bin/bash

# Get the IP address of the 'app' service dynamically
APP_IP=$(getent hosts app | awk '{ print $1 }')

if [ -z "$APP_IP" ]; then
    echo "ERROR: Could not resolve 'app' service IP address"
    exit 1
fi

echo "Resolved 'app' service to IP: $APP_IP"

# Set the base URL using the resolved IP
export PLAYWRIGHT_BASE_URL="http://$APP_IP:80"

echo "Set PLAYWRIGHT_BASE_URL to: $PLAYWRIGHT_BASE_URL"

# Execute the original command
exec "$@"
