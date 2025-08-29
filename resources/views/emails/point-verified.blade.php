<!-- resources/views/emails/point-verified.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Points Verified</title>
    <link rel="preconnect" href="https://rsms.me/">
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css">
</head>
<body class="bg-gradient-to-br from-green-50 to-white text-gray-800 dark:from-gray-900 dark:to-gray-950 dark:text-gray-100">
    <div class="max-w-xl mx-auto my-8 overflow-hidden rounded-xl shadow-lg bg-white dark:bg-gray-800">
        
        <!-- Header -->
        <div class="px-6 py-4 bg-green-600 text-white dark:bg-green-700">
            <h2 class="text-xl font-bold">
                ✅ Points Verified & Totaled to Your Account
            </h2>
        </div>

        <!-- Content -->
        <div class="px-6 py-6 text-sm leading-6">
            <p class="mb-4">Hello <span class="font-semibold">{{ $recipient->name }}</span>,</p>

            <p class="mb-4">
                Good news! Your recent point assignment has been 
                <span class="text-green-600 dark:text-green-400 font-bold">verified</span> 
                by your supervisor and added to your total allocation.
            </p>

            <!-- Assignment Details -->
            <div class="mb-6 rounded-lg bg-gray-50 p-4 dark:bg-gray-700">
                <p class="mb-2 font-semibold">Assignment Details:</p>
                <ul class="space-y-1 text-gray-700 dark:text-gray-300">
                    <li><span class="font-semibold">Assigned By:</span> {{ $assignor->name }}</li>
                    <li><span class="font-semibold">Reason:</span> {{ $assignment->reason }}</li>
                    <li><span class="font-semibold">Points Awarded:</span> 
                        <span class="text-green-600 dark:text-green-400 font-bold">
                            +{{ $assignment->points }}
                        </span>
                    </li>
                    <li><span class="font-semibold">Verified On:</span> {{ now()->format('M d, Y \a\t h:i A') }}</li>
                    <li>
                        <span class="font-semibold">Status:</span>
                        <span class="inline-block rounded-md px-2 py-0.5 text-xs font-semibold bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-200">
                            Verified
                        </span>
                    </li>
                </ul>
            </div>

            <!-- Button -->
            <div class="mt-6">
                <a href="{{ config('app.url') }}/dashboard" 
                   class="inline-flex items-center justify-center rounded-lg bg-green-600 px-5 py-2 text-sm font-medium text-white transition hover:bg-green-700 dark:bg-green-700 dark:hover:bg-green-800">
                    View Your Updated Total
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 text-center text-xs text-gray-500 bg-gray-50 dark:bg-gray-900 dark:text-gray-400">
            <p>This is an automated email from the {{ config('app.name') }} Pointing System.</p>
            <p>If you did not expect this email, please contact support.</p>
        </div>
    </div>
</body>
</html>
