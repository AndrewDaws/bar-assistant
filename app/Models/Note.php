<?php

declare(strict_types=1);

namespace Kami\Cocktail\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Note extends Model
{
    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\NoteFactory> */
    use HasFactory;

    /**
     * @return MorphTo<Cocktail|Model, $this>
     */
    public function noteable(): MorphTo
    {
        return $this->morphTo();
    }
}
