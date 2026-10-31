# Cigarholic developer entry points. Everything here is also what CI and the runbooks use.
.DEFAULT_GOAL := help
export COMPOSER_ALLOW_SUPERUSER = 1
WP = docker compose exec -T php wp

.PHONY: help tools install up down destroy logs shell wp provision seed check test e2e

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-10s\033[0m %s\n", $$1, $$2}'

tools: ## Install the pinned phars (PHPStan, PHPUnit, Deployer) into tools/
	scripts/tools.sh

install: tools ## Composer and npm dependencies on the host
	composer install --no-interaction --no-progress --prefer-dist
	npm ci --no-audit --no-fund

up: ## Start the runtime, install WordPress, provision and seed (idempotent)
	scripts/runtime-up.sh

down: ## Stop the runtime (keeps the database volume)
	docker compose down

destroy: ## Stop the runtime and delete the database volume
	docker compose down -v

logs: ## Follow container logs
	docker compose logs -f --tail=100

shell: ## Shell in the php container
	docker compose exec php bash

wp: ## Run WP-CLI in the php container: make wp ARGS="option get blogname"
	$(WP) $(ARGS)

provision: ## Re-apply settings as code
	$(WP) cigarholic provision

seed: ## Re-create sample data (SAMPLE- SKUs)
	$(WP) cigarholic seed

check: ## Static checks, exactly as CI: composer check + npm run lint
	composer check
	npm run lint

test: ## PHPUnit inside the php container
	docker compose exec -T php tools/phpunit.phar

e2e: ## Playwright smoke suite against the running stack (sandbox: preinstalled Chromium)
	PLAYWRIGHT_BROWSERS_PATH=$${PLAYWRIGHT_BROWSERS_PATH:-/opt/pw-browsers} npx playwright test $(ARGS)
