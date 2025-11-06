<?php

namespace DataProcessingPipeline;

use DataProcessingPipeline\Pipelines\Contracts\ConflictResolverInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineHistoryRecorderInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineNotifierInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineResultInterface;
use DataProcessingPipeline\Pipelines\Contracts\PipelineRunnerInterface;
use DataProcessingPipeline\Pipelines\History\PipelineHistoryRecorder;
use DataProcessingPipeline\Pipelines\Resolution\ConflictResolver;
use DataProcessingPipeline\Pipelines\Results\GenericPipelineResult;
use DataProcessingPipeline\Pipelines\Runner\PipelineRunner;
use DataProcessingPipeline\Services\Notifiers\NullNotifier;
use DataProcessingPipeline\Services\PipelineExecutor;
use Illuminate\Support\ServiceProvider;

class PipelineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->app->bind(ConflictResolverInterface::class, ConflictResolver::class);
        $this->app->bind(PipelineResultInterface::class, GenericPipelineResult::class);
        $this->app->bind(PipelineHistoryRecorderInterface::class, PipelineHistoryRecorder::class);

        $this->app->bind(PipelineRunnerInterface::class, function ($app) {
            return new PipelineRunner(
                steps: [],
                recorder: null
            );
        });

        $this->app->singleton(PipelineExecutor::class);
        $this->app->singleton(PipelineNotifierInterface::class, NullNotifier::class);

        // $this->mergeConfigFrom(
        //     __DIR__ . '/../config/pipeline.php',
        //     'pipeline'
        // );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            // $this->publishes([
            //     __DIR__ . '/../config/pipeline.php' => config_path('pipeline.php'),
            // ], 'pipeline-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'pipeline-migrations');
        }
    }
}
