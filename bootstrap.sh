#!/usr/bin/env sh

php bin/console lexik:jwt:generate-keypair --env=test

php bin/console doctrine:database:create --env=dev
php bin/console doctrine:database:create --env=test
php bin/console doctrine:migrations:migrate --env=dev -n
php bin/console doctrine:migrations:migrate --env=test -n
php bin/console doctrine:fixtures:load -n

mkdir -p var/files
