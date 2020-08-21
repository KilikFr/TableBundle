#!/usr/bin/env bash

docker run -it --rm -u ${UID} -v `pwd`:/app -v `pwd`/.composer:/.composer -w /app kilik/php:7.2-stretch-dev composer install
