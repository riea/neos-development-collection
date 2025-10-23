<?php

declare(strict_types=1);

namespace Neos\ContentRepository\Core\Infrastructure\Tracing;

/**
 * Simple file-based tracer that writes human-readable trace logs incrementally.
 *
 * Format: [timestamp] indentation name (duration)
 * - Spans use > (start) and < (end) with duration
 * - Marks use • with time since last mark
 * - Indentation shows nesting depth
 * - fflush() after each write ensures crash-safety
 *
 * Example:
 * [123.456] > handleCommand {"cmd":"CreateNode"}
 * [123.457]   • validation (+1.2 ms)
 * [123.459] < handleCommand (3.0 ms)
 *
 * @internal
 */
final class LogFileTracer implements TracerInterface
{
    private bool $headerWritten = false;
    /** @var resource|null */
    private $fileHandle = null;
    private int $nestingLevel = 0;
    private float $lastMarkTime = 0.0;

    public function __construct(private readonly string $logFilePath, private readonly float $minimumMarkDurationMs)
    {
    }

    public function span(string $name, array $params, \Closure $fn)
    {
        $this->ensureFileOpen();

        $startTime = microtime(true);
        $paramsStr = empty($params) ? '' : ' ' . json_encode($params);

        $this->writeLine(sprintf(
            "[%.6f] %s> %s%s",
            $startTime,
            str_repeat('  ', $this->nestingLevel),
            $name,
            $paramsStr
        ));

        $this->nestingLevel++;
        $this->lastMarkTime = $startTime;

        try {
            return $fn();
        } finally {
            $this->nestingLevel--;
            $endTime = microtime(true);
            $this->lastMarkTime = $endTime;
            $duration = ($endTime - $startTime) * 1000; // ms

            $this->writeLine(sprintf(
                "[%.6f] %s< %s (%.3f ms)",
                $endTime,
                str_repeat('  ', $this->nestingLevel),
                $name,
                $duration
            ));
        }
    }

    public function mark(string $name, ?array $params = null): void
    {
        $this->ensureFileOpen();

        $currentTime = microtime(true);
        $duration = $this->lastMarkTime > 0
            ? ($currentTime - $this->lastMarkTime) * 1000
            : 0;

        if ($duration > $this->minimumMarkDurationMs) {
            $this->writeLine(sprintf(
                "[%.6f] %s• %s (+%.1f ms)   %s",
                $currentTime,
                str_repeat('  ', $this->nestingLevel),
                $name,
                $duration,
                is_array($params) ? json_encode($params) : ''
            ));
        }

        $this->lastMarkTime = $currentTime;
    }

    private function ensureFileOpen(): void
    {
        if ($this->fileHandle === null) {
            $fileHandle = fopen($this->logFilePath, 'a');
            if (is_resource($fileHandle)) {
                $this->fileHandle = $fileHandle;
            }
            // NOTE: no error handling if there were problems opening the file; as we do not want the tracer to fail the normal processes
        }

        if (!$this->headerWritten) {
            $this->writeLine("\n\n=== Trace started at " . date('Y-m-d H:i:s') . " ===");
            $this->headerWritten = true;
        }
    }

    private function writeLine(string $line): void
    {
        if ($this->fileHandle !== null) {
            fwrite($this->fileHandle, $line . "\n");
            fflush($this->fileHandle);
        }
    }

    public function __destruct()
    {
        if ($this->fileHandle !== null) {
            fclose($this->fileHandle);
        }
    }
}
