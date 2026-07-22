# Déploiement MawenaPulse — pulse.mawena.cloud

Prérequis : VPS Ubuntu/Debian avec la stack LNMP installée via
[mawena/lnmp](https://github.com/mawena/lnmp) (nginx, MySQL/MariaDB, PHP-FPM ≥ 8.3),
Composer 2, Node.js ≥ 20, git.

## 1. Récupérer le code

```bash
cd /var/www
git clone <repo> pulse
cd pulse
```

## 2. Base de données

```bash
sudo mysql <<'SQL'
CREATE DATABASE mawena_pulse_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'mawena_pulse_user'@'localhost' IDENTIFIED BY '<MOT_DE_PASSE_FORT>';
GRANT ALL PRIVILEGES ON mawena_pulse_db.* TO 'mawena_pulse_user'@'localhost';
FLUSH PRIVILEGES;
SQL
```

## 3. Application

```bash
cp .env.example .env
# Éditer .env :
#   APP_NAME=MawenaPulse
#   APP_ENV=production / APP_DEBUG=false
#   APP_URL=https://pulse.mawena.cloud
#   DB_* (identifiants créés ci-dessus)
#   PULSE_USE_SUDO=true
#   PULSE_SERVICE_PHP_FPM=php8.4-fpm   # adapter à la version installée

composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force          # rôles admin/observer + compte admin@mawena.cloud
npm ci && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache

chown -R www-data:www-data storage bootstrap/cache
```

> ⚠️ Le compte seedé `admin@mawena.cloud` a le mot de passe `password` avec
> changement obligatoire à la première connexion. Se connecter immédiatement
> et le changer.

## 4. Sécurité (sudoers + wrappers)

```bash
sudo bash deploy/install-security.sh
```

Le script installe `/usr/local/bin/pulse-kill`, `/usr/local/bin/pulse-service`,
la règle `/etc/sudoers.d/mawenapulse` (validée par `visudo -c`) et vérifie que
les wrappers rejettent signaux/PID/actions/unités hors whitelist.

Si le pool PHP-FPM tourne sous un autre utilisateur que `www-data`, adapter
`deploy/sudoers.d/mawenapulse` avant l'installation.

## 4 bis. Temps réel WebSocket (Reverb)

Le dashboard reçoit les métriques en temps réel via WebSocket. Deux services
systemd sont fournis : le serveur Reverb et la boucle d'échantillonnage.

```bash
sudo cp deploy/systemd/pulse-reverb.service /etc/systemd/system/
sudo cp deploy/systemd/pulse-stream.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now pulse-reverb pulse-stream
```

**Deux connexions distinctes** — ne pas confondre :

1. le **backend** (`pulse:stream`) publie les événements à Reverb **en local**
   (`127.0.0.1:8080`) → variables `REVERB_*` ;
2. le **navigateur** se connecte en `wss://` à l'**URL publique**, que nginx
   proxifie (`location /app/`) vers Reverb → variables `VITE_REVERB_*`,
   à définir **explicitement** (pas d'interpolation `${REVERB_HOST}`).

Dans `.env` de production :

```
# ⚠️ INDISPENSABLE — sans ça, les événements partent dans les logs et
# l'autorisation des canaux renvoie une réponse vide (temps réel muet,
# badge LIVE mais aucune mise à jour)
BROADCAST_CONNECTION=reverb

# Identifiants de l'application (générer des valeurs aléatoires)
REVERB_APP_ID=123456
REVERB_APP_KEY=...       # openssl rand -hex 16
REVERB_APP_SECRET=...    # openssl rand -hex 24
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"

# Backend → Reverb local (publication des événements)
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
# La liste des services systemd dépasse la limite par défaut de 10 Ko
REVERB_MAX_REQUEST_SIZE=262144
REVERB_APP_MAX_MESSAGE_SIZE=262144

# Navigateur → URL publique via nginx (incrustées au build !)
VITE_REVERB_HOST=pulse.mawena.cloud
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

Puis appliquer :

```bash
php artisan config:cache
npm run build          # les VITE_* sont figées dans le bundle au build
sudo systemctl restart pulse-reverb pulse-stream
```

> ⚠️ Les variables `VITE_*` sont lues **au moment du build**, pas à
> l'exécution : toute modification impose un `npm run build`. Si le
> WebSocket est indisponible, le frontend bascule automatiquement en
> polling HTTP (badge « POLL » orange dans la topbar au lieu de « LIVE »).

## 5. Nginx + TLS

```bash
sudo cp deploy/nginx/pulse.mawena.cloud.conf /etc/nginx/sites-available/
sudo ln -s /etc/nginx/sites-available/pulse.mawena.cloud.conf /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d pulse.mawena.cloud
```

## 6. Checklist de validation post-déploiement

- [ ] `https://pulse.mawena.cloud` affiche la page de login (thème sombre)
- [ ] Connexion `admin@mawena.cloud` → changement de mot de passe forcé
- [ ] Dashboard : badge « LIVE » vert (WebSocket) et métriques rafraîchies toutes les ~3 s
- [ ] `systemctl status pulse-reverb pulse-stream` : les deux services tournent
- [ ] Processus : liste visible, kill d'un processus de test fonctionne (`sleep 300 &`)
- [ ] Services LNMP : statuts corrects ; `reload` nginx fonctionne depuis l'UI
- [ ] Audit Trail : les actions kill/restart apparaissent avec utilisateur + IP
- [ ] Compte observer : pas de bouton kill/restart, accès users refusé
- [ ] `sudo -l -U www-data` ne liste QUE pulse-kill et pulse-service

## Dépannage

| Symptôme | Cause probable |
|---|---|
| `composer install` → « ext-dom / ext-xml is missing » | Plusieurs versions PHP installées : le CLI n'est pas celui attendu (vérifier les chemins `/etc/php/X.Y/cli/` dans l'erreur). Aligner le CLI sur la version FPM : `sudo update-alternatives --set php /usr/bin/php8.4`, puis `composer install --no-dev --optimize-autoloader` (jamais les dépendances dev en prod) |
| Badge LIVE mais métriques figées (mise à jour seulement en changeant de page) | `BROADCAST_CONNECTION` ≠ `reverb` dans `.env` (souvent resté sur `log` depuis un ancien `.env.example`) : l'auth de canal renvoie 200 vide et les événements partent dans les logs. Corriger, `php artisan config:cache`, redémarrer `pulse-stream`. Vérifiable en simulant un client WS : l'auth doit renvoyer `{"auth":"…"}` |
| `/api/*` récents en 404 après `git pull` | Cache de routes périmé → `php artisan optimize:clear && php artisan optimize` |
| Kill/restart → « sudo: a password is required » | `install-security.sh` non exécuté ou mauvais utilisateur PHP-FPM dans sudoers |
| Statuts services `unknown` | Unité systemd absente — ajuster `PULSE_SERVICE_*` dans `.env` |
| CPU % reste vide | Normal au 1er appel (calcul par delta) ; vérifie le cache Laravel sinon |
| 419/401 sur l'API | `config:cache` obsolète après édition du .env → relancer `php artisan config:cache` |
