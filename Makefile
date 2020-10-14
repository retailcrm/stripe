ifndef TESTPATH
    TESTPATH = ''
endif

ifndef ENV
    ENV = 'dev'
endif

COMMAND = docker-compose -f docker-compose.yml

start:
	@echo "==> Building $(ENV)"
	@$(COMMAND) -f docker-compose.$(ENV).yml up -d --build

stop:
	@echo "==> Stopping $(ENV)"
	@$(COMMAND) -f docker-compose.$(ENV).yml down

deps:
	@echo "==> Composer install and Yarn install $(ENV)"
	@$(COMMAND) -f docker-compose.$(ENV).yml run --rm --no-deps php /usr/local/bin/composer --no-interaction install --prefer-dist
	@$(COMMAND) -f docker-compose.$(ENV).yml run --rm --no-deps node yarn install

deps_update:
	@echo "==> Composer update $(ENV)"
	@$(COMMAND) -f docker-compose.$(ENV).yml run --rm --no-deps php /usr/local/bin/composer --no-interaction update --prefer-dist

check_cs:
	@echo "==> Check code style $(ENV)"
	@$(COMMAND) -f docker-compose.$(ENV).yml run --rm --no-deps php php vendor/bin/php-cs-fixer fix --dry-run --config=.php_cs.dist --using-cache=no -v

create_database:
	@echo "==> Create database & schema $(ENV)"
	-$(COMMAND) -f docker-compose.$(ENV).yml run --rm --no-deps php bin/console do:da:dr --force
	@$(COMMAND) -f docker-compose.$(ENV).yml run --rm --no-deps php bin/console do:da:cr
	@$(COMMAND) -f docker-compose.$(ENV).yml run --rm --no-deps php bin/console do:sc:cr

update_schema:
	@echo "==> Update schema $(ENV)"
	@$(COMMAND) -f docker-compose.$(ENV).yml run --rm --no-deps php bin/console do:sc:up --force

validate_schema:
	@echo "==> Validate schema $(ENV)"
	@$(COMMAND) -f docker-compose.$(ENV).yml run --rm --no-deps php bin/console doctrine:schema:validate

migrate_schema:
	@echo "==> Run Migrate"
	@$(COMMAND) -f docker-compose.$(ENV).yml run --rm --no-deps php bin/console doctrine:migrations:migrate --no-interaction

diff_schema:
	@echo "==> Run Diff"
	@$(COMMAND) -f docker-compose.$(ENV).yml run --rm --no-deps php bin/console doctrine:migrations:diff

run_fix:
	@echo "==> Run Fix"
	@$(COMMAND) -f docker-compose.test.yml run --rm --no-deps php vendor/bin/php-cs-fixer fix

run_test:
	@echo "==> Run Tests"
	@$(COMMAND) -f docker-compose.test.yml run --rm --no-deps -u root php php bin/phpunit $(TESTPATH)

assets:
	@echo "==> Install assets"
	@$(COMMAND) -f docker-compose.$(ENV).yml run --rm --no-deps php bin/console assets:install public

js_routing:
	@echo "==> Generate js routes"
	@$(COMMAND) -f docker-compose.$(ENV).yml run --rm --no-deps php bin/console fos:js-routing:dump --format=json --target=public/js/fos_js_routes.json

encore:
	@echo "==> Encore run $(ENV)"
	@$(COMMAND) -f docker-compose.$(ENV).yml run --rm --no-deps node yarn dev

clear:
	@echo "==> Clear cache in $(ENV)"
	@$(COMMAND) -f docker-compose.$(ENV).yml run --rm --no-deps php bin/console cache:clear
