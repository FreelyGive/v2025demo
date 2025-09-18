#!/usr/bin/env bash
if [ -n "${DEBUG_SCRIPT:-}" ]; then
  set -x
fi
set -eu -o pipefail
cd $APP_ROOT

LOG_FILE="logs/init-$(date +%F-%T).log"
exec > >(tee $LOG_FILE) 2>&1

TIMEFORMAT=%lR
# For faster performance, don't audit dependencies automatically.
export COMPOSER_NO_AUDIT=1
# For faster performance, don't install dev dependencies.
export COMPOSER_NO_DEV=1

# Install VSCode Extensions
if [ -n "${DP_VSCODE_EXTENSIONS:-}" ]; then
  IFS=','
  for value in $DP_VSCODE_EXTENSIONS; do
    time code-server --install-extension $value
  done
fi

#== Remove root-owned files.
echo
echo Remove root-owned files.
time sudo rm -rf lost+found

#== Composer install.
echo
if [ -f composer.json ]; then
  if composer show --locked cweagans/composer-patches ^2 &> /dev/null; then
    echo 'Update patches.lock.json.'
    time composer prl
    echo
  fi
else
  echo 'Generate composer.json.'
  time source .devpanel/composer_setup.sh
  echo
fi
# If update fails, change it to install.
time composer -n update --no-dev --no-progress

#== Create the private files directory.
if [ ! -d private ]; then
  echo
  echo 'Create the private files directory.'
  time mkdir private
fi

#== Create the config sync directory.
if [ ! -d config/sync ]; then
  echo
  echo 'Create the config sync directory.'
  time mkdir -p config/sync
fi

#== Generate hash salt.
if [ ! -f .devpanel/salt.txt ]; then
  echo
  echo 'Generate hash salt.'
  time openssl rand -hex 32 > .devpanel/salt.txt
fi

#== Pre-install starter recipe.
echo
if [ -z "$(drush status --field=db-status)" ]; then
  echo 'Install Drupal base system.'
  while [ -z "$(drush sget recipe_installer_kit.profile_modules_installed 2> /dev/null)" ]; do
    time .devpanel/install
  done
  drush sdel recipe_installer_kit.profile_modules_installed

  echo
  echo 'Apply required recipes.'
  RECIPES=$(drush sget --format=yaml recipe_installer_kit.required_recipes | grep '^  - .\+/.\+' | cut -f 4 -d ' ')
  RECIPES_PATH=$(drush --include=.devpanel/drush crp)
  RECIPES_APPLIED=''
  for RECIPE in $RECIPES; do
    RECIPE_PATH=$RECIPES_PATH/${RECIPE##*/}
    if [ -d $RECIPE_PATH ]; then
      until time drush --include=.devpanel/drush -q recipe $RECIPE_PATH; do
        time drush cr
      done

      if [ -n "$RECIPES_APPLIED" ]; then
        RECIPES_APPLIED="$RECIPES_APPLIED,\"$RECIPE\""
      else
        RECIPES_APPLIED="\"$RECIPE\""
      fi
    fi
  done
  drush sdel recipe_installer_kit.required_recipes
  drush sset --input-format=yaml installer.applied_recipes "[$RECIPES_APPLIED]"

  echo
  echo 'Tell Automatic Updates about patches.'
  drush -n cset --input-format=yaml package_manager.settings additional_trusted_composer_plugins '["cweagans/composer-patches"]'
  drush -n cset --input-format=yaml package_manager.settings additional_known_files_in_project_root '["patches.json", "patches.lock.json"]'
  time drush ev '\Drupal::moduleHandler()->invoke("automatic_updates", "modules_installed", [[], FALSE])'

  echo
  time drush cr
else
  echo 'Update database.'
  time drush -n updb
fi

#== Warm up caches.
echo
echo 'Run cron.'
time drush cron
echo
echo 'Populate caches.'
time drush cache:warm

#== Finish measuring script time.
INIT_DURATION=$SECONDS
INIT_HOURS=$(($INIT_DURATION / 3600))
INIT_MINUTES=$(($INIT_DURATION % 3600 / 60))
INIT_SECONDS=$(($INIT_DURATION % 60))
printf "\nTotal elapsed time: %d:%02d:%02d\n" $INIT_HOURS $INIT_MINUTES $INIT_SECONDS
