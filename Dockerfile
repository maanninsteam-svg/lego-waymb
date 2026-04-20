FROM php:8.2-apache

# Instalar extensões necessárias
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    libcurl4-openssl-dev \
    && docker-php-ext-install pdo_sqlite curl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Garantir apenas um MPM activo (evita erro "More than one MPM loaded")
RUN a2dismod mpm_event mpm_worker 2>/dev/null || true \
    && a2enmod mpm_prefork

# Activar mod_rewrite
RUN a2enmod rewrite

# Configuração do Apache — AllowOverride All
RUN echo '<Directory /var/www/html>\n\
    Options -Indexes +FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/app.conf \
    && a2enconf app

# Copiar ficheiros do projecto
COPY . /var/www/html/

# Criar script de arranque (inline com printf para garantir LF no Linux)
RUN printf '#!/bin/sh\n\
# 1. Gerar configs JSON a partir das variáveis de ambiente\n\
php /var/www/html/generate-configs.php\n\
# 2. Ajustar porta do Apache para a PORT injectada pela Railway\n\
PORT=${PORT:-80}\n\
sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf\n\
sed -i "s/<VirtualHost \\*:80>/<VirtualHost *:$PORT>/g" /etc/apache2/sites-enabled/000-default.conf\n\
# 3. Arrancar Apache\n\
exec apache2-foreground\n' > /start.sh && chmod +x /start.sh

# Criar directórios de dados e definir permissões
RUN mkdir -p /var/www/html/db /var/www/html/.utmify_pending \
    && chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \; \
    && chmod 777 /var/www/html/db /var/www/html/.utmify_pending \
    && chmod +x /start.sh

EXPOSE 80

CMD ["/start.sh"]
