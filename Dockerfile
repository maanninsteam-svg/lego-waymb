FROM php:8.2-apache

# Instalar extensões necessárias
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    libcurl4-openssl-dev \
    && docker-php-ext-install pdo_sqlite curl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Activar mod_rewrite para .htaccess
RUN a2enmod rewrite

# Configuração do Apache — AllowOverride All para suporte a .htaccess
RUN echo '<Directory /var/www/html>\n\
    Options -Indexes +FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/app.conf \
    && a2enconf app

# Copiar ficheiros do projecto
COPY . /var/www/html/

# Entrypoint script para ajustar a porta dinamicamente (Railway)
COPY docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh

# Criar directórios de dados e definir permissões
RUN mkdir -p /var/www/html/db /var/www/html/.utmify_pending \
    && chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \; \
    && chmod 777 /var/www/html/db /var/www/html/.utmify_pending \
    && chmod +x /docker-entrypoint.sh

EXPOSE 80

CMD ["/docker-entrypoint.sh"]
