#!/bin/bash

composer install --no-interaction

php artisan optimize:clear
php artisan passport:install
#php artisan key:generate
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan serve --port=8000 --host=0.0.0.0