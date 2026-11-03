<?php declare(strict_types=1);

namespace TaxApiGls\Core\Api;

use TaxApiGls\Core\Tracking\Parcel;

/**
 * GET /tracking/simple/trackids/{unitnos} of Track And Trace V1 with a bearer token of the Authentication API v2.
 * Up to 10 parcel numbers per call; the daily quota (500 by default) is the boundary's to keep.
 *
 * @see /mnt/project-files/gls-api/track-and-trace-v1.yaml (1.0.7, from the GLS developer portal)
 */
final class TrackingClient
{
    public const MAX_PER_CALL = 10;

    public function __construct(
        private readonly HttpTransport $transport,
        #[\SensitiveParameter] private readonly string $token,
        private readonly Environment $environment = Environment::Production,
    ) {
    }

    /**
     * @param list<string> $numbers parcel numbers (11 or 12 digits in Germany, 8 characters elsewhere)
     * @return list<Parcel> in GLS's order; numbers GLS does not know are left out, a reference may bring several
     * @throws GlsApiException on an expired token, an exceeded quota or any other failure
     */
    public function parcels(array $numbers, string $language = 'DE'): array
    {
        $numbers = array_values(array_unique(array_filter(array_map(static fn (string $n): string => (string) preg_replace('/[^A-Za-z0-9_]/', '', $n), $numbers))));

        if ($numbers === []) {
            return [];
        }

        if (count($numbers) > self::MAX_PER_CALL) {
            throw new GlsApiException(sprintf('At most %d parcel numbers per call.', self::MAX_PER_CALL));
        }

        $response = $this->transport->send(new HttpRequest('GET', $this->environment->baseUrl() . '/track-and-trace-v1/tracking/simple/trackids/'
            . implode(',', array_map('rawurlencode', $numbers)) . '?showEvents=true', [
            'Authorization'   => 'Bearer ' . $this->token,
            'Accept'          => 'application/json',
            'Accept-Language' => $language,
        ]));

        if ($response->status !== 200) {
            throw new GlsApiException(self::problem($response));
        }

        $found = [];

        foreach (is_array($response->json()['parcels'] ?? null) ? $response->json()['parcels'] : [] as $row) {
            // A number GLS does not know comes back as {requested, errorCode E_404_01}, not as an HTTP error.
            if (! is_array($row) || isset($row['errorCode']) || ! is_string($row['status'] ?? null)) {
                continue;
            }

            $found[] = Parcel::fromApi($row);
        }

        return $found;
    }

    private static function problem(HttpResponse $response): string
    {
        $body = $response->json();
        $text = match ($response->status) {
            401     => 'GLS refused the token',
            429     => 'GLS refused: daily quota used up',
            default => 'GLS answered HTTP ' . $response->status,
        };
        $fault = is_array($body['fault'] ?? null) ? $body['fault'] : [];

        foreach ([$body['detail'] ?? null, $fault['faultstring'] ?? null, $body['title'] ?? null] as $said) {
            if (is_string($said) && $said !== '') {
                return $text . ': ' . $said;
            }
        }

        return $text . '.';
    }
}
