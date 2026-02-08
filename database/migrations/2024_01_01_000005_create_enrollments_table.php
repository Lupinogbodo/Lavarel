<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->string('enrollment_number')->unique();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['pending', 'active', 'completed', 'cancelled', 'expired'])->default('pending');
            $table->decimal('amount_paid', 10, 2);
            $table->decimal('discount_applied', 10, 2)->default(0);
            $table->string('coupon_code')->nullable();
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->integer('progress_percentage')->default(0);
            $table->json('custom_fields')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['student_id', 'course_id'], 'student_course_unique');
            $table->index(['status', 'enrolled_at']);
            $table->index('enrollment_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
