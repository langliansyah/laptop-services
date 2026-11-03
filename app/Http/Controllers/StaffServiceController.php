<?php

namespace App\Http\Controllers;

use App\Enums\ServiceStatus;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;

class StaffServiceController extends Controller
{
    public function index(): View
    {
        $services = Service::query()->with('feedback')->latest()->paginate(20);
        $counts = Service::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $reports = [
            'today_completed' => Service::query()->where('status', ServiceStatus::Completed->value)->whereDate('completed_at', today())->count(),
            'today_revenue' => Service::query()->where('status', ServiceStatus::Completed->value)->whereDate('completed_at', today())->sum('estimated_cost'),
            'month_completed' => Service::query()->where('status', ServiceStatus::Completed->value)->whereMonth('completed_at', now()->month)->whereYear('completed_at', now()->year)->count(),
            'month_revenue' => Service::query()->where('status', ServiceStatus::Completed->value)->whereMonth('completed_at', now()->month)->whereYear('completed_at', now()->year)->sum('estimated_cost'),
        ];

        return view('staff.index', compact('services', 'counts', 'reports'));
    }

    public function receive(Request $request, Service $service): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $validated = $request->validate(['serial_number' => ['required', 'string', 'max:120']]);

        DB::transaction(function () use ($service, $validated) {
            $locked = Service::query()->lockForUpdate()->findOrFail($service->id);
            abort_unless($locked->status->canTransitionTo(ServiceStatus::Diagnosing), 409, 'Antrean sudah diproses sebelumnya.');
            $locked->update([
                'serial_number' => $validated['serial_number'],
                'service_number' => Service::generateServiceNumber(),
                'status' => ServiceStatus::Diagnosing,
                'received_at' => now(),
            ]);
        });

        return back()->with('success', 'Unit diterima. Nomor servis dan bukti tanda terima telah diterbitkan.');
    }

    public function diagnose(Request $request, Service $service): RedirectResponse
    {
        abort_unless($request->user()->isTechnician(), 403);
        $validated = $request->validate([
            'diagnosis' => ['required', 'string', 'max:3000'],
            'diagnostic_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'estimated_cost' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'estimated_days' => ['required', 'integer', 'between:1,365'],
        ]);
        $diagnosticImage = $validated['diagnostic_image'] ?? null;
        unset($validated['diagnostic_image']);

        $imagePath = null;

        try {
            DB::transaction(function () use ($service, $validated, $diagnosticImage, &$imagePath) {
                $locked = Service::query()->lockForUpdate()->findOrFail($service->id);
                abort_unless($locked->status->canTransitionTo(ServiceStatus::WaitingApproval), 409, 'Diagnosis hanya dapat disimpan saat pemeriksaan.');

                if ($diagnosticImage) {
                    $imagePath = Storage::disk('local')->putFile('diagnostic-images', $diagnosticImage);

                    if (! is_string($imagePath)) {
                        throw new RuntimeException('Gagal menyimpan foto diagnosis.');
                    }
                }

                $locked->update([
                    ...$validated,
                    ...($imagePath ? ['diagnostic_image_path' => $imagePath] : []),
                    'status' => ServiceStatus::WaitingApproval,
                ]);
            });
        } catch (\Throwable $exception) {
            if ($imagePath) {
                Storage::disk('local')->delete($imagePath);
            }

            throw $exception;
        }

        return back()->with('success', 'Diagnosis tersimpan dan pelanggan dapat menyetujui atau membatalkan estimasi.');
    }

    public function progress(Request $request, Service $service): RedirectResponse
    {
        abort_unless($request->user()->isTechnician(), 403);
        $validated = $request->validate([
            'progress_note' => ['required', 'string', 'in:Perbaikan berjalan,Menunggu sparepart,Pengujian kualitas (QC)'],
        ]);
        DB::transaction(function () use ($service, $validated) {
            $locked = Service::query()->lockForUpdate()->findOrFail($service->id);
            abort_unless($locked->status === ServiceStatus::InProgress, 409, 'Progres hanya dapat diperbarui saat perbaikan berlangsung.');
            $locked->update($validated);
        });

        return back()->with('success', 'Progres servis diperbarui.');
    }

    public function ready(Request $request, Service $service): RedirectResponse
    {
        abort_unless($request->user()->isTechnician(), 403);
        DB::transaction(function () use ($service) {
            $locked = Service::query()->lockForUpdate()->findOrFail($service->id);
            abort_unless($locked->status->canTransitionTo(ServiceStatus::ReadyForPickup), 409, 'Servis belum dapat ditandai siap diambil.');
            $locked->update(['status' => ServiceStatus::ReadyForPickup, 'progress_note' => 'Perbaikan selesai dan telah lolos pemeriksaan kualitas.']);
        });

        return back()->with('success', 'Servis ditandai siap diambil.');
    }

    public function complete(Request $request, Service $service): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $validated = $request->validate(['payment_method' => ['required', 'string', 'in:Tunai,Transfer,QRIS']]);

        DB::transaction(function () use ($service, $validated) {
            $locked = Service::query()->lockForUpdate()->findOrFail($service->id);
            abort_unless($locked->status->canTransitionTo(ServiceStatus::Completed), 409, 'Unit belum siap diambil.');
            $locked->update([...$validated, 'status' => ServiceStatus::Completed, 'completed_at' => now()]);
        });

        return back()->with('success', 'Pembayaran dikonfirmasi dan servis selesai.');
    }
}
