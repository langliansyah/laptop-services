<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['reservation_id', 'rating', 'comment'])]
class Ulasan extends Model
{
    protected $table = 'feedback';

    public function service(): BelongsTo
    {
        return $this->belongsTo(Servis::class, 'reservation_id');
    }
}
