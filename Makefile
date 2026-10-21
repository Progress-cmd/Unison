# Raccourcis pour les commandes quotidiennes. `make` seul affiche la liste.
# Les commandes PHP passent par -u www-data : les fichiers créés t'appartiennent.

EXEC = docker compose exec -u www-data app

.DEFAULT_GOAL := aide
.PHONY: aide up down build logs sh console composer db droits

aide: ## Affiche cette aide
	@grep -E '^[a-z]+:.*## ' $(MAKEFILE_LIST) | awk -F ':.*## ' '{printf "  make %-10s %s\n", $$1, $$2}'

up: ## Démarre les conteneurs
	docker compose up -d

down: ## Arrête les conteneurs
	docker compose down

build: ## Reconstruit l'image puis démarre
	docker compose up -d --build

logs: ## Suit les logs de l'application
	docker compose logs -f app

sh: ## Ouvre un shell dans le conteneur
	$(EXEC) bash

console: ## Lance bin/console   ex : make console c="make:entity"
	$(EXEC) php bin/console $(c)

composer: ## Lance composer       ex : make composer c="require symfony/mailer"
	$(EXEC) composer $(c)

db: ## Ouvre le client SQL
	docker compose exec db mariadb -u root -p$$(grep '^DB_ROOTPASS=' .env | cut -d= -f2) $$(grep '^DB_NAME=' .env | cut -d= -f2)

droits: ## Rend le projet à ton utilisateur (si des fichiers sont à root)
	docker compose exec -u root app chown -R $$(id -u):$$(id -g) /var/www/html
