<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * SQL пишем руками: без ORM нет метаданных сущностей, и doctrine:migrations:diff сгенерировать его не может.
 */
final class Version20261028131531 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Таблица calculation: параметры сохранённых расчётов (график не хранится — пересчитывается)';
    }

    public function up(Schema $schema): void
    {
        // NUMERIC, а не float: точные десятичные цифры, ровно как в Money (2 знака).
        $this->addSql(<<<'SQL'
            CREATE TABLE calculation (
                id UUID PRIMARY KEY,
                property_price NUMERIC(15, 2) NOT NULL,
                down_payment NUMERIC(15, 2) NOT NULL,
                annual_rate NUMERIC(5, 2) NOT NULL,
                term_months SMALLINT NOT NULL,
                repayment_type VARCHAR(20) NOT NULL,
                start_date DATE NOT NULL,
                created_at TIMESTAMPTZ NOT NULL
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE calculation');
    }
}
