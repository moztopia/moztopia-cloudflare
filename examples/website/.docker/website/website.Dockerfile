FROM node:24.22.2-bookworm-slim AS assets
WORKDIR /build
COPY examples/website/package.json examples/website/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY examples/website/resources ./resources
COPY examples/website/tsconfig.json ./
RUN npm run check && npm run build

FROM composer:2 AS dependencies
WORKDIR /workspace/package
COPY composer.json VERSION LICENSE ./
COPY src ./src
COPY config ./config
WORKDIR /workspace/package/examples/website
COPY examples/website/composer.json examples/website/composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-progress

FROM php:8.5.13-cli-bookworm
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /workspace/package
COPY composer.json VERSION LICENSE ./
COPY src ./src
COPY config ./config
WORKDIR /workspace/package/examples/website
COPY examples/website ./
COPY --from=dependencies /workspace/package/examples/website/vendor ./vendor
COPY --from=assets /build/public/assets ./public/assets
RUN cp .env.example .env && composer dump-autoload --optimize && composer check-platform-reqs \
    && mkdir -p .phpunit.cache .pest vendor/pestphp/pest-plugin-mutate/.temp \
    && chown -R www-data:www-data bootstrap/cache storage .phpunit.cache .pest vendor/pestphp/pest-plugin-mutate/.temp
USER www-data
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
