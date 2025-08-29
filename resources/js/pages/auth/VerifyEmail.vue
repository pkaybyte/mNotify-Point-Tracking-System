<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

defineProps<{
    status?: string;
}>();

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};
</script>

<template>
    <Head title="Verify Email - mNotify Point Tracking System">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <div class="flex min-h-screen flex-col items-center bg-gradient-to-br from-orange-50 to-white p-6 text-gray-800 lg:justify-center lg:p-8 dark:from-orange-950 dark:to-gray-900 dark:text-orange-50">

        <!-- Header navigation -->
        <header class="mb-6 w-full max-w-[335px] text-sm lg:max-w-4xl">
            <nav class="flex items-center justify-end gap-4">
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="inline-block rounded-lg border border-transparent px-5 py-1.5 text-sm leading-normal text-orange-600 transition-all hover:border-orange-300 hover:bg-orange-50 dark:text-orange-200 dark:hover:border-orange-700"
                >
                    Log out
                </Link>
            </nav>
        </header>

        <!-- Auth card -->
        <main class="flex w-full max-w-md flex-col overflow-hidden rounded-xl bg-white p-8 shadow-xl dark:bg-gray-800">
            <!-- Logo -->
            <div class="mb-6 flex items-center justify-center">
                <div class="flex items-center space-x-2">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-orange-500 text-xl font-bold text-white">
                        M
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-white">mNotify</h1>
                </div>
            </div>

            <!-- Title -->
            <h2 class="mb-2 text-center text-2xl font-bold text-orange-600 dark:text-orange-400">Verify your email</h2>
            <p class="mb-6 text-center text-gray-600 dark:text-gray-300">
                Please verify your email address by clicking on the link we just emailed to you.
            </p>

            <!-- Status -->
            <div v-if="status === 'verification-link-sent'" class="mb-4 rounded-lg bg-green-100 px-4 py-2 text-center text-sm font-medium text-green-700 dark:bg-green-900 dark:text-green-300">
                A new verification link has been sent to the email address you provided during registration.
            </div>

            <!-- Form -->
            <form @submit.prevent="submit" class="flex flex-col gap-6">
                <!-- Resend Button -->
                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center rounded-lg bg-orange-500 px-5 py-3 font-medium text-white transition-all hover:bg-orange-600 disabled:opacity-50 dark:bg-orange-600 dark:hover:bg-orange-700"
                    :disabled="form.processing"
                >
                    <LoaderCircle v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                    Resend verification email
                </button>
            </form>
        </main>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-500 dark:text-gray-400">
            <p>© {{ new Date().getFullYear() }} mNotify Ghana. All rights reserved.</p>
        </div>
    </div>
</template>