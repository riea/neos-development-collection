<?php

declare(strict_types=1);

namespace Neos\ContentRepository\Core\Infrastructure\Tracing;

/**
 * @internal
 */
class NullTracer implements TracerInterface
{
    public function span(string $name, array $params, \Closure $fn)
    {
        return $fn();
    }

    public function mark(string $name, ?array $params = null): void
    {
    }
}
