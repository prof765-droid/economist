<?php

declare(strict_types=1);

namespace App\UI\Http\Controller;

use App\Application\Mortgage\SaveCalculation\SaveCalculationHandler;
use App\UI\Http\Request\MortgageParametersRequest;
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
    public function __invoke(#[MapRequestPayload] MortgageParametersRequest $request): JsonResponse
    {
        // v7 начинается со времени создания: новые строки ложатся в конец индекса PK, а не вразброс.
        $id = Uuid::v7();
        $this->handler->handle($request->toSaveCommand($id));

        return $this->json(['id' => $id->toRfc4122()], Response::HTTP_CREATED, [
            'Location' => $this->generateUrl(GetCalculationController::class, ['id' => $id->toRfc4122()]),
        ]);
    }
}
