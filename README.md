# MawenaPulse

Cockpit de monitoring pour VPS Ubuntu/Debian équipé de la stack LNMP
[mawena/lnmp](https://github.com/mawena/lnmp) : métriques système en temps
réel, gestion des processus et pilotage des services, avec rôles, permissions
et audit trail.

> **État de la branche `main`** : elle ne contient pour l'instant que
> l'initialisation du projet (squelette Laravel 13, dépendance
> [mawena/maravel](https://github.com/mawena/maravel), Pest) et les
> spécifications. L'application elle-même est développée sur la branche
> `develop` et n'a pas encore été publiée sur `main`.

## Fonctionnalités prévues

Les spécifications complètes sont dans [`ai/prompt-initial.md`](ai/prompt-initial.md)
et l'avancement dans [`ai/tracker.md`](ai/tracker.md) :

- authentification, rôles (administrateur, observateur) et permissions ;
- dashboard temps réel : CPU, RAM et swap, disques, réseau, uptime/OS/kernel ;
- gestionnaire de processus avec arrêt d'un processus après confirmation ;
- statut et redémarrage/rechargement des services nginx, MySQL/MariaDB et PHP-FPM ;
- audit trail des actions système, validation stricte des entrées et droits
  sudoers restreints.

## Stack technique

| Couche | Technologies |
|---|---|
| Backend | PHP ≥ 8.3 · Laravel 13 · [mawena/maravel](https://github.com/mawena/maravel) |
| Frontend (squelette) | Vite · Tailwind CSS 4 |
| Tests | Pest 4 |

## Prérequis

- PHP 8.3 ou plus récent et Composer
- Node.js et npm
- MySQL (connexion par défaut de `.env.example`)

## Installation

```bash
git clone git@github.com:mawena/pulse.git pulse && cd pulse
composer install
cp .env.example .env          # renseigner DB_*
php artisan key:generate
php artisan migrate
npm install
```

Le script `composer setup` enchaîne ces étapes (et lance `npm run build`).

## Utilisation

```bash
composer dev    # serveur Laravel + queue + logs (pail) + Vite
```

## Configuration

Variables principales de `.env` : `APP_NAME`, `APP_URL`, `DB_CONNECTION`,
`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`,
`QUEUE_CONNECTION`.

## Tests

```bash
php artisan test
```

Les tests utilisent SQLite en mémoire (`phpunit.xml`).

## Licence

Aucune licence n'est déclarée dans ce dépôt.
