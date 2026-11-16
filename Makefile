# Shortcuts for the commands used most while working on Brread!.
# Run `make` on its own to list them.

.DEFAULT_GOAL := help
.PHONY: help setup dev serve build test fresh docker

help: ## List the available commands
	@grep -E '^[a-z]+:.*## ' $(MAKEFILE_LIST) | awk -F':.*## ' '{printf "  make %-8s %s\n", $$1, $$2}'

setup: ## Install everything and prepare a local database
	composer install
	npm install
	test -f .env || cp .env.example .env
	php artisan key:generate
	touch database/database.sqlite
	php artisan migrate --seed
	npm run build

dev: ## Run the Vite dev server for frontend work
	npm run dev

serve: ## Start the app on http://127.0.0.1:8000
	php artisan serve

build: ## Build the production assets
	npm run build

test: ## Run the PHPUnit suite
	php artisan test

fresh: ## Rebuild the local database from scratch with seed data
	php artisan migrate:fresh --seed

docker: ## Build the image and run it on http://localhost:8080
	docker build -t brread .
	docker run --rm -p 8080:8080 -e APP_KEY="base64:$$(openssl rand -base64 32)" brread
