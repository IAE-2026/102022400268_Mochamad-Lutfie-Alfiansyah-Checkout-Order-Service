<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class SsoJwtVerifier
{
    /**
     * @return array<string, mixed>
     */
    public function verify(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new RuntimeException('Invalid SSO token format');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $header = $this->decodeJsonSegment($encodedHeader);
        $payload = $this->decodeJsonSegment($encodedPayload);
        $signature = $this->decodeSegment($encodedSignature);

        if (filter_var(config('services.sso.verify_signature'), FILTER_VALIDATE_BOOL)) {
            $this->verifySignature($header, "{$encodedHeader}.{$encodedPayload}", $signature);
        }

        $this->verifyRegisteredClaims($payload);

        return $payload;
    }

    /**
     * @param array<string, mixed> $header
     */
    private function verifySignature(array $header, string $signingInput, string $signature): void
    {
        $expectedAlgorithm = (string) config('services.sso.algorithm', 'HS256');
        $algorithm = (string) ($header['alg'] ?? '');

        if (! hash_equals($expectedAlgorithm, $algorithm)) {
            throw new RuntimeException('Unexpected SSO token algorithm');
        }

        if ($algorithm === 'HS256') {
            $secret = (string) config('services.sso.jwt_secret', '');

            if ($secret === '') {
                throw new RuntimeException('SSO JWT secret is not configured');
            }

            $expected = hash_hmac('sha256', $signingInput, $secret, true);

            if (! hash_equals($expected, $signature)) {
                throw new RuntimeException('Invalid SSO token signature');
            }

            return;
        }

        if ($algorithm === 'RS256') {
            $publicKey = $this->publicKeyFor($header);

            if ($publicKey === '') {
                throw new RuntimeException('SSO JWT public key is not configured');
            }

            $verified = openssl_verify($signingInput, $signature, $publicKey, OPENSSL_ALGO_SHA256);

            if ($verified !== 1) {
                throw new RuntimeException('Invalid SSO token signature');
            }

            return;
        }

        throw new RuntimeException('Unsupported SSO token algorithm');
    }

    /**
     * @param array<string, mixed> $header
     */
    private function publicKeyFor(array $header): string
    {
        $configuredKey = str_replace('\n', "\n", (string) config('services.sso.jwt_public_key', ''));

        if ($configuredKey !== '') {
            return $configuredKey;
        }

        $jwksUrl = (string) config('services.sso.jwks_url', '');

        if ($jwksUrl === '') {
            return '';
        }

        $response = Http::timeout((int) config('services.central.timeout', 5))
            ->acceptJson()
            ->get($jwksUrl);

        if (! $response->successful()) {
            throw new RuntimeException("SSO JWKS endpoint returned HTTP {$response->status()}");
        }

        $keys = $response->json('keys');

        if (! is_array($keys)) {
            throw new RuntimeException('SSO JWKS response does not contain keys');
        }

        $kid = (string) ($header['kid'] ?? '');
        $jwk = collect($keys)->first(function (mixed $key) use ($kid): bool {
            if (! is_array($key)) {
                return false;
            }

            return $kid === '' || (string) ($key['kid'] ?? '') === $kid;
        });

        if (! is_array($jwk)) {
            throw new RuntimeException('SSO JWT key id was not found in JWKS');
        }

        return $this->rsaJwkToPem($jwk);
    }

    /**
     * @param array<string, mixed> $jwk
     */
    private function rsaJwkToPem(array $jwk): string
    {
        if (($jwk['kty'] ?? '') !== 'RSA' || empty($jwk['n']) || empty($jwk['e'])) {
            throw new RuntimeException('SSO JWKS key is not an RSA public key');
        }

        $modulus = $this->decodeSegment((string) $jwk['n']);
        $exponent = $this->decodeSegment((string) $jwk['e']);
        $rsaPublicKey = $this->asn1Sequence(
            $this->asn1Integer($modulus).
            $this->asn1Integer($exponent),
        );

        $publicKeyInfo = $this->asn1Sequence(
            $this->asn1Sequence(
                $this->asn1ObjectIdentifier("\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01").
                $this->asn1Null(),
            ).
            $this->asn1BitString($rsaPublicKey),
        );

        return "-----BEGIN PUBLIC KEY-----\n".
            chunk_split(base64_encode($publicKeyInfo), 64, "\n").
            "-----END PUBLIC KEY-----\n";
    }

    private function asn1Sequence(string $value): string
    {
        return "\x30".$this->asn1Length(strlen($value)).$value;
    }

    private function asn1Integer(string $value): string
    {
        $value = ltrim($value, "\x00");

        if ($value === '' || (ord($value[0]) & 0x80) !== 0) {
            $value = "\x00".$value;
        }

        return "\x02".$this->asn1Length(strlen($value)).$value;
    }

    private function asn1ObjectIdentifier(string $value): string
    {
        return "\x06".$this->asn1Length(strlen($value)).$value;
    }

    private function asn1Null(): string
    {
        return "\x05\x00";
    }

    private function asn1BitString(string $value): string
    {
        return "\x03".$this->asn1Length(strlen($value) + 1)."\x00".$value;
    }

    private function asn1Length(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $encoded = '';

        while ($length > 0) {
            $encoded = chr($length & 0xff).$encoded;
            $length >>= 8;
        }

        return chr(0x80 | strlen($encoded)).$encoded;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function verifyRegisteredClaims(array $payload): void
    {
        $now = now()->timestamp;

        if (isset($payload['exp']) && (int) $payload['exp'] <= $now) {
            throw new RuntimeException('SSO token has expired');
        }

        if (isset($payload['nbf']) && (int) $payload['nbf'] > $now) {
            throw new RuntimeException('SSO token is not active yet');
        }

        $issuer = (string) config('services.sso.issuer', '');
        if ($issuer !== '' && (string) ($payload['iss'] ?? '') !== $issuer) {
            throw new RuntimeException('Invalid SSO token issuer');
        }

        $audience = (string) config('services.sso.audience', '');
        if ($audience !== '') {
            $tokenAudience = $payload['aud'] ?? [];
            $tokenAudiences = is_array($tokenAudience) ? $tokenAudience : [$tokenAudience];

            if (! in_array($audience, $tokenAudiences, true)) {
                throw new RuntimeException('Invalid SSO token audience');
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonSegment(string $segment): array
    {
        $decoded = json_decode($this->decodeSegment($segment), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Invalid SSO token payload');
        }

        return $decoded;
    }

    private function decodeSegment(string $segment): string
    {
        $base64 = strtr($segment, '-_', '+/');
        $base64 .= str_repeat('=', (4 - strlen($base64) % 4) % 4);
        $decoded = base64_decode($base64, true);

        if ($decoded === false) {
            throw new RuntimeException('Invalid SSO token encoding');
        }

        return $decoded;
    }
}
