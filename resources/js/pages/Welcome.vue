<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

// Get user data from Inertia page props
const page = usePage();
const user = computed(() => page.props.auth?.user);

// Determine dashboard route based on user role
const dashboardRoute = computed(() => {
    //const userRole = user.value?.role;
    const userRole = (user.value as { role?: string })?.role;
    
    switch (userRole) {
        case 'admin':
            return '/admin-dashboard';
        case 'supervisor':
            return '/supervisor-dashboard';
        default:
            return '/dashboard';
    }
});
</script>

<template>
    <Head title="mNotify Point Tracking System">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>
    <div class="flex min-h-screen flex-col items-center bg-gradient-to-br from-orange-50 to-white p-6 text-gray-800 lg:justify-center lg:p-8 dark:from-orange-950 dark:to-gray-900 dark:text-orange-50">
        <header class="mb-6 w-full max-w-[335px] text-sm not-has-[nav]:hidden lg:max-w-4xl">
            <nav class="flex items-center justify-end gap-4">
                <Link
                    v-if="$page.props.auth.user"
                    :href="dashboardRoute"
                    class="inline-block rounded-lg border border-orange-200 bg-white px-5 py-1.5 text-sm leading-normal text-orange-600 transition-all hover:border-orange-400 hover:bg-orange-50 dark:border-orange-700 dark:bg-orange-900 dark:text-orange-100 dark:hover:border-orange-500"
                >
                    Dashboard
                </Link>
                <template v-else>
                    <Link
                        :href="route('login')"
                        class="inline-block rounded-lg border border-orange-500 bg-orange-500 px-5 py-1.5 text-sm leading-normal text-white transition-all hover:border-orange-600 hover:bg-orange-600 dark:border-orange-600 dark:bg-orange-600 dark:hover:border-orange-700 dark:hover:bg-orange-700"
                    >
                        Log in
                    </Link>
                </template>
            </nav>
        </header>
        <div class="flex w-full items-center justify-center opacity-100 transition-opacity duration-750 lg:grow starting:opacity-0">
            <main class="flex w-full max-w-[335px] flex-col-reverse overflow-hidden rounded-xl shadow-xl lg:max-w-4xl lg:flex-row">
                <div
                    class="flex-1 rounded-br-lg rounded-bl-lg bg-white p-6 pb-12 text-[13px] leading-[20px] lg:rounded-tl-lg lg:rounded-br-none lg:p-12 dark:bg-gray-800"
                >
                    <!-- Company Logo -->
                    <div class="mb-6 flex items-center justify-center lg:justify-start">
                        <div class="flex items-center space-x-2">
                            <img 
                                src="/mnotify logo.jpg" 
                                alt="mNotify Logo" 
                                class="h-12 w-12 object-contain rounded-lg"
                            />
                            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">mNotify</h1>
                        </div>
                    </div>
                    
                    <h1 class="mb-1 text-2xl font-bold text-orange-600 dark:text-orange-400">Point Tracking System</h1>
                    <p class="mb-6 text-gray-600 dark:text-gray-300">
                        Track, manage, and optimize your reward points with mNotify's comprehensive point management solution.
                    </p>
                    
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <Link
                            :href="route('login')"
                            class="inline-flex items-center justify-center rounded-lg bg-orange-500 px-5 py-3 font-medium text-white transition-all hover:bg-orange-600 dark:bg-orange-600 dark:hover:bg-orange-700"
                        >
                            Sign In
                            <svg xmlns="http://www.w3.org/2000/svg" class="ml-2 h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M12.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </Link>
                    </div>
                </div>
                
                <!-- Right column with point system visualization -->
                <div
                    class="relative -mb-px aspect-335/376 w-full shrink-0 overflow-hidden rounded-t-lg bg-gradient-to-br from-orange-500 to-amber-500 lg:mb-0 lg:-ml-px lg:aspect-auto lg:w-[438px] lg:rounded-t-none lg:rounded-r-lg"
                >
                    <div class="absolute inset-0 flex flex-col items-center justify-center p-8 text-white">
                        <div class="mb-6 text-center">
                            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-white/20">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                </svg>
                            </div>
                            <h2 class="text-2xl font-bold">Point Tracking System</h2>
                            <p class="mt-2 opacity-90">Powered by mNotify Ghana</p>
                        </div>
                        </div>
                        </div>
            </main>
        </div>
        <div class="mt-8 text-center text-sm text-gray-500 dark:text-gray-400">
            <p>© {{ new Date().getFullYear() }} mNotify Ghana. All rights reserved.</p>
        </div>
    </div>
</template>