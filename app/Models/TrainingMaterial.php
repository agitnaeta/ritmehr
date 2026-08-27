<?php

namespace App\Models;

use App\Traits\Auditable;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * M11 — one chapter/section of a training. Rich text + optional file
 * attachment (StorageManager) and/or a YouTube video URL.
 */
class TrainingMaterial extends Model
{
    use CrudTrait, HasFactory, Auditable;

    protected $table = 'training_materials';

    protected $fillable = [
        'training_id', 'position', 'title', 'content', 'attachment_path', 'video_url',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function training()
    {
        return $this->belongsTo(Training::class);
    }

    /**
     * Normalise a YouTube URL to its embed form for an <iframe>.
     * Returns null when there is no video.
     */
    public function youtubeEmbedUrl(): ?string
    {
        if (! $this->video_url) {
            return null;
        }

        // Accept youtu.be/ID, watch?v=ID, embed/ID.
        if (preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/))([\w-]{6,})~', $this->video_url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }

        return null;
    }
}
