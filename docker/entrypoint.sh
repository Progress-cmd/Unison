#!/bin/sh
# Démarrage des conteneurs de production (app et worker, même image).
set -e

# Les dossiers montés depuis le serveur (/srv/data/unison/…) doivent appartenir à www-data,
# qui écrit les musiques et les pochettes. Seulement s'ils ne lui appartiennent pas déjà :
# sur des milliers de fichiers, un chown à chaque démarrage prendrait du temps.
for dir in "${MUSIC_DIR:-}" /var/www/html/public/covers; do
    if [ -n "$dir" ] && [ -d "$dir" ] && [ "$(stat -c %U "$dir")" != "www-data" ]; then
        chown -R www-data:www-data "$dir"
    fi
done

# Le conteneur web met la base à jour avant d'accepter des visiteurs. Le worker attend
# simplement que ce soit fait (il démarre après l'app, voir deploy/compose.yaml).
if [ "$1" = "apache2-foreground" ]; then
    setpriv --reuid=www-data --regid=www-data --init-groups \
        php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

    exec docker-php-entrypoint "$@"
fi

# Toute autre commande (le worker) tourne sous www-data.
exec setpriv --reuid=www-data --regid=www-data --init-groups "$@"
