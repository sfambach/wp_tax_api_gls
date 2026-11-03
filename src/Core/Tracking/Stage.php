<?php declare(strict_types=1);

namespace TaxApiGls\Core\Tracking;

/**
 * Where a parcel stands for a shipping page — the same four words as the DHL module, so a page can ask either.
 * Derived from GLS's `status`; a parcel in a ParcelShop is not with the recipient yet and stays «shipped» (D-1064, D-1101).
 */
enum Stage: string
{
    /** GLS has the data, not the parcel. */
    case Announced = 'announced';
    case Shipped   = 'shipped';
    case Delivered = 'delivered';
    /** Not picked up, not delivered or cancelled; someone has to look. */
    case Problem   = 'problem';

    public static function of(Parcel $parcel): self
    {
        return match ($parcel->status) {
            'PLANNEDPICKUP', 'INPICKUP', 'PREADVICE'                => self::Announced,
            'INTRANSIT', 'INDELIVERY', 'INWAREHOUSE', 'DELIVEREDPS' => self::Shipped,
            'DELIVERED'                                             => self::Delivered,
            'NOTPICKEDUP', 'NOTDELIVERED', 'CANCELED'               => self::Problem,
            // «History closed» says nothing about how it ended; the events do.
            'FINAL'                                                 => self::fromEvents($parcel),
            default                                                 => self::Announced,
        };
    }

    private static function fromEvents(Parcel $parcel): self
    {
        foreach ($parcel->events as $event) {
            if (str_starts_with($event->code, 'DELIVD.') && $event->code !== 'DELIVD.PARCELSHOP') {
                return self::Delivered;
            }
        }

        return self::Problem;
    }
}
