.PHONY: install tests shell

install:
	docker compose run --rm php composer install

tests:
	docker compose run --rm php vendor/bin/phpunit

shell:
	docker compose run --rm php bash
