<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Tests\Unit\Pipelines\Enums;

use DataProcessingPipeline\Pipelines\Enums\ResultStatus;
use PHPUnit\Framework\TestCase;

final class ResultStatusTest extends TestCase
{
    public function test_has_all_expected_cases(): void
    {
        $cases = ResultStatus::cases();

        $this->assertCount(3, $cases);
        $this->assertEquals('ok', ResultStatus::OK->value);
        $this->assertEquals('skipped', ResultStatus::SKIPPED->value);
        $this->assertEquals('failed', ResultStatus::FAILED->value);
    }
}
