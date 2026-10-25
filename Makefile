PHP = docker compose exec php

.PHONY: up down sh test-db test

up:
	docker compose up -d --build
	$(PHP) composer install
	$(PHP) bin/console doctrine:migrations:migrate -n --allow-no-migration

down:
	docker compose down

sh:
	$(PHP) sh

test-db:
	$(PHP) bin/console doctrine:database:create --env=test --if-not-exists
	$(PHP) bin/console doctrine:migrations:migrate --env=test -n --allow-no-migration

test:
	$(PHP) vendor/bin/phpunit
