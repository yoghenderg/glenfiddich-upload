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

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
