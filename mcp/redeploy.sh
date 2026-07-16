#!/usr/bin/env bash
#
# Update the deployed Handbook MCP checkout to the latest of its branch, for
# hosts where the panel's "Build" does not pull from Git (e.g. Infomaniak
# Node.js sites). Run it over SSH from anywhere inside the deployed repo:
#
#   bash mcp/redeploy.sh          # updates to origin/main
#   bash mcp/redeploy.sh <branch> # updates to a different branch
#
# It fetches and hard-resets the working tree to the remote branch, so the
# deployed files match Git exactly. It does NOT restart the Node process — a
# plain pull leaves the running app on the old code, so afterwards click
# "Restart" in the hosting panel (or re-run the app) to reload the tools.
set -euo pipefail

branch="${1:-main}"

# Move to the repository root (this script lives in mcp/).
cd "$(dirname "$0")/.."

git fetch origin
git reset --hard "origin/${branch}"

echo "Deployed checkout now at: $(git log --oneline -1)"
echo "Next: click Restart in the Node.js panel so the app reloads the new tools."
