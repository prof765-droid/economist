PHP = docker compose exec php
PHP_RUN = docker compose run --rm php
NODE = docker compose exec node
NODE_RUN = docker compose run --rm node

.PHONY: build build-backend build-frontend up up-backend up-frontend down sh test-db test test-backend test-frontend lint lint-backend lint-frontend fix phpstan deptrac

build: build-backend build-frontend

build-backend:
	docker compose build
	$(PHP_RUN) composer install
	$(PHP_RUN) bin/console doctrine:migrations:migrate -n --allow-no-migration

build-frontend:
	$(NODE_RUN) npm ci

up: up-backend up-frontend

up-backend:
	docker compose up -d php nginx

up-frontend:
	docker compose up -d node nginx

down:
	docker compose down

sh:
	$(PHP) sh

test-db:
	$(PHP) bin/console doctrine:database:create --env=test --if-not-exists
	$(PHP) bin/console doctrine:migrations:migrate --env=test -n --allow-no-migration

test: test-backend test-frontend

test-backend:
	$(PHP) vendor/bin/phpunit

test-frontend:
	$(NODE) npm test

lint: lint-backend lint-frontend

lint-backend:
	$(PHP) composer validate --strict --no-check-publish
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff
	$(MAKE) phpstan
	$(MAKE) deptrac
	$(PHP) bin/console lint:container

lint-frontend:
	$(NODE) npm run lint
	$(NODE) npm run typecheck

fix:
	$(PHP) vendor/bin/php-cs-fixer fix

phpstan:
	$(PHP) bin/console cache:warmup --env=dev
	$(PHP) vendor/bin/phpstan analyse --memory-limit=1G

deptrac:
	$(PHP) vendor/bin/deptrac analyse --config-file=deptrac.yaml --fail-on-uncovered --no-progress
