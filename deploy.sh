#!/usr/bin/env bash

# Frontend-only deployment, following ../adminpage/deploy.sh.
# PHP files and app metadata must already be installed on the server.
set -euo pipefail

cd -- "$(dirname -- "${BASH_SOURCE[0]}")"

VPS_USER="${VPS_USER:-root}"
VPS_HOST="${VPS_HOST:-62.171.172.160}"
VPS_NC_PATH="${VPS_NC_PATH:-/opt/nextcloud-stack/nextcloud}"
APP_NAME="${APP_NAME:-employee_dashboard}"
CONTAINER_NAME="${CONTAINER_NAME:-nextcloud-app}"

if [ "$VPS_HOST" = "your-vps-ip" ]; then
  echo "Error: Set VPS_HOST to your server's hostname or IP address."
  exit 1
fi

for command in npm rsync ssh; do
  if ! command -v "$command" >/dev/null 2>&1; then
    echo "Error: Required command '$command' is not installed."
    exit 1
  fi
done

# These values are also passed as arguments to the remote shell.
if [[ ! "$APP_NAME" =~ ^[a-zA-Z0-9_-]+$ ]] ||
   [[ ! "$CONTAINER_NAME" =~ ^[a-zA-Z0-9_.-]+$ ]]; then
  echo "Error: APP_NAME or CONTAINER_NAME contains unsupported characters."
  exit 1
fi

echo "Building frontend assets..."
npm run build

echo "Syncing frontend files to VPS..."
rsync -avz --delete \
  ./js/ "$VPS_USER@$VPS_HOST:$VPS_NC_PATH/custom_apps/$APP_NAME/js/"

echo "Setting permissions and reloading the app inside Docker..."
ssh "$VPS_USER@$VPS_HOST" "bash -s -- '$CONTAINER_NAME' '$APP_NAME'" <<'REMOTE'
set -euo pipefail
container_name="$1"
app_name="$2"

docker exec -u root "$container_name" \
  chown -R www-data:www-data "/var/www/html/custom_apps/$app_name/js"
docker exec -u www-data "$container_name" php occ app:disable "$app_name"
docker exec -u www-data "$container_name" php occ app:enable "$app_name"
REMOTE

echo "Frontend deployment finished successfully!"
