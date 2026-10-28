<?php

declare(strict_types=1);

namespace App\UI\Http\Controller;

use App\Application\Mortgage\CalculateMortgage\CalculateMortgageHandler;
use App\UI\Http\Request\MortgageParametersRequest;
use App\UI\Http\Response\CalculationResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

/**
 * Расчёт без сохранения (дизайн 4.5). Тонкий: JSON → Command → handler → JSON.
 *
 * Ошибки сюда не доходят: формат проверяет #[MapRequestPayload] (422 до вызова метода),
 * бизнес-правила — домен; оба исключения превращает в ответ ExceptionListener.
 */
final class CalculateMortgageController extends AbstractController
{
    public function __construct(
        private readonly CalculateMortgageHandler $handler,
    ) {
    }

    #[Route('/api/mortgage/calculate', name: self::class, methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] MortgageParametersRequest $request): JsonResponse
    {
        $schedule = $this->handler->handle($request->toCalculateCommand());

        // SKIP_NULL_VALUES: id и parameters в ответе calculate отсутствуют, а не равны null (дизайн 4.3).
        return $this->json(CalculationResponse::fromSchedule($schedule), Response::HTTP_OK, [], [
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
        ]);
    }
}
