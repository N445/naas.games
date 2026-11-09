# Naas Games

Symfony 8 + FrankenPHP. Chaque push sur `dev` ou `prod` construit les images
`ghcr.io/n445/naas-games` et `ghcr.io/n445/naas-games-vision`, puis déploie sur le serveur.

## Déployer en prod

### 1. Prérequis serveur

- Docker + Compose, un Traefik sur le réseau `main_traefik` (entrypoints `web` / `websecure`, resolver `letsencrypt`).
- Accès aux images (packages GHCR privés) : `echo <PAT read:packages> | docker login ghcr.io -u n445 --password-stdin`.

### 2. Créer le dossier

```bash
mkdir -p ~/web/naas.games && cd ~/web/naas.games
mkdir -p var storage/screenshots public/media/cache
```

Pour reprendre l'existant, copier les screenshots dans `storage/screenshots/`.

### 3. `compose.yaml`

Copie de [`compose.prod.yaml`](compose.prod.yaml), à coller tel quel :

```yaml
name: ${STACK_NAME:-naas-games}-prod

services:
  naas-games-php:
    container_name: ${STACK_NAME:-naas-games}_php
    image: ${IMAGE:-ghcr.io/n445/naas-games}:${TAG:-prod}
    restart: unless-stopped
    user: ${APP_UID:-1000}:${APP_GID:-1000}
    env_file:
      - path: .env
        required: true
      - path: .env.local
        required: false
    environment:
      APP_ENV: prod
      APP_DEBUG: "0"
      SERVER_NAME: ":8000"
      HOME: /tmp
      XDG_DATA_HOME: /tmp
      XDG_CONFIG_HOME: /tmp
      VISION_URL: http://vision:8000
      CADDY_SERVER_EXTRA_DIRECTIVES: |
        @immutable path /build/* /bundles/*
        handle @immutable {
          header Cache-Control "public, max-age=31536000, immutable"
          file_server
        }
        @media {
          path /media/cache/*
          not path /media/cache/resolve/*
        }
        header @media Cache-Control "public, max-age=604800"
    read_only: true
    tmpfs: [/tmp, /data, /config]
    cap_drop: [ALL]
    # frankenphp porte cap_net_bind_service=ep : sans elle le noyau refuse de l'exécuter.
    cap_add: [NET_BIND_SERVICE]
    security_opt: [no-new-privileges:true]
    volumes:
      - ./var:/app/var
      # En prod Symfony ne revalide jamais le conteneur compilé : le cache doit repartir de zéro à chaque image.
      - type: tmpfs
        target: /app/var/cache
        tmpfs:
          mode: 01777
      - ./storage:/app/storage
      - ./public/media/cache:/app/public/media/cache
    depends_on:
      database: { condition: service_healthy }
      vision: { condition: service_started }
    healthcheck:
      test: ["CMD", "curl", "-sS", "-o", "/dev/null", "-H", "Host: ${APP_DOMAIN}", "http://localhost:8000/"]
      interval: 10s
      timeout: 5s
      start_period: 60s
      retries: 5
    networks: [app, traefik]
    labels:
      - traefik.enable=true
      - traefik.docker.network=${TRAEFIK_NETWORK:-main_traefik}
      - traefik.http.services.${STACK_NAME:-naas-games}.loadbalancer.server.port=8000

      - traefik.http.routers.${STACK_NAME:-naas-games}.rule=Host(`${APP_DOMAIN}`)
      - traefik.http.routers.${STACK_NAME:-naas-games}.entrypoints=${TRAEFIK_ENTRYPOINT_HTTPS:-websecure}
      - traefik.http.routers.${STACK_NAME:-naas-games}.tls=true
      - traefik.http.routers.${STACK_NAME:-naas-games}.tls.certresolver=${TRAEFIK_CERTRESOLVER:-letsencrypt}
      - traefik.http.routers.${STACK_NAME:-naas-games}.middlewares=${STACK_NAME:-naas-games}-secure@docker

      - traefik.http.routers.${STACK_NAME:-naas-games}-http.rule=Host(`${APP_DOMAIN}`)
      - traefik.http.routers.${STACK_NAME:-naas-games}-http.entrypoints=${TRAEFIK_ENTRYPOINT_HTTP:-web}
      - traefik.http.routers.${STACK_NAME:-naas-games}-http.middlewares=${STACK_NAME:-naas-games}-redirect

      - traefik.http.middlewares.${STACK_NAME:-naas-games}-redirect.redirectscheme.scheme=https
      - traefik.http.middlewares.${STACK_NAME:-naas-games}-redirect.redirectscheme.permanent=true
      - traefik.http.middlewares.${STACK_NAME:-naas-games}-secure.chain.middlewares=${STACK_NAME:-naas-games}-headers,${STACK_NAME:-naas-games}-compress
      - traefik.http.middlewares.${STACK_NAME:-naas-games}-headers.headers.stsSeconds=31536000
      - traefik.http.middlewares.${STACK_NAME:-naas-games}-headers.headers.stsIncludeSubdomains=true
      - traefik.http.middlewares.${STACK_NAME:-naas-games}-headers.headers.contentTypeNosniff=true
      - traefik.http.middlewares.${STACK_NAME:-naas-games}-headers.headers.frameDeny=true
      - traefik.http.middlewares.${STACK_NAME:-naas-games}-headers.headers.referrerPolicy=strict-origin-when-cross-origin
      - traefik.http.middlewares.${STACK_NAME:-naas-games}-compress.compress=true

  vision:
    container_name: ${STACK_NAME:-naas-games}_vision
    image: ${VISION_IMAGE:-ghcr.io/n445/naas-games-vision}:${TAG:-prod}
    restart: unless-stopped
    networks: [app]
    volumes:
      - vision_models:/models

  database:
    container_name: ${STACK_NAME:-naas-games}_database
    image: mariadb:10.11
    restart: unless-stopped
    environment:
      MARIADB_DATABASE: ${DB_NAME:-app}
      MARIADB_USER: ${DB_USER:-app}
      MARIADB_PASSWORD: ${DB_PASSWORD}
      MARIADB_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
    networks: [app]
    volumes:
      - db_data:/var/lib/mysql
    healthcheck:
      test: ["CMD", "healthcheck.sh", "--connect", "--innodb_initialized"]
      interval: 5s
      retries: 10

volumes:
  db_data:
  vision_models:

networks:
  app:
  traefik:
    external: true
    name: ${TRAEFIK_NETWORK:-main_traefik}
```

### 4. `.env`

```dotenv
APP_SECRET=<openssl rand -hex 32>
APP_DOMAIN=naas.games
TRUSTED_PROXIES=127.0.0.1,private_ranges
ADMIN_PATH=/admin

DB_NAME=app
DB_USER=app
DB_PASSWORD=<openssl rand -hex 32>
DB_ROOT_PASSWORD=<openssl rand -hex 32>
DATABASE_URL="mysql://app:<DB_PASSWORD>@database:3306/app?serverVersion=10.11.0-MariaDB&charset=utf8mb4"
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0&redeliver_timeout=600

FLICKR_API_KEY=
FLICKR_API_SECRET=
FLICKR_USER_ID=
MISTRAL_API_KEY=
MISTRAL_MODEL=mistral-medium-latest
ANTHROPIC_API_KEY=
CLAUDE_MODEL=claude-opus-5-5

TAG=prod
APP_UID=1000
APP_GID=1000
```

`APP_UID` / `APP_GID` : ceux de l'utilisateur propriétaire du dossier (`id -u`, `id -g`).

### 5. Démarrer

```bash
docker compose pull
docker compose up -d
docker compose exec naas-games-php bin/console doctrine:migrations:migrate -n
docker compose exec naas-games-php bin/console app:user:create
```

Pour reprendre une base existante, l'importer avant les migrations :

```bash
docker compose exec -T database sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"' < dump.sql
```

### 6. Mettre à jour

À la main, avec [`Makefile.prod`](Makefile.prod) copié en `Makefile` sur le serveur :

```bash
make update          # sauvegarde la base, tire les images, redémarre, migre
TAG=a1b2c3d make update   # revenir à un commit précis
```

### 7. Automatiser

Installer un runner GitHub self-hosted sur le serveur (Settings → Actions → Runners) avec le label `prod`.
Le workflow [`deploy.yml`](.github/workflows/deploy.yml) redépose `compose.yaml` et `Makefile` dans
`~/web/naas.games` puis lance `make update` à chaque push sur `prod`. Le `.env` n'est jamais écrasé.

## Développer en local

```bash
make up    # construit, démarre, composer/yarn install, migrations
make sh    # shell dans le conteneur PHP
make lint
```

Site sur http://naas-games.local (Traefik local requis).
