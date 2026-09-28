<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig <mail@ole-hartwig.eu>
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Koh\SyliusWebBotAuth;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Publishes the Web Bot Auth key directory.
 */
final class KohSyliusWebBotAuthBundle extends AbstractBundle
{
    protected string $extensionAlias = 'koh_web_bot_auth';

    /** config/ sits at the package root, not under src/. */
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('purposes')
                    ->info('Declared use of the signed requests, e.g. rag.')
                    ->scalarPrototype()->end()
                    ->defaultValue(['rag'])
                ->end()
                ->arrayNode('keys')
                    ->info('Signing keys, newest first. List the outgoing and the incoming key during a rotation.')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('seed')
                                ->isRequired()
                                ->info('Secret seed. Use a dedicated secret — never APP_SECRET.')
                            ->end()
                            ->integerNode('not_before')
                                ->defaultNull()
                                ->info('Unix seconds. Serialised as milliseconds, per the directory format.')
                            ->end()
                            ->integerNode('expires_at')->defaultNull()->end()
                        ->end()
                    ->end()
                    ->defaultValue([])
                ->end()
            ->end();
    }

    /**
     * @param array<mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Drop entries whose seed resolves to an empty string. Without this a
        // shop with the env var unset publishes a key derived from '' — a
        // predictable identity anyone could impersonate.
        $keys = [];
        foreach ($config['keys'] ?? [] as $key) {
            if (\is_array($key) && '' !== (string) ($key['seed'] ?? '')) {
                $keys[] = $key;
            }
        }

        $builder->setParameter('koh_web_bot_auth.keys', $keys);
        $builder->setParameter('koh_web_bot_auth.purposes', $config['purposes'] ?? ['rag']);

        $container->import('../config/services.php');
    }
}
