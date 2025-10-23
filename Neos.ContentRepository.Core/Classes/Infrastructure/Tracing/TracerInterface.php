<?php

declare(strict_types=1);

namespace Neos\ContentRepository\Core\Infrastructure\Tracing;

/**
 * Interface for tracing performance and execution flow in the Content Repository.
 *
 * @api (experimental) for external tracers. They might need to be adjusted in further versions.
 */
interface TracerInterface
{
    /**
     * Creates a named span that traces the execution of the provided $fn closure.
     *
     * A span represents a unit of work with a defined beginning and end. Any mark()
     * calls made during the closure execution are automatically associated with this span.
     * Spans can be nested, allowing for hierarchical performance analysis.
     *
     * @template T
     * @param string $name A descriptive name for this span (e.g., "contentRepository::handle")
     * @param array<string, mixed> $params attributes to attach to the span (e.g., ['c' => 'CreateNode'])
     * @param \Closure(): T $fn The closure to measure
     * @return T The return value of the executed closure
     */
    public function span(string $name, array $params, \Closure $fn);

    /**
     * Creates a point-in-time marker AFTER an operation completes within the current span.
     *
     * Markers measure the elapsed time from the previous mark (or span start) up to and
     * including the operation just completed. Place mark() calls immediately AFTER the
     * operation you want to measure.
     *
     * @param string $name A descriptive name identifying the operation that just completed
     * @param array<string, mixed> $params attributes to attach to the span (e.g., ['c' => 'CreateNode'])
     */
    public function mark(string $name, ?array $params = null): void;
}
