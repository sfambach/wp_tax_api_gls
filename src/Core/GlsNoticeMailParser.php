<?php declare(strict_types=1);

namespace TaxApiGls\Core;

use Taxmod\Core\Import\Mail\MailMessage;
use Taxmod\Core\Import\Mail\OrderMailParser;
use Taxmod\Core\Import\OrderProposal;
use Taxmod\Core\Import\ProposalKind;

/**
 * GLS's parcel notices («Ihr Paket von … ist unterwegs», «… wurde durch GLS zugestellt», the sender's announcement
 * «Ihr Paket von …»): no shop order, only the parcel number. The modeller gives them to the order that carries that
 * number, whatever shop it is from (D-1020); the mail never creates an order (D-1017).
 *
 * @see https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/import/module-schnitt.md
 */
final class GlsNoticeMailParser implements OrderMailParser
{
    private const DOMAINS = ['gls-group.eu', 'gls-group.com', 'gls-pakete.de', 'gls-one.de'];

    public function __construct(private readonly string $version)
    {
    }

    public function key(): string
    {
        return 'gls';
    }

    public function shopName(): string
    {
        return 'GLS';
    }

    public function recognises(MailMessage $mail): bool
    {
        $domain = $mail->senderDomain();

        return array_filter(self::DOMAINS, static fn (string $gls): bool => $domain === $gls || str_ends_with($domain, '.' . $gls)) !== [];
    }

    public function parse(MailMessage $mail): OrderProposal
    {
        $number = $this->parcelNumber($mail);
        $kind = $this->kind($mail);

        if ($kind === ProposalKind::NoReceipt || $number === null) {
            return new OrderProposal($this->shopName(), '', ProposalKind::NoReceipt);
        }

        return new OrderProposal($this->shopName(), 'sendung:' . $number, $kind, trackingNumbers: [$number], numberMissing: true);
    }

    public function kinds(): array
    {
        return [ProposalKind::Shipped, ProposalKind::Delivered];
    }

    public function version(): string
    {
        return $this->version;
    }

    /**
     * Subject and the sentence naming the parcel, never the whole body: every notice carries the boilerplate
     * «… im nächstgelegenen GLS PaketShop zuzustellen». A change order («Änderungsauftrag», a drop-off permit)
     * moves no status, although it says «wird … zugestellt».
     */
    private function kind(MailMessage $mail): ProposalKind
    {
        $subject = mb_strtolower($mail->subject);

        if (str_contains($subject, 'änderungsauftrag') || str_contains($subject, 'abstellgenehmigung')) {
            return ProposalKind::NoReceipt;
        }

        $sentence = '';

        foreach ($mail->lines() as $line) {
            if (str_contains($line, 'Paket') && preg_match('/\d{11,12}/', $line) === 1) {
                $sentence = $line;
                break;
            }
        }

        $said = $subject . ' ' . mb_strtolower($sentence);

        return match (true) {
            preg_match('/zugestellt|zustellung erfolgt/u', $said) === 1 && ! str_contains($said, 'paketshop') => ProposalKind::Delivered,
            preg_match('/unterwegs|eingegangen|übergeben|paketshop|ihr paket von/u', $said) === 1 => ProposalKind::Shipped,
            default => ProposalKind::NoReceipt,
        };
    }

    /** The tracking link gls-group.eu/track/<number> stands in every notice, in text and HTML alike. */
    private function parcelNumber(MailMessage $mail): ?string
    {
        foreach ([$mail->text, $mail->html] as $body) {
            if (preg_match('/gls-group\.eu\/track\/([0-9A-Z]{8,14})\b/', $body, $found) === 1) {
                return $found[1];
            }
        }

        return preg_match('/Paket(?:nummer)?:?\s+(\d{11,12})\b/', $mail->text, $found) === 1 ? $found[1] : null;
    }
}
