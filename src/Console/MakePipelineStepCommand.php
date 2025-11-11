<?php

namespace DataProcessingPipeline\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakePipelineStepCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:step
        {name : Class name or path. Examples: EmailFormatterStep, Order/TotalCalculationStep, App\Steps\Billing\ChargeStep}
        {--policy=MERGE : Conflict policy (MERGE|OVERWRITE|SKIP|CUSTOM)}
        {--priority=10 : Default priority}
        {--key= : Result key (defaults to snake(name without "Step"))}
        {--force : Overwrite file if it exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Data Processing Pipeline Step class';

    /**
     * Execute the console command.
     */
    public function handle(Filesystem $files): int
    {
        $input = trim((string) $this->argument('name'));
        $input = str_replace('/', '\\', $input); // поддерживаем оба разделителя

        $baseNamespace = 'App\\Pipeline\\Steps';

        $isFqcn = Str::startsWith($input, 'App\\');

        $fqcn = $isFqcn
            ? $input
            : trim($baseNamespace.'\\'.ltrim($input, '\\'), '\\');

        $class = class_basename($fqcn);
        $namespace = Str::beforeLast($fqcn, '\\') ?: $baseNamespace;

        $bare = Str::of($class)->replaceLast('Step', '')->toString();
        $key = strval($this->option('key') ?: Str::snake($bare));

        $policy = strtoupper((string) $this->option('policy'));
        if (! in_array($policy, ['MERGE', 'OVERWRITE', 'SKIP', 'CUSTOM'], true)) {
            $this->components->error('Invalid --policy. Use MERGE|OVERWRITE|SKIP|CUSTOM.');
            return self::FAILURE;
        }
        $priority = intval($this->option('priority'));

        $relative = Str::after($fqcn, 'App\\');
        $path = app_path(str_replace('\\', DIRECTORY_SEPARATOR, $relative).'.php');

        if ($files->exists($path) && ! $this->option('force')) {
            $this->components->error("File already exists: {$path}");
            return self::FAILURE;
        }

        $files->ensureDirectoryExists(dirname($path));

        $stub = $this->resolveStub($files);

        $contents = strtr($stub, [
            '{{ namespace }}' => $namespace,
            '{{ class }}'     => $class,
            '{{ key }}'       => $key,
            '{{ policy }}'    => $policy,
            '{{ priority }}'  => strval($priority),
        ]);

        $files->put($path, $contents);

        $this->components->info("Step created: {$fqcn}");
        $this->components->twoColumnDetail('Path', $path);

        return self::SUCCESS;
    }

    private function resolveStub(Filesystem $files): string
    {
        $published = base_path('stubs/pipeline.step.stub');
        if ($files->exists($published)) {
            return strval($files->get($published));
        }

        $packageStub = __DIR__.'/../../stubs/pipeline.step.stub';
        return strval($files->get($packageStub));
    }
}
