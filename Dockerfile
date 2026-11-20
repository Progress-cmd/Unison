# Trois étapes :
#   base       : PHP, Apache, ffmpeg, yt-dlp, communs au dev et à la prod
#   dev        : ton conteneur de travail (code monté depuis le PC, Composer)
#   production : l'image du serveur (code copié dedans, dépendances sans les outils de dev)
# compose.yaml (dev) construit la cible « dev » ; deploy/compose.yaml (serveur) la cible « production ».

FROM php:8.4-apache AS base

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


FROM base AS dev

# UID/GID de l'utilisateur de la machine : les fichiers créés dans le conteneur
# t'appartiennent aussi côté VS Code (sinon ils seraient à root).
ARG UID=1000
ARG GID=1000

# Apache tourne sous l'identité de ton utilisateur.
RUN groupmod -g ${GID} www-data && usermod -u ${UID} www-data


FROM base AS production

ENV APP_ENV=prod

# PHP de prod : OPcache sans relire les fichiers (le code ne change qu'en reconstruisant l'image),
# erreurs dans les journaux et jamais à l'écran.
COPY docker/php-prod.ini /usr/local/etc/php/conf.d/zz-unison.ini

# Apache : vraie adresse des visiteurs derrière Traefik, fichiers versionnés gardés en cache
# par le navigateur, pas de version d'Apache dans les réponses.
COPY docker/apache-prod.conf /etc/apache2/conf-available/unison-prod.conf
RUN a2enmod headers remoteip && a2enconf unison-prod

# Les dépendances d'abord : cette couche reste en cache tant que composer.json/lock ne bougent pas.
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress

COPY . .

# Symfony lit .env au démarrage : un .env minimal suffit, les vraies valeurs arrivent par
# les variables d'environnement du conteneur (/srv/secrets/unison.env), qui passent avant.
# Puis : autoloader optimisé, paquets JS de l'importmap, fichiers CSS/JS versionnés dans
# public/assets, cache Symfony préparé (le premier visiteur n'attend pas). Ces commandes
# lisent la configuration : les secrets reçoivent des valeurs bidon, jamais utilisées ensuite.
RUN printf 'APP_ENV=prod\nDEFAULT_URI=https://unison.pi5.ovh\nMUSIC_DIR=/data/music\n' > .env \
 && composer dump-autoload --no-dev --classmap-authoritative \
 && export APP_SECRET=build DATABASE_URL='mysql://build:build@localhost/build?serverVersion=12.2.2-MariaDB' \
      MESSENGER_TRANSPORT_DSN='doctrine://default' MAILER_DSN='null://null' MAILER_FROM=build@localhost \
 && php bin/console importmap:install \
 && php bin/console asset-map:compile \
 && php bin/console cache:warmup \
 && mkdir -p public/covers \
 && chown -R www-data:www-data var public/covers

COPY docker/entrypoint.sh /usr/local/bin/unison-entrypoint
RUN chmod +x /usr/local/bin/unison-entrypoint

ENTRYPOINT ["unison-entrypoint"]
CMD ["apache2-foreground"]

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
  CMD curl -fsS -o /dev/null http://localhost/login || exit 1
