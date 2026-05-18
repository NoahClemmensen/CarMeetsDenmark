# Docker Stack

Production deployment uses Docker Swarm via `docker-stack.yml`. Local development uses `docker-compose.yml` (see bottom of this file).

## Services

| Service | Image | Port | Purpose |
|---------|---------------------------------------------|--------------|----------------------------|
| `db` | `mysql:9.3` | `3306` | Application database |
| `php` | `ghcr.io/noahclemmensen/carmeetsdenmark` | `8000` | Symfony app (2 replicas) |
| `mailpit` | `axllent/mailpit` | `8025`, `1025` | SMTP catcher + web UI |

Networking: all services join the `symfony_net` overlay network. The `php` service reaches the database at `db:3306` and the mail catcher at `mailpit:1025`.

Volumes: `db_data`, `uploads_data`, `sessions_data` persist across redeploys.

## Prerequisites

- Docker Engine 20.10+ with Swarm mode initialized (`docker swarm init` on the manager node).
- Access to pull `ghcr.io/noahclemmensen/carmeetsdenmark:latest`. For private images run `docker login ghcr.io` first, then pass `--with-registry-auth` on deploy.
- Ports `3306`, `8000`, `8025`, and `1025` free on the host.

## Deploy the stack

From the repository root:

```bash
docker stack deploy -c docker/docker-stack.yml carmeets --with-registry-auth
```

This creates the stack named `carmeets`. The first deploy pulls images and starts containers; subsequent deploys perform a rolling update on `php` (`parallelism: 1`, `delay: 10s`, rollback on failure).

Verify everything is up:

```bash
docker stack services carmeets
docker stack ps carmeets
```

`REPLICAS` should read `1/1` for `db` and `mailpit`, and `2/2` for `php`. Once healthy:

- App: <http://localhost:8000>
- Mailpit UI: <http://localhost:8025>

## Update / redeploy

Re-running `docker stack deploy` with the same command applies any changes in `docker-stack.yml` and triggers a rolling update. To force a refresh of the `php` image without editing the file:

```bash
docker service update --image ghcr.io/noahclemmensen/carmeetsdenmark:latest --with-registry-auth carmeets_php
```

## Tear down

```bash
docker stack rm carmeets
```

Volumes survive `stack rm`. To wipe persistent data as well:

```bash
docker volume rm carmeets_db_data carmeets_uploads_data carmeets_sessions_data
```

## Debugging

### Inspect service state

```bash
docker stack ps carmeets --no-trunc           # task list, including failed attempts
docker service ps carmeets_php --no-trunc     # one service in detail
docker service inspect carmeets_php           # config, env, mounts
```

`--no-trunc` is important — the default truncates the error column where startup failures show up.

### Logs

```bash
docker service logs -f carmeets_php           # follow all replicas
docker service logs --tail 200 carmeets_db
docker service logs carmeets_mailpit
```

For a single replica, find its container ID via `docker stack ps carmeets` and run `docker logs <container-id>` on the node it's scheduled on.

### Exec into a running container

Swarm doesn't expose a direct `exec` for a service; target a task's container:

```bash
docker ps --filter "name=carmeets_php"
docker exec -it <container-id> bash
```

Useful commands once inside the `php` container:

```bash
php bin/console about
php bin/console doctrine:schema:validate
php bin/console debug:router
tail -f var/log/prod.log
```

### Database access

From inside the stack:

```bash
docker exec -it <db-container-id> mysql -uadmin -pstikadmin1bajer carmeetsdenmark
```

From the host (port `3306` is published): connect any MySQL client to `localhost:3306` with the same credentials.

### Common failures

- **`no such image` on deploy** — you're not logged into GHCR or forgot `--with-registry-auth`.
- **Port already in use** — another local process holds `8000`/`3306`/`8025`/`1025`. Free it or change the published port in `docker-stack.yml`.

### Reset just the database

```bash
docker stack rm carmeets
docker volume rm carmeets_db_data
docker stack deploy -c docker/docker-stack.yml carmeets --with-registry-auth
```

## Local development (compose, not swarm)

`docker/docker-compose.yml` builds the `php` image from source and runs `APP_ENV=dev`. From the `docker/` directory:

```bash
docker compose up --build
```

App runs on <http://localhost:8000>. Use `docker compose logs -f php` and `docker compose exec php bash` to debug — no swarm-specific commands needed here.
