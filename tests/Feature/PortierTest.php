<?php

/*
 * Connexion facultative via Portier, avec un faux fournisseur
 * (Http::fake + clé RSA générée pour le test).
 */

use App\Models\User;
use App\Services\PortierOidc;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

const PORTIER_ISSUER = 'https://portier.test';

beforeEach(function () {
    config([
        'services.portier.actif' => true,
        'services.portier.issuer' => PORTIER_ISSUER,
        'services.portier.client_id' => 'pulse',
        'services.portier.client_secret' => 'secret-de-test',
        'services.portier.redirect' => null,
    ]);

    $this->clePrivee = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $this->idToken = null;

    /** Jeton d'identité signé (par défaut avec la clé publiée dans le JWKS). */
    $this->jeton = function (array $claims, $cle = null): string {
        $h = PortierOidc::base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT', 'kid' => 'cle-test']));
        $p = PortierOidc::base64url(json_encode($claims));
        openssl_sign("$h.$p", $signature, $cle ?? $this->clePrivee, OPENSSL_ALGO_SHA256);

        return "$h.$p.".PortierOidc::base64url($signature);
    };

    /** Départ vers Portier ; renvoie [state, nonce] gardés en session. */
    $this->depart = function (): array {
        $reponse = $this->get('/auth/portier');
        $reponse->assertRedirect();
        expect($reponse->headers->get('Location'))->toStartWith(PORTIER_ISSUER.'/oidc/autoriser?');

        return [session('portier.state'), session('portier.nonce')];
    };

    /** Faux Portier ; un nouvel appel remplace le jeton d'identité renvoyé. */
    $this->fauxPortier = function (array $claims, $cle = null): void {
        $premierAppel = $this->idToken === null;
        $this->idToken = ($this->jeton)($claims, $cle);
        if (! $premierAppel) {
            return;
        }

        $rsa = openssl_pkey_get_details($this->clePrivee)['rsa'];
        Http::fake([
            PORTIER_ISSUER.'/oidc/jeton' => fn () => Http::response(['access_token' => 'x', 'token_type' => 'Bearer', 'id_token' => $this->idToken]),
            PORTIER_ISSUER.'/oidc/cles' => Http::response(['keys' => [[
                'kty' => 'RSA', 'use' => 'sig', 'alg' => 'RS256', 'kid' => 'cle-test',
                'n' => PortierOidc::base64url($rsa['n']),
                'e' => PortierOidc::base64url($rsa['e']),
            ]]]),
        ]);
    };

    $this->claims = fn (string $nonce, array $autres = []): array => array_merge([
        'iss' => PORTIER_ISSUER, 'aud' => 'pulse', 'azp' => 'pulse', 'sub' => 'charles',
        'preferred_username' => 'charles', 'name' => 'Charles',
        'email' => 'charles@example.com', 'email_verified' => true,
        'nonce' => $nonce, 'iat' => time(), 'exp' => time() + 600,
    ], $autres);
});

it('répond 404 et n\'affiche aucun bouton quand Portier est désactivé', function () {
    config(['services.portier.actif' => false]);

    $this->get('/auth/portier')->assertNotFound();
    $this->get('/auth/portier/retour?state=a&code=b')->assertNotFound();
    $this->postJson('/api/auth/portier/echange', ['code' => 'x'])->assertNotFound();
    $this->withoutVite()->get('/login')->assertOk()->assertSee('window.portierActif = false', false);
});

it('annonce Portier à la SPA quand il est actif', function () {
    $this->withoutVite()->get('/login')->assertOk()->assertSee('window.portierActif = true', false);
});

it('laisse la connexion par mot de passe inchangée', function () {
    User::factory()->create(['email' => 'charles@example.com', 'activated' => true]);

    $this->postJson('/api/auth/login', ['email' => 'charles@example.com', 'password' => 'password'])
        ->assertOk()
        ->assertJsonStructure(['data' => ['userToken', 'user']]);
});

it('envoie PKCE S256, state et nonce au départ', function () {
    $location = $this->get('/auth/portier')->headers->get('Location');
    parse_str(parse_url($location, PHP_URL_QUERY), $q);

    expect($q['response_type'])->toBe('code')
        ->and($q['client_id'])->toBe('pulse')
        ->and($q['redirect_uri'])->toBe(url('/auth/portier/retour'))
        ->and($q['scope'])->toBe('openid profile email')
        ->and($q['code_challenge_method'])->toBe('S256')
        ->and($q['code_challenge'])->toBe(PortierOidc::base64url(hash('sha256', session('portier.verificateur'), true)))
        ->and($q['state'])->toBe(session('portier.state'))
        ->and($q['nonce'])->toBe(session('portier.nonce'));
});

it('refuse un state faux sans appeler Portier', function () {
    [, $nonce] = ($this->depart)();
    ($this->fauxPortier)(($this->claims)($nonce));

    $this->get('/auth/portier/retour?state=mauvais&code=abc')
        ->assertRedirect('/login?portier_erreur=etat');
    Http::assertNothingSent();
});

it('refuse un retour sans départ (pas de state en session)', function () {
    $this->get('/auth/portier/retour?state=abc&code=abc')
        ->assertRedirect('/login?portier_erreur=etat');
});

it('connecte un compte existant puis le code ne sert qu\'une fois', function () {
    $user = User::factory()->create(['email' => 'charles@example.com', 'name' => 'Ancien', 'activated' => true]);
    [$state, $nonce] = ($this->depart)();
    ($this->fauxPortier)(($this->claims)($nonce));

    $location = $this->get("/auth/portier/retour?state=$state&code=abc")->headers->get('Location');
    expect($location)->toContain('/login?portier=');
    parse_str(parse_url($location, PHP_URL_QUERY), $q);

    Http::assertSent(fn ($r) => $r->url() === PORTIER_ISSUER.'/oidc/jeton'
        && $r['client_secret'] === 'secret-de-test'
        && $r['client_id'] === 'pulse'
        && $r['code'] === 'abc'
        && $r['redirect_uri'] === url('/auth/portier/retour')
        && is_string($r['code_verifier']));

    $token = $this->postJson('/api/auth/portier/echange', ['code' => $q['portier']])
        ->assertOk()
        ->assertJsonPath('data.user.id', $user->id)
        ->json('data.userToken');

    $this->withToken($token)->getJson('/api/auth/data')->assertOk()->assertJsonPath('data.email', 'charles@example.com');

    // Le code ne sert qu'une fois.
    $this->postJson('/api/auth/portier/echange', ['code' => $q['portier']])->assertUnauthorized();

    // Compte inchangé, pas de doublon.
    expect(User::count())->toBe(1)->and($user->fresh()->name)->toBe('Ancien');
});

it('refuse un code d\'échange inconnu', function () {
    $this->postJson('/api/auth/portier/echange', ['code' => 'inconnu'])->assertUnauthorized();
    $this->postJson('/api/auth/portier/echange', [])->assertUnauthorized();
});

it('ne crée aucun compte : pas d\'inscription publique dans Pulse', function () {
    [$state, $nonce] = ($this->depart)();
    ($this->fauxPortier)(($this->claims)($nonce, ['email' => 'nouveau@example.com']));

    $this->get("/auth/portier/retour?state=$state&code=abc")
        ->assertRedirect('/login?portier_erreur=inscription');
    $this->assertDatabaseCount('users', 0);
});

it('ne crée pas de doublon sur plusieurs connexions', function () {
    $user = User::factory()->create(['email' => 'charles@example.com', 'activated' => true]);

    for ($i = 0; $i < 2; $i++) {
        [$state, $nonce] = ($this->depart)();
        ($this->fauxPortier)(($this->claims)($nonce));
        $location = $this->get("/auth/portier/retour?state=$state&code=abc")->headers->get('Location');
        parse_str(parse_url($location, PHP_URL_QUERY), $q);
        $this->postJson('/api/auth/portier/echange', ['code' => $q['portier']])
            ->assertOk()->assertJsonPath('data.user.id', $user->id);
    }

    $this->assertDatabaseCount('users', 1);
});

it('refuse un e-mail non vérifié, même si le compte existe', function () {
    User::factory()->create(['email' => 'charles@example.com']);
    [$state, $nonce] = ($this->depart)();
    ($this->fauxPortier)(($this->claims)($nonce, ['email_verified' => false]));

    $this->get("/auth/portier/retour?state=$state&code=abc")
        ->assertRedirect('/login?portier_erreur=sans_email');
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('refuse un e-mail absent', function () {
    [$state, $nonce] = ($this->depart)();
    $claims = ($this->claims)($nonce);
    unset($claims['email'], $claims['email_verified']);
    ($this->fauxPortier)($claims);

    $this->get("/auth/portier/retour?state=$state&code=abc")
        ->assertRedirect('/login?portier_erreur=sans_email');
    $this->assertDatabaseCount('users', 0);
});

it('refuse une signature faite avec une autre clé', function () {
    User::factory()->create(['email' => 'charles@example.com']);
    [$state, $nonce] = ($this->depart)();
    $autreCle = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    ($this->fauxPortier)(($this->claims)($nonce), $autreCle);

    $this->get("/auth/portier/retour?state=$state&code=abc")
        ->assertRedirect('/login?portier_erreur=echec');
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('refuse un jeton au nonce, à l\'audience, à l\'émetteur ou à l\'expiration invalides', function (array $faux) {
    User::factory()->create(['email' => 'charles@example.com']);
    [$state, $nonce] = ($this->depart)();
    ($this->fauxPortier)(($this->claims)($nonce, $faux));

    $this->get("/auth/portier/retour?state=$state&code=abc")
        ->assertRedirect('/login?portier_erreur=echec');
    $this->assertDatabaseCount('personal_access_tokens', 0);
})->with([
    'nonce' => [['nonce' => 'autre']],
    'aud' => [['aud' => 'autre-appli']],
    'iss' => [['iss' => 'https://ailleurs.test']],
    'exp' => [['exp' => time() - 3600]],
]);

it('signale le refus de Portier', function () {
    [$state] = ($this->depart)();

    $this->get("/auth/portier/retour?state=$state&error=access_denied")
        ->assertRedirect('/login?portier_erreur=refus');
});

it('convertit une JWK RSA en clé PEM', function () {
    $details = openssl_pkey_get_details($this->clePrivee);
    $pem = PortierOidc::jwkVersPem(PortierOidc::base64url($details['rsa']['n']), PortierOidc::base64url($details['rsa']['e']));

    expect(trim($pem))->toBe(trim($details['key']));
});
