# 📍 Suivi de Déploiement & Tâches (Task Tracker)

> **Règle pour l'IA :** À la fin de chaque fonctionnalité développée ou de chaque étape terminée, mets à jour ce fichier en cochant la case correspondant à la tâche accomplie et en indiquant brièvement les fichiers créés/modifiés.

---

## 📊 Avancement Global

- [ ] **Phase 1 : Initialisation & Architecture** (0%)
- [ ] **Phase 2 : Backend Laravel & Services Système** (0%)
- [ ] **Phase 3 : Frontend Vue 3 & Vuetify** (0%)
- [ ] **Phase 4 : Configuration Sudoers & Sécurité VPS** (0%)
- [ ] **Phase 5 : Déploiement & Tests** (0%)

---

## 📝 Détail des Tâches

### Phase 1 : Initialisation & Architecture
- [ ] Initialiser le projet Laravel et installer `mawena/maravel`
- [ ] Installer et configurer Vue.js 3 avec Vuetify 3 (Thème sombre)
- [ ] Créer les migrations pour Users, Roles, Permissions et Audit Logs
- [ ] Configurer l'authentification (API/Session)

### Phase 2 : Backend Laravel (API & Services)
- [ ] Créer le service `SystemMetricsService.php` (lecture CPU, RAM, Disk, Uptime)
- [ ] Créer le service `ProcessManagerService.php` (liste `ps aux` et `kill PID`)
- [ ] Créer le service `LnmpControlService.php` (statut et gestion des services `mawena/lnmp`)
- [ ] Développer les contrôleurs d'API (`MetricsController`, `ProcessController`, `LnmpController`)
- [ ] Implémenter le middleware d'audit log pour enregistrer les actions critiques

### Phase 3 : Frontend Vuetify
- [ ] Créer le layout principal (Sidebar, Topbar avec switch de rôle/utilisateur)
- [ ] Composant `DashboardView.vue` : Cartes de métriques + Graphiques en temps réel
- [ ] Composant `ProcessManagerView.vue` : Tableau de processus, filtres, modal de confirmation pour kill
- [ ] Composant `LnmpServicesView.vue` : Cartes de statut des services et boutons de redémarrage
- [ ] Page de gestion des utilisateurs et rôles

### Phase 4 : Sécurité & Configuration Système
- [ ] Rédiger les règles `sudoers` ciblées (`/etc/sudoers.d/mawenapulse`) pour l'utilisateur web
- [ ] Ajouter les vérifications d'assainissement d'entrées (validation stricte des PID et noms de services)

### Phase 5 : Déploiement & Finalisation
- [ ] Tester le déploiement sur le VPS sous `pulse.mawena.cloud`
- [ ] Vérifier le comportement du polling / rafraîchissement temps réel sur le serveur live

---

## 📜 Historique des Mises à Jour

*(L'IA inscrira ici le journal de ses modifications au fur et à mesure)*

- **[AAAA-MM-JJ]** : Création du fichier de suivi et structure initiale.
