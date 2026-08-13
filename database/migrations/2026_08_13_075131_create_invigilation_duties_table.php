<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invigilation_duties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invigilator_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('course_codes');
            $table->string('room');
            $table->string('lecturer_name')->nullable();
            $table->unsignedInteger('student_count')->nullable();
            $table->string('student_count_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invigilation_duties');
    }
};