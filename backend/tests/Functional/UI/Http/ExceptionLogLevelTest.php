<?php

declare(strict_types=1);

namespace App\Tests\Functional\UI\Http;

use App\Tests\Functional\ApiTestCase;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Uid\Uuid;

/**
 * Уровни из framework.exceptions: на проде fingers_crossed с порогом error сбрасывает буфер логов
 * на каждое исключение уровня ERROR и выше, ошибки клиента не должны до него дотягивать (дизайн 5).
 */
final class ExceptionLogLevelTest extends ApiTestCase
{
    private const string CALCULATE_URI = '/api/mortgage/calculate';
    // Как в config/packages/rate_limiter.yaml.
    private const int CALCULATE_LIMIT = 60;

    /**
     * Код ответа проверяем, чтобы кейс не «прошёл» на чужой ошибке: например, 415 вместо 422.
     *
     * @return iterable<string, array{string, string, string, ?string, int}> метод, URI, Content-Type, тело, код ответа
     */
    public static function clientErrors(): iterable
    {
        yield 'формат поля, 422 от Validator' => ['POST', self::CALCULATE_URI, 'application/json', self::json(['propertyPrice' => 'abc']), 422];
        yield 'условия кредита, доменный 422' => ['POST', self::CALCULATE_URI, 'application/json', self::json(['downPayment' => '4900000.00']), 422];
        yield 'битый JSON, 400' => ['POST', self::CALCULATE_URI, 'application/json', '{', 400];
        yield 'не JSON, 415' => ['POST', self::CALCULATE_URI, 'application/xml', '<request/>', 415];
        yield 'расчёт не найден, 404' => ['GET', '/api/calculations/'.Uuid::v7()->toRfc4122(), 'application/json', null, 404];
    }

    #[DataProvider('clientErrors')]
    public function testClientErrorIsLoggedAsInfo(string $method, string $uri, string $contentType, ?string $content, int $status): void
    {
        $client = self::createClient();
        $log = self::captureRequestLog();

        $client->request($method, $uri, server: ['CONTENT_TYPE' => $contentType], content: $content);

        self::assertResponseStatusCodeSame($status);
        self::assertSame(Level::Info, self::exceptionRecord($log)->level);
    }

    public function testTooManyRequestsIsLoggedAsWarning(): void
    {
        $client = self::createClient();
        // Счётчики лимитера — в array-кэше: перезагрузка ядра между запросами обнулила бы их.
        $client->disableReboot();
        for ($i = 0; $i < self::CALCULATE_LIMIT; ++$i) {
            self::requestJson($client, 'POST', self::CALCULATE_URI, self::validParameters());
        }
        $log = self::captureRequestLog();

        self::requestJson($client, 'POST', self::CALCULATE_URI, self::validParameters());

        self::assertResponseStatusCodeSame(429);
        self::assertSame(Level::Warning, self::exceptionRecord($log)->level);
    }

    /**
     * ErrorListener пишет в канал request. TestHandler сверху стека получает запись до fingers_crossed,
     * поэтому видно исходный уровень, а не то, что дошло до файла.
     */
    private static function captureRequestLog(): TestHandler
    {
        $logger = self::getContainer()->get('monolog.logger.request');
        self::assertInstanceOf(Logger::class, $logger);
        $logger->pushHandler($handler = new TestHandler());

        return $handler;
    }

    private static function exceptionRecord(TestHandler $log): LogRecord
    {
        $records = array_values(array_filter($log->getRecords(), static fn (LogRecord $r): bool => isset($r->context['exception'])));
        self::assertCount(1, $records, 'ErrorListener должен записать исключение ровно один раз');

        return $records[0];
    }

    /**
     * @param array<string, mixed> $override
     */
    private static function json(array $override): string
    {
        return json_encode(self::validParameters($override), \JSON_THROW_ON_ERROR);
    }
}
