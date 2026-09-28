<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig <mail@ole-hartwig.eu>
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Koh\SyliusWebBotAuth\Tests\Controller;

use Koh\SyliusAgentContracts\Context\AgentContext;
use Koh\SyliusAgentContracts\Http\AgentJsonResponder;
use Koh\SyliusWebBotAuth\Agent\WebBotAuthDescriptor;
use Koh\SyliusWebBotAuth\Controller\SignatureDirectoryController;
use Koh\SyliusWebBotAuth\Directory\SignatureDirectoryBuilder;
use Koh\SyliusWebBotAuth\Key\SeedKeyProvider;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class SignatureDirectoryControllerTest extends TestCase
{
    public function testDirectoryIsServedUnderItsOwnMediaType(): void
    {
        $response = ($this->controller('seed-a'))(new Request());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            SignatureDirectoryBuilder::CONTENT_TYPE.'; charset=utf-8',
            $response->headers->get('Content-Type'),
        );
    }

    /**
     * An empty key list is a stronger claim than "not configured": a verifier
     * reads it as "this origin has no valid keys". 404 is the honest answer.
     */
    public function testUnconfiguredShopReturns404RatherThanAnEmptyKeyList(): void
    {
        $this->expectException(NotFoundHttpException::class);

        ($this->controller(''))(new Request());
    }

    public function testDirectoryIsPubliclyCacheableAndCorsOpen(): void
    {
        $response = ($this->controller('seed-a'))(new Request());

        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testEndpointIsAnnouncedOnlyWhenKeysExist(): void
    {
        self::assertCount(1, iterator_to_array($this->descriptor('seed-a')->getAgentEndpoints($this->context())));
        self::assertCount(0, iterator_to_array($this->descriptor('')->getAgentEndpoints($this->context())));
    }

    public function testAnnouncedPathMatchesTheRegisteredRoute(): void
    {
        $endpoints = iterator_to_array($this->descriptor('seed-a')->getAgentEndpoints($this->context()));

        self::assertSame('/.well-known/http-message-signatures-directory', $endpoints[0]->path);
        self::assertSame(WebBotAuthDescriptor::PATH, $endpoints[0]->path);
    }

    private function controller(string $seed): SignatureDirectoryController
    {
        return new SignatureDirectoryController($this->builder($seed), new AgentJsonResponder());
    }

    private function descriptor(string $seed): WebBotAuthDescriptor
    {
        return new WebBotAuthDescriptor($this->builder($seed));
    }

    private function builder(string $seed): SignatureDirectoryBuilder
    {
        return new SignatureDirectoryBuilder(new SeedKeyProvider([['seed' => $seed]]));
    }

    private function context(): AgentContext
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getCode')->willReturn('WEB_DE');

        return new AgentContext($channel, 'https://shop.example.com', 'de_DE', ['de_DE']);
    }
}
