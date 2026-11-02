<?php declare(strict_types=1);

namespace TaxApiGls\Tests\Core;

use PHPUnit\Framework\TestCase;
use TaxApiGls\Core\GlsNoticeMailParser;
use Taxmod\Core\Import\Mail\MimeParser;

/**
 * The fixtures are synthetic, shaped after real GLS notices of 2019 and 2020.
 */
final class GlsNoticeMailParserTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function read(string $fixture): array
    {
        $parser = new GlsNoticeMailParser('0.0.1');
        $mail = (new MimeParser())->parse(1, (string) file_get_contents(__DIR__ . '/fixtures/' . $fixture . '.eml'));
        self::assertTrue($parser->recognises($mail), 'not recognised: ' . $fixture);

        return $parser->parse($mail)->toArray();
    }

    public function testOnTheWayIsShippedToTheOrderWithItsParcelNumber(): void
    {
        $notice = self::read('g1');

        self::assertSame(['GLS', 'sendung:10000000001', 'shipped', ['10000000001']], [$notice['platform'], $notice['order_number'], $notice['kind'], $notice['tracking']]);
        self::assertTrue($notice['number_missing']);
    }

    public function testDeliveredByGlsIsDelivered(): void
    {
        self::assertSame(['delivered', ['10000000002']], [self::read('g2')['kind'], self::read('g2')['tracking']]);
    }

    public function testTheSendersAnnouncementIsShipped(): void
    {
        self::assertSame(['shipped', ['10000000003']], [self::read('g3')['kind'], self::read('g3')['tracking']]);
    }

    public function testAChangeOrderMovesNoStatusAlthoughItSaysDelivered(): void
    {
        self::assertSame('no_receipt', self::read('g4')['kind']);
    }

    public function testTheNumberIsReadWithoutTheTrackingLink(): void
    {
        self::assertSame(['10000000005'], self::read('g5')['tracking']);
    }

    public function testAMailWithoutParcelNumberBringsNothing(): void
    {
        self::assertSame('no_receipt', self::read('g6')['kind']);
    }

    public function testOtherSendersAreNotGls(): void
    {
        $mail = (new MimeParser())->parse(1, "From: x@dhl.de\r\nSubject: Ihr Paket ist unterwegs\r\n\r\nx");
        self::assertFalse((new GlsNoticeMailParser('0.0.1'))->recognises($mail));
    }
}
