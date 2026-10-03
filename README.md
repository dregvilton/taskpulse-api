# TaskPulse

[![CI](https://github.com/dregvilton/taskpulse-api/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/dregvilton/taskpulse-api/actions/workflows/ci.yml)

TaskPulse — приложение для управления задачами с аналитикой: REST API на Yii2
и интерфейс на Vue 3. Публичное демо можно открыть в браузере без установки проекта.

## Публичное демо

- [Открыть приложение](https://taskpulse-demo.ru/app/)
- [Swagger UI](https://taskpulse-demo.ru/docs/) и [OpenAPI](https://taskpulse-demo.ru/openapi.yaml)
- [Исходный код на GitHub](https://github.com/dregvilton/taskpulse-api)

Войти можно кнопкой «Войти в демо без регистрации» или с данными
`demo@taskpulse.example` / `TaskPulseDemo2026!`. Аккаунт общий: задачи видны
другим посетителям и сбрасываются каждые 30 минут. Не вводите реальные или
персональные данные. Регистрация и изменение профиля на публичном стенде отключены.

## Что внутри

- Задачи с фильтрами, сортировкой, пагинацией и защитой от повторного создания через `Idempotency-Key`.
- Доступ только к своим данным через Bearer-аутентификацию; аналитика задач кешируется в Redis.
- События изменений доставляются через transactional outbox и RabbitMQ
  с повторными попытками и DLQ.

Стек: PHP 8.3, Yii2, PostgreSQL 16, Redis 7, RabbitMQ 4, Vue 3, Nginx
и Docker Compose.

## Локальный запуск

Нужны Docker Compose и GNU Make:

```bash
cp .env.example .env
make init
```

Интерфейс откроется на <http://localhost:8080/app/>, Swagger UI — на
<http://localhost:8080/docs/>. Для полного набора проверок выполните `make check`.

## Подробнее

- [OpenAPI](openapi.yaml) — маршруты, поля, ответы и ошибки API.
- [Архитектура](architecture.md) — компоненты, кеш и обработка событий.
- [Развёртывание](deployment.md) — локальная разработка, production и диагностика.
