COMPOSE := docker compose

.PHONY: init up down restart shell composer-install migrate test stan cs cs-fix check logs

init:
	@test -f .env || cp .env.example .env
	$(COMPOSE) build app
	$(COMPOSE) run --rm app composer install
	$(COMPOSE) up -d
	$(COMPOSE) exec app php yii migrate --interactive=0

up:
	$(COMPOSE) up -d

down:
	$(COMPOSE) down

restart:
	$(COMPOSE) restart

shell:
	$(COMPOSE) exec app sh

composer-install:
	$(COMPOSE) run --rm app composer install

migrate:
	$(COMPOSE) exec app php yii migrate --interactive=0

test:
	APP_PORT=18080 $(COMPOSE) -p taskpulse-test -f compose.yaml -f compose.test.yaml up -d --no-build app nginx postgres redis rabbitmq
	$(COMPOSE) -p taskpulse-test -f compose.yaml -f compose.test.yaml stop publisher worker
	$(COMPOSE) -p taskpulse-test -f compose.yaml -f compose.test.yaml exec -T app php yii migrate --interactive=0
	$(COMPOSE) -p taskpulse-test -f compose.yaml -f compose.test.yaml exec -T app composer test

stan:
	$(COMPOSE) exec app composer stan

cs:
	$(COMPOSE) exec app composer cs

cs-fix:
	$(COMPOSE) exec app composer cs-fix

check: test stan cs

logs:
	$(COMPOSE) logs -f --tail=100
