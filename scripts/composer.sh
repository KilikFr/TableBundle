#!/bin/bash

PHP_IMAGE=kilik/php:7.2-stretch-dev

if [ -t 0 ]
then
	TTY_DOCKER=-it
else
	TTY_DOCKER=
fi

docker run ${TTY_DOCKER} --user ${UID} --rm -v ${PWD}:/app -v ${PWD}/.composer:/.composer -w /app ${PHP_IMAGE} \
composer "$@"
