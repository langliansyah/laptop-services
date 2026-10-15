<?php

namespace App\Models;

use App\Enums\ServiceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable([
    'member_id', 'queue_number', 'service_number', 'customer_name', 'phone',
    'brand', 'model', 'serial_number', 'complaint', 'diagnosis',
    'diagnostic_image_path', 'estimated_cost', 'estimated_days', 'progress_note', 'status',
    'payment_method', 'received_at', 'completed_at',
])]
class Service extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ServiceStatus::class,
            'estimated_cost' => 'decimal:2',
            'received_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(Feedback::class);
    }

    public static function generateQueueNumber(): string
    {
        return 'ANT-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
    }

    public static function generateServiceNumber(): string
    {
        return 'SRV-'.now()->format('ym').'-'.Str::upper(Str::random(7));
    }
}
