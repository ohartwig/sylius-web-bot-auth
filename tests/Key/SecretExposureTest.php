<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig <mail@ole-hartwig.eu>
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Koh\SyliusWebBotAuth\Tests\Key;

use Koh\SyliusWebBotAuth\Key\SigningKey;
use PHPUnit\Framework\TestCase;

/**
 * The only remedy for a leaked signing key is rotation, so the secret half must
 * not travel anywhere the public half does.
 */
final class SecretExposureTest extends TestCase
{
    private const SECRET = 'ssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssss';

    public function testTheSecretDoesNotAppearInADump(): void
    {
        ob_start();
        var_dump($this->key());
        $dump = (string) ob_get_clean();

        self::assertStringNotContainsString(self::SECRET, $dump);
        self::assertStringContainsString('[redacted]', $dump);
    }

    public function testTheDumpStillIdentifiesWhichKeyItIs(): void
    {
        // Redaction that also hides the kid would make key rotation
        // undebuggable, which is how redaction gets removed again.
        $info = $this->key()->__debugInfo();

        self::assertSame($this->key()->kid(), $info['kid']);
    }

    public function testAKeyWithoutASecretIsReportedAsSuch(): void
    {
        $info = (new SigningKey(publicKey: str_repeat('A', 32)))->__debugInfo();

        self::assertNull($info['secretKey']);
    }

    public function testTheJwkCarriesOnlyThePublicHalf(): void
    {
        $jwk = $this->key()->toJwk();

        // 'd' is the private-key member of an OKP JWK (RFC 8037).
        self::assertArrayNotHasKey('d', $jwk);
        self::assertSame(SigningKey::base64Url(str_repeat('A', 32)), $jwk['x']);
    }

    private function key(): SigningKey
    {
        return new SigningKey(publicKey: str_repeat('A', 32), secretKey: self::SECRET);
    }
}
