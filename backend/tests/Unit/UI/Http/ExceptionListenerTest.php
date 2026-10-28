<?php

declare(strict_types=1);

namespace App\Tests\Unit\UI\Http;

use App\Domain\Mortgage\Credit\InvalidCreditTermsException;
use App\UI\Http\ExceptionListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

// Событие собираем вручную: ядро и роутинг не нужны, проверяем только «исключение → ответ».
final class ExceptionListenerTest extends TestCase
{
    public function testDomainViolationsAre422(): void
    {
        $response = $this->handle(new InvalidCreditTermsException([
            'downPayment' => 'Сумма кредита должна быть не меньше 500 000 ₽',
            'termMonths' => 'Срок должен быть от 1 до 360 месяцев',
        ]));

        self::assertProblem(422, [
            'type' => 'https://economist.local/errors/validation',
            'title' => 'Validation failed',
            'status' => 422,
            'violations' => [
                ['field' => 'downPayment', 'message' => 'Сумма кредита должна быть не меньше 500 000 ₽'],
                ['field' => 'termMonths', 'message' => 'Срок должен быть от 1 до 360 месяцев'],
            ],
        ], $response);
    }

    public function testValidatorViolationsHaveSameFormatAsDomain(): void
    {
        // Так #[MapRequestPayload] сообщает об ошибках Validator'а: HttpException(422) с previous.
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Значение не должно быть пустым.', null, [], null, 'termMonths', null),
        ]);
        $response = $this->handle(new HttpException(422, 'termMonths: ...', new ValidationFailedException(null, $violations)));

        self::assertProblem(422, [
            'type' => 'https://economist.local/errors/validation',
            'title' => 'Validation failed',
            'status' => 422,
            'violations' => [['field' => 'termMonths', 'message' => 'Значение не должно быть пустым.']],
        ], $response);
    }

    public function testNotFound(): void
    {
        $response = $this->handle(new NotFoundHttpException('No route found'));

        self::assertProblem(404, ['type' => 'about:blank', 'title' => 'Not Found', 'status' => 404], $response);
    }

    public function testTooManyRequestsKeepsRetryAfter(): void
    {
        $response = $this->handle(new TooManyRequestsHttpException(30));

        self::assertProblem(429, ['type' => 'about:blank', 'title' => 'Too Many Requests', 'status' => 429], $response);
        self::assertSame('30', $response->headers->get('Retry-After'));
    }

    public function testBadRequest(): void
    {
        $response = $this->handle(new BadRequestHttpException('Syntax error'));

        self::assertProblem(400, ['type' => 'about:blank', 'title' => 'Bad Request', 'status' => 400], $response);
    }

    public function testUnexpectedErrorHidesDetails(): void
    {
        $response = $this->handle(new \RuntimeException('secret'));

        self::assertProblem(500, ['type' => 'about:blank', 'title' => 'Internal Server Error', 'status' => 500], $response);
        self::assertStringNotContainsString('secret', (string) $response->getContent());
    }

    private function handle(\Throwable $e): Response
    {
        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/api/x'),
            HttpKernelInterface::MAIN_REQUEST,
            $e,
        );
        (new ExceptionListener())($event);

        $response = $event->getResponse();
        self::assertNotNull($response);

        return $response;
    }

    /**
     * @param array<string, mixed> $expectedBody
     */
    private static function assertProblem(int $status, array $expectedBody, Response $response): void
    {
        self::assertSame($status, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        // assertSame, а не assertEquals: проверяем и порядок ключей — type, title, status, как в RFC.
        self::assertSame($expectedBody, json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR));
    }
}
