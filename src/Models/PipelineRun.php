<?php

namespace DataProcessingPipeline\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read Collection<int, PipelineStep> $steps
 */
class PipelineRun extends Model
{
    protected $fillable = [
        'pipeline_name',
        'status',
        'final',
        'meta',
        'created_at',
        'finished_at',
    ];
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'final' => 'array',
            'meta' => 'array',
            'created_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<PipelineStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(PipelineStep::class, 'run_id', 'id');
    }
}
