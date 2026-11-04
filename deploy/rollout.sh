#!/bin/sh
# Redeploy without a gap. `docker compose up -d --build` stops the old app
# container before the new one is up, and the proxy answers 404 meanwhile.
# Here the new container starts beside the old one; the old goes only once
# the new is healthy and the proxy (Traefik or Caddy) has picked it up.
#
#   ./rollout.sh            # from this directory, after git pull
#
# Its migrations run while the old code still serves: they must work for the
# previous release too (add first, remove one release later).
set -eu
cd "$(dirname "$0")"

# A published image (ghcr.io, the default of compose.standalone.yaml) is
# pulled; anything else is built here. --pull: the base image's tag moves
# with every PHP and Alpine patch, but without it the build keeps the copy
# already on the server.
if docker compose config --images | grep -q '^ghcr\.io/'; then
  docker compose pull app
else
  docker compose build --pull app
fi

old=$(docker compose ps -q app)
if [ -z "$old" ]; then
  docker compose up -d
  exit 0
fi

docker compose up -d --no-deps --no-recreate --scale app=2 app
new=$(docker compose ps -q app | grep -vxF "$old")

echo "Waiting for the new container to become healthy ..."
tries=0
until [ "$(docker inspect -f '{{.State.Health.Status}}' "$new")" = healthy ]; do
  tries=$((tries + 1))
  if [ "$tries" -ge 90 ] || [ "$(docker inspect -f '{{.State.Health.Status}}' "$new")" = unhealthy ]; then
    echo "The new container did not become healthy; the old one keeps serving." >&2
    docker logs --tail 50 "$new" >&2
    docker rm -f "$new" > /dev/null
    exit 1
  fi
  sleep 2
done

# Traefik reacts to the health event, throttled by two seconds; Caddy looks
# the containers up every five.
sleep 5
docker stop "$old" > /dev/null
docker rm "$old" > /dev/null

# Reverb runs from the same image; restart it only if it runs at all.
if [ -n "$(docker compose --profile realtime ps -q reverb)" ]; then
  docker compose --profile realtime up -d --no-deps reverb
fi

echo "Rolled out."
