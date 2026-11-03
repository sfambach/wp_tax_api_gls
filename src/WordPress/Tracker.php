<?php declare(strict_types=1);

namespace TaxApiGls\WordPress;

use Taxmod\Core\Exception\SecretDoesNotOpen;
use Taxmod\WordPress\Import\Vault;
use TaxApiGls\Core\Api\AccessToken;
use TaxApiGls\Core\Api\GlsApiException;
use TaxApiGls\Core\Api\TokenClient;
use TaxApiGls\Core\Api\TrackingClient;
use TaxApiGls\Core\Tracking\Event;
use TaxApiGls\Core\Tracking\Parcel;
use TaxApiGls\Core\Tracking\Stage;

/**
 * Answers the filter `taxmod_gls_tracking` for other modules, in the shape of the DHL module's `taxmod_dhl_tracking`:
 * one parcel number in, its stage out. By default it hands back what was loaded last and never calls GLS; `refresh`
 * asks GLS now and keeps that answer. All calls of a day stay under DAILY_BUDGET, below the quota of 500.
 */
final class Tracker
{
    public const FILTER       = 'taxmod_gls_tracking';
    public const DAILY_BUDGET = 400;
    public const STORED       = 'stored';
    public const REFRESH      = 'refresh';

    private const LAST   = 'taxmod_gls_last_answers';
    private const KEPT   = 500;
    private const BUDGET = 'taxmod_gls_calls';
    // The token lasts four hours; it is a secret for that long, so it is kept sealed (D-951).
    private const TOKEN  = 'taxmod_gls_token';

    /** @var array<string, array<string, mixed>>|null `mixed`: the summary array */
    private static ?array $last = null;

    public function __construct(private readonly StoredAccess $access = new StoredAccess())
    {
    }

    /**
     * @param mixed $previous what an earlier callback returned; `mixed` because filters pass anything along
     * @param mixed $mode `stored` (default: the last answer, no call) or `refresh` (ask GLS now)
     * @return array{tracking_number: string, known: bool, stage: string, status_code: string, status: string, at: string, shipped_at: string, estimated: string, events: list<array{at: string, status_code: string, status: string, description: string, place: string}>, checked_at: int}|null
     *         null when nothing was loaded yet (stored), or the access is missing, the budget is used up or GLS failed
     *         (refresh); a number GLS does not know comes back as `known` false, stage `announced`
     */
    public function filter(mixed $previous, mixed $parcelNumber, mixed $mode = self::STORED): ?array
    {
        if (is_array($previous)) {
            return $previous;
        }

        $number = is_string($parcelNumber) ? (string) preg_replace('/[^A-Za-z0-9_]/', '', $parcelNumber) : '';

        if ($number === '') {
            return null;
        }

        $last = self::lastAnswers()[$number] ?? null;

        if ($mode !== self::REFRESH) {
            return $last;
        }

        try {
            $parcel = $this->ask($number);
        } catch (GlsApiException) {
            return null;
        }

        if ($parcel === null && is_array($last) && ($last['known'] ?? false) === true) {
            return $last;
        }

        return self::remember($parcel === null ? self::unknown($number) : self::summary($number, $parcel));
    }

    /** One call to GLS for one number, counted against the day's budget. */
    public function ask(string $number): ?Parcel
    {
        $this->spend();
        $client = new TrackingClient(new WpHttpTransport(), $this->token(), $this->access->environment());

        try {
            $parcels = $client->parcels([$number], self::language());
        } catch (GlsApiException $failure) {
            // A token GLS let expire early is dropped, so the next call fetches a fresh one.
            delete_transient(self::TOKEN);
            throw $failure;
        }

        return $parcels[0] ?? null;
    }

    /** @return array{day: string, calls: int} */
    public static function callsToday(): array
    {
        $stored = get_option(self::BUDGET);
        $today = current_time('Y-m-d');

        return is_array($stored) && ($stored['day'] ?? '') === $today ? ['day' => $today, 'calls' => (int) ($stored['calls'] ?? 0)] : ['day' => $today, 'calls' => 0];
    }

    /** Forgets the cached token, e.g. when the access changes. */
    public static function forgetToken(): void
    {
        delete_transient(self::TOKEN);
    }

    /** @return array{tracking_number: string, known: bool, stage: string, status_code: string, status: string, at: string, shipped_at: string, estimated: string, events: list<array{at: string, status_code: string, status: string, description: string, place: string}>} */
    public static function summary(string $number, Parcel $parcel): array
    {
        return [
            'tracking_number' => $number,
            'known'           => true,
            'stage'           => $parcel->stage()->value,
            'status_code'     => $parcel->status,
            'status'          => $parcel->events[0]->description ?? $parcel->status,
            'at'              => $parcel->statusAt,
            'shipped_at'      => $parcel->handedOverAt(),
            'estimated'       => '',
            'events'          => array_map(static fn (Event $event): array => [
                'at'          => $event->at,
                'status_code' => $event->code,
                'status'      => $event->description,
                'description' => $event->description,
                'place'       => $event->place,
            ], $parcel->events),
        ];
    }

    /** @return array{tracking_number: string, known: bool, stage: string, status_code: string, status: string, at: string, shipped_at: string, estimated: string, events: list<array{at: string, status_code: string, status: string, description: string, place: string}>} */
    private static function unknown(string $number): array
    {
        return ['tracking_number' => $number, 'known' => false, 'stage' => Stage::Announced->value, 'status_code' => '', 'status' => '',
            'at' => '', 'shipped_at' => '', 'estimated' => '', 'events' => []];
    }

    /**
     * @param array<string, mixed> $answer `mixed`: the summary array
     * @return array<string, mixed>
     */
    private static function remember(array $answer): array
    {
        $answer['checked_at'] = time();
        $all = self::lastAnswers();
        unset($all[$answer['tracking_number']]);
        $all[$answer['tracking_number']] = $answer;
        self::$last = array_slice($all, -self::KEPT, null, true);
        update_option(self::LAST, self::$last, false);

        return $answer;
    }

    /** @return array<string, array<string, mixed>> `mixed`: the summary array */
    private static function lastAnswers(): array
    {
        if (self::$last === null) {
            $stored     = get_option(self::LAST, []);
            self::$last = is_array($stored) ? $stored : [];
        }

        return self::$last;
    }

    private function token(): string
    {
        $sealed = get_transient(self::TOKEN);

        if (is_string($sealed) && $sealed !== '' && StoredAccess::vaultAvailable()) {
            try {
                return Vault::for(StoredAccess::VAULT_CONTEXT)->open($sealed);
            } catch (SecretDoesNotOpen) {
                delete_transient(self::TOKEN);
            }
        }

        $secret = $this->access->clientSecret();

        if ($this->access->clientKey() === '' || $secret === null) {
            throw new GlsApiException('No GLS client key or secret is set.');
        }

        $token = (new TokenClient(new WpHttpTransport(), $this->access->clientKey(), $secret, $this->access->environment()))->token();
        $this->keepToken($token);

        return $token->value;
    }

    private function keepToken(AccessToken $token): void
    {
        if (! StoredAccess::vaultAvailable() || $token->expiresIn < 120) {
            return;
        }

        set_transient(self::TOKEN, Vault::for(StoredAccess::VAULT_CONTEXT)->seal($token->value), $token->expiresIn - 60);
    }

    private function spend(): void
    {
        $today = self::callsToday();

        if ($today['calls'] >= self::DAILY_BUDGET) {
            throw new GlsApiException(sprintf('Daily budget of %d GLS calls used up.', self::DAILY_BUDGET));
        }

        update_option(self::BUDGET, ['day' => $today['day'], 'calls' => $today['calls'] + 1], false);
    }

    private static function language(): string
    {
        return str_starts_with(get_locale(), 'de') ? 'DE' : 'EN';
    }
}
