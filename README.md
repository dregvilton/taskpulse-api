# TaskPulse API

TaskPulse is a production-style task management and analytics REST API built as a compact backend portfolio project.

## Stack

- PHP 8.3 and Yii2 2.0.55
- PostgreSQL 16
- Nginx and PHP-FPM
- Docker Compose
- PHPUnit, PHPStan, and PHP CS Fixer

Redis, RabbitMQ, and CI will be added in later feature iterations.

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

The application foundation is developed directly on `main`. Every subsequent iteration is developed in a dedicated `codex/iteration-*` branch and reviewed through a pull request before merging.
