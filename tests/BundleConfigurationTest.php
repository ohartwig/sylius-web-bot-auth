<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig <mail@ole-hartwig.eu>
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Koh\SyliusWebBotAuth\Tests;

use Koh\SyliusWebBotAuth\KohSyliusWebBotAuthBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * The configuration here decides which signing identity the shop publishes.
 * The failure that matters is quiet: a deployment with the seed environment
 * variable unset would otherwise derive a key from the empty string and publish
 * it, which is a predictable identity anyone could reproduce and sign as.
 */
#[CoversClass(KohSyliusWebBotAuthBundle::class)]
final class BundleConfigurationTest extends TestCase
{
    public function testAKeyWithAnEmptySeedIsNotPublished(): void
    {
        // An unset environment variable resolves to the empty string. Deriving a
        // key from it produces the same key in every shop that has the same gap,
        // so the entry is dropped rather than published.
        $parameters = $this->parameters(['keys' => [['seed' => '']]]);

        self::assertSame([], $parameters['koh_web_bot_auth.keys']);
    }

    public function testAnEmptySeedIsDroppedWithoutDiscardingTheUsableKeys(): void
    {
        // A rotation lists two keys. One unset seed must not take the working
        // key down with it, nor silently promote itself into the directory.
        $parameters = $this->parameters(['keys' => [
            ['seed' => 'the-real-secret'],
            ['seed' => ''],
        ]]);

        self::assertCount(1, $parameters['koh_web_bot_auth.keys']);
        self::assertSame('the-real-secret', $parameters['koh_web_bot_auth.keys'][0]['seed']);
    }

    public function testAConfiguredKeyIsPublishedWithItsValidityWindow(): void
    {
        $parameters = $this->parameters(['keys' => [[
            'seed' => 'the-real-secret',
            'not_before' => 1750000000,
            'expires_at' => 1760000000,
        ]]]);

        $key = $parameters['koh_web_bot_auth.keys'][0];

        self::assertSame('the-real-secret', $key['seed']);
        self::assertSame(1750000000, $key['not_before']);
        self::assertSame(1760000000, $key['expires_at']);
    }

    public function testAKeyWithoutAValidityWindowIsUnbounded(): void
    {
        $key = $this->parameters(['keys' => [['seed' => 'the-real-secret']]])['koh_web_bot_auth.keys'][0];

        self::assertNull($key['not_before']);
        self::assertNull($key['expires_at']);
    }

    public function testAKeyWithoutASeedIsRejectedOutright(): void
    {
        // Distinct from the empty-seed case: a missing key fails the build, so a
        // typo in the config is loud rather than silently unsigned.
        $this->expectException(InvalidConfigurationException::class);

        $this->parameters(['keys' => [['not_before' => 1750000000]]]);
    }

    public function testNoKeysArePublishedByDefault(): void
    {
        // Installing the bundle must not make the shop claim a bot identity.
        self::assertSame([], $this->parameters()['koh_web_bot_auth.keys']);
    }

    public function testThePurposeDefaultsToRag(): void
    {
        self::assertSame(['rag'], $this->parameters()['koh_web_bot_auth.purposes']);
    }

    public function testPurposesCanBeDeclaredExplicitly(): void
    {
        // The declared purpose is what a verifier reads to decide whether the
        // signature is being used for something it is willing to accept.
        $parameters = $this->parameters(['purposes' => ['rag', 'search']]);

        self::assertSame(['rag', 'search'], $parameters['koh_web_bot_auth.purposes']);
    }

    public function testTheExtensionAliasIsStable(): void
    {
        // Operators write this key in config/packages; renaming it discards
        // their key configuration and the shop publishes an empty directory.
        self::assertSame('koh_web_bot_auth', $this->extension()->getAlias());
    }

    public function testTheBundlePathIsThePackageRootSoConfigResolves(): void
    {
        $bundle = new KohSyliusWebBotAuthBundle();

        self::assertDirectoryExists($bundle->getPath() . '/config');
        self::assertFileExists($bundle->getPath() . '/composer.json');
    }

    // --- helpers -------------------------------------------------------------

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function parameters(array $config = []): array
    {
        $builder = $this->containerBuilder();
        $this->extension()->load([$config], $builder);

        return $builder->getParameterBag()->all();
    }

    private function extension(): ExtensionInterface
    {
        $extension = (new KohSyliusWebBotAuthBundle())->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }

    private function containerBuilder(): ContainerBuilder
    {
        return new ContainerBuilder(new ParameterBag([
            'kernel.environment' => 'test',
            'kernel.debug' => true,
            'kernel.build_dir' => sys_get_temp_dir(),
            'kernel.project_dir' => \dirname(__DIR__),
        ]));
    }
}
