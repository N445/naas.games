ENV_FILES := --env-file .env $(if $(wildcard .env.local),--env-file .env.local)

ifeq (prod,$(filter prod,$(MAKECMDGOALS)))
DC := docker compose $(ENV_FILES) -f compose.prod.yaml
else
DC := docker compose $(ENV_FILES)
endif

IMAGE   ?= ghcr.io/n445/naas-games
TAG     ?= $(shell git rev-parse --short HEAD)
CHANNEL ?= $(shell git rev-parse --abbrev-ref HEAD)

.PHONY: prod up start down logs sh install lint build-prod cache-clear

prod:
	@:

up: start install

start:
	@mkdir -p var storage/screenshots public/media/cache
	$(DC) up -d --build

down:
	$(DC) down

logs:
	$(DC) logs -f

sh:
	$(DC) exec naas-games-php bash

install:
	$(DC) exec -T naas-games-php composer install
	$(DC) exec -T naas-games-php yarn install
	$(DC) exec -T naas-games-php yarn build
	$(DC) exec -T naas-games-php bin/console doctrine:migrations:migrate -n --allow-no-migration

lint:
	$(DC) exec -T naas-games-php composer validate --no-check-publish
	$(DC) exec -T naas-games-php composer audit
	$(DC) exec -T naas-games-php bin/console lint:yaml config translations
	$(DC) exec -T naas-games-php bin/console lint:twig templates
	$(DC) exec -T naas-games-php bin/console lint:container

# Le worker FrankenPHP garde le noyau en mémoire : sans redémarrage, l'ancien cache reste servi.
cache-clear:
	$(DC) exec -T naas-games-php bin/console cache:clear
	$(DC) restart naas-games-php

build-prod:
	@if [ -z "$$ALLOW_DIRTY" ] && [ -n "$$(git status --porcelain)" ]; then \
	  echo "Arbre de travail modifié : le tag $(TAG) ne correspondrait à aucun commit."; exit 1; fi
	docker build -f docker/Dockerfile --target prod --provenance=false --sbom=false \
	  --build-arg VERSION=$(TAG) --build-arg REVISION=$(shell git rev-parse HEAD) \
	  -t $(IMAGE):$(TAG) -t $(IMAGE):$(CHANNEL) .
