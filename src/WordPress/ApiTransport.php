<?php declare(strict_types=1);

namespace TaxApiGls\WordPress;

use TaxApiGls\Core\Api\GlsApiException;
use Taxmod\Core\Http\HttpTransport;
use Taxmod\WordPress\Http\WpHttpTransport;

/** This module's way to the web: the modeller's one HTTP block (D-1415) with this module's failure and trace header. */
final class ApiTransport
{
    public static function make(): HttpTransport
    {
        return new WpHttpTransport(static fn (string $why): \Throwable => new GlsApiException('The web server cannot reach GLS (' . $why . ').'), 'correlation-id');
    }
}
