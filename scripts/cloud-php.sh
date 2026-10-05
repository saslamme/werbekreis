#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
# Docker supplies the managed proxy. Mount the public combined CA bundle, never credentials.
exec docker run --rm --user "$(id -u):$(id -g)" \
    --mount "type=bind,src=$PWD,dst=/app" \
    --mount type=bind,src=/etc/ssl/certs/ca-certificates.crt,dst=/run/cloud-ca.pem,readonly \
    -e COMPOSER_CAFILE=/run/cloud-ca.pem -e SSL_CERT_FILE=/run/cloud-ca.pem \
    -e CURL_CA_BUNDLE=/run/cloud-ca.pem -e COMPOSER_HOME=/tmp/composer \
    werbekreis-php:pr1 "$@"
