<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskActivity extends Model
{
    public const STREAM_ACTIVITY = 'activity';
    public const STREAM_AUTHORITY = 'authority';

    protected $fillable = [
        'task_id',
        'actor_id',
        'type',
        'stream',
        'description',
        'meta',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Journalise une action sur une tâche.
     *
     * @param  Task|int  $task
     */
    public static function log(
        $task,
        string $type,
        string $description,
        ?User $actor = null,
        string $stream = self::STREAM_ACTIVITY,
        array $meta = [],
    ): self {
        return static::create([
            'task_id' => $task instanceof Task ? $task->id : $task,
            'actor_id' => $actor?->id,
            'type' => $type,
            'stream' => $stream,
            'description' => $description,
            'meta' => $meta ?: null,
        ]);
    }
}
