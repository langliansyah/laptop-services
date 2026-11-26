<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reservation_diagnostics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('diagnosis')->nullable();
            $table->string('diagnostic_image_path')->nullable();
            $table->decimal('estimated_cost', 12, 2)->nullable();
            $table->unsignedSmallInteger('estimated_days')->nullable();
            $table->timestamps();
        });

        Schema::create('reservation_progress_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->string('note', 160);
            $table->timestamps();
        });

        DB::table('reservations')
            ->where(function ($query): void {
                $query->whereNotNull('diagnosis')
                    ->orWhereNotNull('diagnostic_image_path')
                    ->orWhereNotNull('estimated_cost')
                    ->orWhereNotNull('estimated_days');
            })
            ->orderBy('id')
            ->chunkById(100, function ($reservations): void {
                foreach ($reservations as $reservation) {
                    DB::table('reservation_diagnostics')->insert([
                        'reservation_id' => $reservation->id,
                        'diagnosis' => $reservation->diagnosis,
                        'diagnostic_image_path' => $reservation->diagnostic_image_path,
                        'estimated_cost' => $reservation->estimated_cost,
                        'estimated_days' => $reservation->estimated_days,
                        'created_at' => $reservation->created_at,
                        'updated_at' => $reservation->updated_at,
                    ]);
                }
            });

        DB::table('reservations')
            ->whereNotNull('progress_note')
            ->orderBy('id')
            ->chunkById(100, function ($reservations): void {
                foreach ($reservations as $reservation) {
                    DB::table('reservation_progress_notes')->insert([
                        'reservation_id' => $reservation->id,
                        'note' => $reservation->progress_note,
                        'created_at' => $reservation->updated_at,
                        'updated_at' => $reservation->updated_at,
                    ]);
                }
            });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn([
                'diagnosis',
                'diagnostic_image_path',
                'estimated_cost',
                'estimated_days',
                'progress_note',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $hasMultipleProgressNotes = DB::table('reservation_progress_notes')
            ->select('reservation_id')
            ->groupBy('reservation_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasMultipleProgressNotes) {
            throw new RuntimeException('Cannot roll back because reservation progress history cannot fit in a single column.');
        }

        Schema::table('reservations', function (Blueprint $table) {
            $table->text('diagnosis')->nullable();
            $table->string('diagnostic_image_path')->nullable();
            $table->decimal('estimated_cost', 12, 2)->nullable();
            $table->unsignedSmallInteger('estimated_days')->nullable();
            $table->string('progress_note', 160)->nullable();
        });

        DB::table('reservation_diagnostics')
            ->chunkById(100, function ($diagnostics): void {
                foreach ($diagnostics as $diagnostic) {
                    DB::table('reservations')
                        ->where('id', $diagnostic->reservation_id)
                        ->update([
                            'diagnosis' => $diagnostic->diagnosis,
                            'diagnostic_image_path' => $diagnostic->diagnostic_image_path,
                            'estimated_cost' => $diagnostic->estimated_cost,
                            'estimated_days' => $diagnostic->estimated_days,
                        ]);
                }
            }, 'id');

        DB::table('reservation_progress_notes')
            ->orderBy('reservation_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->chunk(100, function ($notes): void {
                foreach ($notes as $note) {
                    DB::table('reservations')
                        ->where('id', $note->reservation_id)
                        ->update(['progress_note' => $note->note]);
                }
            });

        Schema::dropIfExists('reservation_progress_notes');
        Schema::dropIfExists('reservation_diagnostics');
    }
};
