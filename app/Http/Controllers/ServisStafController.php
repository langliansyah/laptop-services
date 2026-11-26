<?php

namespace App\Http\Controllers;

use App\Enums\StatusServis;
use App\Models\Pembayaran;
use App\Models\Servis;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServisStafController extends Controller
{
    public function index(): View
    {
        $services = Servis::query()->with(['feedback', 'diagnostic'])->latest()->paginate(20);
        $counts = Servis::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('staf.antrean', compact('services', 'counts'));
    }

    public function finance(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        $payments = Pembayaran::query()
            ->with('reservation')
            ->latest('paid_at')
            ->paginate(20);
        $reports = [
            'today_completed' => Servis::query()->where('status', StatusServis::Completed->value)->whereDate('completed_at', today())->count(),
            'today_revenue' => Pembayaran::query()->whereDate('paid_at', today())->sum('amount'),
            'month_completed' => Servis::query()->where('status', StatusServis::Completed->value)->whereMonth('completed_at', now()->month)->whereYear('completed_at', now()->year)->count(),
            'month_revenue' => Pembayaran::query()->whereMonth('paid_at', now()->month)->whereYear('paid_at', now()->year)->sum('amount'),
            'total_revenue' => Pembayaran::query()->sum('amount'),
        ];

        return view('staf.keuangan', compact('payments', 'reports'));
    }

    public function exportDailySummary(Request $request): StreamedResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $validated = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);

        $statusCounts = Servis::query()
            ->whereDate('created_at', $validated['date'])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
        $completedCount = Servis::query()
            ->where('status', StatusServis::Completed->value)
            ->whereDate('completed_at', $validated['date'])
            ->count();
        $revenue = Pembayaran::query()->whereDate('paid_at', $validated['date'])->sum('amount');

        return $this->summaryCsv(
            'Harian',
            $validated['date'],
            $statusCounts,
            $completedCount,
            (float) $revenue,
        );
    }

    public function exportMonthlySummary(Request $request): StreamedResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $validated = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        [$year, $month] = array_map('intval', explode('-', $validated['month']));

        $statusCounts = Servis::query()
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
        $completedCount = Servis::query()
            ->where('status', StatusServis::Completed->value)
            ->whereYear('completed_at', $year)
            ->whereMonth('completed_at', $month)
            ->count();
        $revenue = Pembayaran::query()->whereYear('paid_at', $year)->whereMonth('paid_at', $month)->sum('amount');

        return $this->summaryCsv(
            'Bulanan',
            $validated['month'],
            $statusCounts,
            $completedCount,
            (float) $revenue,
        );
    }

    public function receive(Request $request, Servis $service): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $validated = $request->validate(['serial_number' => ['required', 'string', 'max:120']]);

        DB::transaction(function () use ($service, $validated) {
            $locked = Servis::query()->lockForUpdate()->findOrFail($service->id);
            abort_unless($locked->status->canTransitionTo(StatusServis::Diagnosing), 409, 'Antrean sudah diproses sebelumnya.');
            $locked->update([
                'serial_number' => $validated['serial_number'],
                'service_number' => Servis::generateServiceNumber(),
                'status' => StatusServis::Diagnosing,
                'received_at' => now(),
            ]);
        });

        return back()->with('success', 'Unit diterima. Nomor servis dan bukti tanda terima telah diterbitkan.');
    }

    public function diagnose(Request $request, Servis $service): RedirectResponse
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
                $locked = Servis::query()->lockForUpdate()->findOrFail($service->id);
                abort_unless($locked->status->canTransitionTo(StatusServis::WaitingApproval), 409, 'Diagnosis hanya dapat disimpan saat pemeriksaan.');

                if ($diagnosticImage) {
                    $imagePath = Storage::disk('local')->putFile('diagnostic-images', $diagnosticImage);

                    if (! is_string($imagePath)) {
                        throw new RuntimeException('Gagal menyimpan foto diagnosis.');
                    }
                }

                $diagnosticAttributes = [
                    'diagnosis' => $validated['diagnosis'],
                    'estimated_cost' => $validated['estimated_cost'],
                    'estimated_days' => $validated['estimated_days'],
                ];

                if ($imagePath) {
                    $diagnosticAttributes['diagnostic_image_path'] = $imagePath;
                }

                $locked->diagnostic()->updateOrCreate([], $diagnosticAttributes);
                $locked->update(['status' => StatusServis::WaitingApproval]);
            });
        } catch (\Throwable $exception) {
            if ($imagePath) {
                Storage::disk('local')->delete($imagePath);
            }

            throw $exception;
        }

        return back()->with('success', 'Diagnosis tersimpan dan pelanggan dapat menyetujui atau membatalkan estimasi.');
    }

    public function progress(Request $request, Servis $service): RedirectResponse
    {
        abort_unless($request->user()->isTechnician(), 403);
        $validated = $request->validate([
            'progress_note' => ['required', 'string', 'in:Perbaikan berjalan,Menunggu sparepart,Pengujian kualitas (QC)'],
        ]);
        DB::transaction(function () use ($service, $validated) {
            $locked = Servis::query()->lockForUpdate()->findOrFail($service->id);
            abort_unless($locked->status === StatusServis::InProgress, 409, 'Progres hanya dapat diperbarui saat perbaikan berlangsung.');
            $locked->progressNotes()->create(['note' => $validated['progress_note']]);
        });

        return back()->with('success', 'Progres servis diperbarui.');
    }

    public function ready(Request $request, Servis $service): RedirectResponse
    {
        abort_unless($request->user()->isTechnician(), 403);
        DB::transaction(function () use ($service) {
            $locked = Servis::query()->lockForUpdate()->findOrFail($service->id);
            abort_unless($locked->status->canTransitionTo(StatusServis::ReadyForPickup), 409, 'Servis belum dapat ditandai siap diambil.');
            $locked->update(['status' => StatusServis::ReadyForPickup]);
            $locked->progressNotes()->create(['note' => 'Perbaikan selesai dan telah lolos pemeriksaan kualitas.']);
        });

        return back()->with('success', 'Servis ditandai siap diambil.');
    }

    public function complete(Request $request, Servis $service): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $validated = $request->validate(['payment_method' => ['required', 'string', 'in:Tunai,Transfer,QRIS']]);

        DB::transaction(function () use ($service, $validated) {
            $locked = Servis::query()->lockForUpdate()->findOrFail($service->id);
            abort_unless($locked->status->canTransitionTo(StatusServis::Completed), 409, 'Unit belum siap diambil.');
            $diagnostic = $locked->diagnostic;
            abort_unless($diagnostic?->estimated_cost !== null, 409, 'Estimasi biaya belum tersedia.');
            $locked->update(['status' => StatusServis::Completed, 'completed_at' => now()]);
            $locked->payment()->create([
                'amount' => $diagnostic->estimated_cost,
                'method' => $validated['payment_method'],
                'paid_at' => $locked->completed_at,
            ]);
        });

        return back()->with('success', 'Pembayaran dikonfirmasi dan servis selesai.');
    }

    public function destroy(Request $request, Servis $service): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $diagnosticImagePath = DB::transaction(function () use ($service): ?string {
            $locked = Servis::query()->lockForUpdate()->findOrFail($service->id);
            abort_unless(
                in_array($locked->status, [StatusServis::WaitingQueue, StatusServis::Cancelled], true),
                409,
                'Hanya servis yang masih antre atau dibatalkan yang dapat dihapus.',
            );

            $diagnosticImagePath = $locked->diagnostic?->diagnostic_image_path;
            $locked->delete();

            return $diagnosticImagePath;
        });

        if ($diagnosticImagePath !== null && ! Storage::disk('local')->delete($diagnosticImagePath)) {
            throw new RuntimeException('Gagal menghapus foto diagnosis terkait servis.');
        }

        return redirect()->route('staff.index')->with('success', 'Servis berhasil dihapus.');
    }

    /**
     * @param  array<string, int|string>  $statusCounts
     */
    private function summaryCsv(
        string $periodLabel,
        string $periodValue,
        array $statusCounts,
        int $completedCount,
        float $revenue,
    ): StreamedResponse {
        $statusLabels = [
            StatusServis::WaitingQueue->value => 'Menunggu antrean',
            StatusServis::Diagnosing->value => 'Diagnosis',
            StatusServis::WaitingApproval->value => 'Menunggu persetujuan',
            StatusServis::InProgress->value => 'Dalam pengerjaan',
            StatusServis::ReadyForPickup->value => 'Siap diambil',
        ];
        $rows = [
            ['Jenis laporan', $periodLabel],
            ['Periode', $periodValue],
            [],
            ['Status servis yang dibuat pada periode', 'Jumlah'],
        ];

        foreach ($statusLabels as $status => $label) {
            $rows[] = [$label, (int) ($statusCounts[$status] ?? 0)];
        }

        $rows[] = [];
        $rows[] = ['Servis selesai pada periode', $completedCount];
        $rows[] = ['Pendapatan pada periode (Rp)', number_format($revenue, 2, '.', '')];

        return response()->streamDownload(
            static function () use ($rows): void {
                $stream = fopen('php://output', 'wb');

                if (! is_resource($stream)) {
                    throw new RuntimeException('Gagal membuat file CSV laporan.');
                }

                fwrite($stream, "\xEF\xBB\xBF");

                foreach ($rows as $row) {
                    fputcsv($stream, $row);
                }

                fclose($stream);
            },
            'ringkasan-'.strtolower($periodLabel).'-'.$periodValue.'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
