<?php

declare(strict_types=1);

namespace App\UI\Http\Request;

use App\Application\Mortgage\CalculateMortgage\CalculateMortgageCommand;
use App\Application\Mortgage\SaveCalculation\SaveCalculationCommand;
use App\Domain\Mortgage\Credit\RepaymentType;
use OpenApi\Attributes as OA;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Параметры ипотеки из JSON — общий вход обоих POST (дизайн 4.2).
 *
 * Имя — по основному сценарию (расчёт); сохранение принимает тот же JSON и переиспользует класс.
 * Разделить на два DTO — когда у одного из запросов появится своё поле (дизайн 2.2).
 *
 * Проверяет только формат: поле есть, похоже на число, дата существует. Бизнес-правила
 * (лимиты сумм, срока, ставки) и округление — в домене, CreditTerms::create() (дизайн 2.2).
 *
 * Все поля nullable: отсутствующий ключ становится null, и Validator вернёт 422 по каждому
 * такому полю сразу, а не упадёт десериализатор на первом. Без «= null»: null для отсутствующего
 * ключа подставляет сам сериализатор, а значение по умолчанию документация прочла бы как
 * «поле необязательно» и не взяла бы required из NotBlank/NotNull (дизайн 4.6).
 *
 * example — и подсказка формата (деньги строкой, раздел 1), и готовое тело для «Try it out».
 */
final readonly class CalculateMortgageRequest
{
    // Одна константа на три поля. Лишние знаки после запятой не отклоняем — их округлит домен.
    private const string DECIMAL_PATTERN = '/^\d+(\.\d+)?$/';
    private const string DECIMAL_MESSAGE = 'Введите число, например 5000000.00';
    private const string REQUIRED_MESSAGE = 'Заполните поле';

    public function __construct(
        #[Assert\NotBlank(message: self::REQUIRED_MESSAGE)]
        #[Assert\Length(max: 30, maxMessage: 'Слишком длинное число')]
        #[Assert\Regex(self::DECIMAL_PATTERN, message: self::DECIMAL_MESSAGE)]
        #[OA\Property(description: 'Стоимость недвижимости, ₽', example: '6000000.00')]
        public ?string $propertyPrice,
        #[Assert\NotBlank(message: self::REQUIRED_MESSAGE)]
        #[Assert\Length(max: 30, maxMessage: 'Слишком длинное число')]
        #[Assert\Regex(self::DECIMAL_PATTERN, message: self::DECIMAL_MESSAGE)]
        #[OA\Property(description: 'Первоначальный взнос, ₽', example: '1200000.00')]
        public ?string $downPayment,
        #[Assert\NotBlank(message: self::REQUIRED_MESSAGE)]
        #[Assert\Length(max: 30, maxMessage: 'Слишком длинное число')]
        #[Assert\Regex(self::DECIMAL_PATTERN, message: self::DECIMAL_MESSAGE)]
        #[OA\Property(description: 'Годовая ставка, %', example: '18.50')]
        public ?string $annualRate,
        // Диапазон 1–360 проверяет домен.
        #[Assert\NotNull(message: self::REQUIRED_MESSAGE)]
        #[OA\Property(description: 'Срок, месяцев (1–360)', example: 240)]
        public ?int $termMonths,
        // Строка, а не RepaymentType: неизвестное значение тогда ловит Validator, а не десериализатор, —
        // сообщение на русском и в одном ответе с остальными полями (дизайн 2.2).
        #[Assert\NotNull(message: self::REQUIRED_MESSAGE)]
        #[Assert\Choice(callback: 'repaymentTypes', message: 'Выберите тип погашения')]
        #[OA\Property(description: 'Тип погашения', example: 'annuity')]
        public ?string $repaymentType,
        // Assert\Date проверяет и календарь: 2026-02-30 — ошибка.
        #[Assert\NotBlank(message: self::REQUIRED_MESSAGE)]
        #[Assert\Date(message: 'Введите дату в формате ГГГГ-ММ-ДД')]
        #[OA\Property(description: 'Дата выдачи; первый платёж — через месяц', format: 'date', example: '2026-12-01')]
        public ?string $startDate,
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
