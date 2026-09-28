<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig <mail@ole-hartwig.eu>
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Koh\SyliusWebBotAuth\Controller;

use Koh\SyliusAgentContracts\Http\AgentJsonResponder;
use Koh\SyliusWebBotAuth\Directory\SignatureDirectoryBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves /.well-known/http-message-signatures-directory.
 */
final class SignatureDirectoryController
{
    public function __construct(
        private readonly SignatureDirectoryBuilder $builder,
        private readonly AgentJsonResponder $responder,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        // An empty directory is worse than none: a verifier reads it as "this
        // origin has no valid keys" rather than "not configured yet".
        if (!$this->builder->hasKeys()) {
            throw new NotFoundHttpException('No Web Bot Auth signing keys are configured.');
        }

        return $this->responder->respond(
            $this->builder->build(),
            SignatureDirectoryBuilder::CONTENT_TYPE,
            request: $request,
        );
    }
}
