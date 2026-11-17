<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Client OpenID Connect minimal pour Portier (https://portier.mawena.cloud).
 *
 * Flux Authorization Code + PKCE S256, `state` et `nonce`, secret client
 * (client_secret_post). Le jeton d'identité RS256 est vérifié ici avec
 * openssl_verify et le JWKS de Portier (`/oidc/cles`), sans dépendance externe.
 */
class PortierOidc
{
    /** Tolérance d'horloge, en secondes, pour exp / iat. */
    private const DERIVE = 60;

    public function actif(): bool
    {
        return (bool) config('services.portier.actif');
    }

    public function emetteur(): string
    {
        return rtrim((string) config('services.portier.issuer'), '/');
    }

    public function adresseRetour(): string
    {
        return config('services.portier.redirect') ?: url('/auth/portier/retour');
    }

    /**
     * Adresse d'autorisation chez Portier.
     */
    public function adresseAutorisation(string $state, string $nonce, string $verificateur): string
    {
        $defi = self::base64url(hash('sha256', $verificateur, true));

        return $this->emetteur().'/oidc/autoriser?'.http_build_query([
            'response_type' => 'code',
            'client_id' => config('services.portier.client_id'),
            'redirect_uri' => $this->adresseRetour(),
            'scope' => 'openid profile email',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $defi,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Échange le code contre les jetons et renvoie les revendications vérifiées
     * du jeton d'identité.
     *
     * @return array<string, mixed>
     */
    public function echangerCode(string $code, string $verificateur, string $nonce): array
    {
        $reponse = Http::asForm()->acceptJson()->timeout(10)->post($this->emetteur().'/oidc/jeton', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->adresseRetour(),
            'client_id' => config('services.portier.client_id'),
            'client_secret' => config('services.portier.client_secret'),
            'code_verifier' => $verificateur,
        ]);

        if (! $reponse->successful() || ! is_string($reponse->json('id_token'))) {
            throw new RuntimeException('Échange du code refusé par Portier (HTTP '.$reponse->status().').');
        }

        return $this->verifierJetonIdentite($reponse->json('id_token'), $nonce);
    }

    /**
     * Vérifie signature RS256, iss, aud, exp et nonce d'un jeton d'identité.
     *
     * @return array<string, mixed>
     */
    public function verifierJetonIdentite(string $jeton, string $nonce): array
    {
        $parties = explode('.', $jeton);
        if (count($parties) !== 3) {
            throw new RuntimeException('Jeton d\'identité mal formé.');
        }
        [$h64, $p64, $s64] = $parties;

        $entete = json_decode(self::base64urlDecode($h64), true);
        $claims = json_decode(self::base64urlDecode($p64), true);
        $signature = self::base64urlDecode($s64);
        if (! is_array($entete) || ! is_array($claims)) {
            throw new RuntimeException('Jeton d\'identité illisible.');
        }
        if (($entete['alg'] ?? null) !== 'RS256') {
            throw new RuntimeException('Algorithme de signature refusé.');
        }

        $cle = $this->clePublique($entete['kid'] ?? null);
        if (openssl_verify($h64.'.'.$p64, $signature, $cle, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('Signature du jeton d\'identité invalide.');
        }

        $clientId = (string) config('services.portier.client_id');
        $aud = (array) ($claims['aud'] ?? []);
        $maintenant = time();

        if (($claims['iss'] ?? null) !== $this->emetteur()) {
            throw new RuntimeException('Émetteur (iss) inattendu.');
        }
        if (! in_array($clientId, $aud, true)) {
            throw new RuntimeException('Audience (aud) inattendue.');
        }
        if (count($aud) > 1 && ($claims['azp'] ?? null) !== $clientId) {
            throw new RuntimeException('Partie autorisée (azp) inattendue.');
        }
        if (! is_numeric($claims['exp'] ?? null) || $maintenant > $claims['exp'] + self::DERIVE) {
            throw new RuntimeException('Jeton d\'identité expiré.');
        }
        if (isset($claims['iat']) && $maintenant < $claims['iat'] - self::DERIVE) {
            throw new RuntimeException('Jeton d\'identité émis dans le futur.');
        }
        if (! is_string($claims['nonce'] ?? null) || ! hash_equals($nonce, $claims['nonce'])) {
            throw new RuntimeException('Nonce invalide.');
        }
        if (! is_string($claims['sub'] ?? null) || $claims['sub'] === '') {
            throw new RuntimeException('Revendication sub absente.');
        }

        return $claims;
    }

    /**
     * Clé publique PEM correspondant au `kid` (JWKS en cache une heure,
     * rechargé une fois si le `kid` est inconnu).
     */
    private function clePublique(?string $kid): string
    {
        $cle = $this->chercherCle(Cache::remember('portier.jwks', 3600, fn () => $this->chargerJwks()), $kid);
        if ($cle === null) {
            $jwks = $this->chargerJwks();
            Cache::put('portier.jwks', $jwks, 3600);
            $cle = $this->chercherCle($jwks, $kid);
        }
        if ($cle === null) {
            throw new RuntimeException('Clé de signature inconnue.');
        }

        return self::jwkVersPem($cle['n'], $cle['e']);
    }

    /** @return array<int, array<string, mixed>> */
    private function chargerJwks(): array
    {
        $reponse = Http::acceptJson()->timeout(10)->get($this->emetteur().'/oidc/cles');
        if (! $reponse->successful() || ! is_array($reponse->json('keys'))) {
            throw new RuntimeException('JWKS de Portier indisponible.');
        }

        return $reponse->json('keys');
    }

    /** @param array<int, array<string, mixed>> $cles */
    private function chercherCle(array $cles, ?string $kid): ?array
    {
        $rsa = array_values(array_filter($cles, fn ($c) => ($c['kty'] ?? null) === 'RSA'
            && isset($c['n'], $c['e'])
            && (! isset($c['use']) || $c['use'] === 'sig')));

        if ($kid === null) {
            return count($rsa) === 1 ? $rsa[0] : null;
        }
        foreach ($rsa as $c) {
            if (($c['kid'] ?? null) === $kid) {
                return $c;
            }
        }

        return null;
    }

    /**
     * Construit une clé publique PEM (SubjectPublicKeyInfo) à partir du module
     * et de l'exposant d'une JWK RSA.
     */
    public static function jwkVersPem(string $n, string $e): string
    {
        $entier = function (string $octets): string {
            $octets = ltrim($octets, "\0");
            if ($octets === '' || ord($octets[0]) > 0x7F) {
                $octets = "\0".$octets;
            }

            return "\x02".self::longueurDer(strlen($octets)).$octets;
        };

        $rsaPublicKey = $entier(self::base64urlDecode($n)).$entier(self::base64urlDecode($e));
        $rsaPublicKey = "\x30".self::longueurDer(strlen($rsaPublicKey)).$rsaPublicKey;
        // AlgorithmIdentifier : rsaEncryption (1.2.840.113549.1.1.1), paramètres NULL.
        $algo = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
        $bitString = "\x03".self::longueurDer(strlen($rsaPublicKey) + 1)."\0".$rsaPublicKey;
        $spki = "\x30".self::longueurDer(strlen($algo.$bitString)).$algo.$bitString;

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($spki), 64, "\n")."-----END PUBLIC KEY-----\n";
    }

    private static function longueurDer(int $longueur): string
    {
        if ($longueur < 0x80) {
            return chr($longueur);
        }
        $octets = ltrim(pack('N', $longueur), "\0");

        return chr(0x80 | strlen($octets)).$octets;
    }

    public static function base64url(string $octets): string
    {
        return rtrim(strtr(base64_encode($octets), '+/', '-_'), '=');
    }

    public static function base64urlDecode(string $texte): string
    {
        return (string) base64_decode(strtr($texte, '-_', '+/').str_repeat('=', (4 - strlen($texte) % 4) % 4), true);
    }
}
