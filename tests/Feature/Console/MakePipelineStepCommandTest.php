<?php

declare(strict_types=1);

namespace Console;

use DataProcessingPipeline\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;

final class MakePipelineStepCommandTest extends TestCase
{
    private const CMD = 'make:step';
    private Filesystem $files;
    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem();

        $this->files->deleteDirectory(app_path('Pipeline/Steps'));
        $this->files->ensureDirectoryExists(app_path('Pipeline/Steps'));

        $this->files->ensureDirectoryExists(base_path('stubs'));
        $this->files->put(
            base_path('stubs/pipeline.step.stub'),
            <<<'PHP'
<?php

declare(strict_types=1);

namespace {{ namespace }};

use DataProcessingPipeline\Pipelines\Contracts\PipelineStepInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineContextInterface;
use DataProcessingPipeline\Pipelines\Results\GenericPipelineResult;
use DataProcessingPipeline\Pipelines\Enums\ConflictPolicy;

final class {{ class }} implements PipelineStepInterface
{
    public function handle(PipelineContextInterface $context): GenericPipelineResult
    {
        // $value = $context->getContent('some.path');
        // $prev = $context->getResult('some_key')?->getData();

        return new GenericPipelineResult(
            key: '{{ key }}',
            data: [
                // 'value' => $value,
            ],
            policy: ConflictPolicy::{{ policy }},
            priority: {{ priority }},
            provenance: self::class
        );
    }
}
PHP
        );
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory(app_path('Pipeline/Steps'));
        parent::tearDown();
    }

    public function test_it_generates_step_in_default_namespace(): void
    {
        $exit = $this->artisan(self::CMD, [
            'name' => 'EmailFormatterStep',
        ])->run();

        $this->assertSame(0, $exit);

        $path = app_path('Pipeline/Steps/EmailFormatterStep.php');
        $this->assertFileExists($path);

        $content = $this->files->get($path);

        $this->assertStringContainsString('namespace App\\Pipeline\\Steps;', $content);
        $this->assertStringContainsString('final class EmailFormatterStep', $content);
        $this->assertStringContainsString("key: 'email_formatter'", $content);
        $this->assertStringContainsString('ConflictPolicy::MERGE', $content);
        $this->assertStringContainsString('priority: 10', $content);
    }

    public function test_it_generates_nested_paths_with_slash(): void
    {
        $exit = $this->artisan(self::CMD, [
            'name' => 'Order/TotalCalculationStep',
        ])->run();

        $this->assertSame(0, $exit);

        $path = app_path('Pipeline/Steps/Order/TotalCalculationStep.php');
        $this->assertFileExists($path);

        $content = $this->files->get($path);
        $this->assertStringContainsString('namespace App\\Pipeline\\Steps\\Order;', $content);
        $this->assertStringContainsString('final class TotalCalculationStep', $content);
        $this->assertStringContainsString("key: 'total_calculation'", $content);
    }

    public function test_it_accepts_fqcn(): void
    {
        $exit = $this->artisan(self::CMD, [
            'name' => 'App\\Pipeline\\Steps\\Billing\\ChargeStep',
        ])->run();

        $this->assertSame(0, $exit);

        $path = app_path('Pipeline/Steps/Billing/ChargeStep.php');
        $this->assertFileExists($path);

        $content = $this->files->get($path);
        $this->assertStringContainsString('namespace App\\Pipeline\\Steps\\Billing;', $content);
        $this->assertStringContainsString('final class ChargeStep', $content);
        $this->assertStringContainsString("key: 'charge'", $content);
    }

    public function test_it_respects_options_key_priority_policy(): void
    {
        $exit = $this->artisan(self::CMD, [
            'name'      => 'EmailValidatorStep',
            '--key'     => 'email',
            '--priority' => '20',
            '--policy'  => 'OVERWRITE',
        ])->run();

        $this->assertSame(0, $exit);

        $path = app_path('Pipeline/Steps/EmailValidatorStep.php');
        $content = $this->files->get($path);

        $this->assertStringContainsString("key: 'email'", $content);
        $this->assertStringContainsString('priority: 20', $content);
        $this->assertStringContainsString('ConflictPolicy::OVERWRITE', $content);
    }

    public function test_it_fails_on_invalid_policy_and_does_not_create_file(): void
    {
        $exit = $this->artisan(self::CMD, [
            'name'    => 'BrokenStep',
            '--policy' => 'NOT_A_POLICY',
        ])->run();

        $this->assertSame(1, $exit); // FAILURE

        $path = app_path('Pipeline/Steps/BrokenStep.php');
        $this->assertFileDoesNotExist($path);
    }

    public function test_it_overwrites_with_force(): void
    {
        $target = app_path('Pipeline/Steps/OverwriteMeStep.php');

        $this->artisan(self::CMD, [
            'name'       => 'OverwriteMeStep',
            '--priority' => '5',
        ])->run();

        $this->assertFileExists($target);
        $original = $this->files->get($target);
        $this->assertStringContainsString('priority: 5', $original);

        $this->artisan(self::CMD, [
            'name'       => 'OverwriteMeStep',
            '--priority' => '99',
            '--policy'   => 'SKIP',
            '--force'    => true,
        ])->run();

        $updated = $this->files->get($target);
        $this->assertStringContainsString('priority: 99', $updated);
        $this->assertStringContainsString('ConflictPolicy::SKIP', $updated);
    }

    public function test_it_refuses_when_file_exists_without_force(): void
    {
        $target = app_path('Pipeline/Steps/AlreadyThereStep.php');

        $this->artisan(self::CMD, [
            'name' => 'AlreadyThereStep',
        ])->run();

        $original = $this->files->get($target);

        $exit = $this->artisan(self::CMD, [
            'name' => 'AlreadyThereStep',
        ])
            ->expectsOutputToContain('File already exists')
            ->run();

        $this->assertSame(1, $exit);
        $this->assertSame($original, $this->files->get($target));
    }

    public function test_it_falls_back_to_package_stub_when_published_stub_missing(): void
    {
        $this->files->delete(base_path('stubs/pipeline.step.stub'));

        $exit = $this->artisan(self::CMD, [
            'name' => 'FromPackageStubStep',
        ])->run();

        $this->assertSame(0, $exit);

        $path = app_path('Pipeline/Steps/FromPackageStubStep.php');
        $this->assertFileExists($path);

        $content = $this->files->get($path);

        $this->assertStringContainsString("key: 'from_package_stub'", $content);
        $this->assertStringContainsString('ConflictPolicy::MERGE', $content);
        $this->assertStringContainsString('priority: 10', $content);
    }
}
