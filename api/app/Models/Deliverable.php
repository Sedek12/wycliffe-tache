<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Deliverable extends Model
{
    protected $fillable = [
        'task_id',
        'project_id',
        'uploaded_by',
        'disk',
        'path',
        'original_name',
        'mime',
        'size',
        'note',
        'version',
        'group_key',
    ];

    protected $appends = ['url', 'preview_url'];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Regroupe les versions successives d'un même document ; à défaut d'un
        // group_key explicite (nouvelle version d'un livrable existant), le
        // livrable est sa propre première version.
        static::created(function (Deliverable $deliverable) {
            if (blank($deliverable->group_key)) {
                $deliverable->group_key = (string) $deliverable->id;
                $deliverable->saveQuietly();
            }
        });
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** URL publique directe du fichier. */
    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * URL de prévisualisation. Pour les formats bureautiques, on délègue
     * l'affichage à un visualiseur tiers (aucun visualiseur hébergé en interne).
     */
    public function getPreviewUrlAttribute(): ?string
    {
        $office = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
        $ext = strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));

        if (in_array($ext, $office, true)) {
            return 'https://view.officeapps.live.com/op/embed.aspx?src=' . urlencode($this->url);
        }

        // PDF, images, audio, vidéo : lecture native par le navigateur.
        return $this->url;
    }
}
