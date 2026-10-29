<?php

declare(strict_types=1);

namespace App\Tests\Functional\UI\Http;

use App\Tests\Functional\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class RateLimitTest extends ApiTestCase
{
    private const string SAVE_URI = '/api/calculations';
    private const string CALCULATE_URI = '/api/mortgage/calculate';
    // Как в config/packages/rate_limiter.yaml.
    private const int SAVE_LIMIT = 30;
    private const int CALCULATE_LIMIT = 60;

    public function testSaveIsLimitedTo30PerMinute(): void
    {
        $client = self::client();
        for ($i = 0; $i < self::SAVE_LIMIT; ++$i) {
            self::requestJson($client, 'POST', self::SAVE_URI, self::validParameters());
            self::assertResponseStatusCodeSame(201);
        }

        $body = self::requestJson($client, 'POST', self::SAVE_URI, self::validParameters());

        self::assertTooManyRequestsProblem($client, $body);
    }

    public function testGetIsNotLimited(): void
    {
        $client = self::client();
        self::requestJson($client, 'POST', self::SAVE_URI, self::validParameters());
        $location = (string) $client->getResponse()->headers->get('Location');
        for ($i = 0; $i < self::SAVE_LIMIT; ++$i) {
            self::requestJson($client, 'POST', self::SAVE_URI, self::validParameters());
        }
        self::assertResponseStatusCodeSame(429);

        // Ссылку открывают все, кому ею поделились, — чтение не ограничено.
        self::requestJson($client, 'GET', $location);
        self::assertResponseStatusCodeSame(200);
    }

    public function testLimitIsPerIp(): void
    {
        $client = self::client();
        for ($i = 0; $i <= self::SAVE_LIMIT; ++$i) {
            self::requestJson($client, 'POST', self::SAVE_URI, self::validParameters());
        }
        self::assertResponseStatusCodeSame(429);

        $client->setServerParameter('REMOTE_ADDR', '203.0.113.7');
        self::requestJson($client, 'POST', self::SAVE_URI, self::validParameters());

        self::assertResponseStatusCodeSame(201);
    }

    public function testCalculateIsLimitedTo60PerMinute(): void
    {
        $client = self::client();
        // Мягче, чем сохранение: 31-й расчёт — обычный перебор вариантов, а не спам.
        for ($i = 0; $i < self::CALCULATE_LIMIT; ++$i) {
            self::requestJson($client, 'POST', self::CALCULATE_URI, self::validParameters());
            self::assertResponseStatusCodeSame(200);
        }

        $body = self::requestJson($client, 'POST', self::CALCULATE_URI, self::validParameters());

        self::assertTooManyRequestsProblem($client, $body);
    }

    /**
     * Счётчики лимитера — в array-кэше: перезагрузка ядра между запросами обнулила бы их.
     */
    private static function client(): KernelBrowser
    {
        $client = self::createClient();
        $client->disableReboot();

        return $client;
    }

    /**
     * @param array<mixed> $body
     */
    private static function assertTooManyRequestsProblem(KernelBrowser $client, array $body): void
    {
        self::assertResponseStatusCodeSame(429);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame(['type' => 'about:blank', 'title' => 'Too Many Requests', 'status' => 429], $body);
        // Не 0 («повтори сейчас») и не больше окна в минуту; +1 — округление вверх до целой секунды.
        $retryAfter = (int) $client->getResponse()->headers->get('Retry-After');
        self::assertGreaterThanOrEqual(1, $retryAfter);
        self::assertLessThanOrEqual(61, $retryAfter);
    }
}
