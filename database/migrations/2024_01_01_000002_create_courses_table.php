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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('title');
            $table->text('description');
            $table->string('slug')->unique();
            $table->decimal('price', 10, 2);
            $table->decimal('discount_price', 10, 2)->nullable();
            $table->enum('level', ['beginner', 'intermediate', 'advanced']);
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->integer('duration_hours')->default(0);
            $table->integer('max_students')->nullable();
            $table->integer('enrolled_count')->default(0);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->json('tags')->nullable();
            $table->json('prerequisites')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('status');
            $table->index('code');
            $table->fullText(['title', 'description']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
