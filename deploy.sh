#!/usr/bin/env bash
set -euo pipefail

# Deploy the carmeets stack to Docker Swarm.
#
# `docker stack deploy` does NOT read .env files, so we load the root .env into
# the environment first and verify the required variables are present before
# deploying. The stack file (docker/docker-stack.yml) interpolates ${...} from
# the environment.

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$SCRIPT_DIR/.env.prod"
STACK_FILE="$SCRIPT_DIR/docker/docker-stack.yml"
STACK_NAME="carmeets"

if [ ! -f "$ENV_FILE" ]; then
  echo "Error: .env.prod not found at $ENV_FILE" >&2
  exit 1
fi

# Export every variable defined in .env into the environment.
set -a
# shellcheck disable=SC1090
. "$ENV_FILE"
set +a

# Fail fast if anything the stack needs is missing or empty.
REQUIRED_VARS="MYSQL_DATABASE MYSQL_ROOT_PASSWORD MYSQL_USER MYSQL_PASSWORD APP_SECRET"
missing=""
for var in $REQUIRED_VARS; do
  if [ -z "${!var:-}" ]; then
    missing="$missing $var"
  fi
done
if [ -n "$missing" ]; then
  echo "Error: missing required variable(s) in $ENV_FILE:$missing" >&2
  exit 1
fi

echo "Deploying stack '$STACK_NAME' from $STACK_FILE ..."
docker stack deploy -c "$STACK_FILE" "$STACK_NAME" --with-registry-auth
