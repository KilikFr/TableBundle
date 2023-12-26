#!/usr/bin/env bash

docker run -it --rm -u ${UID} -v `pwd`:/app -v `pwd`/.composer:/.composer -w /app kilik/php:8.3-dev composer install
