<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class ApiTestCase extends WebTestCase
{
    /**
     * Строка уходит в тело как есть — для проверки невалидного JSON.
     *
     * @param array<string, mixed>|string|null $payload
     *
     * @return array<mixed> декодированное тело ответа
     */
    protected static function requestJson(KernelBrowser $client, string $method, string $uri, array|string|null $payload = null): array
    {
        // Без CONTENT_TYPE #[MapRequestPayload] ответит 415.
        $client->request($method, $uri, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], content: \is_array($payload) ? json_encode($payload, \JSON_THROW_ON_ERROR) : $payload);

        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($body);

        return $body;
    }

    /**
     * Запрос из дизайна 4.2; $override заменяет или добавляет поля.
     *
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    protected static function validParameters(array $override = []): array
    {
        return array_merge([
            'propertyPrice' => '5000000.00',
            'downPayment' => '1000000.00',
            'annualRate' => '18.5',
            'termMonths' => 240,
            'repaymentType' => 'annuity',
            'startDate' => '2026-10-23',
        ], $override);
    }
}
