<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['reservation_id', 'note'])]
class CatatanProgresServis extends Model
{
    protected $table = 'reservation_progress_notes';

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Servis::class, 'reservation_id');
    }
}
