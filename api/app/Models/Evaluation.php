<?php

namespace App\Models;

use App\Enums\EvaluationMention;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evaluation extends Model
{
    protected $fillable = [
        'task_id',
        'evaluated_by',
        'score',
        'mention',
        'appreciation',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'mention' => EvaluationMention::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Evaluation $evaluation) {
            $evaluation->mention = EvaluationMention::fromScore($evaluation->score);
        });
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }
}
