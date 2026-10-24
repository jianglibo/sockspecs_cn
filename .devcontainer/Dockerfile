FROM php:8.3-cli-bookworm

ARG DEV_UID=1000
ARG DEV_GID=1000

RUN apt-get update \
	&& apt-get install --no-install-recommends --yes git unzip libzip-dev \
	&& docker-php-ext-install zip \
	&& rm -rf /var/lib/apt/lists/* \
	&& groupadd --gid "$DEV_GID" vscode \
	&& useradd --create-home --uid "$DEV_UID" --gid "$DEV_GID" --shell /bin/bash vscode

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node

USER vscode
WORKDIR /workspace
