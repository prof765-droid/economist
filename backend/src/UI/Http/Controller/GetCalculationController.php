<?php

declare(strict_types=1);

namespace App\UI\Http\Controller;

use App\Application\Mortgage\GetCalculation\GetCalculationHandler;
use App\UI\Http\Response\CalculationResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

/**
 * Сохранённый расчёт по ссылке (дизайн 4.1): параметры из БД и пересчитанный график.
 *
 * Невалидный id сюда не доходит: requirements не совпадает → 404 от роутинга. Строку в Uuid
 * превращает UidValueResolver — handler получает уже типизированный id (дизайн 2.2).
 */
final class GetCalculationController extends AbstractController
{
    public function __construct(
        private readonly GetCalculationHandler $handler,
    ) {
    }

    #[Route('/api/calculations/{id}', name: self::class, requirements: ['id' => Requirement::UUID_V7], methods: ['GET'])]
    public function __invoke(Uuid $id): JsonResponse
    {
        return $this->json(CalculationResponse::fromResult($this->handler->handle($id)));
    }
}
