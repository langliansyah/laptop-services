<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $paidReservationsWithoutEstimate = DB::table('services')
            ->whereNotNull('payment_method')
            ->whereNull('estimated_cost')
            ->exists();

        if ($paidReservationsWithoutEstimate) {
            throw new \RuntimeException('Cannot migrate payments because a completed service has no estimated amount.');
        }

        Schema::rename('services', 'reservations');

        Schema::table('feedback', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
        });

        Schema::table('feedback', function (Blueprint $table) {
            $table->renameColumn('service_id', 'reservation_id');
        });

        Schema::table('feedback', function (Blueprint $table) {
            $table->foreign('reservation_id')->references('id')->on('reservations')->cascadeOnDelete();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 20);
            $table->timestamp('paid_at')->index();
            $table->timestamps();
        });

        DB::table('reservations')
            ->whereNotNull('payment_method')
            ->orderBy('id')
            ->chunkById(100, function ($reservations): void {
                foreach ($reservations as $reservation) {
                    $paidAt = $reservation->completed_at ?? $reservation->updated_at;

                    DB::table('payments')->insert([
                        'reservation_id' => $reservation->id,
                        'amount' => $reservation->estimated_cost,
                        'method' => $reservation->payment_method,
                        'paid_at' => $paidAt,
                        'created_at' => $reservation->created_at,
                        'updated_at' => $reservation->updated_at,
                    ]);
                }
            });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('payment_method', 20)->nullable();
        });

        DB::table('payments')
            ->orderBy('id')
            ->chunkById(100, function ($payments): void {
                foreach ($payments as $payment) {
                    DB::table('reservations')
                        ->where('id', $payment->reservation_id)
                        ->update(['payment_method' => $payment->method]);
                }
            });

        Schema::dropIfExists('payments');

        Schema::table('feedback', function (Blueprint $table) {
            $table->dropForeign(['reservation_id']);
        });

        Schema::rename('reservations', 'services');

        Schema::table('feedback', function (Blueprint $table) {
            $table->renameColumn('reservation_id', 'service_id');
        });

        Schema::table('feedback', function (Blueprint $table) {
            $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
        });
    }
};
