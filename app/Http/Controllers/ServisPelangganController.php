<?php

namespace App\Http\Controllers;

use App\Enums\StatusServis;
use App\Models\Servis;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServisPelangganController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'min:8', 'max:25', function (string $attribute, mixed $value, \Closure $fail): void {
                if (strlen(preg_replace('/\D+/', '', (string) $value)) < 8) {
                    $fail('Nomor WhatsApp harus memiliki sedikitnya 8 angka.');
                }
            }],
            'brand' => ['required', 'string', 'max:80'],
            'model' => ['required', 'string', 'max:120'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'complaint' => ['required', 'string', 'max:3000'],
        ]);
        $validated['phone'] = preg_replace('/\D+/', '', $validated['phone']);

        $service = Servis::create([
            'customer_name' => $validated['customer_name'],
            'phone' => $validated['phone'],
            'brand' => $validated['brand'],
            'model' => $validated['model'],
            'serial_number' => $validated['serial_number'] ?? null,
            'complaint' => $validated['complaint'],
            'queue_number' => Servis::generateQueueNumber(),
            'status' => StatusServis::WaitingQueue,
            'member_id' => $request->user()?->isMember() ? $request->user()->id : null,
        ]);

        $this->rememberTrackingAccess($request, $service);

        return redirect()->route('tracking.show', $service)->with('success', 'Reservasi berhasil dibuat. Simpan nomor antrean Anda.');
    }

    public function lookup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:40'],
            'phone' => ['required', 'string', 'min:8', 'max:25'],
        ]);

        $digits = preg_replace('/\D+/', '', $validated['phone']);
        $service = Servis::query()
            ->where(function ($query) use ($validated) {
                $query->where('queue_number', strtoupper($validated['reference']))
                        ->orWhere('service_number', strtoupper($validated['reference']));
            })
                ->where('phone', $digits)
            ->first();

        if (! $service) {
            return back()->withErrors(['reference' => 'Nomor antrean/servis atau nomor WhatsApp tidak cocok.'])->withInput();
        }

        $this->rememberTrackingAccess($request, $service);

        return redirect()->route('tracking.show', $service);
    }

    public function show(Request $request, Servis $service): View
    {
        abort_unless($this->hasTrackingAccess($request, $service), 403);

        $service->load(['feedback', 'payment']);

        return view('pelacakan.status', compact('service'));
    }

    public function approve(Request $request, Servis $service): RedirectResponse
    {
        abort_unless($this->hasTrackingAccess($request, $service), 403);
        abort_unless($service->status === StatusServis::WaitingApproval, 409, 'Servis ini tidak sedang menunggu persetujuan.');

        DB::transaction(function () use ($service) {
            $locked = Servis::query()->lockForUpdate()->findOrFail($service->id);
            abort_unless($locked->status->canTransitionTo(StatusServis::InProgress), 409);
            $locked->update(['status' => StatusServis::InProgress, 'progress_note' => 'Persetujuan biaya diterima.']);
        });

        return back()->with('success', 'Persetujuan diterima. Teknisi dapat memulai perbaikan.');
    }

    public function cancel(Request $request, Servis $service): RedirectResponse
    {
        abort_unless($this->hasTrackingAccess($request, $service), 403);
        abort_unless($service->status === StatusServis::WaitingApproval, 409, 'Servis ini tidak dapat dibatalkan pada tahap sekarang.');

        DB::transaction(function () use ($service) {
            $locked = Servis::query()->lockForUpdate()->findOrFail($service->id);
            abort_unless($locked->status->canTransitionTo(StatusServis::Cancelled), 409);
            $locked->update(['status' => StatusServis::Cancelled, 'progress_note' => 'Pelanggan menolak estimasi biaya.']);
        });

        return back()->with('success', 'Servis dibatalkan. Silakan hubungi toko untuk pengambilan unit.');
    }

    public function feedback(Request $request, Servis $service): RedirectResponse
    {
        abort_unless($this->hasTrackingAccess($request, $service), 403);
        abort_unless($service->status === StatusServis::Completed, 409, 'Ulasan tersedia setelah servis selesai.');

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1500'],
        ]);

        $service->feedback()->create([
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
        ]);

        return back()->with('success', 'Terima kasih atas ulasan Anda.');
    }

    public function receipt(Request $request, Servis $service): View
    {
        abort_unless($this->hasTrackingAccess($request, $service), 403);

        $service->load('payment');

        return view('pelacakan.nota', compact('service'));
    }

    public function diagnosisImage(Request $request, Servis $service): StreamedResponse
    {
        abort_unless($this->hasTrackingAccess($request, $service), 403);
        abort_unless($service->diagnostic_image_path && Storage::disk('local')->exists($service->diagnostic_image_path), 404);

        $mimeType = match (strtolower(pathinfo($service->diagnostic_image_path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => throw new RuntimeException('Format foto diagnosis tidak didukung.'),
        };

        $disk = Storage::disk('local');
        $stream = $disk->readStream($service->diagnostic_image_path);

        if (! is_resource($stream)) {
            throw new RuntimeException('Gagal membaca foto diagnosis.');
        }

        return response()->stream(
            static function () use ($stream): void {
                try {
                    fpassthru($stream);
                } finally {
                    fclose($stream);
                }
            },
            200,
            [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    private function rememberTrackingAccess(Request $request, Servis $service): void
    {
        $tracked = $request->session()->get('tracked_services', []);
        $tracked[] = $service->id;
        $request->session()->put('tracked_services', array_values(array_unique($tracked)));
    }

    private function hasTrackingAccess(Request $request, Servis $service): bool
    {
        return in_array($service->id, $request->session()->get('tracked_services', []), true)
            || ($request->user()?->isMember() && $service->member_id === $request->user()->id);
    }
}
