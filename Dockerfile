FROM php:8.2-fpm

# Instalar Nginx + extensões PHP
RUN apt-get update && apt-get install -y \
    nginx \
    libsqlite3-dev \
    libcurl4-openssl-dev \
    && docker-php-ext-install pdo_sqlite curl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Configuração do Nginx
COPY nginx.conf /etc/nginx/sites-available/default

# Copiar ficheiros do projecto
COPY . /var/www/html/

# Criar directórios de dados e definir permissões
RUN mkdir -p /var/www/html/db /var/www/html/.utmify_pending \
    && chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \; \
    && chmod 777 /var/www/html/db /var/www/html/.utmify_pending

# Script de arranque com printf (garante LF no Linux)
RUN printf '#!/bin/sh\n\
set -e\n\
# 1. Gerar configs JSON a partir das variaveis de ambiente\n\
php /var/www/html/generate-configs.php\n\
# 2. Corrigir permissoes dos ficheiros gerados em runtime (root -> www-data)\n\
chown www-data:www-data \\\n\
    /var/www/html/admin-config.json \\\n\
    /var/www/html/tracking_config.json \\\n\
    /var/www/html/waymb-config.json \\\n\
    /var/www/html/up1/waymb-config.json 2>/dev/null || true\n\
chmod 664 \\\n\
    /var/www/html/admin-config.json \\\n\
    /var/www/html/tracking_config.json \\\n\
    /var/www/html/waymb-config.json \\\n\
    /var/www/html/up1/waymb-config.json 2>/dev/null || true\n\
# 3. Ajustar porta do Nginx para a PORT da Railway\n\
PORT=${PORT:-80}\n\
sed -i "s/listen 80;/listen $PORT;/" /etc/nginx/sites-available/default\n\
# 4. Iniciar PHP-FPM em background\n\
php-fpm -D\n\
# 5. Iniciar Nginx em foreground\n\
exec nginx -g "daemon off;"\n' > /start.sh && chmod +x /start.sh

EXPOSE 80

CMD ["/start.sh"]
