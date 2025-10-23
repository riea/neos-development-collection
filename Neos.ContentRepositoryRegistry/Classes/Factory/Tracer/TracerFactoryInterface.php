<?php

namespace Neos\ContentRepositoryRegistry\Factory\Tracer;

use Neos\ContentRepository\Core\Infrastructure\Tracing\TracerInterface;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;

interface TracerFactoryInterface
{
    public function build(ContentRepositoryId $contentRepositoryId, array $options): TracerInterface;
}
