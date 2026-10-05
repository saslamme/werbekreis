#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
docker build --secret id=proxy_ca,src=/etc/ssl/certs/ca-certificates.crt -t werbekreis-php:pr1 .
./scripts/cloud-php.sh composer install --no-interaction --prefer-dist
npm ci --no-audit --no-fund
npm run build
./scripts/cloud-php.sh php bin/console asset-map:compile
