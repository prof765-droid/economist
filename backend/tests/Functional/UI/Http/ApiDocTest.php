<?php

declare(strict_types=1);

namespace App\Tests\Functional\UI\Http;

use App\Tests\Functional\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

// Документация API (дизайн 4.6): схема строится из кода — проверяем, что она собирается и говорит правду.
final class ApiDocTest extends ApiTestCase
{
    public function testSchemaDescribesAllEndpoints(): void
    {
        $doc = self::schema(self::createClient());

        $paths = self::at($doc, 'paths');
        self::assertSame(
            ['/api/mortgage/calculate', '/api/calculations/{id}', '/api/calculations'],
            array_keys($paths),
            'Только три эндпоинта API — без самой /api/doc',
        );
        self::assertArrayHasKey('post', self::at($paths, '/api/mortgage/calculate'));
        self::assertArrayHasKey('get', self::at($paths, '/api/calculations/{id}'));
        self::assertArrayHasKey('post', self::at($paths, '/api/calculations'));
    }

    public function testRequestFieldsAreRequired(): void
    {
        $doc = self::schema(self::createClient());

        // required берётся из NotBlank/NotNull, пока у параметров конструктора нет «= null».
        self::assertSame(
            array_keys(self::validParameters()),
            self::at($doc, 'components', 'schemas', 'CalculateMortgageRequest', 'required'),
        );
    }

    public function testRequestBodyIsJsonOnly(): void
    {
        $doc = self::schema(self::createClient());

        foreach (['/api/mortgage/calculate', '/api/calculations'] as $path) {
            self::assertSame(
                ['application/json'],
                array_keys(self::at($doc, 'paths', $path, 'post', 'requestBody', 'content')),
            );
        }
    }

    public function testErrorsAreDescribedAsProblemDetails(): void
    {
        $doc = self::schema(self::createClient());

        $responses = self::at($doc, 'paths', '/api/mortgage/calculate', 'post', 'responses');
        self::assertSame(
            ['$ref' => '#/components/schemas/ProblemDetails'],
            self::at($responses, '422', 'content', 'application/problem+json', 'schema'),
        );
        self::assertArrayHasKey('Retry-After', self::at($responses, '429', 'headers'));
    }

    public function testExampleRequestFromDocsIsAccepted(): void
    {
        $client = self::createClient();
        $properties = self::at(self::schema($client), 'components', 'schemas', 'CalculateMortgageRequest', 'properties');

        // «Try it out» отправляет тело из example — оно должно проходить и формат, и доменные лимиты.
        $payload = [];
        foreach (array_keys($properties) as $field) {
            $payload[(string) $field] = self::at($properties, (string) $field)['example'];
        }
        self::requestJson($client, 'POST', '/api/mortgage/calculate', $payload);

        self::assertResponseStatusCodeSame(200);
    }

    public function testSwaggerUiWorksWithoutBundleAssets(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/doc');

        self::assertResponseIsSuccessful();
        // Режим offline: JS/CSS встроены в страницу — путь /bundles/ nginx отправил бы в Vite.
        self::assertStringNotContainsString('/bundles/', (string) $client->getResponse()->getContent());
    }

    /**
     * Спуск по вложенным ключам с проверкой, что каждый уровень — массив (PHPStan max не пускает в mixed).
     *
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    private static function at(array $data, string ...$keys): array
    {
        foreach ($keys as $key) {
            self::assertArrayHasKey($key, $data);
            $data = $data[$key];
            self::assertIsArray($data, $key);
        }

        return $data;
    }

    /**
     * @return array<mixed>
     */
    private static function schema(KernelBrowser $client): array
    {
        $client->request('GET', '/api/doc.json');
        self::assertResponseIsSuccessful();

        $doc = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($doc);

        return $doc;
    }
}
