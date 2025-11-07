<?php

namespace DataProcessingPipeline\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipelineStep extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'run_id',
        'step_class',
        'key',
        'policy',
        'status',
        'duration_ms',
        'result',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'result' => 'array',
        ];
    }

    /**
     * @return BelongsTo<PipelineRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PipelineRun::class, 'run_id', 'id');
    }
}
