<?php

namespace App\Http\Controllers;

use App\Enums\ServiceStatus;
use App\Models\Feedback;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $completedFeedback = Feedback::query()
            ->whereHas('service', fn (Builder $query) => $query->where('status', ServiceStatus::Completed->value));

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

        return view('home', compact('ratingSummary', 'testimonials'));
    }
}
