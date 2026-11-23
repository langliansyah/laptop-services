<?php

namespace App\Models;

use App\Enums\StatusServis;
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
    'received_at', 'completed_at',
])]
class Servis extends Model
{
    use HasFactory;

    protected $table = 'reservations';

    protected function casts(): array
    {
        return [
            'status' => StatusServis::class,
            'estimated_cost' => 'decimal:2',
            'received_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'member_id');
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(Ulasan::class, 'reservation_id');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Pembayaran::class, 'reservation_id');
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
