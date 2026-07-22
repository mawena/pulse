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
- [ ] Dashboard : CPU %, load, RAM, swap, disques, débit réseau se rafraîchissent (~5 s)
- [ ] Processus : liste visible, kill d'un processus de test fonctionne (`sleep 300 &`)
- [ ] Services LNMP : statuts corrects ; `reload` nginx fonctionne depuis l'UI
- [ ] Audit Trail : les actions kill/restart apparaissent avec utilisateur + IP
- [ ] Compte observer : pas de bouton kill/restart, accès users refusé
- [ ] `sudo -l -U www-data` ne liste QUE pulse-kill et pulse-service

## Dépannage

| Symptôme | Cause probable |
|---|---|
| Kill/restart → « sudo: a password is required » | `install-security.sh` non exécuté ou mauvais utilisateur PHP-FPM dans sudoers |
| Statuts services `unknown` | Unité systemd absente — ajuster `PULSE_SERVICE_*` dans `.env` |
| CPU % reste vide | Normal au 1er appel (calcul par delta) ; vérifie le cache Laravel sinon |
| 419/401 sur l'API | `config:cache` obsolète après édition du .env → relancer `php artisan config:cache` |
