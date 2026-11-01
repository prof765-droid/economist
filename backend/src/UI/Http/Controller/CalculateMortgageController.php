<?php

declare(strict_types=1);

namespace App\UI\Http\Controller;

use App\Application\Mortgage\CalculateMortgage\CalculateMortgageHandler;
use App\UI\Http\Request\CalculateMortgageRequest;
use App\UI\Http\Response\CalculationResponse;
use App\UI\Http\Response\ProblemDetails;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
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
    #[OA\Post(summary: 'Рассчитать график платежей', description: 'Ничего не сохраняет. В ответе нет id и parameters.')]
    #[OA\Response(response: 200, description: 'Сводка и график', content: new Model(type: CalculationResponse::class))]
    #[OA\Response(
        response: 422,
        description: 'Ошибки по полям: формат (Validator) или бизнес-правила (домен)',
        content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: new Model(type: ProblemDetails::class))),
    )]
    #[OA\Response(
        response: 429,
        description: 'Слишком много запросов с IP',
        headers: [new OA\Header(header: 'Retry-After', description: 'Через сколько секунд можно повторить (не меньше 1)', schema: new OA\Schema(type: 'integer', minimum: 1))],
        content: new OA\MediaType(mediaType: 'application/problem+json', schema: new OA\Schema(ref: new Model(type: ProblemDetails::class))),
    )]
    public function __invoke(#[MapRequestPayload] CalculateMortgageRequest $request): JsonResponse
    {
        $schedule = $this->handler->handle($request->toCalculateCommand());

        // SKIP_NULL_VALUES: id и parameters в ответе calculate отсутствуют, а не равны null (дизайн 4.3).
        return $this->json(CalculationResponse::fromSchedule($schedule), Response::HTTP_OK, [], [
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
        ]);
    }
}
