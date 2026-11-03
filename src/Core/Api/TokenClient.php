<?php declare(strict_types=1);

namespace TaxApiGls\Core\Api;

/**
 * POST /oauth2/v2/token of the GLS Authentication API v2: client key and secret as Basic auth, grant type
 * client_credentials. Every other GLS API takes the token as `Authorization: Bearer`.
 *
 * @see https://github.com/smart-dato/gls-authenticator/blob/main/documentation/authentification-service-v2.yaml
 */
final class TokenClient
{
    public function __construct(
        private readonly HttpTransport $transport,
        private readonly string $clientKey,
        #[\SensitiveParameter] private readonly string $clientSecret,
        private readonly Environment $environment = Environment::Production,
    ) {
    }

    /** @throws GlsApiException on refused credentials or any other failure */
    public function token(): AccessToken
    {
        $response = $this->transport->send(new HttpRequest('POST', $this->environment->baseUrl() . '/oauth2/v2/token', [
            'Authorization' => 'Basic ' . base64_encode($this->clientKey . ':' . $this->clientSecret),
            'Content-Type'  => 'application/x-www-form-urlencoded',
            'Accept'        => 'application/json',
        ], 'grant_type=client_credentials'));

        if ($response->status !== 200) {
            throw new GlsApiException(self::problem($response));
        }

        $body = $response->json();
        $token = $body['access_token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new GlsApiException('GLS answered without a token.');
        }

        return new AccessToken($token, is_numeric($body['expires_in'] ?? null) ? (int) $body['expires_in'] : 0);
    }

    private static function problem(HttpResponse $response): string
    {
        $body = $response->json();
        $text = match ($response->status) {
            400, 401, 403 => 'GLS refused client key or secret',
            429           => 'GLS refused: too many calls',
            default       => 'GLS answered HTTP ' . $response->status,
        };

        // The gateway answers {"errors":[{"type":"400","message":"invalid credentials"}]}; OAuth servers use error_description.
        $first = is_array($body['errors'] ?? null) && is_array($body['errors'][0] ?? null) ? $body['errors'][0] : [];

        foreach ([$first['message'] ?? null, $body['error_description'] ?? null, $body['error'] ?? null, $body['message'] ?? null] as $said) {
            if (is_string($said) && $said !== '') {
                return $text . ': ' . $said;
            }
        }

        return $text . '.';
    }
}
