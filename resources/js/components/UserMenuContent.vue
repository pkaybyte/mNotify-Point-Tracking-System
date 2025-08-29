<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import { DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import type { User } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { LogOut, Settings } from 'lucide-vue-next';
import { ref } from 'vue';

interface Props {
    user: User;
}

const isLoggingOut = ref(false);

const handleLogout = async (event: Event) => {
    // Prevent any default behavior and event bubbling
    event.preventDefault();
    event.stopPropagation();
    
    // Prevent multiple logout attempts
    if (isLoggingOut.value) {
        console.log('Logout already in progress - ignoring click');
        return;
    }
    
    console.log('Logout clicked - processing...');
    isLoggingOut.value = true;
    
    // Simple logout without CSRF refresh - let the backend handle it
    router.post(route('logout'), {}, {
        onStart: () => {
            console.log('Starting logout...');
        },
        onSuccess: () => {
            console.log('Logout successful - redirecting...');
            // Clear all Inertia cache and force redirect
            window.location.href = '/';
        },
        onError: (errors) => {
            console.error('Logout failed:', errors);
            
            // For any error (including 419), just redirect to home
            console.log('Logout error - forcing redirect to home');
            window.location.href = '/';
        },
        onFinish: () => {
            console.log('Logout request finished');
            isLoggingOut.value = false;
        }
    });
};

defineProps<Props>();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full" :href="route('profile.edit')" prefetch as="button">
                <Settings class="mr-2 h-4 w-4" />
                Settings
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem as-child>
        <button 
            @click="handleLogout" 
            :disabled="isLoggingOut"
            :class="[
                'flex w-full items-center px-2 py-1.5 text-sm rounded-sm',
                isLoggingOut 
                    ? 'cursor-not-allowed opacity-50 bg-gray-100' 
                    : 'cursor-pointer hover:bg-accent hover:text-accent-foreground'
            ]"
        >
            <LogOut class="mr-2 h-4 w-4" />
            <span v-if="!isLoggingOut">Log out</span>
            <span v-else>Logging out...</span>
        </button>
    </DropdownMenuItem>
</template>
