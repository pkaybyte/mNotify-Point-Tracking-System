<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\PointAssignment;
use App\Mail\AdminPointAssignedNotification;
use App\Mail\PointRejectedNotification;
use App\Events\PointAssigned;
use Illuminate\Support\Facades\Mail;

class TestEmailNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:emails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test email notifications for point assignments and rejections';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing Email Notifications...');

        // Get a test user (first user in database)
        $testUser = User::first();
        if (!$testUser) {
            $this->error('No users found in database! Please create at least one user first.');
            return Command::FAILURE;
        }

        // Create a test point assignment
        $testAssignment = PointAssignment::create([
            'assignor_id' => $testUser->id,
            'recipient_id' => $testUser->id,
            'points' => 5,
            'reason' => 'Test point assignment for email testing',
            'status' => 'verified',
            'verified_by' => $testUser->id,
            'verified_at' => now(),
        ]);

        $testAssignment->load(['assignor', 'recipient', 'verifier']);

        // Test 1: Admin notification email
        $this->info('Testing admin notification email...');
        try {
            Mail::to('hr@mnotify.com')->send(new AdminPointAssignedNotification($testAssignment));
            $this->info('✅ Admin notification email sent successfully!');
        } catch (\Exception $e) {
            $this->error('❌ Failed to send admin notification email: ' . $e->getMessage());
        }

        // Test 2: Create a rejected assignment for testing rejection emails
        $rejectedAssignment = PointAssignment::create([
            'assignor_id' => $testUser->id,
            'recipient_id' => $testUser->id,
            'points' => 3,
            'reason' => 'Test rejected point assignment',
            'status' => 'rejected',
            'verified_by' => $testUser->id,
            'verified_at' => now(),
            'rejection_reason' => 'This is a test rejection - the assignment did not meet the required criteria for approval.',
        ]);

        $rejectedAssignment->load(['assignor', 'recipient', 'verifier']);

        // Test 3: Rejection notification to assignor
        $this->info('Testing rejection notification email (assignor)...');
        try {
            Mail::to($testUser->email)->send(new PointRejectedNotification($rejectedAssignment, 'assignor'));
            $this->info('✅ Rejection notification email (assignor) sent successfully!');
        } catch (\Exception $e) {
            $this->error('❌ Failed to send rejection notification email (assignor): ' . $e->getMessage());
        }

        // Test 4: Rejection notification to recipient
        $this->info('Testing rejection notification email (recipient)...');
        try {
            Mail::to($testUser->email)->send(new PointRejectedNotification($rejectedAssignment, 'recipient'));
            $this->info('✅ Rejection notification email (recipient) sent successfully!');
        } catch (\Exception $e) {
            $this->error('❌ Failed to send rejection notification email (recipient): ' . $e->getMessage());
        }

        // Test 5: Event-based email (Point assigned event)
        $this->info('Testing event-based admin notification...');
        try {
            $eventTestAssignment = PointAssignment::create([
                'assignor_id' => $testUser->id,
                'recipient_id' => $testUser->id,
                'points' => 2,
                'reason' => 'Event-based test assignment',
                'status' => 'verified',
                'verified_by' => $testUser->id,
                'verified_at' => now(),
            ]);
            $eventTestAssignment->load(['assignor', 'recipient']);
            
            event(new PointAssigned($eventTestAssignment));
            $this->info('✅ Event-based notification triggered successfully!');
        } catch (\Exception $e) {
            $this->error('❌ Failed to trigger event-based notification: ' . $e->getMessage());
        }

        // Clean up test assignments
        $this->info('Cleaning up test data...');
        PointAssignment::whereIn('reason', [
            'Test point assignment for email testing',
            'Test rejected point assignment',
            'Event-based test assignment'
        ])->delete();

        $this->info('✅ Email notification testing completed!');
        $this->info('💡 Check your mail logs or inbox to verify emails were sent correctly.');
        
        return Command::SUCCESS;
    }
}
