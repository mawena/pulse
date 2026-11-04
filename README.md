<p align="center">
  <img src="public/favicon.svg" width="72" alt="MawenaPulse">
</p>

<h1 align="center">MawenaPulse</h1>

<p align="center"><em>Les signes vitaux de votre serveur, en direct.</em></p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white" alt="Laravel 13">
  <img src="https://img.shields.io/badge/Vue-3-42B883?logo=vuedotjs&logoColor=white" alt="Vue 3">
  <img src="https://img.shields.io/badge/Vuetify-4-1867C0?logo=vuetify&logoColor=white" alt="Vuetify 4">
  <img src="https://img.shields.io/badge/Tests-Pest-8BC34A" alt="Pest">
</p>

---

**MawenaPulse** est un cockpit de monitoring pour VPS Ubuntu/Debian équipé de la
stack LNMP [mawena/lnmp](https://github.com/mawena/lnmp). Il affiche les
métriques système en temps réel, gère les processus et pilote les services,
avec des rôles, des permissions et un audit trail complet.

Déployé sur **[pulse.mawena.cloud](https://pulse.mawena.cloud)**.

## Fonctionnalités

- 📊 **Dashboard temps réel** : CPU (%, load 1/5/15), RAM & swap, disques,
  débit réseau IN/OUT, uptime/OS/kernel, graphiques ApexCharts. Les métriques
  sont échantillonnées côté serveur par `php artisan pulse:stream` (toutes les
  0,5 s par défaut) et poussées par **WebSocket (Laravel Reverb)** ; si le
  WebSocket est indisponible, le frontend bascule en polling HTTP de
  `/api/metrics` (toutes les 5 s). L'indicateur de la barre supérieure affiche
  `LIVE` (WebSocket) ou `POLL` (polling) sur toutes les pages.
- ⚙️ **Gestionnaire de processus** : liste `ps` triée par CPU, mise à jour en
  temps réel, recherche et tri, kill SIGTERM/SIGKILL avec confirmation
  (permission `manage` sur `system`).
- 🔁 **Services LNMP** : statut systemd de nginx / MySQL / PHP-FPM,
  restart et reload sécurisés depuis l'interface.
- 🧩 **Services système & jobs** : toutes les unités systemd de type service
  avec leur état, file de jobs Laravel (en attente / en cours / échoués) avec
  relance des jobs échoués.
- 👥 **Utilisateurs, rôles & permissions** : RBAC dynamique en base
  ([mawena/maravel](https://github.com/mawena/maravel)), rôles `admin`
  (accès complet) et `observer` (lecture seule du monitoring), permissions au
  format CASL exposées au frontend, gestion des utilisateurs, des rôles et de
  leurs permissions depuis l'interface.
- 📜 **Audit trail** : journal append-only des actions système (kill,
  restart/reload, relance de job, changement de rôles), y compris les
  tentatives refusées, avec utilisateur, IP et détails.
- 🔐 **Sécurité en profondeur** : validation stricte des entrées sur 3 couches
  (FormRequests, services, wrappers sudo), commandes exécutées sans shell,
  sudoers limité à deux wrappers dédiés, changement de mot de passe forcé à la
  première connexion.

## Stack technique

| Couche | Technologies |
|---|---|
| Backend | PHP ≥ 8.3 · Laravel 13 · Sanctum · Laravel Reverb · [mawena/maravel](https://github.com/mawena/maravel) (RBAC + APIController) |
| Frontend | Vue 3 · Vuetify 4 (thème sombre `pulseDark`) · Pinia · vue-router · ApexCharts · Laravel Echo (pusher-js) · Vite |
| Système | `/proc`, `ps`, `df`, `systemctl` (wrappers sudo validés en production) |
| Tests | Pest 4 |

## Prérequis

- PHP 8.3 ou plus récent et Composer 2
- Node.js (≥ 20 indiqué dans [`deploy/DEPLOYMENT.md`](deploy/DEPLOYMENT.md)) et npm
- MySQL/MariaDB (connexion par défaut de `.env.example`)
- Un système Linux avec systemd (les sondes lisent `/proc` et interrogent `systemctl`)

## Installation (développement)

```bash
git clone git@github.com:mawena/pulse.git pulse && cd pulse

# Backend
composer install
cp .env.example .env          # configurer DB_* (MySQL)
php artisan key:generate
php artisan migrate --seed    # rôles + compte admin@mawena.cloud / password

# Frontend
npm install
```

## Utilisation

```bash
composer dev
```

lance en parallèle le serveur Laravel, la queue, les logs (`pail`), Vite, le
serveur Reverb (`php artisan reverb:start`) et la boucle de streaming
(`php artisan pulse:stream`).

Connectez-vous avec `admin@mawena.cloud` / `password` : un nouveau mot de passe
vous sera demandé immédiatement.

Options de la boucle de streaming :

```bash
php artisan pulse:stream --interval=0.5   # intervalle en secondes (min 0.2)
php artisan pulse:stream --once           # un seul échantillon puis sortie
```

## Configuration

Principales variables de `.env` (ne jamais committer leurs valeurs) :

| Variables | Rôle |
|---|---|
| `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_URL` | Application Laravel |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Base de données |
| `QUEUE_CONNECTION` | File de jobs (`database` par défaut) |
| `BROADCAST_CONNECTION` | Doit valoir `reverb` pour le temps réel (`log` dans `.env.example`) |
| `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`, `REVERB_MAX_REQUEST_SIZE`, `REVERB_APP_MAX_MESSAGE_SIZE` | Serveur Reverb et publication des événements par le backend |
| `VITE_REVERB_APP_KEY`, `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, `VITE_REVERB_SCHEME` | Connexion WebSocket du navigateur (figées au `npm run build`) |
| `PULSE_USE_SUDO`, `PULSE_SUDO_KILL`, `PULSE_SUDO_SERVICE` | Passage par les wrappers sudo (production) |
| `PULSE_SERVICE_NGINX`, `PULSE_SERVICE_MYSQL`, `PULSE_SERVICE_PHP_FPM` | Noms des unités systemd supervisées |

Les variables `PULSE_*` sont lues par [`config/pulse.php`](config/pulse.php)
(avec des valeurs par défaut) et ne figurent pas dans `.env.example`.

## Tests

```bash
php artisan test
```

Les tests Pest (`tests/Feature`) couvrent l'authentification, l'API de
monitoring, le temps réel et l'audit trail ; ils utilisent SQLite en mémoire
(`phpunit.xml`).

## Déploiement en production

La procédure complète (base de données, `.env`, wrappers sudo, temps réel
Reverb, vhost nginx, TLS, checklist de validation, dépannage) est documentée
dans [`deploy/DEPLOYMENT.md`](deploy/DEPLOYMENT.md).

En résumé :

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force && php artisan db:seed --force
npm ci && npm run build
sudo bash deploy/install-security.sh   # wrappers pulse-kill / pulse-service + sudoers
sudo cp deploy/systemd/pulse-reverb.service deploy/systemd/pulse-stream.service /etc/systemd/system/
sudo systemctl daemon-reload && sudo systemctl enable --now pulse-reverb pulse-stream
```

> ⚠️ En production, activez `PULSE_USE_SUDO=true`, mettez
> `BROADCAST_CONNECTION=reverb` et adaptez `PULSE_SERVICE_PHP_FPM` à la version
> PHP installée.

## Structure du projet

```
app/
├── Console/Commands/       # StreamMetrics (pulse:stream)
├── Events/                 # Metrics/Processes/Services/JobsUpdated (diffusés via Reverb)
├── Http/Controllers/API/   # Auth, Users, Roles, Permissions, Metrics, Process, Lnmp,
│                           # SystemServices, Jobs, AuditLog
├── Http/Middleware/        # EnsurePermission (RBAC), AuditTrail
├── Http/Requests/          # KillProcessRequest, ServiceActionRequest
├── Services/               # SystemMetrics, ProcessManager, LnmpControl,
│                           # SystemServices, JobMonitor
config/pulse.php            # services supervisés, whitelists, wrappers sudo
routes/
├── api.php                 # API REST (préfixe /api)
├── channels.php            # canaux privés metrics, services, processes, jobs
resources/js/
├── pages/                  # Dashboard, Processus, Services LNMP, Services système,
│                           # Jobs, Utilisateurs, Rôles, Audit, Login, Mot de passe
├── stores/                 # auth (CASL), metrics (WebSocket + fallback polling)
├── lib/                    # http (axios), echo (Reverb), useRealtime, format
├── components/             # ApexChart, PulseLogo, PageHeader
deploy/                     # DEPLOYMENT.md, sudoers, wrappers, vhost nginx, unités systemd
ai/                         # spécifications et suivi du projet
```

## Suivi du projet

L'avancement détaillé phase par phase est tenu dans
[`ai/tracker.md`](ai/tracker.md).

## Licence

Aucune licence n'est déclarée dans ce dépôt.
