<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

defineProps<{
    status?: string;
}>();

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <Head title="Forgot Password - mNotify Point Tracking System">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <div class="flex min-h-screen flex-col items-center bg-gradient-to-br from-orange-50 to-white p-6 text-gray-800 lg:justify-center lg:p-8 dark:from-orange-950 dark:to-gray-900 dark:text-orange-50">

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
            <h2 class="mb-2 text-center text-2xl font-bold text-orange-600 dark:text-orange-400">Forgot your password?</h2>
            <p class="mb-6 text-center text-gray-600 dark:text-gray-300">
                Enter your email address below and we’ll send you a link to reset your password.
            </p>

            <!-- Status Message -->
            <div v-if="status" class="mb-4 rounded-md bg-green-100 px-4 py-2 text-center text-sm font-medium text-green-700 dark:bg-green-900 dark:text-green-300">
                {{ status }}
            </div>

            <!-- Form -->
            <form @submit.prevent="submit" class="flex flex-col gap-6">
                <!-- Email -->
                <div class="grid gap-2">
                    <label for="email" class="text-sm font-medium">Email address</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        v-model="form.email"
                        required
                        autofocus
                        autocomplete="off"
                        placeholder="email@example.com"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-orange-500 focus:ring-orange-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <!-- Submit -->
                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center rounded-lg bg-orange-500 px-5 py-3 font-medium text-white transition-all hover:bg-orange-600 disabled:opacity-50 dark:bg-orange-600 dark:hover:bg-orange-700"
                    :disabled="form.processing"
                >
                    <LoaderCircle v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                    Email password reset link
                </button>
            </form>

            <!-- Back to login -->
            <div class="mt-6 text-center text-sm text-gray-600 dark:text-gray-300">
                <span>Or return to </span>
                <a :href="route('login')" class="font-medium text-orange-600 hover:underline dark:text-orange-400">log in</a>
            </div>
        </main>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-500 dark:text-gray-400">
            <p>© {{ new Date().getFullYear() }} mNotify Ghana. All rights reserved.</p>
        </div>
    </div>
</template>