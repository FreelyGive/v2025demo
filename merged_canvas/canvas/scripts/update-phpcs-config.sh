#!/bin/bash

# This script updates the `core.phpcs.xml.dist` file to match Drupal 11.x.
#
# Usage:
#   sh scripts/update-phpcs-config.sh

# Ensure `curl` is installed before proceeding.
command -v curl >/dev/null 2>&1 || { echo "Error: curl is not installed." >&2; exit 1; }

# Navigate to the repo's root directory.
cd "$(dirname "$0")/../" || { echo "Error: Failed to change directory." >&2; exit 1; }

# Download the latest PHPCS configuration file from Drupal 11.x.
if ! curl --fail --silent --show-error -o core.phpcs.xml.dist \
  "https://git.drupalcode.org/project/drupal/-/raw/11.x/core/phpcs.xml.dist?ref_type=heads"; then
  echo "Error: Failed to download core.phpcs.xml.dist." >&2
  exit 1
fi

# Check if there are actual changes before committing.
if git diff --quiet; then
  echo "No changes detected in core.phpcs.xml.dist. Skipping commit."
  exit 0
fi

# Commit the updated file.
git add core.phpcs.xml.dist
git commit -m "Update core.phpcs.xml.dist to match Drupal 11.x."

# Show a success message.
echo
echo "Successfully updated core.phpcs.xml.dist."
echo "Run \`git show\` to review the commit."
