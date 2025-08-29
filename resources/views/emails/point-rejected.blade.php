<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Point Assignment Rejected</title>
    <link rel="preconnect" href="https://rsms.me/">
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css">
</head>
<body class="bg-gradient-to-br from-red-50 to-white text-gray-800 dark:from-gray-900 dark:to-gray-950 dark:text-gray-100">
    <div class="max-w-xl mx-auto my-8 overflow-hidden rounded-xl shadow-lg bg-white dark:bg-gray-800">
        
        <!-- Header -->
        <div class="px-6 py-4 bg-red-500 text-white dark:bg-red-600">
            <h2 class="text-xl font-bold">
                ❌ Point Assignment Rejected
            </h2>
        </div>

        <!-- Content -->
        <div class="px-6 py-6 text-sm leading-6">
            @if($userType === 'assignor')
                <p class="mb-4">Hello <span class="font-semibold">{{ $assignor->name }}</span>,</p>
                <p class="mb-4">
                    Your point assignment has been rejected by a supervisor.
                </p>
            @else
                <p class="mb-4">Hello <span class="font-semibold">{{ $recipient->name }}</span>,</p>
                <p class="mb-4">
                    A point assignment that was made to you has been rejected by a supervisor.
                </p>
            @endif

            <!-- Assignment Details -->
            <div class="mb-6 rounded-lg bg-gray-50 p-4 dark:bg-gray-700">
                <p class="mb-2 font-semibold">Rejected Assignment Details:</p>
                <ul class="space-y-1 text-gray-700 dark:text-gray-300">
                    <li><span class="font-semibold">Points:</span> 
                        <span class="text-red-600 dark:text-red-400 font-bold">
                            {{ $assignment->points > 0 ? '+' : '' }}{{ $assignment->points }}
                        </span>
                    </li>
                    <li><span class="font-semibold">From:</span> {{ $assignor->name }}</li>
                    <li><span class="font-semibold">To:</span> {{ $recipient->name }}</li>
                    <li><span class="font-semibold">Original Reason:</span> {{ $assignment->reason }}</li>
                    <li><span class="font-semibold">Date Assigned:</span> {{ $assignment->created_at->format('M d, Y \a\t h:i A') }}</li>
                    <li><span class="font-semibold">Rejected By:</span> {{ $verifier->name ?? 'Supervisor' }}</li>
                    <li><span class="font-semibold">Date Rejected:</span> {{ $assignment->verified_at->format('M d, Y \a\t h:i A') }}</li>
                </ul>
            </div>

            <!-- Rejection Reason -->
            @if($rejectionReason)
            <div class="mb-6 rounded-lg bg-red-50 p-4 border border-red-200 dark:bg-red-900/20 dark:border-red-800">
                <p class="mb-2 font-semibold text-red-800 dark:text-red-200">Reason for Rejection:</p>
                <p class="text-red-700 dark:text-red-300 italic">{{ $rejectionReason }}</p>
            </div>
            @endif

            <!-- Next Steps -->
            <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg dark:bg-blue-900/20 dark:border-blue-800">
                @if($userType === 'assignor')
                    <p class="text-blue-800 dark:text-blue-200 text-sm">
                        💡 <strong>What's Next:</strong> You can review the rejection reason and consider submitting a new point assignment with proper justification if appropriate.
                    </p>
                @else
                    <p class="text-blue-800 dark:text-blue-200 text-sm">
                        💡 <strong>Note:</strong> These points will not be added to your total. If you believe this rejection was made in error, please contact your supervisor.
                    </p>
                @endif
            </div>

            <!-- Button -->
            <div class="mt-6">
                <a href="{{ config('app.url') }}/dashboard" 
                   class="inline-flex items-center justify-center rounded-lg bg-red-500 px-5 py-2 text-sm font-medium text-white transition hover:bg-red-600 dark:bg-red-600 dark:hover:bg-red-700">
                    View Your Dashboard
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 text-center text-xs text-gray-500 bg-gray-50 dark:bg-gray-900 dark:text-gray-400">
            <p>This is an automated email from the {{ config('app.name') }} Point Tracking System.</p>
            <p>You can manage your email preferences in your account settings.</p>
        </div>
    </div>
</body>
</html>