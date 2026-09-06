#!/usr/bin/env bash
# Deploys this repo's backend/ code to the standalone production copy at
# /opt/mmc_backend and restarts the "mitt_mediearkiv" pm2 process.
#
# Why a separate deploy step: the pm2 process used to run directly from
# this git checkout (backend/.venv, cwd = this repo). That meant a running
# process kept whatever code was in memory since its last (re)start, but
# checking out a different branch here (e.g. to work on an unrelated
# feature) silently changed what code would load on the NEXT restart, with
# no relation to what was actually meant to be live. Now the deployed copy
# at /opt/mmc_backend is completely independent of whatever branch happens
# to be checked out here - only running this script updates it.
#
# Usage: ./deploy_backend.sh   (run from anywhere; always deploys the
# currently checked-out state of THIS repo's backend/ directory)
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEPLOY_DIR="/opt/mmc_backend"

echo "Syncing code from $SCRIPT_DIR to $DEPLOY_DIR ..."
rsync -a --delete \
  --exclude='.venv' \
  --exclude='__pycache__' \
  --exclude='*.pyc' \
  --exclude='logs' \
  --exclude='config/.env' \
  "$SCRIPT_DIR/app" "$SCRIPT_DIR/config" "$SCRIPT_DIR/requirements.txt" "$SCRIPT_DIR/ecosystem.config.js" \
  "$DEPLOY_DIR/"

mkdir -p "$DEPLOY_DIR/logs"

if [ ! -d "$DEPLOY_DIR/.venv" ]; then
  echo "Creating venv at $DEPLOY_DIR/.venv ..."
  python3 -m venv "$DEPLOY_DIR/.venv"
fi

echo "Installing/updating dependencies ..."
"$DEPLOY_DIR/.venv/bin/pip" install --upgrade pip -q
"$DEPLOY_DIR/.venv/bin/pip" install -r "$DEPLOY_DIR/requirements.txt" -q

if [ ! -f "$DEPLOY_DIR/config/.env" ]; then
  echo "WARNING: $DEPLOY_DIR/config/.env does not exist. It is intentionally" \
       "excluded from this sync (contains secrets, is gitignored)." \
       "Copy it there manually once (e.g. from $SCRIPT_DIR/config/.env)."
fi

echo "Sanity-checking that the app still imports ..."
"$DEPLOY_DIR/.venv/bin/python" -c "import sys; sys.path.insert(0, '$DEPLOY_DIR'); import app.server" || {
  echo "ERROR: app failed to import after deploy - NOT restarting pm2. Fix the error above first." >&2
  exit 1
}

echo "Applying pm2 process config (delete + start fresh from the deployed" \
     "ecosystem file, so script/cwd changes actually take effect - a plain" \
     "'pm2 restart' reuses whatever script/cwd the process was originally" \
     "started with, ignoring the file) ..."
pm2 delete mitt_mediearkiv >/dev/null 2>&1 || true
pm2 start "$DEPLOY_DIR/ecosystem.config.js"
pm2 save

echo "Done."
