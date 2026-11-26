<?php

namespace Tests\Feature;

use App\Enums\StatusServis;
use App\Models\Pengguna;
use App\Models\Servis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServiceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservation_form_has_a_separate_page_from_the_homepage(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('reservations.create'), false)
            ->assertDontSee('name="customer_name"', false);

        $this->get(route('reservations.create'))
            ->assertOk()
            ->assertSee('name="customer_name"', false)
            ->assertSee(route('reservations.store'), false);
    }

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

        $service = Servis::query()->firstOrFail();
        $response->assertRedirect(route('tracking.show', $service));
        $this->assertSame('6281234567890', $service->phone);
        $this->assertNull($service->service_number);
        $this->assertSame(StatusServis::WaitingQueue, $service->status);
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

        $member = Pengguna::query()->where('email', 'dewi@example.test')->firstOrFail();
        $this->assertSame('member', $member->role);

        $this->post(route('reservations.store'), [
            'customer_name' => 'Dewi Member',
            'phone' => '081234567890',
            'brand' => 'Lenovo',
            'model' => 'ThinkPad T14',
            'complaint' => 'Baterai cepat habis.',
        ])->assertRedirect();

        $service = Servis::query()->firstOrFail();
        $this->assertSame($member->id, $service->member_id);
        $this->get(route('account.history'))
            ->assertOk()
            ->assertSee($service->queue_number)
            ->assertSee('Reservasi')
            ->assertSee('Lacak')
            ->assertSee('Riwayat')
            ->assertDontSee('Panel');
        $this->get(route('tracking.show', $service))->assertOk();
    }

    public function test_admin_can_create_admin_and_technician_accounts(): void
    {
        $admin = Pengguna::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('staff.accounts'))
            ->assertOk()
            ->assertSee('Buat akun staf');

        foreach (['technician', 'admin'] as $role) {
            $email = $role.'-new@example.test';

            $this->post(route('staff.accounts.store'), [
                'name' => 'Staf Baru',
                'email' => $email,
                'phone' => '+62 812-3456-7890',
                'role' => $role,
                'password' => 'secure-pass-123',
                'password_confirmation' => 'secure-pass-123',
            ])->assertRedirect(route('staff.accounts'));

            $this->assertDatabaseHas('users', [
                'email' => $email,
                'phone' => '6281234567890',
                'role' => $role,
            ]);
            $account = Pengguna::query()->where('email', $email)->firstOrFail();
            $this->assertTrue(Hash::check('secure-pass-123', $account->password));
        }

        $this->get(route('staff.accounts'))
            ->assertOk()
            ->assertSee('technician-new@example.test')
            ->assertSee('admin-new@example.test');
    }

    public function test_only_admins_can_create_staff_accounts(): void
    {
        $technician = Pengguna::factory()->create(['role' => 'technician']);
        $this->actingAs($technician)
            ->get(route('staff.accounts'))
            ->assertForbidden();

        $this->post(route('staff.accounts.store'), [
            'name' => 'Akun Terlarang',
            'email' => 'forbidden@example.test',
            'phone' => '081234567890',
            'role' => 'admin',
            'password' => 'secure-pass-123',
            'password_confirmation' => 'secure-pass-123',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'forbidden@example.test']);
    }

    public function test_admin_cannot_create_a_member_using_staff_account_form(): void
    {
        $admin = Pengguna::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('staff.accounts.store'), [
                'name' => 'Akun Member',
                'email' => 'member-staff-form@example.test',
                'phone' => '081234567890',
                'role' => 'member',
                'password' => 'secure-pass-123',
                'password_confirmation' => 'secure-pass-123',
            ])
            ->assertInvalid(['role']);

        $this->assertDatabaseMissing('users', ['email' => 'member-staff-form@example.test']);
    }

    public function test_admin_can_update_staff_account_details_role_and_optional_password(): void
    {
        $admin = Pengguna::factory()->create(['role' => 'admin']);
        $technician = Pengguna::factory()->create(['role' => 'technician']);

        $this->actingAs($admin)
            ->get(route('staff.accounts.edit', $technician))
            ->assertOk()
            ->assertSee($technician->email);

        $this->put(route('staff.accounts.update', $technician), [
            'name' => 'Teknisi Diperbarui',
            'email' => 'updated-technician@example.test',
            'phone' => '+62 812-3456-7890',
            'role' => 'admin',
            'password' => '',
            'password_confirmation' => '',
        ])->assertRedirect(route('staff.accounts'));

        $updated = $technician->fresh();
        $this->assertSame('Teknisi Diperbarui', $updated->name);
        $this->assertSame('updated-technician@example.test', $updated->email);
        $this->assertSame('6281234567890', $updated->phone);
        $this->assertSame('admin', $updated->role);
        $this->assertTrue(Hash::check('password', $updated->password));
    }

    public function test_admin_can_delete_other_technician_and_admin_accounts(): void
    {
        $admin = Pengguna::factory()->create(['role' => 'admin']);
        $technician = Pengguna::factory()->create(['role' => 'technician']);
        $otherAdmin = Pengguna::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->delete(route('staff.accounts.destroy', $technician))
            ->assertRedirect(route('staff.accounts'));
        $this->delete(route('staff.accounts.destroy', $otherAdmin))
            ->assertRedirect(route('staff.accounts'));

        $this->assertDatabaseMissing('users', ['id' => $technician->id]);
        $this->assertDatabaseMissing('users', ['id' => $otherAdmin->id]);
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin']);
    }

    public function test_admin_cannot_downgrade_or_delete_their_own_last_admin_account(): void
    {
        $admin = Pengguna::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->put(route('staff.accounts.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'phone' => '081234567890',
                'role' => 'technician',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertInvalid(['role']);

        $this->delete(route('staff.accounts.destroy', $admin))
            ->assertInvalid(['account']);

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin']);
    }

    public function test_only_admins_can_edit_or_delete_staff_accounts(): void
    {
        $admin = Pengguna::factory()->create(['role' => 'admin']);
        $technician = Pengguna::factory()->create(['role' => 'technician']);
        $otherTechnician = Pengguna::factory()->create(['role' => 'technician']);

        $this->actingAs($technician)
            ->get(route('staff.accounts.edit', $otherTechnician))
            ->assertForbidden();

        $this->put(route('staff.accounts.update', $otherTechnician), [
            'name' => 'Tidak Diubah',
            'email' => $otherTechnician->email,
            'phone' => $otherTechnician->phone,
            'role' => 'admin',
            'password' => '',
            'password_confirmation' => '',
        ])->assertForbidden();
        $this->delete(route('staff.accounts.destroy', $otherTechnician))->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $otherTechnician->id, 'role' => 'technician']);
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin']);
    }

    public function test_customer_must_be_tracked_before_approving_and_can_only_approve_a_diagnosis(): void
    {
        $service = $this->createService(StatusServis::WaitingApproval);

        $this->post(route('tracking.approve', $service))->assertForbidden();
        $this->post(route('tracking.lookup.submit'), [
            'reference' => $service->service_number,
            'phone' => '(0812) 3456-7890',
        ])->assertRedirect(route('tracking.show', $service));
        $this->post(route('tracking.approve', $service))->assertRedirect();

        $this->assertSame(StatusServis::InProgress, $service->fresh()->status);
    }

    public function test_customer_can_open_and_print_a_completed_service_receipt(): void
    {
        $service = $this->createService(StatusServis::Completed);
        $service->diagnostic()->create([
            'diagnosis' => 'Konektor daya telah diganti.',
            'estimated_cost' => 450000,
            'estimated_days' => 2,
        ]);
        $service->payment()->create([
            'amount' => 450000,
            'method' => 'QRIS',
            'paid_at' => now(),
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
        $service = $this->createService(StatusServis::Completed);
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
        $completedService = $this->createService(StatusServis::Completed);
        $completedService->feedback()->create(['rating' => 5, 'comment' => 'Pelayanan sangat ramah.']);
        $anotherCompletedService = $this->createService(StatusServis::Completed);
        $anotherCompletedService->feedback()->create(['rating' => 4, 'comment' => 'Laptop kembali normal.']);
        $completedWithoutComment = $this->createService(StatusServis::Completed);
        $completedWithoutComment->feedback()->create(['rating' => 3, 'comment' => null]);
        $unfinishedService = $this->createService(StatusServis::WaitingApproval);
        $unfinishedService->feedback()->create(['rating' => 1, 'comment' => 'Ulasan servis belum selesai.']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Reservasi')
            ->assertSee('Lacak')
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
        $technician = Pengguna::factory()->create(['role' => 'technician']);
        $service = $this->createService(StatusServis::Diagnosing);

        $this->actingAs($technician)
            ->get(route('staff.index'))
            ->assertOk()
            ->assertDontSee('Reservasi')
            ->assertDontSee('Lacak')
            ->assertSee('Panel')
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
        $this->assertSame(StatusServis::WaitingApproval, $service->status);
        $diagnosticImagePath = $service->diagnostic->diagnostic_image_path;
        $this->assertNotNull($diagnosticImagePath);
        $this->assertTrue(Storage::disk('local')->exists($diagnosticImagePath));

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
        $technician = Pengguna::factory()->create(['role' => 'technician']);
        $service = $this->createService(StatusServis::Diagnosing);

        $this->actingAs($technician)
            ->post(route('staff.diagnose', $service), [
                'diagnosis' => 'Konektor daya terbakar.',
                'diagnostic_image' => UploadedFile::fake()->create('diagnosis.pdf', 10, 'application/pdf'),
                'estimated_cost' => 450000,
                'estimated_days' => 2,
            ])
            ->assertInvalid(['diagnostic_image']);

        $this->assertSame(StatusServis::Diagnosing, $service->fresh()->status);
        $this->assertNull($service->fresh()->diagnostic?->diagnostic_image_path);
    }

    public function test_admin_cannot_submit_a_diagnosis_or_view_the_diagnosis_form(): void
    {
        $admin = Pengguna::factory()->create(['role' => 'admin']);
        $service = $this->createService(StatusServis::Diagnosing);

        $this->actingAs($admin)
            ->get(route('staff.index'))
            ->assertOk()
            ->assertDontSee('Reservasi')
            ->assertDontSee('Lacak')
            ->assertSee('Panel')
            ->assertSee('Menunggu teknisi melakukan diagnosis.')
            ->assertDontSee('name="diagnosis"', false)
            ->assertDontSee('name="diagnostic_image"', false);

        $this->post(route('staff.diagnose', $service), [
            'diagnosis' => 'Konektor daya terbakar.',
            'estimated_cost' => 450000,
            'estimated_days' => 2,
        ])->assertForbidden();

        $this->assertSame(StatusServis::Diagnosing, $service->fresh()->status);
        $this->assertNull($service->fresh()->diagnostic);
    }

    public function test_admin_cannot_update_repair_progress_or_mark_a_service_ready(): void
    {
        $admin = Pengguna::factory()->create(['role' => 'admin']);
        $service = $this->createService(StatusServis::InProgress);

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

        $this->assertSame(StatusServis::InProgress, $service->fresh()->status);
        $this->assertDatabaseCount('reservation_progress_notes', 0);
    }

    public function test_admin_can_receive_a_unit_and_issue_a_service_number(): void
    {
        $admin = Pengguna::factory()->create(['role' => 'admin']);
        $service = $this->createService(StatusServis::WaitingQueue);

        $this->actingAs($admin)
            ->post(route('staff.receive', $service), ['serial_number' => 'SN-VERIFIED-1'])
            ->assertRedirect();

        $service->refresh();
        $this->assertSame(StatusServis::Diagnosing, $service->status);
        $this->assertSame('SN-VERIFIED-1', $service->serial_number);
        $this->assertNotNull($service->service_number);
        $this->assertNotNull($service->received_at);
        $this->get(route('staff.queue'))
            ->assertOk()
            ->assertDontSee('staff-tabs')
            ->assertSee(route('staff.finance'), false)
            ->assertSee(route('staff.accounts'), false);
        $this->get(route('staff.finance'))->assertOk()->assertSee('Pendapatan bulan ini');
    }

    public function test_service_follows_the_full_approval_payment_and_feedback_workflow(): void
    {
        $admin = Pengguna::factory()->create(['role' => 'admin']);
        $technician = Pengguna::factory()->create(['role' => 'technician']);
        $service = $this->createService(StatusServis::WaitingQueue);

        $this->actingAs($admin)->post(route('staff.receive', $service), ['serial_number' => 'SN-VERIFIED-2'])->assertRedirect();
        $this->actingAs($technician)->post(route('staff.diagnose', $service), [
            'diagnosis' => 'Konektor daya perlu diganti.',
            'estimated_cost' => 450000,
            'estimated_days' => 2,
        ])->assertRedirect();

        $service->refresh();
        $this->assertSame(StatusServis::WaitingApproval, $service->status);
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
        $this->assertSame(StatusServis::Completed, $service->status);
        $this->assertSame('QRIS', $service->payment->method);
        $this->assertSame('Konektor daya perlu diganti.', $service->diagnostic->diagnosis);
        $this->assertSame([
            'Perbaikan selesai dan telah lolos pemeriksaan kualitas.',
            'Menunggu sparepart',
            'Persetujuan biaya diterima.',
        ], $service->progressNotes()->orderByDesc('id')->pluck('note')->all());
        $this->assertDatabaseHas('payments', [
            'reservation_id' => $service->id,
            'amount' => 450000,
            'method' => 'QRIS',
        ]);
        $this->assertDatabaseHas('feedback', ['reservation_id' => $service->id, 'rating' => 5]);
    }

    public function test_technician_cannot_receive_the_unit_or_confirm_payment(): void
    {
        $technician = Pengguna::factory()->create(['role' => 'technician']);
        $queued = $this->createService(StatusServis::WaitingQueue);
        $ready = $this->createService(StatusServis::ReadyForPickup);

        $this->actingAs($technician)
            ->post(route('staff.receive', $queued), ['serial_number' => 'SN-1'])
            ->assertForbidden();
        $this->post(route('staff.complete', $ready), ['payment_method' => 'Tunai'])
            ->assertForbidden();
    }

    public function test_staff_cannot_open_the_operations_dashboard(): void
    {
        $this->get(route('staff.index'))->assertRedirect(route('login'));

        $this->actingAs(Pengguna::factory()->create(['role' => 'member']))
            ->get(route('staff.index'))
            ->assertForbidden();
    }

    public function test_admin_can_delete_queued_and_cancelled_services(): void
    {
        Storage::fake('local');
        $admin = Pengguna::factory()->create(['role' => 'admin']);
        $queued = $this->createService(StatusServis::WaitingQueue);
        $cancelled = $this->createService(StatusServis::Cancelled);
        Storage::disk('local')->put('diagnostic-images/cancelled.jpg', 'image contents');
        $cancelled->diagnostic()->create([
            'diagnosis' => 'Kerusakan yang dibatalkan.',
            'diagnostic_image_path' => 'diagnostic-images/cancelled.jpg',
        ]);

        $this->actingAs($admin)
            ->get(route('staff.index'))
            ->assertOk()
            ->assertSee('Hapus servis');

        $this->delete(route('staff.destroy', $queued))
            ->assertRedirect(route('staff.index'))
            ->assertSessionHas('success', 'Servis berhasil dihapus.');
        $this->delete(route('staff.destroy', $cancelled))->assertRedirect(route('staff.index'));

        $this->assertDatabaseMissing('reservations', ['id' => $queued->id]);
        $this->assertDatabaseMissing('reservations', ['id' => $cancelled->id]);
        Storage::disk('local')->assertMissing('diagnostic-images/cancelled.jpg');
    }

    public function test_admin_cannot_delete_a_service_after_processing_has_started(): void
    {
        $admin = Pengguna::factory()->create(['role' => 'admin']);
        $service = $this->createService(StatusServis::Diagnosing);

        $this->actingAs($admin)
            ->delete(route('staff.destroy', $service))
            ->assertConflict();

        $this->assertModelExists($service);
    }

    public function test_technician_cannot_delete_a_service(): void
    {
        $technician = Pengguna::factory()->create(['role' => 'technician']);
        $service = $this->createService(StatusServis::WaitingQueue);

        $this->actingAs($technician)
            ->get(route('staff.index'))
            ->assertOk()
            ->assertDontSee('Hapus servis');

        $this->delete(route('staff.destroy', $service))->assertForbidden();

        $this->assertModelExists($service);
    }

    public function test_admin_can_download_a_daily_summary_csv_for_a_selected_date(): void
    {
        $admin = Pengguna::factory()->create(['role' => 'admin']);
        $this->travelTo('2026-10-14 12:00:00');
        $this->createService(StatusServis::WaitingQueue);
        $this->createService(StatusServis::Diagnosing);
        $completedService = $this->createService(StatusServis::Completed);
        $completedService->diagnostic()->create([
            'estimated_cost' => 50000,
        ]);
        $completedService->update(['completed_at' => '2026-10-14 11:00:00']);
        $completedService->payment()->create([
            'amount' => 50000,
            'method' => 'Tunai',
            'paid_at' => '2026-10-14 11:00:00',
        ]);
        $this->travelTo('2026-10-15 12:00:00');
        $this->createService(StatusServis::WaitingQueue);

        $this->actingAs($admin)
            ->get(route('staff.finance'))
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
        $admin = Pengguna::factory()->create(['role' => 'admin']);
        $this->travelTo('2026-10-14 12:00:00');
        $this->createService(StatusServis::WaitingApproval);
        $completedService = $this->createService(StatusServis::Completed);
        $completedService->diagnostic()->create([
            'estimated_cost' => 50000,
        ]);
        $completedService->update(['completed_at' => '2026-10-14 11:00:00']);
        $completedService->payment()->create([
            'amount' => 50000,
            'method' => 'QRIS',
            'paid_at' => '2026-10-14 11:00:00',
        ]);
        $this->travelTo('2026-09-30 12:00:00');
        $olderService = $this->createService(StatusServis::Completed);
        $olderService->diagnostic()->create([
            'estimated_cost' => 20000,
        ]);
        $olderService->update(['completed_at' => '2026-10-02 11:00:00']);
        $olderService->payment()->create([
            'amount' => 20000,
            'method' => 'Transfer',
            'paid_at' => '2026-10-02 11:00:00',
        ]);

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
        $technician = Pengguna::factory()->create(['role' => 'technician']);
        $this->actingAs($technician)
            ->get(route('staff.reports.daily', ['date' => '2026-10-14']))
            ->assertForbidden();

        $admin = Pengguna::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)
            ->get(route('staff.reports.daily', ['date' => '14-10-2026']))
            ->assertInvalid(['date']);
        $this->get(route('staff.reports.monthly', ['month' => '2026-13']))
            ->assertInvalid(['month']);
    }

    public function test_migrations_create_service_tables_and_drop_unused_infrastructure_tables(): void
    {
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('reservations'));
        $this->assertTrue(Schema::hasTable('feedback'));
        $this->assertTrue(Schema::hasTable('payments'));
        $this->assertTrue(Schema::hasTable('reservation_diagnostics'));
        $this->assertTrue(Schema::hasTable('reservation_progress_notes'));
        $this->assertFalse(Schema::hasTable('pengguna'));
        $this->assertFalse(Schema::hasTable('servis'));
        $this->assertFalse(Schema::hasTable('ulasan'));
        $this->assertFalse(Schema::hasTable('pembayaran'));
        $this->assertFalse(Schema::hasTable('services'));
        $this->assertTrue(Schema::hasColumn('users', 'password'));
        $this->assertTrue(Schema::hasColumn('reservations', 'queue_number'));
        $this->assertTrue(Schema::hasColumn('feedback', 'reservation_id'));
        $this->assertTrue(Schema::hasColumn('payments', 'paid_at'));
        foreach (['diagnosis', 'diagnostic_image_path', 'estimated_cost', 'estimated_days', 'progress_note'] as $column) {
            $this->assertFalse(Schema::hasColumn('reservations', $column));
        }

        foreach (['cache', 'cache_locks', 'failed_jobs', 'job_batches', 'jobs', 'password_reset_tokens', 'sessions'] as $table) {
            $this->assertFalse(Schema::hasTable($table), "The {$table} table should not be required.");
        }
    }

    private function createService(StatusServis $status): Servis
    {
        $service = Servis::create([
            'queue_number' => Servis::generateQueueNumber(),
            'service_number' => $status === StatusServis::WaitingQueue ? null : Servis::generateServiceNumber(),
            'customer_name' => 'Dewi Putri',
            'phone' => '081234567890',
            'brand' => 'ASUS',
            'model' => 'VivoBook 14',
            'serial_number' => 'SN-ORIGINAL',
            'complaint' => 'Laptop tidak dapat mengisi daya.',
            'status' => $status,
        ]);

        if ($status === StatusServis::WaitingApproval) {
            $service->diagnostic()->create([
                'diagnosis' => 'Konektor daya perlu diganti.',
                'estimated_cost' => 450000,
                'estimated_days' => 2,
            ]);
        }

        return $service;
    }
}
