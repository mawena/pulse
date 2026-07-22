# Spécifications du Projet : MawenaPulse (ou MawenaOps)

## Context & Stack
- **Domaine de déploiement :** `pulse.mawena.cloud`
- **Backend :** Laravel 11+
  - Librairie interne obligatoire : `mawena/maravel` (https://github.com/mawena/maravel)
  - Exécution sécurisée des commandes système via `Symfony/Process` / PHP shell.
- **Frontend :** Vue.js 3 + Vuetify 3 (Dark Theme par défaut) + ApexCharts / Chart.js.
- **Environnement Serveur :** VPS Ubuntu/Debian avec stack LNMP déjà installée via `mawena/lnmp` (https://github.com/mawena/lnmp).

---

## Fonctionnalités Principales

1. **Authentification & Roles/Permissions :**
   - JWT / Session Laravel Sanitizer.
   - Rôles (Admin, Observer) & Permission `system.manage` pour les actions critiques.

2. **Dashboard Temps Réel (Polling / SSE) :**
   - CPU (Utilisation %, Load average 1m/5m/15m).
   - RAM & Swap (Total, Utilisée, Libre, Cache).
   - Disques / Partitions (Espace utilisé/disponible %).
   - Réseau (Débit IN/OUT).
   - Uptime, OS, Kernel.

3. **Gestionnaire de Processus (Task Manager) :**
   - Liste des processus (`ps aux` / `/proc`) : PID, User, % CPU, % RAM, Command.
   - Recherche, filtres, tri.
   - Action "Tuer le processus" (KILL / SIGKILL) avec validation modal et vérification des droits.

4. **Intégration & Control LNMP (`mawena/lnmp`) :**
   - Statut des services : Nginx, MySQL/MariaDB, PHP-FPM.
   - Actions rapides : Redémarrer / Recharger un service de façon sécurisée.

5. **Sécurité & Logs :**
   - Audit trail des actions système effectuées par les utilisateurs.
   - Sanitisation absolue des entrées pour éviter tout Command Injection.
   - Droits Sudoers restreints pour l'utilisateur de l'application web.
