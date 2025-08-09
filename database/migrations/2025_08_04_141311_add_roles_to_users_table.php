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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['user', 'supervisor', 'admin'])->default('user');
            $table->integer('total_verified_points')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
        });
        
        // Migration: create_point_transactions_table
        Schema::create('point_transactions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('assignor_id')->constrained('users');
        $table->foreignId('recipient_id')->constrained('users');
        $table->integer('points');
        $table->text('reason');
        $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
        $table->foreignId('verified_by')->nullable()->constrained('users');
        $table->timestamp('verified_at')->nullable();
        $table->boolean('is_bulk_assignment')->default(false);
        $table->timestamps();
        $table->softDeletes(); // For audit trail
            
        });

        // Migration: create_audit_logs_table (NFR6.1)
        Schema::create('audit_logs', function (Blueprint $table) {
        $table->id();
        $table->string('action');
        $table->json('data');
        $table->foreignId('user_id')->constrained();
        $table->string('ip_address');
        $table->timestamps();
        });
    } 

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
