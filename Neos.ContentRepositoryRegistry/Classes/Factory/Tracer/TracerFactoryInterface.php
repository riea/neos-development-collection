<?php
declare(strict_types=1);
namespace Neos\ContentRepositoryRegistry\Factory\Tracer;

use Neos\ContentRepository\Core\Infrastructure\Tracing\TracerInterface;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;

interface TracerFactoryInterface
{
    /** @param array<string, mixed> $options */
    public function build(ContentRepositoryId $contentRepositoryId, array $options): TracerInterface;
}
