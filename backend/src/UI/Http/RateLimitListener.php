<?php

declare(strict_types=1);

namespace App\UI\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Лимит запросов с IP на выбранные роуты (дизайн 4.1); превышение → 429, ответ собирает ExceptionListener.
 *
 * Приоритет по умолчанию (0) — после RouterListener (32): _route уже известен.
 */
#[AsEventListener(event: KernelEvents::REQUEST)]
final readonly class RateLimitListener
{
    /**
     * @param array<string, RateLimiterFactoryInterface> $limiters имя роута → лимитер (services.yaml)
     */
    public function __construct(
        private array $limiters,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $limiter = $this->limiters[$request->attributes->getString('_route')] ?? null;
        if (null === $limiter) {
            return;
        }

        $limit = $limiter->create($request->getClientIp())->consume();
        if ($limit->isAccepted()) {
            return;
        }

        // Лимитер уже округлил момент вверх до секунды — раньше времени клиент не придёт.
        // max(1): на краю окна ожидание бывает нулевым, а Retry-After: 0 — «повтори сейчас» (дизайн 5).
        throw new TooManyRequestsHttpException(max(1, $limit->getRetryAfter()->getTimestamp() - time()));
    }
}
