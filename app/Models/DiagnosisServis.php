<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['reservation_id', 'diagnosis', 'diagnostic_image_path', 'estimated_cost', 'estimated_days'])]
class DiagnosisServis extends Model
{
    protected $table = 'reservation_diagnostics';

    protected function casts(): array
    {
        return [
            'estimated_cost' => 'decimal:2',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Servis::class, 'reservation_id');
    }
}
