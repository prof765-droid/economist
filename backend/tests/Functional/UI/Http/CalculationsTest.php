<?php

declare(strict_types=1);

namespace App\Tests\Functional\UI\Http;

use App\Tests\Functional\ApiTestCase;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

final class CalculationsTest extends ApiTestCase
{
    private const string URI = '/api/calculations';

    public function testSaveReturnsIdAndLocation(): void
    {
        $client = self::createClient();
        $body = self::requestJson($client, 'POST', self::URI, self::validParameters());

        self::assertResponseStatusCodeSame(201);
        // Только id: результат «Поделиться» — ссылка, график фронт уже показал (дизайн 4.1).
        self::assertSame(['id'], array_keys($body));
        self::assertIsString($body['id']);
        self::assertInstanceOf(UuidV7::class, Uuid::fromString($body['id']));
        self::assertResponseHeaderSame('Location', self::URI.'/'.$body['id']);
    }

    public function testGetReturnsSameScheduleAsCalculate(): void
    {
        $client = self::createClient();
        self::requestJson($client, 'POST', self::URI, self::validParameters());
        $location = (string) $client->getResponse()->headers->get('Location');

        $saved = self::requestJson($client, 'GET', $location);
        self::assertResponseStatusCodeSame(200);
        $calculated = self::requestJson($client, 'POST', '/api/mortgage/calculate', self::validParameters());

        self::assertSame(['id', 'parameters', 'summary', 'schedule'], array_keys($saved));
        self::assertIsString($saved['id']);
        self::assertSame(self::URI.'/'.$saved['id'], $location);
        // В формате запроса, но уже после домена: ставка "18.5" округлена до "18.50".
        self::assertSame([
            'propertyPrice' => '5000000.00',
            'downPayment' => '1000000.00',
            'annualRate' => '18.50',
            'termMonths' => 240,
            'repaymentType' => 'annuity',
            'startDate' => '2026-10-23',
        ], $saved['parameters']);
        // Хранятся только параметры — график пересчитан и совпадает с calculate (дизайн 4.4).
        self::assertSame($calculated['summary'], $saved['summary']);
        self::assertSame($calculated['schedule'], $saved['schedule']);
    }

    public function testUnknownIdIs404(): void
    {
        $client = self::createClient();
        $body = self::requestJson($client, 'GET', self::URI.'/'.Uuid::v7()->toRfc4122());

        self::assertNotFoundProblem($body);
    }

    #[DataProvider('invalidIdProvider')]
    public function testInvalidIdIs404(string $id): void
    {
        $client = self::createClient();
        $body = self::requestJson($client, 'GET', self::URI.'/'.$id);

        self::assertNotFoundProblem($body);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidIdProvider(): iterable
    {
        yield 'not a uuid' => ['not-a-uuid'];
        // Валидный UUID, но не v7 — такие id сервер не выдаёт, роут не совпадает.
        yield 'uuid v4' => [Uuid::v4()->toRfc4122()];
    }

    public function testInvalidParametersAreNotSaved(): void
    {
        $client = self::createClient();
        // Ошибка домена (сумма кредита < 500 000) — тот же 422, что у calculate.
        $body = self::requestJson($client, 'POST', self::URI, self::validParameters(['downPayment' => '4900000.00']));

        self::assertResponseStatusCodeSame(422);
        self::assertSame('https://economist.local/errors/validation', $body['type']);
        // Соединение то же, что у приложения: dama держит одну транзакцию на тест.
        $connection = self::getContainer()->get(Connection::class);
        self::assertSame(0, $connection->fetchOne('SELECT count(*) FROM calculation'));
    }

    /**
     * @param array<mixed> $body
     */
    private static function assertNotFoundProblem(array $body): void
    {
        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame(['type' => 'about:blank', 'title' => 'Not Found', 'status' => 404], $body);
    }
}
