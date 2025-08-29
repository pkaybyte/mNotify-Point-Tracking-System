<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Log in - mNotify Point Tracking System">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <div class="flex min-h-screen flex-col items-center bg-gradient-to-br from-orange-50 to-white p-6 text-gray-800 lg:justify-center lg:p-8 dark:from-orange-950 dark:to-gray-900 dark:text-orange-50">
        

        <!-- Auth card -->
        <main class="flex w-full max-w-md flex-col overflow-hidden rounded-xl bg-white p-8 shadow-xl dark:bg-gray-800">
            <!-- Logo -->
            <div class="mb-6 flex items-center justify-center">
                <div class="flex items-center space-x-2">
                    <img 
                        src="/mnotify logo.jpg" 
                        alt="mNotify Logo" 
                        class="h-12 w-12 object-contain rounded-lg"
                    />
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-white">mNotify</h1>
                </div>
            </div>

            <!-- Title -->
            <h2 class="mb-2 text-center text-2xl font-bold text-orange-600 dark:text-orange-400">Log in to your account</h2>
            <p class="mb-6 text-center text-gray-600 dark:text-gray-300">
                Enter your email and password below to access your dashboard.
            </p>

            <!-- Status -->
            <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600">
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
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="email"
                        v-model="form.email"
                        placeholder="email@example.com"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-orange-500 focus:ring-orange-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <!-- Password -->
                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <label for="password" class="text-sm font-medium">Password</label>
                        <TextLink v-if="canResetPassword" :href="route('password.request')" class="text-sm text-orange-600 dark:text-orange-400 hover:underline" :tabindex="5">
                            Forgot password?
                        </TextLink>
                    </div>
                    <input
                        id="password"
                        type="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        v-model="form.password"
                        placeholder="Password"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-700 focus:border-orange-500 focus:ring-orange-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <!-- Remember me -->
                <label for="remember" class="flex items-center space-x-3 text-sm">
                    <input id="remember" type="checkbox" v-model="form.remember" :tabindex="3" class="rounded border-gray-300 text-orange-600 focus:ring-orange-500 dark:border-gray-600 dark:bg-gray-700" />
                    <span>Remember me</span>
                </label>

                <!-- Submit button -->
                <button
                    type="submit"
                    class="mt-4 inline-flex w-full items-center justify-center rounded-lg bg-orange-500 px-5 py-3 font-medium text-white transition-all hover:bg-orange-600 disabled:opacity-50 dark:bg-orange-600 dark:hover:bg-orange-700"
                    :tabindex="4"
                    :disabled="form.processing"
                >
                    <LoaderCircle v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                    Log in
                </button>
            </form>

        </main>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-500 dark:text-gray-400">
            <p>© {{ new Date().getFullYear() }} mNotify Ghana. All rights reserved.</p>
        </div>
    </div>
</template>