<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Koh\SyliusWebBotAuth\Tests\Directory;

use Koh\SyliusWebBotAuth\Directory\SignatureDirectoryBuilder;
use Koh\SyliusWebBotAuth\Key\SeedKeyProvider;
use Koh\SyliusWebBotAuth\Key\SigningKey;
use PHPUnit\Framework\TestCase;

final class SignatureDirectoryTest extends TestCase
{
    public function testDirectoryHasKeysAndPurpose(): void
    {
        $directory = $this->builder()->build();

        self::assertArrayHasKey('keys', $directory);
        self::assertSame('rag', $directory['purpose']);
        self::assertCount(1, $directory['keys']);
    }

    public function testJwkCarriesTheEd25519Members(): void
    {
        $jwk = $this->builder()->build()['keys'][0];

        self::assertSame('OKP', $jwk['kty']);
        self::assertSame('Ed25519', $jwk['crv']);
        self::assertSame('sig', $jwk['use']);
        self::assertSame('EdDSA', $jwk['alg']);
        self::assertArrayHasKey('kid', $jwk);
        self::assertArrayHasKey('x', $jwk);
    }

    /** base64url: no padding, no + or / — a verifier that decodes strictly rejects those. */
    public function testPublicKeyIsBase64UrlWithoutPadding(): void
    {
        $x = $this->builder()->build()['keys'][0]['x'];

        self::assertDoesNotMatchRegularExpression('/[+\/=]/', $x);
        self::assertSame(32, \strlen((string) base64_decode(strtr($x, '-_', '+/'), true)));
    }

    /**
     * RFC 7638: SHA-256 over {"crv","kty","x"} in lexicographic order, no
     * whitespace. Verified against an independently built canonical form rather
     * than against the implementation's own output.
     */
    public function testKidIsTheRfc7638Thumbprint(): void
    {
        $pair = sodium_crypto_sign_seed_keypair(hash('sha256', 'seed-a', true));
        $public = sodium_crypto_sign_publickey($pair);

        $expected = SigningKey::base64Url(hash('sha256', json_encode([
            'crv' => 'Ed25519',
            'kty' => 'OKP',
            'x' => SigningKey::base64Url($public),
        ], \JSON_UNESCAPED_SLASHES), true));

        self::assertSame($expected, (new SigningKey($public))->kid());
    }

    /** The directory format carries nbf/exp in milliseconds, not seconds. */
    public function testValidityWindowIsSerialisedInMilliseconds(): void
    {
        $jwk = (new SigningKey(str_repeat("\x01", 32), notBefore: 1_800_000_000, expiresAt: 1_900_000_000))->toJwk();

        self::assertSame(1_800_000_000_000, $jwk['nbf']);
        self::assertSame(1_900_000_000_000, $jwk['exp']);
    }

    public function testTimestampsAreOmittedWhenNotConfigured(): void
    {
        $jwk = (new SigningKey(str_repeat("\x01", 32)))->toJwk();

        self::assertArrayNotHasKey('nbf', $jwk);
        self::assertArrayNotHasKey('exp', $jwk);
    }

    /** Rotation is the reason the directory is a list: both keys are published at once. */
    public function testRotationPublishesBothKeys(): void
    {
        $builder = new SignatureDirectoryBuilder(new SeedKeyProvider([
            ['seed' => 'new-key'],
            ['seed' => 'old-key'],
        ]));

        $kids = array_column($builder->build()['keys'], 'kid');

        self::assertCount(2, $kids);
        self::assertCount(2, array_unique($kids));
    }

    /**
     * Every instance behind the load balancer must publish the same directory,
     * or a verifier gets a different answer per request.
     */
    public function testKeysAreDeterministicAcrossInstances(): void
    {
        $a = (new SeedKeyProvider([['seed' => 'same']]))->getKeys()[0];
        $b = (new SeedKeyProvider([['seed' => 'same']]))->getKeys()[0];

        self::assertSame($a->kid(), $b->kid());
    }

    public function testEmptySeedsAreIgnored(): void
    {
        $builder = new SignatureDirectoryBuilder(new SeedKeyProvider([['seed' => '']]));

        self::assertFalse($builder->hasKeys());
        self::assertSame([], $builder->build()['keys']);
    }

    private function builder(): SignatureDirectoryBuilder
    {
        return new SignatureDirectoryBuilder(new SeedKeyProvider([['seed' => 'seed-a']]));
    }
}
