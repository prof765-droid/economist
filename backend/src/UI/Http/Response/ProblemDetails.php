<?php

declare(strict_types=1);

namespace App\UI\Http\Response;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/**
 * Тело любого ответа с ошибкой — Problem Details, RFC 9457 (дизайн 5).
 *
 * Класс, а не массив в ExceptionListener: из него же строится схема документации (дизайн 4.6) —
 * формат ошибки и его описание не разойдутся.
 *
 * JsonSerializable, а не Serializer: listener ошибок не должен зависеть от того, что само может упасть.
 */
#[OA\Schema(description: 'Ошибка в формате RFC 9457 (application/problem+json)')]
final readonly class ProblemDetails implements \JsonSerializable
{
    /**
     * @param list<ViolationResponse>|null $violations
     */
    public function __construct(
        #[OA\Property(description: 'about:blank — ошибка без своего типа; у 422 — свой URI', example: 'about:blank')]
        public string $type,
        #[OA\Property(description: 'Описание type: для about:blank — текст HTTP-статуса', example: 'Not Found')]
        public string $title,
        #[OA\Property(example: 404)]
        public int $status,
        // Тип элементов явно: PropertyInfo сначала спрашивает конструктор через ReflectionExtractor
        // (with_constructor_extractor), видит просто array, и до phpdoc очередь не доходит.
        #[OA\Property(description: 'Только у 422: ошибки по полям запроса', type: 'array', items: new OA\Items(ref: new Model(type: ViolationResponse::class)))]
        public ?array $violations = null,
    ) {
    }

    /**
     * violations нет в ответе вовсе, а не null — как было до DTO (дизайн 5).
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $body = ['type' => $this->type, 'title' => $this->title, 'status' => $this->status];
        if (null !== $this->violations) {
            $body['violations'] = $this->violations;
        }

        return $body;
    }
}
