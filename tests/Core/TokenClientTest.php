<?php declare(strict_types=1);

namespace TaxApiGls\Tests\Core;

use PHPUnit\Framework\TestCase;
use TaxApiGls\Core\Api\Environment;
use TaxApiGls\Core\Api\GlsApiException;
use TaxApiGls\Core\Api\HttpResponse;
use TaxApiGls\Core\Api\TokenClient;

final class TokenClientTest extends TestCase
{
    public function testAsksForAClientCredentialsTokenWithBasicAuth(): void
    {
        $transport = new ScriptedTransport([new HttpResponse(200, '{"access_token":"abc","token_type":"Bearer","expires_in":14400}')]);
        $token = (new TokenClient($transport, 'key', 'secret', Environment::Sandbox))->token();

        self::assertSame(['abc', 14400], [$token->value, $token->expiresIn]);
        $sent = $transport->sent[0];
        self::assertSame(['POST', 'https://api-sandbox.gls-group.net/oauth2/v2/token', 'grant_type=client_credentials'], [$sent->method, $sent->url, $sent->body]);
        self::assertSame('Basic ' . base64_encode('key:secret'), $sent->headers['Authorization']);
    }

    public function testRefusedCredentialsSaySo(): void
    {
        $this->expectException(GlsApiException::class);
        $this->expectExceptionMessage('GLS refused client key or secret: invalid_client');

        (new TokenClient(new ScriptedTransport([new HttpResponse(401, '{"error":"invalid_client"}')]), 'key', 'wrong'))->token();
    }

    public function testTheGatewaysErrorListIsRead(): void
    {
        $this->expectExceptionMessage('GLS refused client key or secret: invalid credentials');

        (new TokenClient(new ScriptedTransport([new HttpResponse(400, '{"errors":[{"type":"400","message":"invalid credentials"}]}')]), 'x', 'y'))->token();
    }

    public function testAnAnswerWithoutTokenIsAFailure(): void
    {
        $this->expectException(GlsApiException::class);

        (new TokenClient(new ScriptedTransport([new HttpResponse(200, '{}')]), 'key', 'secret'))->token();
    }
}
