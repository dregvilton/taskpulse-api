#!/usr/bin/env bash

set -Eeuo pipefail

if [[ -z ${BASE_URL:-} || -z ${DEMO_EMAIL:-} || -z ${DEMO_PASSWORD:-} ]]; then
    echo 'Нужны BASE_URL, DEMO_EMAIL и DEMO_PASSWORD.' >&2
    exit 1
fi
if ! command -v jq >/dev/null; then
    echo 'Для smoke-теста нужен jq.' >&2
    exit 1
fi

base_url=${BASE_URL%/}
token=''
idempotency_key="smoke-$(date +%s)-$$"

request() {
    local method=$1 path=$2 body=${3:-}
    local -a args=(--silent --show-error --max-time 15 --request "$method" --write-out $'\n%{http_code}')
    if [[ -n $body ]]; then
        args+=(--header 'Content-Type: application/json' --data "$body")
    fi
    if [[ -n $token ]]; then
        args+=(--header "Authorization: Bearer $token")
    fi
    if [[ $path == /tasks && $method == POST ]]; then
        args+=(--header "Idempotency-Key: $idempotency_key")
    fi

    local response
    response=$(curl "${args[@]}" "$base_url$path")
    status=${response##*$'\n'}
    payload=${response%$'\n'*}
}

expect_status() {
    local expected=$1 label=$2
    if [[ $status != "$expected" ]]; then
        echo "$label: ожидался HTTP $expected, получен $status." >&2
        exit 1
    fi
    echo "$label: HTTP $status"
}

request GET /
expect_status 302 '/'
request GET /app/
expect_status 200 '/app/'
asset=$(printf '%s' "$payload" | grep -oE '/app/assets/[^" ]+\.js' | head -n 1)
request GET "$asset"
expect_status 200 'Vue asset'
request GET /docs/
expect_status 200 '/docs/'
request GET /openapi.yaml
expect_status 200 '/openapi.yaml'
request GET /health
expect_status 200 '/health'
printf '%s' "$payload" | jq -e '.status == "ok" and (.services | all(. == "ok"))' >/dev/null

request GET /tasks
expect_status 401 'GET /tasks без токена'
request POST /auth/login '{"email":"other@example.test","password":"not-a-demo-account"}'
expect_status 401 'Вход не в демо-аккаунт'

login_body=$(jq -cn --arg email "$DEMO_EMAIL" --arg password "$DEMO_PASSWORD" \
    '{email:$email,password:$password}')
request POST /auth/login "$login_body"
expect_status 200 'Вход в демо'
token=$(printf '%s' "$payload" | jq -er '.accessToken')
user_id=$(printf '%s' "$payload" | jq -er '.userId')

request GET /users
expect_status 200 'GET /users'
request PATCH "/users/$user_id" '{"fullName":"Нельзя изменять"}'
expect_status 403 'Изменение профиля запрещено'
request POST /users '{"fullName":"Другой","email":"new@example.test","password":"another-long-password"}'
expect_status 403 'Регистрация запрещена'

request POST /tasks '{"title":"Smoke test TaskPulse"}'
expect_status 201 'Создание задачи'
task_id=$(printf '%s' "$payload" | jq -er '.id')
request POST /tasks '{"title":"Smoke test TaskPulse"}'
expect_status 201 'Идемпотентный повтор'
repeat_id=$(printf '%s' "$payload" | jq -er '.id')
if [[ $task_id != "$repeat_id" ]]; then
    echo 'Повтор создал другую задачу.' >&2
    exit 1
fi
request POST /tasks '{"title":"Другое тело"}'
expect_status 409 'Конфликт Idempotency-Key'

request PATCH "/tasks/$task_id" '{"completed":true}'
expect_status 200 'Завершение задачи'
request GET "/tasks/$task_id"
expect_status 200 'Чтение задачи'
printf '%s' "$payload" | jq -e '.completed == true and .completedAt != null' >/dev/null
request GET /analytics/tasks
expect_status 200 'Аналитика'
printf '%s' "$payload" | jq -e '.totalCreated >= 1 and .totalCompleted >= 1' >/dev/null

request DELETE "/tasks/$task_id"
expect_status 204 'Удаление задачи'
request POST /auth/logout
expect_status 204 'Выход'
request GET /tasks
expect_status 401 'Отозванный токен'

echo 'Smoke-тест демо API и Vue прошёл.'
