<?php declare(strict_types=1);

namespace TaxApiGls\Core\Tracking;

/**
 * One entry of a parcel's history as GLS reports it (EventDTO of Track And Trace V1).
 */
final class Event
{
    public function __construct(
        public readonly string $code,
        public readonly string $description,
        public readonly string $at,
        public readonly string $place,
    ) {
    }

    /** @param array<string, mixed> $event `mixed`: decoded JSON */
    public static function fromApi(array $event): self
    {
        $text = static fn (string $key): string => is_string($event[$key] ?? null) ? trim($event[$key]) : '';
        $place = trim($text('postalCode') . ' ' . $text('city'));

        return new self($text('code'), $text('description'), $text('eventDateTime'), $place === '' ? $text('country') : $place . ($text('country') === '' ? '' : ', ' . $text('country')));
    }
}
