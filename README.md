<!--
SPDX-FileCopyrightText: 2026 Kai Ole Hartwig <mail@ole-hartwig.eu>
SPDX-License-Identifier: MIT
-->

# koh/sylius-web-bot-auth

Publishes a [Web Bot Auth](https://datatracker.ietf.org/wg/webbotauth/about/) key
directory at `/.well-known/http-message-signatures-directory`.

## What this does — and what it does not

The directory lets a **receiving** site verify HTTP Message Signatures on
requests **this shop sends out** (a price crawl, a feed fetch, an outbound agent
call). It says nothing about requests arriving here, and installing it does not
make the shop verify anything.

That distinction is worth stating because a published directory reads like
inbound verification. If you want to authenticate incoming bots, this package is
the wrong half of the protocol.

## Install

```bash
composer require koh/sylius-web-bot-auth
```

```php
// config/bundles.php
Koh\SyliusWebBotAuth\KohSyliusWebBotAuthBundle::class => ['all' => true],
```

```yaml
# config/routes/koh_agent.yaml
koh_web_bot_auth:
    resource: '@KohSyliusWebBotAuthBundle/config/routes.php'
    ignore_errors: not_found
```

## Configure

```yaml
# config/packages/koh_web_bot_auth.yaml
koh_web_bot_auth:
    purposes: ['rag']
    keys:
        - seed: '%env(WEB_BOT_AUTH_SEED)%'
```

Generate a seed:

```bash
php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
```

**Use a dedicated secret. Never derive the seed from `APP_SECRET`.** It is
tempting — the value is already there — but it welds the signing identity to the
session secret: rotating one invalidates the other, and anything that leaks
`APP_SECRET` also lets an attacker sign as this bot.

Keys are derived deterministically, so every instance behind the load balancer
publishes the same directory. A per-process random key would make the directory
contradict itself between requests.

With no key configured the route returns 404. That is deliberate: an empty
directory reads to a verifier as *this origin has no valid keys*, which is a
stronger and wronger claim than *not configured*.

### Rotation

List both keys, newest first:

```yaml
koh_web_bot_auth:
    keys:
        - seed: '%env(WEB_BOT_AUTH_SEED_2027)%'
          not_before: 1798761600
        - seed: '%env(WEB_BOT_AUTH_SEED_2026)%'
          expires_at: 1798761600
```

`not_before` and `expires_at` are configured in unix **seconds** and serialised
as **milliseconds**, which is what the directory format expects.

The `kid` is not configurable — it is the RFC 7638 thumbprint of the key it
names, derived on every render. A hand-maintained `kid` can drift from its key,
and a drifted `kid` makes a rotation impossible to verify.

### Custom key source

Alias the interface to use a KMS, a file, or a hardware token:

```yaml
services:
    Koh\SyliusWebBotAuth\Key\KeyProviderInterface: '@App\Security\KmsKeyProvider'
```

## Output

```json
{
  "keys": [
    {
      "kid": "NzbLsXh8uDCcd-6MNwXF4W_7noWXFZAfHkxZsRGC9Xs",
      "kty": "OKP",
      "crv": "Ed25519",
      "x": "11qYAYKxCrfVS_7TyWQHOg7hcvPapiMlrwIaaPcHURo",
      "use": "sig",
      "alg": "EdDSA"
    }
  ],
  "purpose": "rag"
}
```

## Decommission

Remove the `composer require`, the `config/bundles.php` entry, the route import
and `config/packages/koh_web_bot_auth.yaml`. Then **revoke the seed**: the
directory disappearing does not invalidate signatures already made with that
key, so rotate the secret wherever it is stored.

## Security

Report vulnerabilities privately — see [SECURITY.md](SECURITY.md). Advisories
are published as CSAF 2.0 at <https://ole-hartwig.eu/.well-known/csaf/>.

The family threat model, covering this package, is at
<https://github.com/ohartwig/sylius-agent-ready/blob/main/docs/threat-model.md>.

## Licence

MIT — see [LICENSE](LICENSE).
