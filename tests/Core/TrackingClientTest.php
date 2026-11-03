<?php declare(strict_types=1);

namespace TaxApiGls\Tests\Core;

use PHPUnit\Framework\TestCase;
use TaxApiGls\Core\Api\ConnectionTest;
use TaxApiGls\Core\Api\GlsApiException;
use TaxApiGls\Core\Api\HttpResponse;
use TaxApiGls\Core\Api\TokenClient;
use TaxApiGls\Core\Api\TrackingClient;
use TaxApiGls\Core\Tracking\Event;
use TaxApiGls\Core\Tracking\Parcel;
use TaxApiGls\Core\Tracking\Stage;

/**
 * The fixture is built from the examples of the specification 1.0.7.
 */
final class TrackingClientTest extends TestCase
{
    public function testAsksForSeveralNumbersInOneCallWithTheBearerToken(): void
    {
        $transport = new ScriptedTransport([new HttpResponse(200, (string) file_get_contents(__DIR__ . '/fixtures/parcels-example.json'))]);
        $parcels = (new TrackingClient($transport, 'tok'))->parcels(['42249018928', '363 0191 7596', '42249028960']);

        $sent = $transport->sent[0];
        self::assertSame('https://api.gls-group.net/track-and-trace-v1/tracking/simple/trackids/42249018928,36301917596,42249028960?showEvents=true', $sent->url);
        self::assertSame(['Bearer tok', 'DE'], [$sent->headers['Authorization'], $sent->headers['Accept-Language']]);
        self::assertSame(['36301917596', '42249028960'], array_map(static fn (Parcel $p): string => $p->requested, $parcels), 'an unknown number is left out');
        self::assertSame(['PREADVICE', 'announced'], [$parcels[0]->status, $parcels[0]->stage()->value]);
    }

    public function testAParcelInAParcelShopIsShippedNotDelivered(): void
    {
        $parcel = (new TrackingClient(new ScriptedTransport([new HttpResponse(200, (string) file_get_contents(__DIR__ . '/fixtures/parcels-example.json'))]), 't'))->parcels(['42249028960'])[1];

        self::assertSame(Stage::Shipped, $parcel->stage());
        self::assertSame('2025-02-10T15:11:12+0100', $parcel->handedOverAt());
        self::assertSame('36286 Neuenstein, DE', $parcel->events[1]->place);
    }

    /** @return array<string, array{string, list<string>, Stage}> */
    public static function statuses(): array
    {
        return [
            'in transit'           => ['INTRANSIT', [], Stage::Shipped],
            'in delivery'          => ['INDELIVERY', [], Stage::Shipped],
            'delivered'            => ['DELIVERED', [], Stage::Delivered],
            'not delivered'        => ['NOTDELIVERED', [], Stage::Problem],
            'cancelled'            => ['CANCELED', [], Stage::Problem],
            'closed after door'    => ['FINAL', ['DELIVD.NORMAL'], Stage::Delivered],
            'closed in parcelshop' => ['FINAL', ['DELIVD.PARCELSHOP'], Stage::Problem],
            'planned pickup'       => ['PLANNEDPICKUP', [], Stage::Announced],
        ];
    }

    /** @param list<string> $codes */
    #[\PHPUnit\Framework\Attributes\DataProvider('statuses')]
    public function testStatusGivesStage(string $status, array $codes, Stage $stage): void
    {
        $events = array_map(static fn (string $code): Event => new Event($code, '', '', ''), $codes);

        self::assertSame($stage, (new Parcel('1', '1', $status, '', $events))->stage());
    }

    public function testMoreThanTenNumbersAreRefusedBeforeAsking(): void
    {
        $this->expectException(GlsApiException::class);

        (new TrackingClient(new ScriptedTransport([]), 't'))->parcels(array_map('strval', range(10000000001, 10000000011)));
    }

    public function testAnExpiredTokenSaysSo(): void
    {
        $this->expectExceptionMessage('GLS refused the token: The Token has expired: policy(Verify-JWT)');

        (new TrackingClient(new ScriptedTransport([new HttpResponse(401, '{"fault":{"faultstring":"The Token has expired: policy(Verify-JWT)"}}')]), 't'))->parcels(['1']);
    }

    public function testTheConnectionTestReadsGlsTestParcel(): void
    {
        $transport = new ScriptedTransport([
            new HttpResponse(200, '{"access_token":"abc","expires_in":14400}'),
            new HttpResponse(200, '{"parcels":[{"requested":"QAS_0000008","unitno":"QAS_0000008","status":"DELIVERED","events":[]}]}'),
        ]);

        self::assertSame('credentials accepted (token valid 240 minutes), Track And Trace works, test parcel DELIVERED',
            (new ConnectionTest(new TokenClient($transport, 'key', 'secret'), $transport))->run());
        self::assertStringContainsString('/trackids/QAS_0000008?', $transport->sent[1]->url);
    }
}
