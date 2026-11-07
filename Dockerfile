FROM php:8.4-apache

# UID/GID de l'utilisateur de la machine : les fichiers créés dans le conteneur
# t'appartiennent aussi côté VS Code (sinon ils seraient à root).
ARG UID=1000
ARG GID=1000

# Paquets système, puis extensions PHP :
#   pdo_mysql : parler à MariaDB
#   intl      : dates, langues et formats (utilisé par Symfony)
#   zip       : Composer télécharge plus vite les archives
#   ffmpeg    : l'audio et la forme d'onde
#   python3   : yt-dlp est écrit en Python
#   quickjs   : Youtube chiffre les URL des flux, il faut permettre à yt-dlp de les déchiffrer
RUN apt-get update && apt-get install -y --no-install-recommends \
      unzip libicu-dev libzip-dev \
      ffmpeg python3 quickjs curl \
 && docker-php-ext-install pdo_mysql intl zip \
 && rm -rf /var/lib/apt/lists/*

# on prend la release GitHub plutôt que le paquet Debian, parce que yt-dlp doit suivre les changements de YouTube de très près.
RUN curl -fL https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp \
    -o /usr/local/bin/yt-dlp \
    && chmod +x /usr/local/bin/yt-dlp

# Apache tourne sous l'identité de ton utilisateur.
RUN groupmod -g ${GID} www-data && usermod -u ${UID} www-data

# La racine web est public/ : le reste du projet n'est jamais servi en HTTP.
# FallbackResource envoie toute URL qui n'est pas un fichier vers index.php,
# ce qui remplace le .htaccess et mod_rewrite.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && printf '<Directory ${APACHE_DOCUMENT_ROOT}>\n    FallbackResource /index.php\n</Directory>\n' \
      > /etc/apache2/conf-available/symfony.conf \
 && a2enconf symfony

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_HOME=/tmp/composer

WORKDIR /var/www/html
