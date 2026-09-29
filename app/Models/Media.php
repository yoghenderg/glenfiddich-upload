<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = [
        'title', 'original_filename', 'disk', 'path', 'poster_path',
        'type', 'mime_type', 'size_bytes', 'width', 'height', 'duration_ms', 'captured_at',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
        'size_bytes' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'duration_ms' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Media $media): void {
            $media->public_id = (string) Str::uuid();
        });
    }

    public function galleryItem(): array
    {
        $date = $this->captured_at ?? $this->created_at;

        return [
            'id' => $this->public_id, 'title' => $this->title ?: $this->original_filename,
            'filename' => $this->original_filename, 'type' => $this->type,
            'src' => route('media.file', $this), 'download' => route('media.download', $this),
            'poster' => $this->type === 'image' ? route('media.file', $this) : '',
            'mime_type' => $this->mime_type, 'alt' => $this->title ?: $this->original_filename,
            'date' => $date->toDateString(), 'date_display' => $date->format('d-m-Y'),
            'captured_at' => $date->toIso8601String(), 'time_display' => $date->format('h:i A'),
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
