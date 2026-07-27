<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig
// SPDX-License-Identifier: MIT

declare(strict_types=1);

use Koh\SyliusWebBotAuth\Agent\WebBotAuthDescriptor;
use Koh\SyliusWebBotAuth\Controller\SignatureDirectoryController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->add('koh_web_bot_auth_directory', WebBotAuthDescriptor::PATH)
        ->controller(SignatureDirectoryController::class)
        ->methods(['GET', 'HEAD']);
};
