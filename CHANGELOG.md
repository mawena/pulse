# Journal des modifications

Toutes les modifications notables de ce projet sont consignées dans ce fichier.

Le format s'appuie sur [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/)
et le projet suit le [versionnage sémantique](https://semver.org/lang/fr/).

## [Non publié]

## [0.1.0] - 2026-11-17

### Ajouté

- Connexion facultative via Portier (OpenID Connect : code + PKCE S256, state, nonce,
  jeton d'identité RS256 vérifié par JWKS) : bouton « Se connecter via Portier » sur `/login`
  pour les comptes existants, en plus du mot de passe. Désactivée par défaut
  (`PORTIER_ACTIF=false`, routes en 404). Pas d'inscription via Portier : Pulse n'a pas
  d'inscription publique. Voir `docs/portier.md`.
- `ROADMAP.md`.

## [0.0.1] - 2026-11-04

### Modifié

- README propre au projet à la place du README par défaut de Laravel.

[Non publié]: https://github.com/mawena/pulse/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/mawena/pulse/compare/v0.0.1...v0.1.0
[0.0.1]: https://github.com/mawena/pulse/releases/tag/v0.0.1
