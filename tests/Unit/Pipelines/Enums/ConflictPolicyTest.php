<?php

declare(strict_types=1);

namespace DataProcessingPipeline\Tests\Unit\Pipelines\Enums;

use DataProcessingPipeline\Pipelines\Enums\ConflictPolicy;
use DataProcessingPipeline\Tests\TestCase;

final class ConflictPolicyTest extends TestCase
{
    public function test_has_all_expected_cases(): void
    {
        $cases = ConflictPolicy::cases();

        $this->assertCount(4, $cases);
        $this->assertEquals('merge', ConflictPolicy::MERGE->value);
        $this->assertEquals('overwrite', ConflictPolicy::OVERWRITE->value);
        $this->assertEquals('skip', ConflictPolicy::SKIP->value);
        $this->assertEquals('custom', ConflictPolicy::CUSTOM->value);
    }

    public function test_can_create_from_string(): void
    {
        $this->assertEquals(ConflictPolicy::MERGE, ConflictPolicy::from('merge'));
        $this->assertEquals(ConflictPolicy::OVERWRITE, ConflictPolicy::from('overwrite'));
    }
}
