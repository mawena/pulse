# 📍 Suivi de Déploiement & Tâches (Task Tracker)

> **Règle pour l'IA :** À la fin de chaque fonctionnalité développée ou de chaque étape terminée, mets à jour ce fichier en cochant la case correspondant à la tâche accomplie et en indiquant brièvement les fichiers créés/modifiés.

---

## 📊 Avancement Global

- [x] **Phase 1 : Initialisation & Architecture** (100%)
- [x] **Phase 2 : Backend Laravel & Services Système** (100%)
- [x] **Phase 3 : Frontend Vue 3 & Vuetify** (100%)
- [x] **Phase 4 : Configuration Sudoers & Sécurité VPS** (100%)
- [ ] **Phase 5 : Déploiement & Tests** (50% — livrables prêts, exécution sur le VPS restante)

---

## 📝 Détail des Tâches

### Phase 1 : Initialisation & Architecture
- [x] Initialiser le projet Laravel et installer `mawena/maravel`
  - Laravel installé, `mawena/maravel` ^4.1 en dépendance, `php artisan maravel:install` exécuté (Sanctum, AuthController, User/Role/Permission Controllers + Policies, seeder RBAC, routes API).
  - Alias middleware `account.status` ajouté dans `bootstrap/app.php`.
- [x] Installer et configurer Vue.js 3 avec Vuetify 3 (Thème sombre)
  - Packages : `vue`, `vuetify`, `@mdi/font`, `vue-router`, `pinia`, `axios`, `apexcharts`, `vue3-apexcharts`, `@vitejs/plugin-vue`, `vite-plugin-vuetify` (Tailwind retiré).
  - `vite.config.js` (plugins vue + vuetify, alias `@` → `resources/js`).
  - Thème sombre par défaut `pulseDark` : `resources/js/plugins/vuetify.js`.
  - Structure SPA : `resources/js/app.js`, `App.vue`, `router/index.js` (guards auth), `layouts/MainLayout.vue` (sidebar + topbar), `pages/LoginView.vue`, `pages/DashboardView.vue` (placeholder), `lib/http.js` (axios + Bearer token), `stores/auth.js` (Pinia).
  - Entrée SPA : `resources/views/app.blade.php` + route catch-all dans `routes/web.php`. Build Vite OK.
- [x] Créer les migrations pour Users, Roles, Permissions et Audit Logs
  - Migrations RBAC générées par maravel (`permissions`, `roles`, `permission_role`, `role_user`, colonnes `activated`/`password_change_required`).
  - Migration `2026_07_22_150000_create_audit_logs_table.php` + modèle `app/Models/AuditLog.php` (helper `AuditLog::record()`, append-only).
  - Base MySQL `mawena_pulse_db` créée, migrations exécutées.
- [x] Configurer l'authentification (API/Session)
  - Sanctum (tokens Bearer) via maravel : `POST /api/auth/login`, `GET /api/auth/data`, `DELETE /api/auth/logout`.
  - Seeder : rôles `admin` (super-admin) et `observer` (lecture seule : `read` sur `system`/`process`/`service`), permissions système (`manage/system`, `read/system`, etc. — format CASL action/subject).
  - Utilisateur par défaut : `admin@mawena.cloud` (mdp `password`, changement requis à la 1ère connexion).
  - Tests Pest : `tests/Feature/AuthenticationTest.php` (login, rejet credentials invalides, ability_rules CASL, permissions observer, audit log) — 7/7 tests passent.

### Phase 2 : Backend Laravel (API & Services)
- [x] Créer le service `SystemMetricsService.php` (lecture CPU, RAM, Disk, Uptime)
  - `app/Services/SystemMetricsService.php` : CPU % (delta /proc/stat mis en cache), load 1/5/15, RAM/Swap (/proc/meminfo), disques (`df` args fixes), réseau (/proc/net/dev, compteurs cumulés — débit calculé côté client), uptime/OS/kernel/hostname.
- [x] Créer le service `ProcessManagerService.php` (liste `ps aux` et `kill PID`)
  - `app/Services/ProcessManagerService.php` : liste `ps axo` triée par CPU, `kill()` avec validation stricte (PID ≥ 2, ≠ process courant, signaux whitelist TERM/KILL), audit automatique, mode sudo wrapper pour la prod.
- [x] Créer le service `LnmpControlService.php` (statut et gestion des services `mawena/lnmp`)
  - `app/Services/LnmpControlService.php` : statut systemd (`systemctl show`), restart/reload avec whitelist stricte (unités depuis `config/pulse.php`, jamais depuis la requête), audit automatique.
- [x] Développer les contrôleurs d'API (`MetricsController`, `ProcessController`, `LnmpController`)
  - + `AuditLogController` (lecture seule, APIController maravel) et `AuditLogPolicy` (append-only, même pour super-admin).
  - FormRequests `KillProcessRequest` / `ServiceActionRequest` (validation anti-injection).
  - Routes : `GET /api/metrics`, `GET/POST /api/processes[/kill]`, `GET/POST /api/lnmp[/action]`, `GET /api/audit-logs` — protégées par `permission:action,subject`.
- [x] Implémenter le middleware d'audit log pour enregistrer les actions critiques
  - `app/Http/Middleware/AuditTrail.php` (trace les refus 403 / échecs — placé avant `permission` dans la pile) + `EnsurePermission.php` (RBAC CASL via `hasPermissionTo`). Alias `audit` et `permission` dans `bootstrap/app.php`.
  - Tests : `tests/Feature/MonitoringApiTest.php` (12 tests — snapshot métriques, kill admin + audit, refus observer audité, PIDs/services/actions invalides rejetés, audit-logs non modifiables) — **19/19 tests passent**.

### Phase 3 : Frontend Vuetify
- [x] Créer le layout principal (Sidebar, Topbar avec switch de rôle/utilisateur)
  - `layouts/MainLayout.vue` : navigation filtrée par permissions CASL (`auth.can`), chip utilisateur, bouton logout.
- [x] Composant `DashboardView.vue` : Cartes de métriques + Graphiques en temps réel
  - `stores/metrics.js` : polling 5s, historique 30 points, débit réseau par delta des compteurs. `lib/format.js` (bytes, uptime, dates).
  - Cartes CPU/RAM/Swap/Réseau + graphiques ApexCharts (CPU %, RAM %, débit IN/OUT) + barres d'utilisation disques. Chip hostname/OS/kernel/uptime.
- [x] Composant `ProcessManagerView.vue` : Tableau de processus, filtres, modal de confirmation pour kill
  - v-data-table (recherche, tri, pagination), auto-refresh 10s, modal kill (choix SIGTERM/SIGKILL) visible uniquement avec `manage/system`.
- [x] Composant `LnmpServicesView.vue` : Cartes de statut des services et boutons de redémarrage
  - Cartes nginx/mysql/php-fpm avec état systemd, refresh 15s, boutons Restart/Reload (admin) + modal de confirmation.
- [x] Page de gestion des utilisateurs et rôles
  - `UsersView.vue` : CRUD complet (recherche serveur, pagination), dialog création/édition avec assignation multi-rôles (nouvel endpoint `PUT /api/users/{id}/roles` + `syncRoles()` maravel, audité), switches activé/changement mdp.
  - Bonus : `AuditLogsView.vue` (consultation de l'audit trail avec filtres statut + recherche).
  - Router : routes `/processes`, `/services`, `/users`, `/audit-logs` avec garde de permission côté client.

### Phase 4 : Sécurité & Configuration Système
- [x] Rédiger les règles `sudoers` ciblées (`/etc/sudoers.d/mawenapulse`) pour l'utilisateur web
  - `deploy/sudoers.d/mawenapulse` : www-data n'a accès qu'à deux wrappers dédiés (jamais kill/systemctl directs — les wildcards sudoers sont injectables).
  - `deploy/bin/pulse-kill` + `deploy/bin/pulse-service` : wrappers bash `set -euo pipefail` validant signal (TERM/KILL), PID (regex `^[0-9]+$`, ≥ 2), action (restart/reload) et unité (nginx/mysql/mariadb/phpX.Y-fpm) — testés localement contre les injections.
  - `deploy/install-security.sh` : installation root avec `visudo -c` + auto-tests de refus.
- [x] Ajouter les vérifications d'assainissement d'entrées (validation stricte des PID et noms de services)
  - Défense en profondeur sur 3 couches : FormRequests (`KillProcessRequest`, `ServiceActionRequest` — Rule::in sur whitelists de config), services (re-validation + unités jamais issues de la requête + commandes en tableaux d'arguments sans shell), wrappers sudo (validation finale côté root).

### Phase 5 : Déploiement & Finalisation
- [ ] Tester le déploiement sur le VPS sous `pulse.mawena.cloud`
  - ✅ Livrables prêts : `deploy/DEPLOYMENT.md` (procédure complète : DB, .env, migrations, build, sécurité, TLS certbot, dépannage), `deploy/nginx/pulse.mawena.cloud.conf` (vhost avec headers sécurité + cache assets), variables `PULSE_*` ajoutées au `.env.example`.
  - ⏳ Reste : exécuter la procédure sur le VPS (nécessite un accès au serveur).
- [ ] Vérifier le comportement du polling / rafraîchissement temps réel sur le serveur live
  - ✅ Checklist de validation post-déploiement rédigée dans `deploy/DEPLOYMENT.md` (§6).
  - ⏳ Reste : validation sur le serveur live.

---

## 📜 Historique des Mises à Jour

- **[2026-07-22]** : 🎨 **Refonte design « moniteur de signes vitaux »** — identité visuelle ECG (fond bleu-nuit #0B1020, panneaux bordés sans ombres, accent teal #35E0C2, statuts sémantiques distincts), typographie Space Grotesk (titres) / Inter (UI) / JetBrains Mono (données, chiffres tabulaires) auto-hébergée. Signature : logo ECG animé (`PulseLogo.vue`, tracé en boucle, `prefers-reduced-motion` respecté) + indicateur LIVE battant dans la topbar. Ergonomie : sidebar rail repliable (desktop), **bottom navigation mobile**, menu utilisateur (avatar, rôle, changement de mdp volontaire — garde router ajustée), tables empilées sur mobile (`mobile-breakpoint`), `PageHeader.vue` unifié, focus clavier visible, skeletons de chargement. Dashboard : tuiles vitales redessinées + graphes area teal/bleu/vert-ambre. Favicon SVG + ICO multi-tailles générés, `app.blade.php` enrichi (theme-color, description), **README.md du projet réécrit**. Vérifié par captures d'écran headless (login desktop/mobile, fix double contour de focus). 21/21 tests.

- **[2026-07-22]** : 🔧 **Flux de changement de mot de passe obligatoire** (fix du 403 sub_code 002 à la connexion). `ChangePasswordView.vue` (page dédiée avec déconnexion possible), action `updatePassword` dans le store auth (`PUT /users/update-password`), garde router forçant la page tant que `password_change_required` est vrai, intercepteur HTTP gérant les sub_codes maravel (002 → change-password, 001 compte désactivé → login). Casts booléens `activated`/`password_change_required` ajoutés au modèle User. 21/21 tests Pest (2 nouveaux : blocage jusqu'au changement + rejet mauvais mot de passe actuel).

*(L'IA inscrira ici le journal de ses modifications au fur et à mesure)*

- **[AAAA-MM-JJ]** : Création du fichier de suivi et structure initiale.
- **[2026-07-22]** : ✅ **Phases 2, 3 et 4 terminées, Phase 5 préparée.** Backend : `SystemMetricsService` (/proc + df), `ProcessManagerService` (ps + kill whitelist TERM/KILL), `LnmpControlService` (systemctl + whitelist config), contrôleurs `Metrics/Process/Lnmp/AuditLog`, middlewares `permission` (RBAC CASL) et `audit` (trace 403/échecs), FormRequests anti-injection, endpoint `PUT /users/{id}/roles`. Frontend : dashboard temps réel ApexCharts (polling 5s, cartes CPU/RAM/Swap/Réseau/Disques), task manager avec modal kill, vue services LNMP, gestion utilisateurs/rôles, audit trail — navigation et actions filtrées par permissions. Sécurité : wrappers sudo `pulse-kill`/`pulse-service` + sudoers restreint + script d'installation auto-testé. Déploiement : `deploy/DEPLOYMENT.md` + vhost nginx. Vérifié : build Vite OK, **19/19 tests Pest**, Pint appliqué. Reste : exécution du déploiement sur le VPS `pulse.mawena.cloud`.
- **[2026-07-22]** : ✅ **Phase 1 terminée.** Backend : `maravel:install` (Sanctum + RBAC dynamique), migration + modèle `AuditLog`, seeder rôles `admin`/`observer` + permissions système, compte admin par défaut, base `mawena_pulse_db` migrée/seedée. Frontend : Vue 3 + Vuetify 3 (thème sombre `pulseDark`), vue-router avec guards, Pinia (store auth CASL), axios, ApexCharts installé, layout principal + page de login + dashboard placeholder, SPA servie par `app.blade.php`. Vérifié : build Vite OK, 7/7 tests Pest passent.
