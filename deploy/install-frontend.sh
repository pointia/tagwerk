#!/bin/sh
# Points /var/www/app — what nginx serves at / — at the frontend to show:
#
#   TAGWERK_FRONTEND="acme/tagwerk-kiosk"        a package, installed with the
#   TAGWERK_FRONTEND="acme/tagwerk-kiosk:^1.0"   plugins (install-plugins.sh)
#
# Empty means Tagwerk's own app. The package names its built files in
# composer.json (`extra.tagwerk.app`, a directory with index.html and
# assets/); `tagwerk:frontend` checks them. A frontend that cannot be served
# stops the start: a blank page on every wall is worse than no new container,
# and rollout.sh keeps the old one running then.
set -eu

name="${TAGWERK_FRONTEND:-}"
name="${name%%:*}"

if [ -z "$name" ]; then
  ln -sfn /var/www/tagwerk-app /var/www/app
  exit 0
fi

if ! directory="$(su-exec www-data php artisan tagwerk:frontend "$name")"; then
  echo "TAGWERK_FRONTEND: $directory" >&2
  exit 1
fi

echo "Serving the frontend $name from $directory"
ln -sfn "$directory" /var/www/app
