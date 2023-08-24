<?php

declare(strict_types=1);

namespace Kami\Cocktail\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Glass extends Model
{
    use HasFactory, HasBarAwareScope;

    /**
     * @return HasMany<Cocktail>
     */
    public function cocktails(): HasMany
    {
        return $this->hasMany(Cocktail::class);
    }

    /**
     * @return BelongsTo<Bar, Collection>
     */
    public function bar(): BelongsTo
    {
        return $this->belongsTo(Bar::class);
    }

    public function delete(): bool
    {
        $cocktailIds = $this->cocktails->pluck('id');
        DB::table('cocktails')->where('glass_id', $this->id)->update(['glass_id' => null]);
        /** @phpstan-ignore-next-line Laravel macro */
        Cocktail::find($cocktailIds)->searchable();

        return parent::delete();
    }
}
