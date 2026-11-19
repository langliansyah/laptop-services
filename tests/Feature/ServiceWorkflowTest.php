<?php

namespace Tests\Feature;

use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServiceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_book_and_track_a_queue_ticket_without_an_account(): void
    {
        $response = $this->post(route('reservations.store'), [
            'customer_name' => 'Dewi Putri',
            'phone' => '+62 812-3456-7890',
            'brand' => 'ASUS',
            'model' => 'VivoBook 14',
            'serial_number' => '',
            'complaint' => 'Laptop tidak dapat mengisi daya.',
        ]);

        $service = Service::query()->firstOrFail();
        $response->assertRedirect(route('tracking.show', $service));
        $this->assertSame('6281234567890', $service->phone);
        $this->assertNull($service->service_number);
        $this->assertSame(ServiceStatus::WaitingQueue, $service->status);
        $this->get(route('tracking.show', $service))->assertOk()->assertSee($service->queue_number);
    }

    public function test_member_registration_is_optional_and_future_bookings_are_saved_to_history(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Dewi Member',
            'email' => 'dewi@example.test',
            'phone' => '081234567890',
            'password' => 'secure-pass-123',
            'password_confirmation' => 'secure-pass-123',
        ])->assertRedirect(route('account.history'));

        $member = User::query()->where('email', 'dewi@example.test')->firstOrFail();
        $this->assertSame('member', $member->role);

        $this->post(route('reservations.store'), [
            'customer_name' => 'Dewi Member',
            'phone' => '081234567890',
            'brand' => 'Lenovo',
            'model' => 'ThinkPad T14',
            'complaint' => 'Baterai cepat habis.',
        ])->assertRedirect();

        $service = Service::query()->firstOrFail();
        $this->assertSame($member->id, $service->member_id);
        $this->get(route('account.history'))
            ->assertOk()
            ->assertSee($service->queue_number)
            ->assertSee('Buat reservasi')
            ->assertSee('Lacak servis')
            ->assertSee('Riwayat saya')
            ->assertDontSee('Panel staf');
        $this->get(route('tracking.show', $service))->assertOk();
    }

    public function test_customer_must_be_tracked_before_approving_and_can_only_approve_a_diagnosis(): void
    {
        $service = $this->createService(ServiceStatus::WaitingApproval);

        $this->post(route('tracking.approve', $service))->assertForbidden();
        $this->post(route('tracking.lookup.submit'), [
            'reference' => $service->service_number,
            'phone' => '(0812) 3456-7890',
        ])->assertRedirect(route('tracking.show', $service));
        $this->post(route('tracking.approve', $service))->assertRedirect();

        $this->assertSame(ServiceStatus::InProgress, $service->fresh()->status);
    }

    public function test_customer_can_open_and_print_a_completed_service_receipt(): void
    {
        $service = $this->createService(ServiceStatus::Completed);
        $service->update([
            'diagnosis' => 'Konektor daya telah diganti.',
            'estimated_cost' => 450000,
            'payment_method' => 'QRIS',
        ]);

        $this->post(route('tracking.lookup.submit'), [
            'reference' => $service->service_number,
            'phone' => $service->phone,
        ])->assertRedirect(route('tracking.show', $service));

        $response = $this->get(route('tracking.receipt', $service));

        $response->assertOk()
            ->assertSee('NOTA PEMBAYARAN')
            ->assertSee('Rp 450.000')
            ->assertSee('QRIS')
            ->assertSee('onclick="window.print()"', false)
            ->assertDontSee('@if');
    }

    public function test_completed_customer_can_choose_a_star_rating(): void
    {
        $service = $this->createService(ServiceStatus::Completed);
        $this->post(route('tracking.lookup.submit'), [
            'reference' => $service->service_number,
            'phone' => $service->phone,
        ])->assertRedirect(route('tracking.show', $service));

        $this->get(route('tracking.show', $service))
            ->assertOk()
            ->assertSee('class="star-rating"', false)
            ->assertSee('name="rating" value="5"', false)
            ->assertSee('name="rating" value="1"', false)
            ->assertSee('Ulasan dapat ditampilkan sebagai testimoni di halaman utama dengan nama depan saja.');
    }

    public function test_homepage_shows_completed_customer_ratings_and_comment_testimonials(): void
    {
        $completedService = $this->createService(ServiceStatus::Completed);
        $completedService->feedback()->create(['rating' => 5, 'comment' => 'Pelayanan sangat ramah.']);
        $anotherCompletedService = $this->createService(ServiceStatus::Completed);
        $anotherCompletedService->feedback()->create(['rating' => 4, 'comment' => 'Laptop kembali normal.']);
        $completedWithoutComment = $this->createService(ServiceStatus::Completed);
        $completedWithoutComment->feedback()->create(['rating' => 3, 'comment' => null]);
        $unfinishedService = $this->createService(ServiceStatus::WaitingApproval);
        $unfinishedService->feedback()->create(['rating' => 1, 'comment' => 'Ulasan servis belum selesai.']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Buat reservasi')
            ->assertSee('Lacak servis')
            ->assertSee('4,0')
            ->assertSee('3 rating pelanggan')
            ->assertSee('Pelayanan sangat ramah.')
            ->assertSee('Laptop kembali normal.')
            ->assertDontSee('Ulasan servis belum selesai.')
            ->assertDontSee('name="rating"', false);
    }

    public function test_technician_can_upload_a_diagnostic_image_for_the_customer_to_view(): void
    {
        Storage::fake('local');
        $technician = User::factory()->create(['role' => 'technician']);
        $service = $this->createService(ServiceStatus::Diagnosing);

        $this->actingAs($technician)
            ->get(route('staff.index'))
            ->assertOk()
            ->assertDontSee('Buat reservasi')
            ->assertDontSee('Lacak servis')
            ->assertSee('Panel staf')
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="diagnostic_image"', false);

        $this->post(route('staff.diagnose', $service), [
            'diagnosis' => 'Konektor daya terbakar.',
            'diagnostic_image' => UploadedFile::fake()->image('kerusakan.jpg'),
            'estimated_cost' => 450000,
            'estimated_days' => 2,
        ])
            ->assertRedirect();

        $service->refresh();
        $this->assertSame(ServiceStatus::WaitingApproval, $service->status);
        $this->assertNotNull($service->diagnostic_image_path);
        $this->assertTrue(Storage::disk('local')->exists($service->diagnostic_image_path));

        $imageUrl = route('tracking.diagnosis-image', $service);
        $this->get($imageUrl)->assertForbidden();

        $this->post(route('tracking.lookup.submit'), [
            'reference' => $service->service_number,
            'phone' => $service->phone,
        ])->assertRedirect(route('tracking.show', $service));

        $this->get(route('tracking.show', $service))
            ->assertOk()
            ->assertSee($imageUrl)
            ->assertSee('Foto kerusakan ASUS VivoBook 14');
        $this->get(route('tracking.receipt', $service))->assertOk()->assertSee($imageUrl);
        $this->get($imageUrl)->assertOk()->assertHeader('content-type', 'image/jpeg');
    }

    public function test_diagnostic_image_upload_rejects_non_image_files(): void
    {
        Storage::fake('local');
        $technician = User::factory()->create(['role' => 'technician']);
        $service = $this->createService(ServiceStatus::Diagnosing);

        $this->actingAs($technician)
            ->post(route('staff.diagnose', $service), [
                'diagnosis' => 'Konektor daya terbakar.',
                'diagnostic_image' => UploadedFile::fake()->create('diagnosis.pdf', 10, 'application/pdf'),
                'estimated_cost' => 450000,
                'estimated_days' => 2,
            ])
            ->assertInvalid(['diagnostic_image']);

        $this->assertSame(ServiceStatus::Diagnosing, $service->fresh()->status);
        $this->assertNull($service->fresh()->diagnostic_image_path);
    }

    public function test_admin_cannot_submit_a_diagnosis_or_view_the_diagnosis_form(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $service = $this->createService(ServiceStatus::Diagnosing);

        $this->actingAs($admin)
            ->get(route('staff.index'))
            ->assertOk()
            ->assertDontSee('Buat reservasi')
            ->assertDontSee('Lacak servis')
            ->assertSee('Panel staf')
            ->assertSee('Menunggu teknisi melakukan diagnosis.')
            ->assertDontSee('name="diagnosis"', false)
            ->assertDontSee('name="diagnostic_image"', false);

        $this->post(route('staff.diagnose', $service), [
            'diagnosis' => 'Konektor daya terbakar.',
            'estimated_cost' => 450000,
            'estimated_days' => 2,
        ])->assertForbidden();

        $this->assertSame(ServiceStatus::Diagnosing, $service->fresh()->status);
        $this->assertNull($service->fresh()->diagnosis);
    }

    public function test_admin_cannot_update_repair_progress_or_mark_a_service_ready(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $service = $this->createService(ServiceStatus::InProgress);

        $this->actingAs($admin)
            ->get(route('staff.index'))
            ->assertOk()
            ->assertSee('Menunggu teknisi memperbarui progres.')
            ->assertDontSee('name="progress_note"', false)
            ->assertDontSee('Selesai & siap diambil');

        $this->post(route('staff.progress', $service), [
            'progress_note' => 'Menunggu sparepart',
        ])->assertForbidden();

        $this->post(route('staff.ready', $service))->assertForbidden();

        $this->assertSame(ServiceStatus::InProgress, $service->fresh()->status);
        $this->assertNull($service->fresh()->progress_note);
    }

    public function test_admin_can_receive_a_unit_and_issue_a_service_number(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $service = $this->createService(ServiceStatus::WaitingQueue);

        $this->actingAs($admin)
            ->post(route('staff.receive', $service), ['serial_number' => 'SN-VERIFIED-1'])
            ->assertRedirect();

        $service->refresh();
        $this->assertSame(ServiceStatus::Diagnosing, $service->status);
        $this->assertSame('SN-VERIFIED-1', $service->serial_number);
        $this->assertNotNull($service->service_number);
        $this->assertNotNull($service->received_at);
        $this->get(route('staff.index'))->assertOk()->assertSee('Pendapatan bulan ini');
    }

    public function test_service_follows_the_full_approval_payment_and_feedback_workflow(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $technician = User::factory()->create(['role' => 'technician']);
        $service = $this->createService(ServiceStatus::WaitingQueue);

        $this->actingAs($admin)->post(route('staff.receive', $service), ['serial_number' => 'SN-VERIFIED-2'])->assertRedirect();
        $this->actingAs($technician)->post(route('staff.diagnose', $service), [
            'diagnosis' => 'Konektor daya perlu diganti.',
            'estimated_cost' => 450000,
            'estimated_days' => 2,
        ])->assertRedirect();

        $service->refresh();
        $this->assertSame(ServiceStatus::WaitingApproval, $service->status);
        $this->post(route('tracking.lookup.submit'), [
            'reference' => $service->service_number,
            'phone' => $service->phone,
        ])->assertRedirect(route('tracking.show', $service));
        $this->post(route('tracking.approve', $service))->assertRedirect();
        $this->actingAs($technician)
            ->post(route('staff.progress', $service), ['progress_note' => 'Menunggu sparepart'])
            ->assertRedirect();
        $this->post(route('staff.ready', $service))->assertRedirect();
        $this->actingAs($admin)
            ->post(route('staff.complete', $service), ['payment_method' => 'QRIS'])
            ->assertRedirect();

        $this->post(route('logout'));
        $this->post(route('tracking.lookup.submit'), [
            'reference' => $service->service_number,
            'phone' => $service->phone,
        ])->assertRedirect(route('tracking.show', $service));
        $this->post(route('tracking.feedback', $service), ['rating' => 5, 'comment' => 'Pelayanan baik.'])->assertRedirect();

        $service->refresh();
        $this->assertSame(ServiceStatus::Completed, $service->status);
        $this->assertSame('QRIS', $service->payment_method);
        $this->assertDatabaseHas('feedback', ['service_id' => $service->id, 'rating' => 5]);
    }

    public function test_technician_cannot_receive_the_unit_or_confirm_payment(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        $queued = $this->createService(ServiceStatus::WaitingQueue);
        $ready = $this->createService(ServiceStatus::ReadyForPickup);

        $this->actingAs($technician)
            ->post(route('staff.receive', $queued), ['serial_number' => 'SN-1'])
            ->assertForbidden();
        $this->post(route('staff.complete', $ready), ['payment_method' => 'Tunai'])
            ->assertForbidden();
    }

    public function test_staff_cannot_open_the_operations_dashboard(): void
    {
        $this->get(route('staff.index'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create(['role' => 'member']))
            ->get(route('staff.index'))
            ->assertForbidden();
    }

    public function test_admin_can_delete_queued_and_cancelled_services(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $queued = $this->createService(ServiceStatus::WaitingQueue);
        $cancelled = $this->createService(ServiceStatus::Cancelled);
        Storage::disk('local')->put('diagnostic-images/cancelled.jpg', 'image contents');
        $cancelled->update(['diagnostic_image_path' => 'diagnostic-images/cancelled.jpg']);

        $this->actingAs($admin)
            ->get(route('staff.index'))
            ->assertOk()
            ->assertSee('Hapus servis');

        $this->delete(route('staff.destroy', $queued))
            ->assertRedirect(route('staff.index'))
            ->assertSessionHas('success', 'Servis berhasil dihapus.');
        $this->delete(route('staff.destroy', $cancelled))->assertRedirect(route('staff.index'));

        $this->assertDatabaseMissing('services', ['id' => $queued->id]);
        $this->assertDatabaseMissing('services', ['id' => $cancelled->id]);
        Storage::disk('local')->assertMissing('diagnostic-images/cancelled.jpg');
    }

    public function test_admin_cannot_delete_a_service_after_processing_has_started(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $service = $this->createService(ServiceStatus::Diagnosing);

        $this->actingAs($admin)
            ->delete(route('staff.destroy', $service))
            ->assertConflict();

        $this->assertModelExists($service);
    }

    public function test_technician_cannot_delete_a_service(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        $service = $this->createService(ServiceStatus::WaitingQueue);

        $this->actingAs($technician)
            ->get(route('staff.index'))
            ->assertOk()
            ->assertDontSee('Hapus servis');

        $this->delete(route('staff.destroy', $service))->assertForbidden();

        $this->assertModelExists($service);
    }

    public function test_admin_can_download_a_daily_summary_csv_for_a_selected_date(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->travelTo('2026-10-14 12:00:00');
        $this->createService(ServiceStatus::WaitingQueue);
        $this->createService(ServiceStatus::Diagnosing);
        $completedService = $this->createService(ServiceStatus::Completed);
        $completedService->forceFill([
            'estimated_cost' => 50000,
            'completed_at' => '2026-10-14 11:00:00',
        ])->save();
        $this->travelTo('2026-10-15 12:00:00');
        $this->createService(ServiceStatus::WaitingQueue);

        $this->actingAs($admin)
            ->get(route('staff.index'))
            ->assertOk()
            ->assertSee('Ekspor ringkasan harian')
            ->assertSee('Ekspor ringkasan bulanan');

        $response = $this->get(route('staff.reports.daily', ['date' => '2026-10-14']));

        $response->assertDownload('ringkasan-harian-2026-10-14.csv');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('"Menunggu antrean",1', $csv);
        $this->assertStringContainsString('Diagnosis,1', $csv);
        $this->assertStringContainsString('"Servis selesai pada periode",1', $csv);
        $this->assertStringContainsString('"Pendapatan pada periode (Rp)",50000.00', $csv);
    }

    public function test_admin_can_download_a_monthly_summary_csv_for_a_selected_month(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->travelTo('2026-10-14 12:00:00');
        $this->createService(ServiceStatus::WaitingApproval);
        $completedService = $this->createService(ServiceStatus::Completed);
        $completedService->forceFill([
            'estimated_cost' => 50000,
            'completed_at' => '2026-10-14 11:00:00',
        ])->save();
        $this->travelTo('2026-09-30 12:00:00');
        $olderService = $this->createService(ServiceStatus::Completed);
        $olderService->forceFill([
            'estimated_cost' => 20000,
            'completed_at' => '2026-10-02 11:00:00',
        ])->save();

        $response = $this->actingAs($admin)
            ->get(route('staff.reports.monthly', ['month' => '2026-10']));

        $response->assertDownload('ringkasan-bulanan-2026-10.csv');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('"Menunggu persetujuan",1', $csv);
        $this->assertStringContainsString('"Servis selesai pada periode",2', $csv);
        $this->assertStringContainsString('"Pendapatan pada periode (Rp)",70000.00', $csv);
    }

    public function test_only_admins_can_export_and_export_periods_must_be_valid(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        $this->actingAs($technician)
            ->get(route('staff.reports.daily', ['date' => '2026-10-14']))
            ->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)
            ->get(route('staff.reports.daily', ['date' => '14-10-2026']))
            ->assertInvalid(['date']);
        $this->get(route('staff.reports.monthly', ['month' => '2026-13']))
            ->assertInvalid(['month']);
    }

    public function test_migrations_do_not_leave_unused_infrastructure_tables(): void
    {
        foreach (['cache', 'cache_locks', 'failed_jobs', 'job_batches', 'jobs', 'password_reset_tokens', 'sessions'] as $table) {
            $this->assertFalse(Schema::hasTable($table), "The {$table} table should not be required.");
        }
    }

    private function createService(ServiceStatus $status): Service
    {
        return Service::create([
            'queue_number' => Service::generateQueueNumber(),
            'service_number' => $status === ServiceStatus::WaitingQueue ? null : Service::generateServiceNumber(),
            'customer_name' => 'Dewi Putri',
            'phone' => '081234567890',
            'brand' => 'ASUS',
            'model' => 'VivoBook 14',
            'serial_number' => 'SN-ORIGINAL',
            'complaint' => 'Laptop tidak dapat mengisi daya.',
            'diagnosis' => $status === ServiceStatus::WaitingApproval ? 'Konektor daya perlu diganti.' : null,
            'estimated_cost' => $status === ServiceStatus::WaitingApproval ? 450000 : null,
            'estimated_days' => $status === ServiceStatus::WaitingApproval ? 2 : null,
            'status' => $status,
        ]);
    }
}
