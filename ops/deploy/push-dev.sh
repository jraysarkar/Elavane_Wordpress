#!/usr/bin/env bash
# Manual deploy to dev from a developer machine — same steps as the GitHub
# Actions workflow. Needs the deploy key at ~/.ssh/elvane_dev_deploy.
set -euo pipefail

HOST="${DEV_HOST:-167.71.233.143}"
KEY="${DEV_SSH_KEY_FILE:-$HOME/.ssh/elvane_dev_deploy}"
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"

rsync -az --delete -e "ssh -i $KEY -o IdentitiesOnly=yes" \
	--exclude .git --exclude .github \
	"$ROOT/" "deploy@$HOST:/home/deploy/incoming/"
ssh -i "$KEY" -o IdentitiesOnly=yes "deploy@$HOST" 'sudo /usr/local/bin/elvane-deploy dev'
