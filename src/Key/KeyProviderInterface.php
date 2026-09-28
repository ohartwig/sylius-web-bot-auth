<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig <mail@ole-hartwig.eu>
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Koh\SyliusWebBotAuth\Key;

/**
 * Supplies the keys published in the signature directory.
 *
 * An interface rather than one hard-wired source because rotation is the whole
 * point of the directory: during a rollover both the old and the new key have
 * to be listed, and where the material lives (env, file, KMS) is a deployment
 * decision.
 */
interface KeyProviderInterface
{
    /** @return list<SigningKey> ordered newest first; the first entry is used for signing */
    public function getKeys(): array;
}
