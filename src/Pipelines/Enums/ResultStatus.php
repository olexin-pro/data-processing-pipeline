<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Pipelines\Enums;

enum ResultStatus: string
{
    case OK = 'ok';
    case SKIPPED = 'skipped';
    case FAILED = 'failed';
}
