<?php

declare(strict_types=1);

namespace App\UI\Http\Controller;

use App\Application\Mortgage\SaveCalculation\SaveCalculationHandler;
use App\UI\Http\Request\CalculateMortgageRequest;
use App\UI\Http\Response\ProblemDetails;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Сохранение параметров по кнопке «Поделиться» (дизайн 4.1). Вход — тот же Request DTO, что у calculate.
 *
 * В ответе только id: результат операции — ссылка, график фронт уже показал (дизайн 4.1).
 */
final class SaveCalculationController extends AbstractController
{
    public function __construct(
        private readonly SaveCalculationHandler $handler,
    ) {
    }

    #[Route('/api/calculations', name: self::class, methods: ['POST'])]
    #[OA\Post(summary: 'Сохранить расчёт для ссылки', description: 'Сохраняет только параметры; график пересчитывается при каждом GET.')]
    #[OA\Response(
        response: 201,
        description: 'Сохранено',
        headers: [new OA\Header(header: 'Location', description: 'Адрес сохранённого расчёта', schema: new OA\Schema(type: 'string', example: '/api/calculations/019a3f2e-7c1b-7d4e-9a10-3b5c8e2f6d71'))],
        content: new OA\JsonContent(
            required: ['id'],
            properties: [new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '019a3f2e-7c1b-7d4e-9a10-3b5c8e2f6d71')],
        ),
    )]
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
    public function __invoke(#[MapRequestPayload(acceptFormat: 'json')] CalculateMortgageRequest $request): JsonResponse
    {
        // v7 начинается со времени создания: новые строки ложатся в конец индекса PK, а не вразброс.
        $id = Uuid::v7();
        $this->handler->handle($request->toSaveCommand($id));

        return $this->json(['id' => $id->toRfc4122()], Response::HTTP_CREATED, [
            'Location' => $this->generateUrl(GetCalculationController::class, ['id' => $id->toRfc4122()]),
        ]);
    }
}
