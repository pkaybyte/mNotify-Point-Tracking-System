<!-- resources/views/emails/point-assigned.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Points Assigned</title>
    <link rel="preconnect" href="https://rsms.me/">
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css">
</head>
<body class="bg-gradient-to-br from-orange-50 to-white text-gray-800 dark:from-gray-900 dark:to-gray-950 dark:text-gray-100">
    <div class="max-w-xl mx-auto my-8 overflow-hidden rounded-xl shadow-lg bg-white dark:bg-gray-800">
        
        <!-- Header -->
        <div class="px-6 py-4 bg-orange-500 text-white dark:bg-orange-600">
            <h2 class="text-xl font-bold">
                {{ $isPositive ? '🎉' : '⚠' }} Points Assigned to You
            </h2>
        </div>

        <!-- Content -->
        <div class="px-6 py-6 text-sm leading-6">
            <p class="mb-4">Hello <span class="font-semibold">{{ $recipient->name }}</span>,</p>

            <p class="mb-4">
                You have received 
                <span class="{{ $isPositive ? 'text-green-600 dark:text-green-400 font-bold text-lg' : 'text-red-600 dark:text-red-400 font-bold text-lg' }}">
                    {{ $assignment->points > 0 ? '+' : '' }}{{ $assignment->points }} points
                </span>
            </p>

            <!-- Assignment Details -->
            <div class="mb-6 rounded-lg bg-gray-50 p-4 dark:bg-gray-700">
                <p class="mb-2 font-semibold">Assignment Details:</p>
                <ul class="space-y-1 text-gray-700 dark:text-gray-300">
                    <li><span class="font-semibold">From:</span> {{ $assignor->name }}</li>
                    <li><span class="font-semibold">Reason:</span> {{ $assignment->reason }}</li>
                    <li><span class="font-semibold">Date:</span> {{ $assignment->created_at->format('M d, Y \a\t h:i A') }}</li>
                    <li>
                        <span class="font-semibold">Status:</span>
                        <span class="inline-block rounded-md px-2 py-0.5 text-xs font-semibold
                            @if($assignment->status === 'pending') bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-200 
                            @elseif($assignment->status === 'verified') bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-200 
                            @else bg-gray-200 text-gray-700 dark:bg-gray-600 dark:text-gray-200 @endif">
                            {{ ucfirst($assignment->status) }}
                        </span>
                    </li>
                </ul>
            </div>

            <!-- Status Note -->
            @if($assignment->status === 'pending')
                <p class="italic text-gray-600 dark:text-gray-400">
                    Note: These points are pending supervisor verification and will be added to your total once approved.
                </p>
            @else
                <p class="italic text-gray-600 dark:text-gray-400">
                    These points have been automatically verified and added to your total.
                </p>
            @endif

            <!-- Button -->
            <div class="mt-6">
                <a href="{{ config('app.url') }}/dashboard" 
                   class="inline-flex items-center justify-center rounded-lg bg-orange-500 px-5 py-2 text-sm font-medium text-white transition hover:bg-orange-600 dark:bg-orange-600 dark:hover:bg-orange-700">
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