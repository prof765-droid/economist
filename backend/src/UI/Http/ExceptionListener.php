<?php

declare(strict_types=1);

namespace App\UI\Http;

use App\Domain\Mortgage\Credit\InvalidCreditTermsException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Любое исключение → ответ в формате Problem Details (RFC 9457, дизайн 5).
 *
 * Приоритет -64: после ErrorListener::logKernelException (0) — исключение уже записано в лог,
 * но до ErrorListener::onKernelException (-128) — он не сработает, ответ уже выставлен.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: -64)]
final readonly class ExceptionListener
{
    private const string VALIDATION_TYPE = 'https://economist.local/errors/validation';

    // Домен называет поля по-своему, API — по-своему. Сейчас совпадают; при /api/v2 меняется только таблица.
    private const array DOMAIN_TO_API_FIELD = [
        'propertyPrice' => 'propertyPrice',
        'downPayment' => 'downPayment',
        'annualRate' => 'annualRate',
        'termMonths' => 'termMonths',
    ];

    public function __invoke(ExceptionEvent $event): void
    {
        $e = $event->getThrowable();
        $previous = $e->getPrevious();

        $response = match (true) {
            $e instanceof InvalidCreditTermsException => $this->validationProblem($this->domainViolations($e)),
            $e instanceof HttpExceptionInterface && $previous instanceof ValidationFailedException => $this->validationProblem($this->validatorViolations($previous)),
            // Заголовки исключения — например, Retry-After у 429.
            $e instanceof HttpExceptionInterface => $this->problem($e->getStatusCode(), headers: $e->getHeaders()),
            // Без деталей: сообщение может содержать SQL, пути, данные — их видно только в логе.
            default => $this->problem(Response::HTTP_INTERNAL_SERVER_ERROR),
        };

        $event->setResponse($response);
    }

    /**
     * 422 одного вида и от Validator'а, и от домена — фронт разбирает один формат.
     *
     * @param list<array{field: string, message: string}> $violations
     */
    private function validationProblem(array $violations): JsonResponse
    {
        return $this->problem(Response::HTTP_UNPROCESSABLE_ENTITY, [
            'type' => self::VALIDATION_TYPE,
            'title' => 'Validation failed',
            'violations' => $violations,
        ]);
    }

    /**
     * @return list<array{field: string, message: string}>
     */
    private function domainViolations(InvalidCreditTermsException $e): array
    {
        $violations = [];
        foreach ($e->getViolations() as $field => $message) {
            // Поле без записи в таблице отдаём как есть: лучше доменное имя, чем потерянная ошибка.
            $violations[] = ['field' => self::DOMAIN_TO_API_FIELD[$field] ?? $field, 'message' => $message];
        }

        return $violations;
    }

    /**
     * @return list<array{field: string, message: string}>
     */
    private function validatorViolations(ValidationFailedException $e): array
    {
        $violations = [];
        foreach ($e->getViolations() as $violation) {
            $violations[] = ['field' => $violation->getPropertyPath(), 'message' => (string) $violation->getMessage()];
        }

        return $violations;
    }

    /**
     * По умолчанию type — about:blank, а title — текст статуса: так требует RFC 9457 для ошибок
     * без своего типа. Ветка со своим типом передаёт type и title в $extra.
     *
     * @param array<string, mixed> $extra
     * @param array<mixed>         $headers HttpExceptionInterface::getHeaders() типизирован просто как array
     */
    private function problem(int $status, array $extra = [], array $headers = []): JsonResponse
    {
        $body = array_merge([
            'type' => 'about:blank',
            'title' => Response::$statusTexts[$status] ?? 'Unknown Error',
            'status' => $status,
        ], $extra);

        $response = new JsonResponse($body, $status, ['Content-Type' => 'application/problem+json'] + $headers);
        // Кириллица в сообщениях — как есть, а не \u0417…: читается в curl и логах, JSON остаётся валидным.
        $response->setEncodingOptions(JsonResponse::DEFAULT_ENCODING_OPTIONS | \JSON_UNESCAPED_UNICODE);

        return $response;
    }
}
