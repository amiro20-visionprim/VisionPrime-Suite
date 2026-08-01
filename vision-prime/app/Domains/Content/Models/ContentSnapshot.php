<?php

declare(strict_types=1);

namespace App\Domains\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = ['url_profile_id', 'content_hash', 'title', 'meta', 'headings', 'content', 'word_count', 'captured_at'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'headings' => 'array', 'captured_at' => 'immutable_datetime'];
    }

    public function urlProfile(): BelongsTo
    {
        return $this->belongsTo(UrlProfile::class);
    }
}
