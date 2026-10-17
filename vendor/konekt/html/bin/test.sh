#!/bin/bash

# Define the PHP version and container name
PHP_VERSION=8.4.0RC1
CONTAINER_NAME=php-test-runner

# Create a Docker container with PHP 8.4
docker run --rm \
    -v $(pwd):/app \
    -w /app \
    --name $CONTAINER_NAME \
    php:$PHP_VERSION-cli bash -c "
#    # Update and install necessary packages
    apt-get update && apt-get install -y unzip git && \

    # Install composer
    curl -sS https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer && \

    # Install dependencies
    composer install && \

    # Run the test suite
    vendor/bin/phpunit --display-deprecations
    "
