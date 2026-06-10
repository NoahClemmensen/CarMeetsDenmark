#!/usr/bin/env bash
set -euo pipefail

# Generate demo/test data on the running carmeets stack.
#
# This execs into a running php task and runs the `app:seed-demo-full` console
# command, which creates fake users (with avatars), teams, events, posts and
# attendance using the car/face images baked into the image under
# docker/test_data. Run this on a Swarm node that has a php replica.
#
# Any extra arguments are passed straight through to the command, e.g.:
#   ./test_data.sh --users=50
#   ./test_data.sh --images=/var/www/docker/test_data/car_images

STACK_NAME="${STACK_NAME:-carmeets}"
SERVICE="${STACK_NAME}_php"

# Find a php task container running on THIS node. uploads_data is a per-node
# volume, so the seeded images live on whichever node actually runs the command.
CONTAINER="$(docker ps -q -f "name=${SERVICE}" | head -n1)"

if [ -z "$CONTAINER" ]; then
  echo "Error: no running '${SERVICE}' task found on this node." >&2
  echo "Run this on a node that hosts a php replica. Replica placement:" >&2
  docker service ps "$SERVICE" --filter desired-state=running 2>/dev/null >&2 || true
  exit 1
fi

echo "Seeding demo data via ${SERVICE} (container ${CONTAINER}) ..."
docker exec "$CONTAINER" php bin/console app:seed-demo-full "$@"
echo "Done."
