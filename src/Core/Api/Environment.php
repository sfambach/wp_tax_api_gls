<?php declare(strict_types=1);

namespace TaxApiGls\Core\Api;

/**
 * The two gateways of the GLS Group developer portal. Sandbox and production take the credentials of the same app;
 * which one an app may use is the portal's to say.
 *
 * @see https://gls-group.eu/DE/en/register-api-access/
 */
enum Environment: string
{
    case Sandbox    = 'sandbox';
    case Production = 'production';

    public function baseUrl(): string
    {
        return match ($this) {
            self::Sandbox    => 'https://api-sandbox.gls-group.net',
            self::Production => 'https://api.gls-group.net',
        };
    }
}
