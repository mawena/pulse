<p align="center">
  <img src="public/favicon.svg" width="72" alt="MawenaPulse">
</p>

<h1 align="center">MawenaPulse</h1>

<p align="center"><em>Les signes vitaux de votre serveur, en direct.</em></p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white" alt="Laravel">
  <img src="https://img.shields.io/badge/Vue-3-42B883?logo=vuedotjs&logoColor=white" alt="Vue 3">
  <img src="https://img.shields.io/badge/Vuetify-3-1867C0?logo=vuetify&logoColor=white" alt="Vuetify 3">
  <img src="https://img.shields.io/badge/Tests-Pest-8BC34A" alt="Pest">
</p>

---

**MawenaPulse** est un cockpit de monitoring pour VPS Ubuntu/Debian équipé de la
stack LNMP [mawena/lnmp](https://github.com/mawena/lnmp). Il affiche les
métriques système en temps réel, gère les processus et pilote les services —
avec des rôles, des permissions et un audit trail complet.

Déployé sur **[pulse.mawena.cloud](https://pulse.mawena.cloud)**.

## Fonctionnalités

- 📊 **Dashboard temps réel** — CPU (%, load 1/5/15), RAM & swap, disques,
  débit réseau IN/OUT, uptime/OS/kernel. **WebSocket (Laravel Reverb)** avec
  échantillonnage backend toutes les 3 s et fallback automatique en polling
  HTTP. Graphiques ApexCharts.
- 🧩 **Services système & Jobs** — toutes les unités systemd avec leur état
  (poussées en temps réel), file de jobs Laravel (en attente / en cours /
  échoués) avec relance des échecs.
- ⚙️ **Gestionnaire de processus** — liste `ps` triée par CPU, recherche et tri,
  kill SIGTERM/SIGKILL avec confirmation (admins uniquement).
- 🔁 **Services LNMP** — statut systemd de nginx / MySQL / PHP-FPM,
  restart & reload sécurisés depuis l'interface.
- 👥 **Rôles & permissions** — RBAC dynamique en base
  ([mawena/maravel](https://github.com/mawena/maravel)) : super-admin,
  observateur lecture seule, permissions CASL exposées au frontend.
- 📜 **Audit trail** — journal append-only de toutes les actions système
  (y compris les tentatives refusées), avec utilisateur, IP et détails.
- 🔐 **Sécurité en profondeur** — validation stricte des entrées (3 couches),
  commandes sans shell, sudoers limité à deux wrappers dédiés,
  changement de mot de passe forcé à la première connexion.

## Stack

| Couche | Technologies |
|---|---|
| Backend | Laravel 13 · Sanctum · [mawena/maravel](https://github.com/mawena/maravel) (RBAC + APIController) |
| Frontend | Vue 3 · Vuetify 3 (thème sombre) · Pinia · vue-router · ApexCharts |
| Système | `/proc`, `ps`, `df`, `systemctl` via wrappers sudo validés |
| Tests | Pest |

## Démarrage rapide (développement)

```bash
git clone <repo> pulse && cd pulse

# Backend
composer install
cp .env.example .env          # configurer DB_* (MySQL)
php artisan key:generate
php artisan migrate --seed    # rôles + compte admin@mawena.cloud / password

# Frontend
npm install
composer dev                  # serveur + queue + logs + vite
```

Connectez-vous avec `admin@mawena.cloud` / `password` — un nouveau mot de passe
vous sera demandé immédiatement.

## Tests

```bash
php artisan test
```

## Déploiement en production

La procédure complète (base de données, `.env`, wrappers sudo, vhost nginx,
TLS, checklist de validation) est documentée dans
[`deploy/DEPLOYMENT.md`](deploy/DEPLOYMENT.md).

En résumé :

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force && php artisan db:seed --force
npm ci && npm run build
sudo bash deploy/install-security.sh   # wrappers pulse-kill / pulse-service + sudoers
```

> ⚠️ En production, activez `PULSE_USE_SUDO=true` et adaptez
> `PULSE_SERVICE_PHP_FPM` à la version PHP installée.

## Structure du projet

```
app/
├── Http/Controllers/API/   # Auth, Users, Roles, Metrics, Process, Lnmp, AuditLog
├── Http/Middleware/        # EnsurePermission (RBAC), AuditTrail
├── Services/               # SystemMetrics, ProcessManager, LnmpControl
resources/js/
├── pages/                  # Dashboard, Processus, Services, Users, Audit, Auth
├── stores/                 # auth (CASL), metrics (polling + historique)
├── components/             # ApexChart, PulseLogo, PageHeader
deploy/                     # sudoers, wrappers, vhost nginx, DEPLOYMENT.md
ai/                         # spécifications et suivi du projet
```

## Suivi du projet

L'avancement détaillé phase par phase est tenu à jour dans
[`ai/tracker.md`](ai/tracker.md).
