#!/usr/bin/env bash

set -Eeuo pipefail

if [[ $EUID -ne 0 || $PWD != /opt/taskpulse ]]; then
    echo 'Запустите от root из /opt/taskpulse.' >&2
    exit 1
fi
if [[ ! -f .env || $(stat -c %a .env) != 600 ]]; then
    echo 'Нужен /opt/taskpulse/.env с правами 600.' >&2
    exit 1
fi
for required in 'APP_ENV=prod' 'APP_DEBUG=false' 'APP_DEMO_MODE=true'; do
    if ! grep -Fxq "$required" .env; then
        echo "В .env отсутствует обязательная настройка: $required" >&2
        exit 1
    fi
done

compose=(docker compose -f compose.prod.yaml)
git diff --quiet
git diff --cached --quiet
git fetch origin main
git switch main
git pull --ff-only origin main
"${compose[@]}" config --quiet

"${compose[@]}" build app nginx
"${compose[@]}" up -d --wait --wait-timeout 120 postgres redis rabbitmq

install -m 700 -d /opt/taskpulse/backups
backup=$(mktemp "/opt/taskpulse/backups/taskpulse-$(date -u +%Y%m%dT%H%M%SZ)-XXXXXX.dump")
if ! "${compose[@]}" exec -T postgres sh -c \
    'PGPASSWORD="$POSTGRES_PASSWORD" exec pg_dump -U "$POSTGRES_USER" --format=custom "$POSTGRES_DB"' \
    > "$backup"; then
    echo 'Резервная копия PostgreSQL не создана; миграции не запускались.' >&2
    exit 1
fi

"${compose[@]}" up -d --force-recreate app
"${compose[@]}" exec -T app php yii migrate --interactive=0
"${compose[@]}" exec -T app php yii demo/reset
"${compose[@]}" up -d --force-recreate nginx publisher worker

ready=false
for _ in {1..30}; do
    if curl --fail --silent --show-error http://127.0.0.1:8080/health >/dev/null 2>&1; then
        ready=true
        break
    fi
    sleep 2
done
if [[ $ready != true ]]; then
    echo 'Локальный /health не стал доступен за минуту.' >&2
    exit 1
fi

install -m 644 deploy/systemd/taskpulse-demo-reset.service /etc/systemd/system/
install -m 644 deploy/systemd/taskpulse-demo-reset.timer /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now taskpulse-demo-reset.timer
systemctl start taskpulse-demo-reset.service

site_domain=$(sed -n 's/^SITE_DOMAIN=//p' .env | head -n 1)
if [[ -n $site_domain ]]; then
    if [[ ! $site_domain =~ ^[a-z0-9][a-z0-9.-]*[a-z0-9]$ ]]; then
        echo 'SITE_DOMAIN имеет неверный формат.' >&2
        exit 1
    fi
    sed "s/__SITE_DOMAIN__/$site_domain/" deploy/Caddyfile.template > /etc/caddy/Caddyfile
    caddy validate --config /etc/caddy/Caddyfile
    systemctl enable --now caddy
    systemctl reload caddy
else
    echo 'SITE_DOMAIN пока не задан: приложение доступно только на loopback, HTTPS не включён.'
fi

echo "Развёрнут коммит $(git rev-parse HEAD). Backup: $backup"
"${compose[@]}" ps
systemctl is-active taskpulse-demo-reset.timer
