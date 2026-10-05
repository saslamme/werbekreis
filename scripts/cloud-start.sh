#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
if ! docker network inspect wk-pr1 >/dev/null 2>&1; then docker network create wk-pr1; fi
if docker container inspect wk-pr1-db >/dev/null 2>&1; then
    docker start wk-pr1-db >/dev/null
else
    docker run -d --name wk-pr1-db --network wk-pr1 --network-alias database \
        -e MARIADB_ROOT_PASSWORD=local-root-password -e MARIADB_DATABASE=werbekreis \
        -e MARIADB_USER=app -e MARIADB_PASSWORD=password mariadb:10.11
fi
ready=false
for attempt in $(seq 1 30); do
    if docker exec wk-pr1-db healthcheck.sh --connect --innodb_initialized >/dev/null 2>&1; then ready=true; break; fi
    sleep 1
done
if [ "$ready" != true ]; then echo 'MariaDB did not become ready.' >&2; exit 1; fi
if [ ! -f .env.local ]; then
    ./scripts/cloud-php.sh php -r 'file_put_contents(".env.local", "APP_SECRET=".bin2hex(random_bytes(32)).PHP_EOL); chmod(".env.local", 0600);'
fi
# These are project-owned development containers. Recreate PHP to refresh session proxy/CA settings.
if docker container inspect wk-pr1-php >/dev/null 2>&1; then docker rm -f wk-pr1-php >/dev/null; fi
docker run -d --name wk-pr1-php --network wk-pr1 --user "$(id -u):$(id -g)" \
    --mount "type=bind,src=$PWD,dst=/app" \
    --mount type=bind,src=/etc/ssl/certs/ca-certificates.crt,dst=/run/cloud-ca.pem,readonly \
    -e WK_CA_BUNDLE=/run/cloud-ca.pem -e COMPOSER_CAFILE=/run/cloud-ca.pem -e SSL_CERT_FILE=/run/cloud-ca.pem \
    -e CURL_CA_BUNDLE=/run/cloud-ca.pem -e COMPOSER_HOME=/tmp/composer \
    -e 'DATABASE_URL=mysql://app:password@database:3306/werbekreis?serverVersion=mariadb-10.11.0&charset=utf8mb4' \
    werbekreis-php:pr1 tail -f /dev/null
docker exec wk-pr1-php php bin/console doctrine:migrations:migrate --no-interaction
docker exec -d wk-pr1-php php -S 0.0.0.0:8080 -t public public/router.php
for attempt in $(seq 1 15); do
    if docker exec wk-pr1-php php -r 'exit(str_contains(file_get_contents("http://127.0.0.1:8080/"), "Haselünne hat mehr zu bieten.") ? 0 : 1);' 2>/dev/null; then
        echo 'Homepage HTTP readiness check passed.'
        exit 0
    fi
    sleep 1
done
echo 'Homepage readiness check failed.' >&2
exit 1
