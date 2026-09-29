<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UploadSession extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = ['size' => 'integer', 'next_index' => 'integer', 'cancelled' => 'boolean'];
}
