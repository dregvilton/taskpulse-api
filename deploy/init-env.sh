#!/usr/bin/env bash

set -Eeuo pipefail

if [[ $EUID -ne 0 || $PWD != /opt/taskpulse ]]; then
    echo 'Запустите от root из /opt/taskpulse.' >&2
    exit 1
fi
if [[ -e .env ]]; then
    echo '.env уже существует; секреты не перезаписаны.' >&2
    exit 1
fi

site_domain=${1:-}
if [[ -n $site_domain && ! $site_domain =~ ^[a-z0-9][a-z0-9.-]*[a-z0-9]$ ]]; then
    echo 'Имя домена имеет неверный формат.' >&2
    exit 1
fi

umask 077
cookie_key=$(openssl rand -hex 32)
db_password=$(openssl rand -hex 32)
rabbitmq_password=$(openssl rand -hex 32)
install -m 600 /dev/null .env
printf 'APP_ENV=prod\nAPP_DEBUG=false\nAPP_PUBLIC_WRITES=false\nAPP_DEMO_MODE=true\nAPP_COOKIE_VALIDATION_KEY=%s\nSENTRY_DSN=\nSITE_DOMAIN=%s\n\nDB_HOST=postgres\nDB_PORT=5432\nDB_NAME=taskpulse\nDB_USER=taskpulse\nDB_PASSWORD=%s\n\nREDIS_HOST=redis\nREDIS_PORT=6379\nREDIS_DB=0\n\nRABBITMQ_HOST=rabbitmq\nRABBITMQ_PORT=5672\nRABBITMQ_USER=taskpulse\nRABBITMQ_PASSWORD=%s\n\nDEMO_EMAIL=demo@taskpulse.example\nDEMO_FULL_NAME="Гость TaskPulse"\nDEMO_PASSWORD=TaskPulseDemo2026!\n' \
    "$cookie_key" "$site_domain" "$db_password" "$rabbitmq_password" > .env
chmod 600 .env
echo 'Production .env создан с правами 600; случайные секреты не выводились.'
