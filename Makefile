PHP = docker compose exec php
PHP_RUN = docker compose run --rm php

.PHONY: build up down sh test-db test

build:
	docker compose build
	$(PHP_RUN) composer install
	$(PHP_RUN) bin/console doctrine:migrations:migrate -n --allow-no-migration

up:
	docker compose up -d

down:
	docker compose down

sh:
	$(PHP) sh

test-db:
	$(PHP) bin/console doctrine:database:create --env=test --if-not-exists
	$(PHP) bin/console doctrine:migrations:migrate --env=test -n --allow-no-migration

test:
	$(PHP) vendor/bin/phpunit
