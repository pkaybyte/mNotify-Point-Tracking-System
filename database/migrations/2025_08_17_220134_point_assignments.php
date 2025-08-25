<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::create('point_assignments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('assignor_id')->constrained('users');
        $table->foreignId('recipient_id')->constrained('users');
        $table->text('reason');
        $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
        $table->timestamps();
        
        $table->index(['recipient_id', 'status']);
        $table->index(['assignor_id', 'created_at']);
    });
}

public function down(): void
{
    Schema::dropIfExists('point_assignments');
}

};
