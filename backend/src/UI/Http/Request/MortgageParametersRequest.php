<?php

declare(strict_types=1);

namespace App\UI\Http\Request;

use App\Application\Mortgage\CalculateMortgage\CalculateMortgageCommand;
use App\Application\Mortgage\SaveCalculation\SaveCalculationCommand;
use App\Domain\Mortgage\Credit\RepaymentType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Параметры ипотеки из JSON — общий вход обоих POST (дизайн 4.2).
 *
 * Проверяет только формат: поле есть, похоже на число, дата существует. Бизнес-правила
 * (лимиты сумм, срока, ставки) и округление — в домене, CreditTerms::create() (дизайн 2.2).
 *
 * Все поля nullable: отсутствующий ключ становится null, и Validator вернёт 422 по каждому
 * такому полю сразу, а не упадёт десериализатор на первом.
 */
final readonly class MortgageParametersRequest
{
    // Одна константа на три поля. Лишние знаки после запятой не отклоняем — их округлит домен.
    private const string DECIMAL_PATTERN = '/^\d+(\.\d+)?$/';
    private const string DECIMAL_MESSAGE = 'Введите число, например 5000000.00';
    private const string REQUIRED_MESSAGE = 'Заполните поле';

    public function __construct(
        #[Assert\NotBlank(message: self::REQUIRED_MESSAGE)]
        #[Assert\Length(max: 30, maxMessage: 'Слишком длинное число')]
        #[Assert\Regex(self::DECIMAL_PATTERN, message: self::DECIMAL_MESSAGE)]
        public ?string $propertyPrice = null,
        #[Assert\NotBlank(message: self::REQUIRED_MESSAGE)]
        #[Assert\Length(max: 30, maxMessage: 'Слишком длинное число')]
        #[Assert\Regex(self::DECIMAL_PATTERN, message: self::DECIMAL_MESSAGE)]
        public ?string $downPayment = null,
        #[Assert\NotBlank(message: self::REQUIRED_MESSAGE)]
        #[Assert\Length(max: 30, maxMessage: 'Слишком длинное число')]
        #[Assert\Regex(self::DECIMAL_PATTERN, message: self::DECIMAL_MESSAGE)]
        public ?string $annualRate = null,
        // Диапазон 1–360 проверяет домен.
        #[Assert\NotNull(message: self::REQUIRED_MESSAGE)]
        public ?int $termMonths = null,
        // Строка, а не RepaymentType: неизвестное значение тогда ловит Validator, а не десериализатор, —
        // сообщение на русском и в одном ответе с остальными полями (дизайн 2.2).
        #[Assert\NotNull(message: self::REQUIRED_MESSAGE)]
        #[Assert\Choice(callback: 'repaymentTypes', message: 'Выберите тип погашения')]
        public ?string $repaymentType = null,
        // Assert\Date проверяет и календарь: 2026-02-30 — ошибка.
        #[Assert\NotBlank(message: self::REQUIRED_MESSAGE)]
        #[Assert\Date(message: 'Введите дату в формате ГГГГ-ММ-ДД')]
        public ?string $startDate = null,
    ) {
    }

    // Вызывать только после валидации — её делает #[MapRequestPayload] до контроллера.
    public function toCalculateCommand(): CalculateMortgageCommand
    {
        return new CalculateMortgageCommand(...$this->commandArguments());
    }

    // id приходит снаружи: его генерирует контроллер, в запросе его нет (дизайн 2.2).
    public function toSaveCommand(Uuid $id): SaveCalculationCommand
    {
        return new SaveCalculationCommand($id, ...$this->commandArguments());
    }

    /**
     * Допустимые значения берём из enum — новый тип погашения попадёт в проверку сам.
     *
     * @return list<string>
     */
    public static function repaymentTypes(): array
    {
        return array_column(RepaymentType::cases(), 'value');
    }

    /**
     * Параметры обеих команд в порядке их конструкторов — маппинг полей описан один раз.
     *
     * @return array{string, string, string, int, RepaymentType, \DateTimeImmutable}
     */
    private function commandArguments(): array
    {
        return [
            self::required($this->propertyPrice),
            self::required($this->downPayment),
            self::required($this->annualRate),
            self::required($this->termMonths),
            RepaymentType::from(self::required($this->repaymentType)),
            $this->startDate(),
        ];
    }

    // «!» обнуляет время: дата платежа — без часов и минут.
    private function startDate(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromFormat('!Y-m-d', self::required($this->startDate))
            ?: throw new \LogicException('Request must be validated first');
    }

    /**
     * Переход nullable → non-null: после валидации null здесь быть не может, а если пришёл —
     * значит, DTO использовали без валидации, это баг (500), а не ошибка ввода.
     *
     * @template T
     *
     * @param T|null $value
     *
     * @return T
     */
    private static function required(mixed $value): mixed
    {
        return $value ?? throw new \LogicException('Request must be validated first');
    }
}
