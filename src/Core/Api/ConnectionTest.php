<?php declare(strict_types=1);

namespace TaxApiGls\Core\Api;

/**
 * Proves client key and secret by asking for a token; it reads no parcel and changes nothing.
 */
final class ConnectionTest
{
    public function __construct(private readonly TokenClient $client)
    {
    }

    /** @return string what GLS answered, in a few words */
    public function run(): string
    {
        $token = $this->client->token();

        return $token->expiresIn > 0
            ? sprintf('credentials accepted, token valid %d minutes', intdiv($token->expiresIn, 60))
            : 'credentials accepted';
    }
}
