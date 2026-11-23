<?php

namespace App\Http\Controllers;

use App\Enums\StatusServis;
use App\Models\Ulasan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class BerandaController extends Controller
{
    public function __invoke(): View
    {
        $completedFeedback = Ulasan::query()
            ->whereHas('service', fn (Builder $query) => $query->where('status', StatusServis::Completed->value));

        $ratingSummary = (clone $completedFeedback)
            ->selectRaw('COUNT(*) as total, AVG(rating) as average_rating')
            ->first();

        $testimonials = (clone $completedFeedback)
            ->whereNotNull('comment')
            ->where('comment', '!=', '')
            ->with('service:id,customer_name')
            ->latest()
            ->limit(6)
            ->get();

        return view('beranda', compact('ratingSummary', 'testimonials'));
    }
}
