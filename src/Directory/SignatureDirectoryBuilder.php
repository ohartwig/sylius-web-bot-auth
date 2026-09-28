<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig <mail@ole-hartwig.eu>
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Koh\SyliusWebBotAuth\Directory;

use Koh\SyliusWebBotAuth\Key\KeyProviderInterface;

/**
 * Builds the HTTP Message Signatures directory document.
 *
 * Publishing the directory is only half of Web Bot Auth: it lets a receiving
 * site verify requests *this* shop sends out. It says nothing about requests
 * arriving here. The README is explicit about that, because a published
 * directory reads like inbound verification and is not.
 */
final class SignatureDirectoryBuilder
{
    public const CONTENT_TYPE = 'application/http-message-signatures-directory+json';

    /** @param list<string> $purposes */
    public function __construct(
        private readonly KeyProviderInterface $keyProvider,
        private readonly array $purposes = ['rag'],
    ) {
    }

    /** @return array<string, mixed> */
    public function build(): array
    {
        $keys = [];
        foreach ($this->keyProvider->getKeys() as $key) {
            $keys[] = $key->toJwk();
        }

        $directory = ['keys' => $keys];

        if ([] !== $this->purposes) {
            $directory['purpose'] = 1 === \count($this->purposes) ? $this->purposes[0] : $this->purposes;
        }

        return $directory;
    }

    public function hasKeys(): bool
    {
        return [] !== $this->keyProvider->getKeys();
    }
}
