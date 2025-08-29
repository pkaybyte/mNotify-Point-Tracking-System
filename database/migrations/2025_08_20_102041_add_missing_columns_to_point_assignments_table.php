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
        Schema::table('point_assignments', function (Blueprint $table) {
            // Add the points column (this is the main one causing the error)
            $table->integer('points')->default(1)->after('recipient_id');
            
            // Add verification tracking columns
            $table->timestamp('verified_at')->nullable()->after('status');
            $table->unsignedBigInteger('verified_by')->nullable()->after('verified_at');
            
            // Add bulk assignment tracking
            $table->boolean('is_bulk_assignment')->default(false)->after('verified_by');
            
            // Add foreign key constraint for verified_by
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');
            
            // Add useful indexes
            $table->index(['status', 'created_at']);
            $table->index(['verified_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('point_assignments', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['verified_by']);
            
            // Drop indexes
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['verified_by']);
            
            // Drop the columns
            $table->dropColumn([
                'points',
                'verified_at', 
                'verified_by',
                'is_bulk_assignment'
            ]);
        });
    }
};