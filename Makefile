

.PHONY: install tests cs-fix cs-check shell help

SHELL := bash
.ONESHELL:
.SHELLFLAGS := -eu -o pipefail -c
MAKEFLAGS += --warn-undefined-variables
MAKEFLAGS += --no-builtin-rules

.DEFAULT_GOAL := help

help: ## Outputs this help screen.
	@grep -E '(^[a-zA-Z0-9_\/\-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'


install: ## install
	docker compose run --rm php composer install

tests: ## tests
	docker compose run --rm php vendor/bin/phpunit

cs-fix: ## cs-fix
	docker compose run --rm php vendor/bin/php-cs-fixer fix

cs-check:
	docker compose run --rm php vendor/bin/php-cs-fixer fix --dry-run --diff

shell:
	docker compose run --rm php bash
