FROM wordpress:6.9.1-php8.3-apache

ARG WOOCOMMERCE_VERSION=10.8.1

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends unzip; \
    rm -rf /var/lib/apt/lists/*; \
    curl -fsSL --retry 4 --retry-delay 3 "https://downloads.wordpress.org/plugin/woocommerce.${WOOCOMMERCE_VERSION}.zip" -o /tmp/woocommerce.zip; \
    unzip -q /tmp/woocommerce.zip -d /usr/src/wordpress/wp-content/plugins/; \
    rm /tmp/woocommerce.zip; \
    curl -fsSL --retry 4 --retry-delay 3 "https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar" -o /usr/local/bin/wp; \
    chmod +x /usr/local/bin/wp; \
    wp --allow-root --info; \
    a2enmod expires headers rewrite remoteip

COPY wp-content/themes/supreme-autoparts/ /usr/src/wordpress/wp-content/themes/supreme-autoparts/
COPY wp-content/plugins/supreme-autoparts-core/ /usr/src/wordpress/wp-content/plugins/supreme-autoparts-core/
COPY wp-content/mu-plugins/ /usr/src/wordpress/wp-content/mu-plugins/
COPY wp-content/db.php /usr/src/wordpress/wp-content/db.php
COPY wp-content/db-error.php /usr/src/wordpress/wp-content/db-error.php
COPY data/products.csv data/vehicle-hierarchy.json /opt/supreme/data/
COPY deploy/healthz.php /usr/src/wordpress/healthz.php
COPY deploy/db-healthz.php /usr/src/wordpress/db-healthz.php
COPY deploy/wordpress.htaccess /usr/src/wordpress/.htaccess
COPY deploy/railway-entrypoint.sh /usr/local/bin/railway-entrypoint
COPY deploy/supreme-bootstrap.sh /usr/local/bin/supreme-bootstrap
COPY deploy/apache-security.conf /etc/apache2/conf-available/supreme-security.conf

RUN sed -i 's/\r$//' /usr/local/bin/railway-entrypoint /usr/local/bin/supreme-bootstrap \
    && chmod +x /usr/local/bin/railway-entrypoint /usr/local/bin/supreme-bootstrap \
    && a2enconf supreme-security \
    && chown -R www-data:www-data /usr/src/wordpress/wp-content

EXPOSE 80
ENTRYPOINT ["railway-entrypoint"]
CMD ["apache2-foreground"]
