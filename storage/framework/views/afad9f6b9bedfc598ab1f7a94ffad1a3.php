<!-- resources/views/emails/point-assigned.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Points Assigned</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; }
        .header { background: #f8f9fa; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { padding: 20px; border: 1px solid #e9ecef; }
        .footer { background: #f8f9fa; padding: 15px; border-radius: 0 0 8px 8px; font-size: 12px; color: #6c757d; }
        .points-positive { color: #28a745; font-weight: bold; font-size: 18px; }
        .points-negative { color: #dc3545; font-weight: bold; font-size: 18px; }
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-verified { background: #d4edda; color: #155724; }
        .button { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h2><?php echo e($isPositive ? '🎉' : '⚠️'); ?> Points Assigned to You</h2>
    </div>
    
    <div class="content">
        <p>Hello <strong><?php echo e($recipient->name); ?></strong>,</p>
        
        <p>You have received 
            <span class="<?php echo e($isPositive ? 'points-positive' : 'points-negative'); ?>">
                <?php echo e($assignment->points > 0 ? '+' : ''); ?><?php echo e($assignment->points); ?> points
            </span>
        </p>
        
        <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 15px 0;">
            <p><strong>Assignment Details:</strong></p>
            <ul style="margin: 0; padding-left: 20px;">
                <li><strong>From:</strong> <?php echo e($assignor->name); ?></li>
                <li><strong>Reason:</strong> <?php echo e($assignment->reason); ?></li>
                <li><strong>Date:</strong> <?php echo e($assignment->created_at->format('M d, Y at h:i A')); ?></li>
                <li><strong>Status:</strong> 
                    <span class="status-badge status-<?php echo e($assignment->status); ?>">
                        <?php echo e(ucfirst($assignment->status)); ?>

                    </span>
                </li>
            </ul>
        </div>
        
        <?php if($assignment->status === 'pending'): ?>
            <p><em>Note: These points are pending supervisor verification and will be added to your total once approved.</em></p>
        <?php else: ?>
            <p><em>These points have been automatically verified and added to your total.</em></p>
        <?php endif; ?>
        
        <a href="<?php echo e(config('app.url')); ?>/dashboard" class="button">View Your Dashboard</a>
    </div>
    
    <div class="footer">
        <p>This is an automated email from the <?php echo e(config('app.name')); ?> Point Tracking System.</p>
        <p>You can manage your email preferences in your account settings.</p>
    </div>
</body>
</html><?php /**PATH C:\Users\lovkw\pointingsystem\point-tracking-system3\point-tracking-system\resources\views/emails/point-assigned.blade.php ENDPATH**/ ?>