<?php declare(strict_types=1);

namespace TaxApiGls\Core\Api;

/**
 * A bearer token of the Authentication API v2 and how long it lasts.
 */
final class AccessToken
{
    /** @param int $expiresIn seconds */
    public function __construct(
        #[\SensitiveParameter] public readonly string $value,
        public readonly int $expiresIn,
    ) {
    }
}
