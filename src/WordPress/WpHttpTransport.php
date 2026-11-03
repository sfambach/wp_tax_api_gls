<?php declare(strict_types=1);

namespace TaxApiGls\WordPress;

use TaxApiGls\Core\Api\GlsApiException;
use TaxApiGls\Core\Api\HttpRequest;
use TaxApiGls\Core\Api\HttpResponse;
use TaxApiGls\Core\Api\HttpTransport;

/**
 * The core's HTTP port over `wp_remote_request`.
 */
final class WpHttpTransport implements HttpTransport
{
    public function send(HttpRequest $request): HttpResponse
    {
        $answer = wp_remote_request($request->url, [
            'method'  => $request->method,
            'headers' => $request->headers,
            'body'    => $request->body === '' ? null : $request->body,
            'timeout' => 20,
        ]);

        if (is_wp_error($answer)) {
            // Said apart from a refused key: a host that blocks outgoing calls makes applying for a key pointless.
            throw new GlsApiException('The web server cannot reach GLS (' . $answer->get_error_message() . ').');
        }

        return new HttpResponse(
            (int) wp_remote_retrieve_response_code($answer),
            (string) wp_remote_retrieve_body($answer),
            (string) wp_remote_retrieve_header($answer, 'correlation-id')
        );
    }
}
