<script setup>
import { onMounted, ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import axios from 'axios';

const breadcrumbs = [
    { title: 'Points History', href: '/points-history' }
];

// Data refs
const pointLogs = ref([]);
const currentUser = ref({ name: 'User' });
const loading = ref(true);

// Filter refs
const activeFilter = ref('all');
const dateFilter = ref('all-time');
const customStartDate = ref('');
const customEndDate = ref('');
const showCustomDatePicker = ref(false);
const searchQuery = ref('');

// Date presets for quick selection
const datePresets = [
    { label: 'All Time', value: 'all-time' },
    { label: 'Today', value: 'today' },
    { label: 'This Week', value: 'this-week' },
    { label: 'This Month', value: 'this-month' },
    { label: 'Last 7 Days', value: 'last-7-days' },
    { label: 'Last 30 Days', value: 'last-30-days' },
    { label: 'Custom Range', value: 'custom' }
];

// Computed filters
const filteredPointLogs = computed(() => {
    if (!pointLogs.value || pointLogs.value.length === 0) {
        return [];
    }
    
    let filtered = [...pointLogs.value];
    
    // Apply status/type filters
    switch (activeFilter.value) {
        case 'pending':
            filtered = filtered.filter(log => log.status?.toLowerCase() === 'pending');
            break;
        case 'verified':
            filtered = filtered.filter(log => log.status?.toLowerCase() === 'verified');
            break;
        case 'rejected':
            filtered = filtered.filter(log => log.status?.toLowerCase() === 'rejected');
            break;
        case 'positive':
            filtered = filtered.filter(log => Number(log.points) > 0);
            break;
        case 'negative':
            filtered = filtered.filter(log => Number(log.points) < 0);
            break;
        case 'assigned-by-me':
            filtered = filtered.filter(log => log.assignor_id === currentUser.value.id);
            break;
        case 'assigned-to-me':
            filtered = filtered.filter(log => log.recipient_id === currentUser.value.id);
            break;
        default:
            // 'all' - no additional filtering
            break;
    }
    
    // Apply date filters
    filtered = applyDateFilter(filtered);
    
    // Apply search filter
    if (searchQuery.value.trim()) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(log => 
            log.assignor_name?.toLowerCase().includes(query) ||
            log.recipient_name?.toLowerCase().includes(query) ||
            log.reason?.toLowerCase().includes(query) ||
            log.categories?.some(cat => cat.toLowerCase().includes(query))
        );
    }
    
    // Sort by newest first
    return filtered.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
});

const applyDateFilter = (logs) => {
    if (dateFilter.value === 'all-time') {
        return logs;
    }
    
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    
    return logs.filter(log => {
        const logDate = new Date(log.created_at);
        
        switch (dateFilter.value) {
            case 'today':
                return logDate >= today;
            case 'this-week':
                const weekStart = new Date(today);
                weekStart.setDate(today.getDate() - today.getDay());
                return logDate >= weekStart;
            case 'this-month':
                const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
                return logDate >= monthStart;
            case 'last-7-days':
                const sevenDaysAgo = new Date(today);
                sevenDaysAgo.setDate(today.getDate() - 7);
                return logDate >= sevenDaysAgo;
            case 'last-30-days':
                const thirtyDaysAgo = new Date(today);
                thirtyDaysAgo.setDate(today.getDate() - 30);
                return logDate >= thirtyDaysAgo;
            case 'custom':
                if (customStartDate.value && customEndDate.value) {
                    const startDate = new Date(customStartDate.value);
                    const endDate = new Date(customEndDate.value);
                    endDate.setHours(23, 59, 59, 999); // Include the end date
                    return logDate >= startDate && logDate <= endDate;
                }
                return true;
            default:
                return true;
        }
    });
};

// Statistics computed properties
const stats = computed(() => {
    const logs = filteredPointLogs.value;
    return {
        total: logs.length,
        verified: logs.filter(log => log.status === 'verified').length,
        pending: logs.filter(log => log.status === 'pending').length,
        rejected: logs.filter(log => log.status === 'rejected').length,
        positive: logs.filter(log => log.points > 0).length,
        negative: logs.filter(log => log.points < 0).length,
        assignedByMe: logs.filter(log => log.assignor_id === currentUser.value.id).length,
        assignedToMe: logs.filter(log => log.recipient_id === currentUser.value.id).length,
        totalPoints: logs.filter(log => log.status === 'verified').reduce((sum, log) => sum + log.points, 0)
    };
});

// Methods
const loadPointsHistory = async () => {
    try {
        loading.value = true;
        
        // Load current user
        try {
            const userRes = await axios.get('/api/users/current');
            currentUser.value = userRes.data;
        } catch (error) {
            console.error('Error loading current user:', error);
        }
        
        // Load point logs
        const response = await axios.get('/api/point-assignments/logs');
        pointLogs.value = response.data || [];
        
    } catch (error) {
        console.error('Error loading points history:', error);
        pointLogs.value = [];
    } finally {
        loading.value = false;
    }
};

const setFilter = (filter) => {
    activeFilter.value = filter;
};

const setDateFilter = (preset) => {
    dateFilter.value = preset.value;
    showCustomDatePicker.value = preset.value === 'custom';
    
    if (preset.value !== 'custom') {
        customStartDate.value = '';
        customEndDate.value = '';
    }
};

const applyCustomDateRange = () => {
    if (customStartDate.value && customEndDate.value) {
        if (new Date(customStartDate.value) > new Date(customEndDate.value)) {
            alert('Start date cannot be after end date');
            return;
        }
        dateFilter.value = 'custom';
    }
};

const clearDateFilter = () => {
    dateFilter.value = 'all-time';
    customStartDate.value = '';
    customEndDate.value = '';
    showCustomDatePicker.value = false;
};

const exportData = () => {
    const csvContent = "data:text/csv;charset=utf-8," 
        + "Date,Assignor,Recipient,Points,Status,Reason,Categories\n"
        + filteredPointLogs.value.map(log => 
            `"${formatDate(log.created_at)}","${log.assignor_name}","${log.recipient_name}",${log.points},"${log.status}","${log.reason}","${log.categories?.join(', ') || ''}"`
        ).join("\n");
    
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `points-history-${formatDate(new Date(), true)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

// Utility functions
const formatDate = (dateString, forFilename = false) => {
    const date = new Date(dateString);
    if (forFilename) {
        return date.toISOString().split('T')[0];
    }
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
};

const getRelativeTime = (dateString) => {
    const date = new Date(dateString);
    const now = new Date();
    const diffInHours = Math.floor((now - date) / (1000 * 60 * 60));
    
    if (diffInHours < 1) return 'Just now';
    if (diffInHours < 24) return `${diffInHours}h ago`;
    const diffInDays = Math.floor(diffInHours / 24);
    if (diffInDays < 7) return `${diffInDays}d ago`;
    return formatDate(dateString);
};

const getPointClass = (points) => {
    if (points > 0) {
        return points >= 5 ? 'text-green-700 font-bold' : 'text-green-600';
    } else {
        return points <= -3 ? 'text-red-700 font-bold' : 'text-red-600';
    }
};

const getStatusClass = (status) => {
    switch (status?.toLowerCase()) {
        case 'verified': return 'text-green-600 bg-green-100 dark:bg-green-900 dark:text-green-400';
        case 'pending': return 'text-yellow-600 bg-yellow-100 dark:bg-yellow-900 dark:text-yellow-400';
        case 'rejected': return 'text-red-600 bg-red-100 dark:bg-red-900 dark:text-red-400';
        default: return 'text-gray-600 bg-gray-100 dark:bg-gray-900 dark:text-gray-400';
    }
};

const getActionIcon = (points) => {
    if (points > 0) return '➕';
    if (points < 0) return '➖';
    return '📝';
};

// Initialize
onMounted(() => {
    loadPointsHistory();
});
</script>

<template>
    <Head title="Points History" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-2 sm:p-4">
            
            <!-- Header Section -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-300 dark:border-gray-700 p-6">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Points History</h1>
                        <p class="text-gray-600 dark:text-gray-400">Comprehensive view of all point assignments</p>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <button 
                            @click="loadPointsHistory"
                            :disabled="loading"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors flex items-center gap-2"
                        >
                            <span v-if="loading">🔄</span>
                            <span v-else>🔄</span>
                            Refresh
                        </button>
                        <button 
                            @click="exportData"
                            :disabled="filteredPointLogs.length === 0"
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 transition-colors flex items-center gap-2"
                        >
                            📊 Export CSV
                        </button>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3">
                    <div class="bg-blue-50 dark:bg-blue-900/20 p-3 rounded-lg">
                        <div class="text-sm text-blue-600 dark:text-blue-400">Total</div>
                        <div class="text-lg font-bold text-blue-800 dark:text-blue-200">{{ stats.total }}</div>
                    </div>
                    <div class="bg-green-50 dark:bg-green-900/20 p-3 rounded-lg">
                        <div class="text-sm text-green-600 dark:text-green-400">Verified</div>
                        <div class="text-lg font-bold text-green-800 dark:text-green-200">{{ stats.verified }}</div>
                    </div>
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 p-3 rounded-lg">
                        <div class="text-sm text-yellow-600 dark:text-yellow-400">Pending</div>
                        <div class="text-lg font-bold text-yellow-800 dark:text-yellow-200">{{ stats.pending }}</div>
                    </div>
                    <div class="bg-red-50 dark:bg-red-900/20 p-3 rounded-lg">
                        <div class="text-sm text-red-600 dark:text-red-400">Rejected</div>
                        <div class="text-lg font-bold text-red-800 dark:text-red-200">{{ stats.rejected }}</div>
                    </div>
                    <div class="bg-emerald-50 dark:bg-emerald-900/20 p-3 rounded-lg">
                        <div class="text-sm text-emerald-600 dark:text-emerald-400">Positive</div>
                        <div class="text-lg font-bold text-emerald-800 dark:text-emerald-200">{{ stats.positive }}</div>
                    </div>
                    <div class="bg-rose-50 dark:bg-rose-900/20 p-3 rounded-lg">
                        <div class="text-sm text-rose-600 dark:text-rose-400">Negative</div>
                        <div class="text-lg font-bold text-rose-800 dark:text-rose-200">{{ stats.negative }}</div>
                    </div>
                    <div class="bg-purple-50 dark:bg-purple-900/20 p-3 rounded-lg">
                        <div class="text-sm text-purple-600 dark:text-purple-400">By Me</div>
                        <div class="text-lg font-bold text-purple-800 dark:text-purple-200">{{ stats.assignedByMe }}</div>
                    </div>
                    <div class="bg-indigo-50 dark:bg-indigo-900/20 p-3 rounded-lg">
                        <div class="text-sm text-indigo-600 dark:text-indigo-400">To Me</div>
                        <div class="text-lg font-bold text-indigo-800 dark:text-indigo-200">{{ stats.assignedToMe }}</div>
                    </div>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-300 dark:border-gray-700 p-4">
                
                <!-- Search Bar -->
                <div class="mb-4">
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search by assignor, recipient, reason, or category..."
                        class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    />
                </div>

                <!-- Status/Type Filters -->
                <div class="mb-4">
                    <h3 class="text-sm font-medium mb-2">Filter by Status & Type</h3>
                    <div class="flex flex-wrap gap-2">
                        <button 
                            v-for="filter in [
                                { key: 'all', label: 'All', count: stats.total },
                                { key: 'verified', label: 'Verified', count: stats.verified },
                                { key: 'pending', label: 'Pending', count: stats.pending },
                                { key: 'rejected', label: 'Rejected', count: stats.rejected },
                                { key: 'positive', label: 'Positive', count: stats.positive },
                                { key: 'negative', label: 'Negative', count: stats.negative },
                                { key: 'assigned-by-me', label: 'Assigned by Me', count: stats.assignedByMe },
                                { key: 'assigned-to-me', label: 'Assigned to Me', count: stats.assignedToMe }
                            ]"
                            :key="filter.key"
                            @click="setFilter(filter.key)"
                            :class="[
                                'px-3 py-2 text-sm rounded-lg font-medium transition-colors',
                                activeFilter === filter.key 
                                    ? 'bg-blue-600 text-white' 
                                    : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600'
                            ]"
                        >
                            {{ filter.label }} ({{ filter.count }})
                        </button>
                    </div>
                </div>

                <!-- Date Filters -->
                <div class="mb-4">
                    <h3 class="text-sm font-medium mb-2">Filter by Date</h3>
                    <div class="flex flex-wrap gap-2 mb-3">
                        <button 
                            v-for="preset in datePresets"
                            :key="preset.value"
                            @click="setDateFilter(preset)"
                            :class="[
                                'px-3 py-2 text-sm rounded-lg font-medium transition-colors',
                                dateFilter === preset.value 
                                    ? 'bg-green-600 text-white' 
                                    : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600'
                            ]"
                        >
                            {{ preset.label }}
                        </button>
                    </div>
                    
                    <!-- Custom Date Range -->
                    <div v-if="showCustomDatePicker" class="flex flex-col sm:flex-row gap-3 items-end">
                        <div class="flex-1">
                            <label class="block text-xs text-gray-500 mb-1">Start Date</label>
                            <input
                                v-model="customStartDate"
                                type="date"
                                class="w-full p-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            />
                        </div>
                        <div class="flex-1">
                            <label class="block text-xs text-gray-500 mb-1">End Date</label>
                            <input
                                v-model="customEndDate"
                                type="date"
                                class="w-full p-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            />
                        </div>
                        <button 
                            @click="applyCustomDateRange"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors whitespace-nowrap"
                        >
                            Apply Range
                        </button>
                        <button 
                            @click="clearDateFilter"
                            class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors"
                        >
                            Clear
                        </button>
                    </div>
                </div>
            </div>

            <!-- Results Section -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-300 dark:border-gray-700 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold">
                        Point Assignment History ({{ filteredPointLogs.length }} results)
                    </h3>
                </div>

                <div v-if="loading" class="text-center py-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto"></div>
                    <p class="mt-2 text-gray-500">Loading history...</p>
                </div>

                <div v-else-if="filteredPointLogs.length === 0" class="text-center py-8 text-gray-500">
                    <span class="text-4xl mb-4 block">📋</span>
                    <p v-if="searchQuery.trim()">No results found for your search criteria</p>
                    <p v-else-if="activeFilter !== 'all' || dateFilter !== 'all-time'">No results found for the selected filters</p>
                    <p v-else>No point assignments found</p>
                </div>

                <div v-else class="space-y-4">
                    <div 
                        v-for="log in filteredPointLogs" 
                        :key="log.id"
                        :class="[
                            'border rounded-xl p-4 transition-all hover:shadow-md',
                            log.points > 0 ? 'border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-800' :
                            'border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800'
                        ]"
                    >
                        <div class="flex flex-col sm:flex-row items-start justify-between mb-3 gap-3">
                            <div class="flex items-start gap-3 flex-1">
                                <span class="text-2xl">{{ getActionIcon(log.points) }}</span>
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-base break-words">
                                        {{ log.assignor_name }} → {{ log.recipient_name }}
                                    </p>
                                    <div class="flex flex-wrap items-center gap-2 text-sm text-gray-500 mt-1">
                                        <span>{{ getRelativeTime(log.created_at) }}</span>
                                        <span :class="['px-2 py-1 rounded text-xs font-medium', getStatusClass(log.status)]">
                                            {{ log.status?.toUpperCase() }}
                                        </span>
                                        <span v-if="log.categories && log.categories.length > 0" class="text-xs bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">
                                            {{ log.categories.join(', ') }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center sm:text-right">
                                <span :class="['text-xl font-bold', getPointClass(log.points)]">
                                    {{ log.points > 0 ? '+' : '' }}{{ log.points }}
                                </span>
                                <p class="text-sm text-gray-500">points</p>
                            </div>
                        </div>

                        <div v-if="log.reason">
                            <p class="text-gray-700 dark:text-gray-300 leading-relaxed text-sm">
                                {{ log.reason }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.hover\:shadow-md:hover {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

input[type="date"]::-webkit-calendar-picker-indicator {
    cursor: pointer;
}

.border-green-200 {
    border-left-width: 4px;
    border-left-color: #10b981;
}

.border-red-200 {
    border-left-width: 4px;
    border-left-color: #ef4444;
}
</style>