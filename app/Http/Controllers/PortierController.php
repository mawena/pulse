<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PortierOidc;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maravel\Http\Controllers\APIController;
use Throwable;

/**
 * « Se connecter via Portier », en plus de la connexion par mot de passe.
 *
 * 1. GET  /auth/portier          : départ vers Portier (state, nonce, PKCE en session).
 * 2. GET  /auth/portier/retour   : retour de Portier ; vérifie le jeton d'identité,
 *    cherche le compte local dont l'e-mail (vérifié) correspond, puis renvoie vers
 *    /login?portier=<code à usage unique, 60 s>.
 * 3. POST /api/auth/portier/echange : la SPA échange ce code contre le jeton Sanctum,
 *    exactement comme après POST /api/auth/login.
 *
 * Pulse n'a pas d'inscription publique (les comptes sont créés par un administrateur) :
 * une identité Portier sans compte local est refusée, rien n'est créé.
 *
 * Désactivé (PORTIER_ACTIF=false) : les trois routes répondent 404.
 */
class PortierController extends APIController
{
    public function __construct(private PortierOidc $portier) {}

    public function depart(Request $request): RedirectResponse
    {
        abort_unless($this->portier->actif(), 404);

        $state = Str::random(40);
        $nonce = Str::random(40);
        $verificateur = PortierOidc::base64url(random_bytes(48));

        $request->session()->put('portier', compact('state', 'nonce', 'verificateur'));

        return redirect()->away($this->portier->adresseAutorisation($state, $nonce, $verificateur));
    }

    public function retour(Request $request): RedirectResponse
    {
        abort_unless($this->portier->actif(), 404);

        $attendu = $request->session()->pull('portier');
        $state = $request->query('state');

        if (! is_array($attendu) || ! is_string($state) || ! hash_equals($attendu['state'], $state)) {
            return $this->echec('etat');
        }
        if ($request->filled('error')) {
            return $this->echec($request->query('error') === 'access_denied' ? 'refus' : 'echec');
        }
        if (! is_string($request->query('code')) || $request->query('code') === '') {
            return $this->echec('echec');
        }

        try {
            $claims = $this->portier->echangerCode($request->query('code'), $attendu['verificateur'], $attendu['nonce']);
        } catch (Throwable $e) {
            Log::warning('Connexion Portier refusée : '.$e->getMessage());

            return $this->echec('echec');
        }

        $email = $claims['email'] ?? null;
        if (! is_string($email) || $email === '' || ($claims['email_verified'] ?? false) !== true) {
            return $this->echec('sans_email');
        }

        $user = User::where('email', $email)->first();
        if (! $user) {
            // Pas d'inscription publique dans Pulse : aucun compte n'est créé.
            Log::info('Connexion Portier sans compte Pulse', ['sub' => $claims['sub'], 'email' => $email]);

            return $this->echec('inscription');
        }

        Log::info('Connexion via Portier', ['sub' => $claims['sub'], 'user' => $user->getKey()]);

        $code = Str::random(64);
        Cache::put('portier.code.'.hash('sha256', $code), $user->getKey(), 60);

        return redirect('/login?'.http_build_query(['portier' => $code]));
    }

    public function echange(Request $request)
    {
        abort_unless($this->portier->actif(), 404);

        $code = $request->input('code');
        $id = is_string($code) && $code !== '' ? Cache::pull('portier.code.'.hash('sha256', $code)) : null;
        $user = $id ? User::find($id) : null;

        if (! $user) {
            return $this->responseError(['portier' => ['Code de connexion Portier invalide ou expiré.']], 401);
        }

        return $this->responseOk([
            'userToken' => $user->createToken($user->email)->plainTextToken,
            'user' => $user,
        ]);
    }

    private function echec(string $raison): RedirectResponse
    {
        return redirect('/login?'.http_build_query(['portier_erreur' => $raison]));
    }
}
