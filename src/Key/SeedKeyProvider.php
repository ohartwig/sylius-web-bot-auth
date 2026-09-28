<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig <mail@ole-hartwig.eu>
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Koh\SyliusWebBotAuth\Key;

/**
 * Derives Ed25519 key pairs deterministically from configured seeds.
 *
 * Deterministic so that every application instance behind a load balancer
 * publishes the same directory — a per-process random key would make the
 * directory contradict itself.
 *
 * The seeds are secrets and must be configured explicitly. Deriving them from
 * APP_SECRET would be convenient and wrong: the signing identity would then be
 * impossible to rotate without invalidating sessions, and anything that leaks
 * APP_SECRET would also let an attacker sign as this bot.
 */
final class SeedKeyProvider implements KeyProviderInterface
{
    /** @var list<SigningKey>|null */
    private ?array $keys = null;

    /**
     * @param list<array{seed: string, not_before?: int|null, expires_at?: int|null}> $seedConfigs
     */
    public function __construct(private readonly array $seedConfigs)
    {
    }

    /** @return list<SigningKey> */
    public function getKeys(): array
    {
        if (null !== $this->keys) {
            return $this->keys;
        }

        $keys = [];
        foreach ($this->seedConfigs as $config) {
            $seed = $config['seed'];
            if ('' === $seed) {
                continue;
            }

            // sodium wants exactly 32 bytes; hash whatever the operator supplied
            // so a passphrase works as well as a generated 32-byte secret.
            $pair = sodium_crypto_sign_seed_keypair(hash('sha256', $seed, true));

            $keys[] = new SigningKey(
                publicKey: sodium_crypto_sign_publickey($pair),
                secretKey: sodium_crypto_sign_secretkey($pair),
                notBefore: $config['not_before'] ?? null,
                expiresAt: $config['expires_at'] ?? null,
            );
        }

        return $this->keys = $keys;
    }
}
