#!/bin/bash

set -e

cd /var/www/html

echo "Instalando dependências do Composer..."

if [ -f composer.json ]; then
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

echo "Iniciando Apache..."

exec apache2-foreground