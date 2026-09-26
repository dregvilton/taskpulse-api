# TaskPulse API

TaskPulse is a production-style task management and analytics REST API built as a compact backend portfolio project.

## Stack

- PHP 8.3 and Yii2 2.0.55
- PostgreSQL 16
- Redis 7
- Nginx and PHP-FPM
- Docker Compose
- PHPUnit, PHPStan, and PHP CS Fixer

RabbitMQ and CI will be added in later feature iterations.

## Local setup

Requirements: Docker with the Compose plugin and GNU Make.

```bash
cp .env.example .env
make init
```

The API is then available at <http://localhost:8080>.

Check application and database health:

```bash
curl --fail http://localhost:8080/health
```

Expected response:

```json
{
  "status": "ok",
  "services": {
    "app": "ok",
    "postgres": "ok"
  }
}
```

## Users API

```text
POST   /users
GET    /users?page=1&perPage=20
GET    /users/{id}
PATCH  /users/{id}
DELETE /users/{id}
```

Удаление пользователей выполняется мягко: запись остаётся в базе данных и исключается из API.
Контракт запросов и ответов описан в [OpenAPI](openapi.yaml).

## Tasks API

```text
POST   /tasks
GET    /tasks?authorId=1&completed=false&page=1&perPage=20&sort=-createdAt
GET    /tasks/{id}
GET    /users/{id}/tasks
PATCH  /tasks/{id}
DELETE /tasks/{id}
```

Задачи поддерживают фильтрацию по автору, состоянию и датам, сортировку и пагинацию.
При изменении `completed` поле `completedAt` устанавливается или очищается автоматически.
Удаление задач выполняется мягко.

Повторное создание можно защитить заголовком `Idempotency-Key`:

```bash
curl -i -X POST http://localhost:8080/tasks \
  -H 'Content-Type: application/json' \
  -H 'Idempotency-Key: report-task-2026-09-26' \
  -d '{"authorId":1,"title":"Подготовить отчёт"}'
```

Повтор того же запроса с тем же ключом возвращает исходные `201`, `Location` и JSON-ответ,
не создавая новую задачу. Если тело запроса отличается, API отвечает `409`. Ключ
необязателен и хранится в PostgreSQL без срока действия; корректность не зависит от Redis.

## Аналитика задач

```bash
curl 'http://localhost:8080/analytics/tasks?authorId=1&createdFrom=2026-09-01T00%3A00%3A00%2B00%3A00'
```

Ответ содержит `totalCreated`, `totalCompleted`, `completionPercent` и
`avgCompletionTimeSeconds`. Период относится к дате создания задачи; удалённые задачи
не учитываются. Среднее время равно `null`, если завершённых задач нет.

Результаты кешируются в Redis на 300 секунд. Изменения задач через API инвалидируют
кеш всех фильтров. При недоступности Redis аналитика рассчитывается из PostgreSQL;
если Redis был недоступен во время записи задачи, старое значение может сохраняться
до истечения TTL.

## Development commands

```bash
make up                 # start services
make down               # stop services
make migrate            # apply database migrations
make test               # run unit tests
make stan               # run static analysis
make cs                 # check code style
make check              # run every code check
make logs               # follow container logs
```

## Iteration workflow

Каждая итерация разрабатывается в отдельной ветке `iteration-*` и проверяется через pull request перед слиянием.
