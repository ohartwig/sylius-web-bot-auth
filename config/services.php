<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig <mail@ole-hartwig.eu>
// SPDX-License-Identifier: MIT

declare(strict_types=1);

use Koh\SyliusAgentContracts\Http\AgentJsonResponder;
use Koh\SyliusWebBotAuth\Agent\WebBotAuthDescriptor;
use Koh\SyliusWebBotAuth\Controller\SignatureDirectoryController;
use Koh\SyliusWebBotAuth\Directory\SignatureDirectoryBuilder;
use Koh\SyliusWebBotAuth\Key\KeyProviderInterface;
use Koh\SyliusWebBotAuth\Key\SeedKeyProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(SeedKeyProvider::class)
        ->args([param('koh_web_bot_auth.keys')]);

    // Swap in your own KMS-backed provider by aliasing this interface.
    $services->alias(KeyProviderInterface::class, SeedKeyProvider::class);

    $services->set(SignatureDirectoryBuilder::class)
        ->args([service(KeyProviderInterface::class), param('koh_web_bot_auth.purposes')])
        ->public();

    $services->set(SignatureDirectoryController::class)
        ->args([service(SignatureDirectoryBuilder::class), service(AgentJsonResponder::class)])
        ->public()
        ->tag('controller.service_arguments');

    $services->set(WebBotAuthDescriptor::class)
        ->args([service(SignatureDirectoryBuilder::class)])
        ->tag('koh_agent.endpoint');
};
