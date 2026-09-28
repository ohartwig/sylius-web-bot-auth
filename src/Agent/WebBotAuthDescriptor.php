<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig <mail@ole-hartwig.eu>
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Koh\SyliusWebBotAuth\Agent;

use Koh\SyliusAgentContracts\Context\AgentContext;
use Koh\SyliusAgentContracts\Model\AgentEndpoint;
use Koh\SyliusAgentContracts\Provider\AgentEndpointProviderInterface;
use Koh\SyliusWebBotAuth\Directory\SignatureDirectoryBuilder;

/**
 * Announces the directory so llms.txt and the api-catalog pick it up.
 *
 * Contributes nothing when no keys are configured — the family's rule is never
 * to advertise a capability that has no endpoint behind it.
 */
final class WebBotAuthDescriptor implements AgentEndpointProviderInterface
{
    public const PATH = '/.well-known/http-message-signatures-directory';

    public function __construct(private readonly SignatureDirectoryBuilder $builder)
    {
    }

    public function getAgentEndpoints(AgentContext $context): iterable
    {
        if (!$this->builder->hasKeys()) {
            return;
        }

        yield new AgentEndpoint(
            path: self::PATH,
            title: 'Web Bot Auth key directory',
            contentType: SignatureDirectoryBuilder::CONTENT_TYPE,
            protocol: 'web-bot-auth',
            description: 'Ed25519 public keys used to sign requests this shop sends.',
        );
    }
}
