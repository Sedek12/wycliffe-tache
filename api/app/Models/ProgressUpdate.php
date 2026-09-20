<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProgressUpdate extends Model
{
    /** Étapes d'avancement proposées à l'assigné (bande + % enregistré). */
    public const STAGES = [
        'demarrage' => ['label' => 'Démarrage', 'band' => '0 – 30 %', 'percent' => 30],
        'mi_parcours' => ['label' => 'Mi-parcours', 'band' => '30 – 70 %', 'percent' => 70],
        'finalisation' => ['label' => 'Finalisation', 'band' => '70 – 100 %', 'percent' => 95],
        'livraison' => ['label' => 'Livraison finale', 'band' => '100 %', 'percent' => 100],
    ];

    protected $fillable = [
        'task_id',
        'user_id',
        'percent',
        'stage',
        'comment',
        'disk',
        'path',
        'original_name',
        'mime',
        'size',
        'reviewed_at',
        'reviewed_by',
        'rejected_at',
        'rejected_by',
    ];

    protected $appends = ['proof_url', 'proof_preview_url'];

    protected function casts(): array
    {
        return [
            'percent' => 'integer',
            'size' => 'integer',
            'reviewed_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function getProofUrlAttribute(): ?string
    {
        return $this->path ? Storage::disk($this->disk ?? 'public')->url($this->path) : null;
    }

    public function getProofPreviewUrlAttribute(): ?string
    {
        if (! $this->path) {
            return null;
        }

        $ext = strtolower(pathinfo((string) $this->original_name, PATHINFO_EXTENSION));
        if (in_array($ext, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'], true)) {
            return 'https://view.officeapps.live.com/op/embed.aspx?src=' . urlencode($this->proof_url);
        }

        return $this->proof_url;
    }
}
