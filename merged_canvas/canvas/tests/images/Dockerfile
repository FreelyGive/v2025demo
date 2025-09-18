# Testing image used for GitLab CI

FROM registry.gitlab.com/drupal-infrastructure/drupalci/drupalci-environments/php-8.3-ubuntu-apache:production

RUN sudo apt-get -y update
ENV NVM_DIR=/root/.nvm
RUN curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.3/install.sh | bash
RUN . /root/.nvm/nvm.sh && nvm install 24 && nvm alias default 24
RUN node -v
RUN corepack enable
RUN npx playwright install-deps

# Install projects so we can cache dependencies
# This can be removed when https://www.drupal.org/project/infrastructure/issues/3387117
# is implemented
COPY package.json package.json
COPY package-lock.json package-lock.json
RUN npm ci

RUN npx playwright install

COPY ui/package.json ui/package.json
COPY ui/package-lock.json ui/package-lock.json
COPY  ui/lib/astro-hydration/package.json  ui/lib/astro-hydration/package.json
RUN cd ui && npm install && cd ../

RUN composer create-project drupal/recommended-project _drupal
RUN cd _drupal && \
    composer config minimum-stability dev && \
    composer require drupal/core-dev "drupal/canvas @dev" drush/drush --with-all-dependencies && \
    cd web/modules/contrib/canvas && \
    composer run install-dev-deps && \
    cd ../../../../../

# Clean up
RUN rm -rf *
