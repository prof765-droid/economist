PHP = docker compose exec php
PHP_RUN = docker compose run --rm php

.PHONY: build up down sh test-db test lint fix phpstan deptrac

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

lint:
	$(PHP) composer validate --strict --no-check-publish
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff
	$(MAKE) phpstan
	$(MAKE) deptrac
	$(PHP) bin/console lint:container

fix:
	$(PHP) vendor/bin/php-cs-fixer fix

phpstan:
	$(PHP) bin/console cache:warmup --env=dev
	$(PHP) vendor/bin/phpstan analyse --memory-limit=1G

deptrac:
	$(PHP) vendor/bin/deptrac analyse --config-file=deptrac.yaml --fail-on-uncovered --no-progress
