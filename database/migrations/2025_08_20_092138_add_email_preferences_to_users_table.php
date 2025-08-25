<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('email_on_point_received')->default(true);
            $table->boolean('email_on_point_verified')->default(true);
            $table->boolean('email_on_pending_points')->default(true); // For supervisors
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'email_on_point_received',
                'email_on_point_verified', 
                'email_on_pending_points'
            ]);
        });
    }
};