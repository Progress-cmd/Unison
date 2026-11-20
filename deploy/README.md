# unison — musique (public, sauf l'administration)

À copier dans `/srv/stacks/unison/README.md`, avec `deploy/compose.yaml` comme `compose.yaml`.

| | |
| --- | --- |
| Adresses | https://unison.pi5.ovh (public, **sans** `/admin`) ; https://unison-admin.pi5.ovh (privé, Netbird : tout, administration comprise) |
| Conteneurs | `unison-app` (Apache + PHP, image locale `unison-app:v2`), `unison-worker` (imports : yt-dlp, ffmpeg ; même image), `unison-db` (MariaDB 12.2.2) |
| Code de l'appli | `/srv/stacks/unison/app` : dépôt GitHub `Progress-cmd/Unison`, sur un tag (`v2.x.y`), ignoré par le Git des stacks mais présent dans la sauvegarde |
| Données | `/srv/data/unison/{db,music,covers,youtube}` |
| Secrets | `/srv/secrets/unison.env` (modèle : `app/deploy/unison.env.exemple`) : base, `APP_SECRET`, clé Resend |
| Réseaux | `edge` (Traefik) et `internal` pour l'app ; `internal` et `outbound` (Internet, pour YouTube) pour le worker ; `internal` seul pour la base |

## Utiliser

- État : `sudo docker compose ps` ; journaux : `sudo docker logs --tail 50 unison-app` (ou `unison-worker`).
- Commandes Symfony : `sudo docker exec -u www-data unison-app php bin/console <commande>`.
- Cookies YouTube (vidéos avec vérification d'âge) : `/srv/data/unison/youtube/cookies.txt`, format Netscape, compte secondaire, propriétaire `www-data` (le conteneur corrige le propriétaire du dossier au démarrage), droits 600.
- Les erreurs des imports, des recherches et du serveur sont sur la page **Erreurs** de l'administration.

## Mettre à jour

L'image se **reconstruit** sur place depuis `./app` (cible `production` du `Dockerfile`). Au démarrage, `unison-app` applique seul les migrations de la base.

```bash
git -C /srv/stacks/unison/app fetch --tags
git -C /srv/stacks/unison/app checkout v2.x.y        # la version voulue
sudo update.sh unison --build                       # dump de la base, reconstruction, attente de la santé
```

Revenir en arrière : `checkout` du tag précédent, puis `sudo update.sh unison --build`. Si la version abandonnée avait migré la base, recharger d'abord le dump pris par `update.sh` (`RESTORE.md` §3 et §4).

MariaDB est épinglée par digest : change son tag, puis `sudo update.sh unison`.

Mettre yt-dlp à jour (YouTube change souvent) : reconstruire l'image, `sudo update.sh unison --build`, qui retélécharge la dernière version.

## Restaurer

Base : `RESTORE.md` §4 (`sudo restaurer-db unison-db`, après avoir arrêté `app` et `worker`). Musiques, pochettes et cookies : fichiers de la sauvegarde.
Reconstruction complète : `sudo docker compose build app`, puis base et reste comme au §6.5 de `RESTORE.md`.

## Installation de la v2 (remplace la v1)

À faire une seule fois. La v1 s'arrête ; ses fichiers restent sur le disque pour revenir en arrière.

1. **Arrêter la v1**, garder un dump de sa base, et mettre ses données de côté. Le dump quotidien ne suffit pas : il s'appelle `unison-db.sql.gz` comme celui de la v2, qui l'écrasera dès la première nuit.
   ```bash
   cd /srv/stacks/unison && sudo docker compose stop app             # plus d'écriture dans la base
   sudo docker exec unison-db sh -c 'exec mariadb-dump -uroot -p"$MARIADB_ROOT_PASSWORD" --single-transaction "$MARIADB_DATABASE"' \
     | gzip | sudo tee /srv/data/unison/v1-final.sql.gz >/dev/null
   sudo chmod 600 /srv/data/unison/v1-final.sql.gz                    # mots de passe (hashés) et e-mails dedans
   sudo gunzip -c /srv/data/unison/v1-final.sql.gz | grep -c '^INSERT INTO'   # doit afficher un nombre, pas 0
   sudo docker compose down
   sudo mv /srv/data/unison /srv/data/unison-v1
   sudo mv /srv/stacks/unison/app /srv/stacks/unison/app-v1
   sudo cp /srv/secrets/unison.env /srv/secrets/unison-v1.env
   ```
2. **Le code de la v2** : `git clone https://github.com/Progress-cmd/Unison.git /srv/stacks/unison/app`, puis `git -C /srv/stacks/unison/app checkout v2.x.y`.
3. **Les fichiers de la stack** : copier `app/deploy/compose.yaml` en `/srv/stacks/unison/compose.yaml` et `app/deploy/README.md` en `/srv/stacks/unison/README.md`.
4. **Les secrets** : réécrire `/srv/secrets/unison.env` d'après `app/deploy/unison.env.exemple` (droits 600). La clé Resend est celle de la v1 (`unison-v1.env`).
5. **Les dossiers** : `sudo install -d /srv/data/unison/{db,music,covers,youtube}`. Reprendre les cookies de la v1 : `sudo cp -a /srv/data/unison-v1/music/.config/cookies.txt /srv/data/unison/youtube/`.
6. **Démarrer** : `cd /srv/stacks/unison && sudo docker compose config -q && sudo docker compose up -d --build`, attendre `healthy` (`sudo docker compose ps`).
7. **Vérifier** : https://unison-admin.pi5.ovh répond (Netbird), https://unison.pi5.ovh/admin répond 404, `sudo ss -tlnp` ne montre aucun nouveau port.
8. **Reprendre les données de la v1** : section suivante.
9. **Commiter** : `git -C /srv/stacks add -A && git -C /srv/stacks commit -m "unison : v2"`. Dans `/srv/stacks/RESTORE.md`, le dépôt de l'appli devient `Progress-cmd/Unison`.

Revenir à la v1 : `sudo docker compose down`, remettre `unison-v1` / `app-v1` / `unison-v1.env` à leur place et l'ancien `compose.yaml` (`git -C /srv/stacks checkout <commit> -- unison/compose.yaml`), puis `sudo docker compose up -d`.

## Reprendre les données de la v1

Repris : les comptes (même e-mail, **même mot de passe**, même thème), tous les titres, les playlists dans leur ordre, les favoris, l'historique d'écoute, et les dates d'origine. Pas repris : le compte `system`, les « Wait Tracks », les messages, annonces, notes et artistes favoris.

Les musiques ne sont pas copiées : elles sont **retéléchargées** (titres et pochettes plus propres). Le site reste utilisable pendant ce temps, la bibliothèque se remplit au fil de l'eau.

1. **Charger le dump** dans la base de la v2, sous le nom `unison_v1`, lisible (seulement) par l'appli :
   ```bash
   sudo docker exec unison-db sh -c 'exec mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" -e "CREATE DATABASE unison_v1; GRANT SELECT ON unison_v1.* TO unison@\"%\""'
   sudo gunzip -c /srv/data/unison-v1/v1-final.sql.gz | sudo docker exec -i unison-db sh -c 'exec mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" unison_v1'
   ```
2. **Un raccourci** pour lancer les commandes avec l'adresse de cette base (le mot de passe vient de `DATABASE_URL`, rien à taper) :
   ```bash
   legacy() { sudo docker exec -u www-data unison-app sh -c 'LEGACY_DATABASE_URL="${DATABASE_URL%%\?*}_v1?${DATABASE_URL#*\?}" exec php bin/console "$@"' sh "$@"; }
   ```
3. **Les comptes, et les titres en file de téléchargement** :
   ```bash
   legacy app:legacy:import --admin=<nom> --limit=3   # <nom> : ton nom d'utilisateur de la v1, qui devient admin ; d'abord trois titres, pour voir
   legacy app:legacy:import --admin=<nom>             # puis tout
   ```
   Le worker télécharge un titre à la fois : compter une nuit, voire plus. Suivi : page **Importer**, ou `sudo docker exec -u www-data unison-app php bin/console messenger:stats async`.
4. **Rattacher** playlists, favoris, écoutes et dates, une fois la file vide (possible aussi en cours de route : il ne rattache que ce qui est téléchargé) :
   ```bash
   legacy app:legacy:link
   ```
   Il dit combien de titres manquent et pourquoi. **En échec** (YouTube refuse parfois une série de téléchargements, ou une vidéo a disparu) : relancer `legacy app:legacy:import` (il ne redemande que ceux-là), attendre, puis `legacy app:legacy:link` à nouveau. Les deux commandes se relancent sans risque : rien n'est créé en double, et l'ordre d'une playlist réorganisée entre-temps n'est pas touché.
5. **Prévenir** les comptes : même adresse, même e-mail, même mot de passe.
6. **Ranger**, quand tout est là : `sudo docker exec unison-db sh -c 'exec mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" -e "DROP DATABASE unison_v1; REVOKE ALL ON unison_v1.* FROM unison@\"%\""'`. Le dump reste dans `/srv/data/unison-v1/v1-final.sql.gz`.
