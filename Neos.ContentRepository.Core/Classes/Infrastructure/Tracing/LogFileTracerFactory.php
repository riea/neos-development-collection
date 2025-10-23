<?php

declare(strict_types=1);

namespace Neos\ContentRepository\Core\Infrastructure\Tracing;

use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepositoryRegistry\Exception\InvalidConfigurationException;
use Neos\ContentRepositoryRegistry\Factory\Tracer\TracerFactoryInterface;


/**
 * @api
 */
final class LogFileTracerFactory implements TracerFactoryInterface
{

    public function build(ContentRepositoryId $contentRepositoryId, array $options): TracerInterface
    {
        isset($options['fileName']) || throw InvalidConfigurationException::fromMessage('Content repository "%s" does not have debug.tracer.options.fileName configured. Recommended: %%FLOW_PATH_DATA%%Logs/ContentRepositoryProfile.log', $contentRepositoryId->value);

        return new LogFileTracer($options['fileName'], $options['minimumMarkDurationMs'] ?? 0);
    }
}
