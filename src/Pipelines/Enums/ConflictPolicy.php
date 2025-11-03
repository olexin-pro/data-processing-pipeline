<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Pipelines\Enums;

enum ConflictPolicy: string
{
    case MERGE = 'merge';
    case OVERWRITE = 'overwrite';
    case SKIP = 'skip';
    case CUSTOM = 'custom';
}
