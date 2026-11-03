<?php

namespace App\Enums;

enum ServiceStatus: string
{
    case WaitingQueue = 'waiting_queue';
    case Diagnosing = 'diagnosing';
    case WaitingApproval = 'waiting_approval';
    case InProgress = 'in_progress';
    case ReadyForPickup = 'ready_for_pickup';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::WaitingQueue => 'Menunggu antrean',
            self::Diagnosing => 'Sedang didiagnosis',
            self::WaitingApproval => 'Menunggu persetujuan',
            self::InProgress => 'Sedang diperbaiki',
            self::ReadyForPickup => 'Siap diambil',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::WaitingQueue => $next === self::Diagnosing,
            self::Diagnosing => $next === self::WaitingApproval,
            self::WaitingApproval => in_array($next, [self::InProgress, self::Cancelled], true),
            self::InProgress => $next === self::ReadyForPickup,
            self::ReadyForPickup => $next === self::Completed,
            self::Completed, self::Cancelled => false,
        };
    }
}
