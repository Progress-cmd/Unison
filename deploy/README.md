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

1. **Arrêter la v1** et mettre ses données de côté :
   ```bash
   cd /srv/stacks/unison && sudo docker compose down
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
8. **Reprendre les données de la v1** : voir la section suivante (à venir).
9. **Commiter** : `git -C /srv/stacks add -A && git -C /srv/stacks commit -m "unison : v2"`.

Revenir à la v1 : `sudo docker compose down`, remettre `unison-v1` / `app-v1` / `unison-v1.env` à leur place et l'ancien `compose.yaml` (`git -C /srv/stacks checkout <commit> -- unison/compose.yaml`), puis `sudo docker compose up -d`.
