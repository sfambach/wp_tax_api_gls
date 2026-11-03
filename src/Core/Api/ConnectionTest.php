<?php declare(strict_types=1);

namespace TaxApiGls\Core\Api;

/**
 * Proves client key and secret by asking for a token, and Track And Trace V1 by reading GLS's own test parcel; it
 * changes nothing.
 */
final class ConnectionTest
{
    /** From the test data set of the specification 1.0.7: always «DELIVERED», in sandbox and production alike. */
    public const PROBE_NUMBER = 'QAS_0000008';

    public function __construct(
        private readonly TokenClient $tokens,
        private readonly HttpTransport $transport,
        private readonly Environment $environment = Environment::Production,
    ) {
    }

    /** @return string what GLS answered, in a few words */
    public function run(): string
    {
        $token = $this->tokens->token();
        $parcels = (new TrackingClient($this->transport, $token->value, $this->environment))->parcels([self::PROBE_NUMBER], 'EN');
        $probe = $parcels[0] ?? null;

        return 'credentials accepted' . ($token->expiresIn > 0 ? sprintf(' (token valid %d minutes)', intdiv($token->expiresIn, 60)) : '')
            . ', Track And Trace ' . ($probe === null ? 'answers, test parcel unknown' : 'works, test parcel ' . $probe->status);
    }
}
