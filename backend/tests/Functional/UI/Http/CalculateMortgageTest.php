<?php

declare(strict_types=1);

namespace App\Tests\Functional\UI\Http;

use App\Tests\Functional\ApiTestCase;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\Attributes\DataProvider;

final class CalculateMortgageTest extends ApiTestCase
{
    private const string URI = '/api/mortgage/calculate';

    public function testAnnuity(): void
    {
        $client = self::createClient();
        $body = self::requestJson($client, 'POST', self::URI, self::validParameters());

        self::assertResponseStatusCodeSame(200);
        // id и parameters — только у сохранённого расчёта (дизайн 4.3).
        self::assertSame(['summary', 'schedule'], array_keys($body));
        self::assertSame([
            'creditAmount' => '4000000.00',
            'monthlyPayment' => '63275.86',
            'totalPayment' => '15186214.84',
            'overpayment' => '11186214.84',
        ], $body['summary']);

        $schedule = $body['schedule'];
        self::assertIsArray($schedule);
        self::assertCount(240, $schedule);
        self::assertIsArray($schedule[0]);
        self::assertSame(1, $schedule[0]['number']);
        self::assertSame('2026-11-23', $schedule[0]['date']);
        self::assertSame('63275.86', $schedule[0]['payment']);
        self::assertIsArray($schedule[239]);
        self::assertSame('0.00', $schedule[239]['balance']);
    }

    public function testDifferentiated(): void
    {
        $client = self::createClient();
        $body = self::requestJson($client, 'POST', self::URI, self::validParameters(['repaymentType' => 'differentiated']));

        self::assertResponseStatusCodeSame(200);
        $schedule = $body['schedule'];
        self::assertIsArray($schedule);
        self::assertIsArray($first = $schedule[0]);
        self::assertIsArray($last = $schedule[array_key_last($schedule)]);
        self::assertIsString($first['payment']);
        self::assertIsString($last['payment']);
        // Деньги в JSON — строки; сравниваем как десятичные числа, без float.
        self::assertTrue(BigDecimal::of($first['payment'])->isGreaterThan($last['payment']));
    }

    public function testEmptyBodyReportsEveryField(): void
    {
        $client = self::createClient();
        $body = self::requestJson($client, 'POST', self::URI, []);

        self::assertValidationProblem(
            ['propertyPrice', 'downPayment', 'annualRate', 'termMonths', 'repaymentType', 'startDate'],
            $body,
        );
    }

    /**
     * @param array<string, mixed> $override
     */
    #[DataProvider('invalidFormatProvider')]
    public function testInvalidFormatIs422(array $override, string $field): void
    {
        $client = self::createClient();
        $body = self::requestJson($client, 'POST', self::URI, self::validParameters($override));

        self::assertValidationProblem([$field], $body);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidFormatProvider(): iterable
    {
        // Отсекает Validator (Choice): такого значения нет в enum RepaymentType.
        yield 'unknown repayment type' => [['repaymentType' => 'foo'], 'repaymentType'];
        // Отсекает десериализатор: строка вместо int.
        yield 'term is not a number' => [['termMonths' => 'abc'], 'termMonths'];
        // Отсекает Validator: формат верный, но 30 февраля нет.
        yield 'nonexistent date' => [['startDate' => '2026-02-30'], 'startDate'];
        yield 'price is not a number' => [['propertyPrice' => '5 000 000'], 'propertyPrice'];
    }

    public function testUnknownRepaymentTypeDoesNotHideOtherErrors(): void
    {
        $client = self::createClient();
        // Тип строковый — десериализатор пропускает, и Validator проверяет все поля разом.
        $body = self::requestJson($client, 'POST', self::URI, self::validParameters([
            'repaymentType' => 'foo',
            'startDate' => '2026-02-30',
        ]));

        self::assertValidationProblem(['repaymentType', 'startDate'], $body);
    }

    public function testDomainViolationsComeInOneResponse(): void
    {
        $client = self::createClient();
        // Формат верный — Validator пропускает; оба правила нарушает домен.
        $body = self::requestJson($client, 'POST', self::URI, self::validParameters([
            'downPayment' => '5000000.00',
            'termMonths' => 0,
        ]));

        self::assertValidationProblem(['downPayment', 'termMonths'], $body);
    }

    public function testInvalidJsonIs400(): void
    {
        $client = self::createClient();
        $body = self::requestJson($client, 'POST', self::URI, '{');

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame(['type' => 'about:blank', 'title' => 'Bad Request', 'status' => 400], $body);
    }

    public function testExtraDecimalsAreRoundedByDomain(): void
    {
        $client = self::createClient();
        self::requestJson($client, 'POST', self::URI, self::validParameters(['annualRate' => '18.555']));

        self::assertResponseStatusCodeSame(200);
    }

    /**
     * Один формат 422 и от Validator'а, и от домена — фронт разбирает его одинаково.
     *
     * @param list<string> $fields
     * @param array<mixed> $body
     */
    private static function assertValidationProblem(array $fields, array $body): void
    {
        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame('https://economist.local/errors/validation', $body['type']);
        self::assertSame('Validation failed', $body['title']);
        self::assertSame(422, $body['status']);

        $violations = $body['violations'];
        self::assertIsArray($violations);
        $actualFields = [];
        foreach ($violations as $violation) {
            self::assertIsArray($violation);
            self::assertSame(['field', 'message'], array_keys($violation));
            self::assertIsString($violation['message']);
            self::assertNotSame('', $violation['message']);
            $actualFields[] = $violation['field'];
        }
        self::assertSame($fields, $actualFields);
    }
}
