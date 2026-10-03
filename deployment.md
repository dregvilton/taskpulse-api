# Развёртывание TaskPulse

## Локальная разработка

Нужны Docker Compose и GNU Make. Скопируйте `.env.example` в `.env`, замените примерные значения и выполните `make init`. Интерфейс доступен на `/app/`, Swagger UI — на `/docs/`, состояние зависимостей — на `/health`. `make check` запускает обычные тесты, отдельные тесты демо-режима, PHPStan, проверку стиля, Vue-тесты и OpenAPI lint.

## Production на одном VPS

Предусмотренная схема: Ubuntu 24.04, Docker Engine и Compose plugin, Caddy как внешний HTTPS-прокси, `compose.prod.yaml` с приложением на `127.0.0.1:8080`. PostgreSQL, Redis и RabbitMQ не публикуют порты. Nginx доверяет `X-Forwarded-For` только Docker-шлюзу `172.28.42.1`; Caddy не принимает произвольный клиентский `X-Forwarded-For` как доверенный. Приложение и Composer-зависимости находятся внутри PHP-образа, исходники не монтируются из checkout. Все сервисы имеют restart policy и ротацию Docker-логов.

На новом VPS от root:

```bash
git clone https://github.com/dregvilton/taskpulse-api.git /opt/taskpulse
cd /opt/taskpulse
bash deploy/bootstrap-vps.sh
bash deploy/init-env.sh
bash deploy/deploy.sh
```

`bootstrap-vps.sh` обновляет Ubuntu, создаёт постоянный swap 2 ГБ, устанавливает Docker/Compose, Caddy и UFW. Разрешены только входящие 22/80/443. После включения firewall обязательно проверьте новый SSH-сеанс; не отключайте прежний способ входа до этой проверки. Docker может обходить правила UFW для опубликованных портов, поэтому `compose.prod.yaml` привязывает только Nginx к loopback и не публикует PostgreSQL, Redis или RabbitMQ.

`init-env.sh` создаёт `/opt/taskpulse/.env` с правами `600` и случайными ключом cookie и паролями БД/брокера. Секреты не выводятся и не попадают в Git. Повторный запуск не перезаписывает `.env`. Если домен уже есть, вместо команды без аргумента выполните `bash deploy/init-env.sh demo.example.org`, подставив свой домен, и заранее направьте A-запись на IP VPS. Если домен ещё не выбран, приложение будет доступно только на loopback, Caddy останется выключенным. После добавления `SITE_DOMAIN` в `.env` повторите `deploy.sh`. `SENTRY_DSN` оставлен пустым.

Обязательные настройки: `APP_ENV=prod`, `APP_DEBUG=false`, `APP_COOKIE_VALIDATION_KEY`, `APP_DEMO_MODE=true`, `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `REDIS_HOST`, `RABBITMQ_HOST`, `RABBITMQ_USER`, `RABBITMQ_PASSWORD`, `DEMO_EMAIL`, `DEMO_FULL_NAME`, `DEMO_PASSWORD`. `APP_PUBLIC_WRITES=false` остаётся безопасным значением: в демо-режиме разрешены только операции с задачами фиксированного аккаунта. Публичная регистрация и изменение профиля запрещены. Демо-пароль намеренно публичен и не должен совпадать с инфраструктурными паролями.

## Обновление и резервная копия

После слияния PR в `main` выполните на VPS `cd /opt/taskpulse && bash deploy/deploy.sh`. Скрипт делает только fast-forward `main`, собирает production-образы, запускает зависимости, сохраняет `pg_dump` в `/opt/taskpulse/backups/` перед миграциями, явно выполняет `php yii migrate --interactive=0`, восстанавливает демоданные и ждёт локальный `/health`. Неполученная резервная копия останавливает процесс до миграции. Не запускайте `migrate/down` в production: auth-миграция намеренно необратима.

Резервные копии имеют права `600`, каталог — `700`. Регулярно проверяйте свободное место и проверяйте восстановление на отдельной тестовой базе; наличие файла дампа само по себе не доказывает пригодность копии. Для отката кода верните нужный проверенный коммит через обычный Git workflow и пересоберите образы. Если миграция изменила схему несовместимо, остановите приложение и восстановите PostgreSQL из конкретного дампа после проверки его содержимого. Не удаляйте тома `docker compose down --volumes` на живом стенде.

## Демо и диагностика

`php yii demo/reset` работает только при включённом демо-режиме, обновляет один фиксированный аккаунт и его примеры задач в транзакции, удаляет его ключи идемпотентности и истёкшие токены, не трогает других пользователей. Опубликованные outbox-события сохраняются, чтобы поздняя доставка из RabbitMQ не попадала в DLQ из-за отсутствующего event ID. Кеш аналитики инвалидируется. Сброс запускается после деплоя и таймером `taskpulse-demo-reset.timer` каждые 30 минут. Проверки:

```bash
systemctl status taskpulse-demo-reset.timer
systemctl status taskpulse-demo-reset.service
docker compose -f compose.prod.yaml ps
docker compose -f compose.prod.yaml logs --tail=100 app publisher worker
curl --fail http://127.0.0.1:8080/health
```

Через публичный HTTPS проверьте `/`, `/app/`, `/health`, `/docs/`, `/openapi.yaml`, вход, задачи и аналитику. При ошибке используйте `X-Request-Id`, логи контейнеров и `journalctl -u caddy`; проверьте статус Caddy и DNS A-запись. Для проверки очередей используйте `docker compose -f compose.prod.yaml exec rabbitmq rabbitmqctl list_queues name messages_ready messages_unacknowledged`. RabbitMQ management-порт наружу не открыт.
