<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Koh\SyliusWebBotAuth\Key;

/**
 * One Ed25519 key pair, published as a JWK in the signature directory.
 *
 * Only the public half ever leaves this object through toJwk(); the secret key
 * is held so the same object can sign outgoing requests once that half of the
 * protocol is implemented.
 */
final readonly class SigningKey
{
    /**
     * @param string  $publicKey raw 32-byte Ed25519 public key
     * @param ?string $secretKey raw 64-byte Ed25519 secret key, if this instance can sign
     * @param ?int    $notBefore unix seconds; serialised as milliseconds
     */
    public function __construct(
        public string $publicKey,
        public ?string $secretKey = null,
        public ?int $notBefore = null,
        public ?int $expiresAt = null,
    ) {
    }

    /**
     * RFC 7638 thumbprint: SHA-256 over the required JWK members, in
     * lexicographic order, with no whitespace — for OKP that is crv, kty, x.
     *
     * Deriving the kid instead of configuring it means it cannot drift from the
     * key it names, which is the failure that makes rotation unverifiable.
     */
    public function kid(): string
    {
        $canonical = \sprintf(
            '{"crv":"Ed25519","kty":"OKP","x":"%s"}',
            self::base64Url($this->publicKey),
        );

        return self::base64Url(hash('sha256', $canonical, true));
    }

    /**
     * @return array<string, string|int>
     */
    public function toJwk(): array
    {
        $jwk = [
            'kid' => $this->kid(),
            'kty' => 'OKP',
            'crv' => 'Ed25519',
            'x' => self::base64Url($this->publicKey),
            'use' => 'sig',
            'alg' => 'EdDSA',
        ];

        // The directory format carries these in milliseconds, not seconds.
        if (null !== $this->notBefore) {
            $jwk['nbf'] = $this->notBefore * 1000;
        }

        if (null !== $this->expiresAt) {
            $jwk['exp'] = $this->expiresAt * 1000;
        }

        return $jwk;
    }

    public static function base64Url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
