# unison — musique (public, sauf l'administration)

À copier dans `/srv/stacks/unison/README.md`, avec `deploy/compose.yaml` comme `compose.yaml`.

| | |
| --- | --- |
| Adresse | https://unison.pi5.ovh (public ; l'administration `/admin` est réservée aux comptes admin, la connexion se bloque 15 min après 5 échecs) |
| Conteneurs | `unison-app` (Apache + PHP, image locale `unison-app:v2`), `unison-worker` (imports : yt-dlp, ffmpeg ; même image), `unison-db` (MariaDB 12.2.2) |
| Code de l'appli | `/srv/stacks/unison/app` : dépôt GitHub `Progress-cmd/Unison`, sur un tag (`v2.x.y`), ignoré par le Git des stacks mais présent dans la sauvegarde |
| Données | `/srv/data/unison/{db,music,covers,youtube}` |
| Secrets | `/srv/secrets/unison.env` (modèle : `app/deploy/unison.env.exemple`) : base, `APP_SECRET`, clé Resend |
| Réseaux | `edge` (Traefik) et `internal` pour l'app ; `internal` et `outbound` (Internet, pour YouTube) pour le worker ; `internal` seul pour la base |

## Utiliser

- État : `sudo docker compose ps` ; journaux : `sudo docker logs --tail 50 unison-app` (ou `unison-worker`).
- Commandes Symfony : `sudo docker exec -u www-data unison-app php bin/console <commande>`.
- Cookies YouTube (vidéos avec vérification d'âge) : à envoyer depuis **Administration → Maintenance** (cookies.txt au format Netscape, d'un compte secondaire). Ils sont rangés dans `/srv/data/unison/youtube/cookies.txt`, propriétaire `www-data`, droits 600.
- Les erreurs des imports, des recherches et du serveur sont sur la page **Erreurs** de l'administration.
- Fichiers audio ou pochettes manquants, pochettes encore chez YouTube, fichiers orphelins, version de yt-dlp : **Administration → Maintenance** (« Réparer » passe par le worker). En ligne de commande, pour les seules pochettes : `sudo docker exec -u www-data unison-app php bin/console app:covers:localize`.

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

## Installation neuve

Pour monter la stack sur un serveur qui ne l'a pas (ou la remonter de zéro).

1. **Le code** : `git clone https://github.com/Progress-cmd/Unison.git /srv/stacks/unison/app`, puis `git -C /srv/stacks/unison/app checkout v2.x.y` (la dernière version).
2. **Les fichiers de la stack** : copier `app/deploy/compose.yaml` en `/srv/stacks/unison/compose.yaml` et `app/deploy/README.md` en `/srv/stacks/unison/README.md`.
3. **Les secrets** : écrire `/srv/secrets/unison.env` d'après `app/deploy/unison.env.exemple` (droits 600).
4. **Les dossiers** : `sudo install -d /srv/data/unison/{db,music,covers,youtube}`.
5. **Démarrer** : `cd /srv/stacks/unison && sudo docker compose config -q && sudo docker compose up -d --build`, attendre `healthy` (`sudo docker compose ps`). La base se crée au premier démarrage.
6. **Le premier compte**, admin : `sudo docker exec -u www-data unison-app php bin/console app:user:create <e-mail> <nom> <mot de passe> --admin`. Les suivants se créent depuis **Administration → Comptes** (un lien par e-mail pour choisir leur mot de passe). Les cookies YouTube s'envoient depuis **Administration → Maintenance**.
7. **Vérifier** : https://unison.pi5.ovh répond, `/admin` renvoie à la connexion tant qu'on n'est pas connecté avec un compte admin, `sudo ss -tlnp` ne montre aucun nouveau port.
8. **Commiter** : `git -C /srv/stacks add -A && git -C /srv/stacks commit -m "unison : installation"`.

## L'ancienne version

La v2 a remplacé la v1 en novembre 2026 (v2.23.0) : comptes, mots de passe, playlists, favoris, historique d'écoute et dates repris, musiques retéléchargées. Les commandes de cette reprise ont été retirées ensuite (v2.32.0). De la v1, il reste sur le disque ses données et son code dans `/srv/data/unison-v1` (dont le dump final de sa base, `v1-final.sql.gz`, droits 600), et ses secrets dans `/srv/secrets/unison-v1.env` : à supprimer quand plus rien ne les justifie.
