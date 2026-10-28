<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application;

use App\Application\Mortgage\CalculateMortgage\CalculateMortgageCommand;
use App\Application\Mortgage\CalculateMortgage\CalculateMortgageHandler;
use App\Domain\Mortgage\Credit\RepaymentType;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Handler из настоящего контейнера (дизайн 6.1). Ловит забытый тег стратегии:
 * !tagged_iterator тогда молча пустой, контейнер собирается, а падает только сам расчёт.
 */
final class HandlersWiringTest extends KernelTestCase
{
    #[DataProvider('repaymentTypeProvider')]
    public function testCalculatesEveryRepaymentType(RepaymentType $type): void
    {
        $handler = self::getContainer()->get(CalculateMortgageHandler::class);

        $schedule = $handler->handle(new CalculateMortgageCommand(
            '1000000.00',
            '0.00',
            '10',
            12,
            $type,
            new \DateTimeImmutable('2026-10-23'),
        ));

        self::assertCount(12, $schedule->getPayments());
    }

    /**
     * @return iterable<string, array{RepaymentType}>
     */
    public static function repaymentTypeProvider(): iterable
    {
        foreach (RepaymentType::cases() as $type) {
            yield $type->value => [$type];
        }
    }
}
