# Connexion via Portier

Pulse peut proposer **« Se connecter via Portier »** sur sa page `/login`, en plus de la
connexion par e-mail et mot de passe, qui reste identique.
[Portier](https://portier.mawena.cloud) est le fournisseur OpenID Connect de mawena.cloud
(connexion unique, mot de passe + double facteur).

## Fonctionnement
1. Le bouton de `/login` mène à `GET /auth/portier`, qui garde en session `state`, `nonce` et le
   vérificateur PKCE, puis redirige vers `https://portier.mawena.cloud/oidc/autoriser`
   (`response_type=code`, portées `openid profile email`, PKCE S256).
2. Portier renvoie vers `GET /auth/portier/retour?code=…&state=…`. Pulse vérifie le `state`,
   échange le code à `/oidc/jeton` (secret client dans le corps, `client_secret_post`, et
   `code_verifier`), puis vérifie le jeton d'identité : signature RS256 avec la clé du JWKS
   `/oidc/cles` (mis en cache une heure, rechargé si le `kid` est inconnu), `iss`, `aud`
   (et `azp`), `exp`, `nonce`. Aucune dépendance ajoutée (`openssl_verify` et le client HTTP
   de Laravel).
3. Le compte local est celui dont l'adresse e-mail est égale à la revendication `email`
   (`email_verified` doit être vrai ; sinon, ou sans e-mail, refus). Un compte existant n'est
   jamais modifié.
4. **Pas d'inscription via Portier** : Pulse n'a pas d'inscription publique (les comptes sont
   créés par un administrateur, page Utilisateurs). Une identité Portier sans compte Pulse
   est donc refusée et rien n'est créé. Il n'y a pas non plus de page d'inscription, donc pas
   de bouton « S'inscrire via Portier ».
5. Pulse étant une SPA authentifiée par jeton Sanctum, le retour ne pose pas de session : il
   renvoie vers `/login?portier=<code>` (code aléatoire à usage unique, valable 60 s, gardé dans
   le cache Laravel). La page de connexion l'échange par `POST /api/auth/portier/echange`
   (limité à 10 essais par minute) contre le même jeton Sanctum que `POST /api/auth/login`,
   puis continue comme une connexion habituelle (le middleware `account.status` s'applique :
   compte désactivé refusé, changement de mot de passe exigé s'il est demandé).

Erreurs affichées sur `/login` (`?portier_erreur=`) : `etat` (state absent ou faux, session
expirée), `refus` (Portier a refusé l'accès, `access_denied`), `sans_email` (e-mail absent ou
non vérifié), `inscription` (aucun compte Pulse avec cette adresse), `echec` (échange,
signature ou revendications invalides ; le détail est dans le journal Laravel).

Pas de jeton de rafraîchissement : la session Pulse est ensuite celle du jeton Sanctum.
La déconnexion de Pulse ne ferme pas la session Portier (et inversement).

## Prérequis
- **Session** : le départ et le retour passent par le groupe `web` (cookie de session) ;
  `SESSION_DRIVER=database` utilise la table `sessions` déjà créée par les migrations.
- **Cache partagé** : le code à usage unique est écrit par la requête de retour et lu par
  l'échange ; `CACHE_STORE` doit donc être un cache partagé entre les processus PHP-FPM
  (`database`, la valeur de `.env.example`, `redis` ou `file`), jamais `array`.
  Avec `database`, la table `cache` existe déjà. Aucune migration nouvelle.

## Réglages (`.env`)
```
PORTIER_ACTIF=true
PORTIER_ISSUER=https://portier.mawena.cloud
PORTIER_CLIENT_ID=pulse
PORTIER_CLIENT_SECRET=<secret donné par « portier client-secret »>
PORTIER_REDIRECT_URI=          # vide : APP_URL + /auth/portier/retour
```
`PORTIER_ACTIF=false` (défaut) : aucun bouton, et `/auth/portier`, `/auth/portier/retour`,
`/api/auth/portier/echange` répondent 404. Après modification : `php artisan config:cache`
si la configuration est mise en cache. L'état est lu à chaque chargement de page : pas besoin
de reconstruire le frontend pour activer ou désactiver Portier.

## Déclaration dans Portier
Adresse de retour exacte (production, `APP_URL=https://pulse.mawena.cloud`) :

```
https://pulse.mawena.cloud/auth/portier/retour
```

```toml
[[clients]]
id = "pulse"
nom = "Pulse"
secret_sha256 = "<empreinte donnée par portier client-secret>"
redirections = ["https://pulse.mawena.cloud/auth/portier/retour"]
acces = ["charles-gamligo"]   # ou "*"
```
Pulse n'utilise pas la déconnexion OpenID Connect : aucune adresse `deconnexions` n'est
nécessaire. Chaque personne doit avoir dans Portier l'adresse e-mail de son compte Pulse :
`portier courriel <nom> <adresse>`.

## Tests
`php artisan test tests/Feature/PortierTest.php` : faux Portier (`Http::fake` et clé RSA
générée), avec désactivation, state faux, signature, nonce, audience, émetteur et expiration
invalides, compte inconnu (rien créé), pas de doublon, e-mail absent ou non vérifié, code
d'échange à usage unique.
