<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import axios from 'axios';

const dateFilter = ref('month');
const startDate = ref('');
const endDate = ref('');
const sortDirection = ref('desc');
const users = ref([]);
const loading = ref(true);

const breadcrumbs = [
    { title: 'Leaderboard', href: '/leaderboard' }
];

const filteredAndSortedUsers = computed(() => {
    return [...users.value].sort((a, b) => {
        const comparison = b.total_points - a.total_points;
        return sortDirection.value === 'desc' ? comparison : -comparison;
    });
});

const fetchUsers = async () => {
    try {
        const params = {};
        
        if (dateFilter.value === 'custom' && startDate.value && endDate.value) {
            params.start_date = startDate.value;
            params.end_date = endDate.value;
        } else {
            params.period = dateFilter.value;
        }
        
        const response = await axios.get('/api/leaderboard', { params });
        users.value = response.data;
    } catch (error) {
        console.error('Error fetching leaderboard:', error);
    } finally {
        loading.value = false;
    }
};

// Watch for filter changes
watch(dateFilter, () => {
    if (dateFilter.value !== 'custom') {
        fetchUsers();
    }
});

// Watch for custom date changes
watch([startDate, endDate], () => {
    if (dateFilter.value === 'custom' && startDate.value && endDate.value) {
        fetchUsers();
    }
});

onMounted(() => {
    fetchUsers();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Point Leaderboard" />
        
        <div class="p-6">
            <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Point Leaderboard</h1>
                
                <div class="flex flex-col sm:flex-row gap-4">
                    <div class="flex gap-4">
                        <select 
                            v-model="dateFilter"
                            class="border border-gray-300 dark:border-gray-600 rounded-lg px-4 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                            <option value="month">This Month</option>
                            <option value="quarter">This Quarter</option>
                            <option value="year">This Year</option>
                            <option value="custom">Custom Date Range</option>
                        </select>
                        
                        <button 
                            @click="sortDirection = sortDirection === 'desc' ? 'asc' : 'desc'"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                        >
                            {{ sortDirection === 'desc' ? '↓' : '↑' }}
                        </button>
                    </div>
                    
                    <!-- Custom Date Range Inputs -->
                    <div v-if="dateFilter === 'custom'" class="flex flex-col sm:flex-row gap-2 items-center">
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <input 
                                type="date" 
                                v-model="startDate"
                                placeholder="Start Date"
                                class="pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            />
                        </div>
                        <span class="text-gray-500 dark:text-gray-400">to</span>
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <input 
                                type="date" 
                                v-model="endDate"
                                placeholder="End Date"
                                class="pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="loading" class="text-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto mb-2"></div>
                <p class="text-gray-500 dark:text-gray-400">Loading leaderboard...</p>
            </div>

            <div v-else-if="filteredAndSortedUsers.length === 0" class="text-center py-8">
                <span class="text-4xl mb-4 block">🏆</span>
                <p class="text-gray-500 dark:text-gray-400">No users found for the selected period</p>
            </div>

            <div v-else class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr class="border-b border-gray-200 dark:border-gray-600">
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Rank</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Points</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                            <tr 
                                v-for="(user, index) in filteredAndSortedUsers" 
                                :key="user.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                            >
                                <td class="px-6 py-4">
                                    <span class="font-semibold text-gray-900 dark:text-gray-100">
                                        {{ index + 1 }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-medium text-gray-900 dark:text-gray-100">
                                        {{ user.name }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <span class="font-bold text-lg" :class="[
                                        user.total_points > 0 ? 'text-green-600 dark:text-green-400' : 
                                        user.total_points < 0 ? 'text-red-600 dark:text-red-400' : 
                                        'text-gray-600 dark:text-gray-400'
                                    ]">
                                        {{ user.total_points }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.animate-fade-in {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>