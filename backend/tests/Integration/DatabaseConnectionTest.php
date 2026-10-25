<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DatabaseConnectionTest extends KernelTestCase
{
    public function testConnectsToDatabase(): void
    {
        $connection = self::getContainer()->get(Connection::class);

        self::assertInstanceOf(Connection::class, $connection);
        self::assertSame(1, $connection->fetchOne('SELECT 1'));
    }
}
