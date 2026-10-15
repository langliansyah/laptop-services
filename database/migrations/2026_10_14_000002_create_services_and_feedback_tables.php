<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('queue_number', 32)->unique();
            $table->string('service_number', 32)->nullable()->unique();
            $table->string('customer_name', 120);
            $table->string('phone', 25)->index();
            $table->string('brand', 80);
            $table->string('model', 120);
            $table->string('serial_number', 120)->nullable();
            $table->text('complaint');
            $table->text('diagnosis')->nullable();
            $table->decimal('estimated_cost', 12, 2)->nullable();
            $table->unsignedSmallInteger('estimated_days')->nullable();
            $table->string('progress_note', 160)->nullable();
            $table->string('status', 30)->default('waiting_queue')->index();
            $table->string('payment_method', 20)->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
        Schema::dropIfExists('services');
    }
};
