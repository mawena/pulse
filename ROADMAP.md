# Feuille de route

Le suivi détaillé des phases initiales est dans [`ai/tracker.md`](ai/tracker.md).

## Phase — Connexion via Portier (0.1.0)
- [x] Client OpenID Connect sans dépendance (`app/Services/PortierOidc.php`) : code + PKCE S256, state, nonce, id_token RS256 vérifié par JWKS
- [x] Routes `/auth/portier`, `/auth/portier/retour`, `/api/auth/portier/echange` (404 si `PORTIER_ACTIF=false`)
- [x] Bouton « Se connecter via Portier » sur `/login` et échange du code contre le jeton Sanctum
- [x] Refus propre sans compte local (pas d'inscription publique dans Pulse)
- [x] Tests ciblés (`tests/Feature/PortierTest.php`)
- [x] Documentation (`docs/portier.md`, README, `.env.example`)
- [ ] Déclarer le client `pulse` dans Portier et renseigner le secret en production (Charles)
- [ ] Déployer et activer `PORTIER_ACTIF=true` en production (Charles)
