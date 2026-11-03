<?php declare(strict_types=1);

namespace TaxApiGls\Core\Tracking;

/**
 * A parcel's current status and history (ParcelDTO of Track And Trace V1), newest event first as GLS sends them.
 */
final class Parcel
{
    /** @param list<Event> $events */
    public function __construct(
        public readonly string $requested,
        public readonly string $unitNumber,
        public readonly string $status,
        public readonly string $statusAt,
        public readonly array $events,
    ) {
    }

    /** @param array<string, mixed> $parcel `mixed`: decoded JSON */
    public static function fromApi(array $parcel): self
    {
        $text = static fn (string $key): string => is_string($parcel[$key] ?? null) ? trim($parcel[$key]) : '';
        $events = is_array($parcel['events'] ?? null) ? $parcel['events'] : [];

        return new self(
            $text('requested'),
            $text('unitno') === '' ? $text('requested') : $text('unitno'),
            $text('status'),
            $text('statusDateTime'),
            array_values(array_map(static fn (array $event): Event => Event::fromApi($event), array_filter($events, 'is_array'))),
        );
    }

    public function stage(): Stage
    {
        return Stage::of($this);
    }

    /** When GLS first had the parcel in hand: the oldest event that is not only data (INTIAL.PREADVICE, INTIAL.E48_DATA). */
    public function handedOverAt(): string
    {
        foreach (array_reverse($this->events) as $event) {
            if (! in_array($event->code, ['INTIAL.PREADVICE', 'INTIAL.E48_DATA'], true)) {
                return $event->at;
            }
        }

        return '';
    }
}
