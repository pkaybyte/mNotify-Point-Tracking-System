<script setup>
import { onMounted, ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import axios from 'axios';

// Chart.js imports - Fixed: Added DoughnutController and CategoryScale  
import { 
    Chart as ChartJS, 
    Title, 
    Tooltip, 
    Legend, 
    ArcElement, 
    DoughnutController,
    CategoryScale,
    LinearScale
} from 'chart.js';

ChartJS.register(Title, Tooltip, Legend, ArcElement, DoughnutController, CategoryScale, LinearScale);

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' }
];

// Existing refs
const chartCanvas = ref(null);
const chartInstance = ref(null);
const user = ref(null);
const totalVerifiedPoints = ref(0);
const totalUnverifiedPoints = ref(0);
const loadingChart = ref(true);

// Enhanced refs for point management
const showAssignPointModal = ref(false);
const showPointLogsModal = ref(false);
const users = ref([]);
const pointLogs = ref([]);
const auditLogs = ref([]);
const assignPointForm = ref({
    recipient_id: '',
    points: 1,
    reason: '',
    categories: []
});
const searchQuery = ref('');
const selectedUser = ref(null);
const showUserDropdown = ref(false);
const isSubmitting = ref(false);
const activeLogFilter = ref('all');

// Point presets for quick assignment
const pointPresets = [
    { value: 1, label: '+1 (Good work)', type: 'positive', color: 'bg-green-500' },
    { value: 3, label: '+3 (Great job)', type: 'positive', color: 'bg-green-600' },
    { value: 5, label: '+5 (Excellent)', type: 'positive', color: 'bg-green-700' },
    { value: -1, label: '-1 (Minor issue)', type: 'negative', color: 'bg-red-500' },
    { value: -3, label: '-3 (Needs improvement)', type: 'negative', color: 'bg-red-600' },
    { value: -5, label: '-5 (Significant issue)', type: 'negative', color: 'bg-red-700' },
];

// Point categories
const pointCategories = [
    'Job Delivery',
    'Work Ethics', 
    'Going The Extra Mile',
    'Anniversary Contribution',
    'Company Projects'
];

const customCategory = ref('');
const showAddCategory = ref(false);

// Enhanced computed properties
const filteredPointLogs = computed(() => {
    if (!user.value) return [];
    
    switch (activeLogFilter.value) {
        case 'assigned':
            return pointLogs.value.filter(log => log.assignor_id === user.value.id);
        case 'received':
            return pointLogs.value.filter(log => log.recipient_id === user.value.id);
        case 'positive':
            return pointLogs.value.filter(log => log.points > 0);
        case 'negative':
            return pointLogs.value.filter(log => log.points < 0);
        default:
            return pointLogs.value;
    }
});

// Point statistics computed - Updated for total accumulated points
const pointStats = computed(() => {
    if (!user.value || !pointLogs.value.length) {
        return { 
            totalAssigned: 0, 
            totalReceived: 0, 
            totalPositive: 0, 
            totalNegative: 0,
            positiveReceived: 0, 
            negativeReceived: 0 
        };
    }
    
    const assigned = pointLogs.value.filter(log => log.assignor_id === user.value.id);
    const received = pointLogs.value.filter(log => log.recipient_id === user.value.id && log.status === 'verified');
    
    // Calculate total accumulated positive and negative points
    const allUserPoints = pointLogs.value.filter(log => log.recipient_id === user.value.id && log.status === 'verified');
    const totalPositive = allUserPoints.filter(log => log.points > 0).reduce((sum, log) => sum + log.points, 0);
    const totalNegative = allUserPoints.filter(log => log.points < 0).reduce((sum, log) => sum + log.points, 0);
    
    return {
        totalAssigned: assigned.reduce((sum, log) => sum + log.points, 0),
        totalReceived: received.reduce((sum, log) => sum + log.points, 0),
        totalPositive: totalPositive,
        totalNegative: Math.abs(totalNegative), // Make it positive for display
        positiveReceived: received.filter(log => log.points > 0).reduce((sum, log) => sum + log.points, 0),
        negativeReceived: received.filter(log => log.points < 0).reduce((sum, log) => sum + log.points, 0)
    };
});

// Filtered users for search
const filteredUsers = computed(() => {
    if (!searchQuery.value.trim()) {
        return users.value;
    }
    return users.value.filter(u => 
        u.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        u.email.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});

// Computed for user's chart data - Fixed like supervisor dashboard
const userChartData = computed(() => {
    const verifiedPoints = Math.abs(totalVerifiedPoints.value);
    const unverifiedPoints = Math.abs(totalUnverifiedPoints.value);
    const total = verifiedPoints + unverifiedPoints;
    
    if (total === 0) {
        return {
            verified: 0,
            unverified: 0,
            verifiedPercentage: 0,
            unverifiedPercentage: 0
        };
    }
    
    return {
        verified: verifiedPoints,
        unverified: unverifiedPoints,
        verifiedPercentage: Math.round((verifiedPoints / total) * 100),
        unverifiedPercentage: Math.round((unverifiedPoints / total) * 100)
    };
});

// Chart data & options - Working implementation from supervisor dashboard
const chartData = computed(() => ({
    labels: ['Verified Points', 'Unverified Points'],
    datasets: [
        {
            label: 'My Points',
            data: [userChartData.value.verified, userChartData.value.unverified],
            backgroundColor: ['#22C55E', '#F97316'], // Bright green and orange
            borderWidth: 0,
            hoverBackgroundColor: ['#16A34A', '#EA580C']
        }
    ]
}));

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { 
            position: 'bottom',
            labels: {
                padding: 10,
                usePointStyle: true,
                pointStyle: 'circle',
                font: {
                    size: 12
                },
                color: '#6B7280'
            }
        },
        tooltip: {
            callbacks: {
                label: function(context) {
                    const label = context.label || '';
                    const value = context.parsed || 0;
                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                    const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                    return `${label}: ${value} (${percentage}%)`;
                }
            }
        }
    },
    cutout: '75%', // Thick doughnut
    elements: {
        arc: {
            borderWidth: 0
        }
    }
};

// Methods
const loadDashboardData = async () => {
    try {
        // Fetch user info
        const userRes = await axios.get('/api/user');
        user.value = userRes.data;

        // Fetch chart data
        const res = await axios.get('/api/points');
        totalVerifiedPoints.value = res.data.verified_points || 0;
        totalUnverifiedPoints.value = res.data.unverified_points || 0;

        // Create/update chart
        loadingChart.value = false;
        
        // Render chart after data is loaded
        setTimeout(() => {
            renderChart();
        }, 100);
        
        // Load audit logs
        await loadAuditLogs();
        
        // Load point logs for stats calculation
        await loadPointLogs();
        
    } catch (err) {
        console.error('Error loading dashboard data:', err);
        // Fallback data for development
        totalVerifiedPoints.value = 25;
        totalUnverifiedPoints.value = 8;
        loadingChart.value = false;
        setTimeout(() => {
            renderChart();
        }, 100);
    }
};

// Fixed: Chart rendering function from supervisor dashboard
const renderChart = () => {
    if (!chartCanvas.value) return;
    
    // Destroy existing chart
    if (chartInstance.value) {
        chartInstance.value.destroy();
        chartInstance.value = null;
    }
    
    try {
        chartInstance.value = new ChartJS(chartCanvas.value, {
            type: 'doughnut',
            data: chartData.value,
            options: chartOptions
        });
    } catch (error) {
        console.error('Error creating chart:', error);
    }
};

const createChart = async () => {
    if (!chartCanvas.value) return;
    
    // Wait for next tick to ensure canvas is ready
    await new Promise(resolve => setTimeout(resolve, 100));
    
    renderChart();
};

const loadUsers = async () => {
    try {
        const response = await axios.get('/api/users');
        users.value = response.data || [];
    } catch (error) {
        console.error('Error loading users:', error);
        users.value = [];
    }
};

const loadAuditLogs = async () => {
    try {
        const response = await axios.get('/api/audit-logs');
        auditLogs.value = response.data || [];
    } catch (error) {
        console.error('Error loading audit logs:', error);
        auditLogs.value = [];
    }
};

const loadPointLogs = async () => {
    try {
        const response = await axios.get('/api/point-assignments/logs');
        pointLogs.value = response.data || [];
    } catch (error) {
        console.error('Error loading point logs:', error);
        pointLogs.value = [];
    }
};

const openAssignPointModal = async () => {
    await loadUsers();
    showAssignPointModal.value = true;
};

const closeAssignPointModal = () => {
    showAssignPointModal.value = false;
    assignPointForm.value = { 
        recipient_id: '', 
        points: 1, 
        reason: '', 
        categories: []
    };
    searchQuery.value = '';
    selectedUser.value = null;
    showUserDropdown.value = false;
    customCategory.value = '';
    showAddCategory.value = false;
};

const openPointLogsModal = async () => {
    await loadPointLogs();
    showPointLogsModal.value = true;
};

const closePointLogsModal = () => {
    showPointLogsModal.value = false;
    activeLogFilter.value = 'all';
};

const selectPointPreset = (preset) => {
    assignPointForm.value.points = preset.value;
    assignPointForm.value.point_type = preset.type;
};

const handlePointsChange = () => {
    const points = assignPointForm.value.points;
    assignPointForm.value.point_type = points >= 0 ? 'positive' : 'negative';
};

const submitPointAssignment = async () => {
    if (!assignPointForm.value.recipient_id || !assignPointForm.value.reason.trim()) {
        alert('Please fill in all fields');
        return;
    }

    if (assignPointForm.value.reason.trim().length < 10) {
        alert('Please provide a more detailed reason (at least 10 characters)');
        return;
    }


    if (assignPointForm.value.categories.length === 0) {
        alert('Please select at least one category');
        return;
    }

    isSubmitting.value = true;

    try {
        const response = await axios.post('/api/point-assignments', {
            recipient_id: parseInt(assignPointForm.value.recipient_id),
            points: parseInt(assignPointForm.value.points),
            reason: assignPointForm.value.reason.trim(),
            categories: assignPointForm.value.categories
        });

        if (response.status === 201) {
            const pointText = assignPointForm.value.points > 0 ? 'points' : 'penalty points';
            alert(`${Math.abs(assignPointForm.value.points)} ${pointText} assigned successfully!`);
            closeAssignPointModal();
            // Auto-reload the page after successful point assignment
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }
    } catch (error) {
        console.error('Error assigning point:', error);
        const errorMessage = error.response?.data?.message || 
                           error.response?.data?.errors || 
                           'Please try again.';
        alert('Error assigning point: ' + JSON.stringify(errorMessage));
    } finally {
        isSubmitting.value = false;
    }
};

const setLogFilter = (filter) => {
    activeLogFilter.value = filter;
};

const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
};

const getStatusClass = (status) => {
    switch (status) {
        case 'verified': return 'text-green-600 bg-green-100 dark:bg-green-900 dark:text-green-400';
        case 'pending': return 'text-yellow-600 bg-yellow-100 dark:bg-yellow-900 dark:text-yellow-400';
        case 'rejected': return 'text-red-600 bg-red-100 dark:bg-red-900 dark:text-red-400';
        default: return 'text-gray-600 bg-gray-100 dark:bg-gray-900 dark:text-gray-400';
    }
};

const getPointClass = (points) => {
    if (points > 0) {
        return points >= 5 ? 'text-green-700 font-bold' : 'text-green-600';
    } else {
        return points <= -3 ? 'text-red-700 font-bold' : 'text-red-600';
    }
};

const getActionIcon = (action, points = null) => {
    switch (action) {
        case 'assigned_point': 
            return points !== null ? (points > 0 ? '➕' : '➖') : '➕';
        case 'approved_point': return '✅';
        case 'rejected_point': return '❌';
        default: return '📝';
    }
};

// Search functionality
const selectUser = (user) => {
    selectedUser.value = user;
    assignPointForm.value.recipient_id = user.id;
    searchQuery.value = user.name;
    showUserDropdown.value = false;
};

const onSearchInput = () => {
    showUserDropdown.value = true;
    selectedUser.value = null;
    assignPointForm.value.recipient_id = '';
};

const onSearchFocus = () => {
    showUserDropdown.value = true;
};

const onSearchBlur = () => {
    // Delay hiding dropdown to allow click on options
    setTimeout(() => {
        showUserDropdown.value = false;
    }, 300);
};

// Category management
const toggleCategory = (category) => {
    const index = assignPointForm.value.categories.indexOf(category);
    if (index === -1) {
        assignPointForm.value.categories.push(category);
    } else {
        assignPointForm.value.categories.splice(index, 1);
    }
};

const addCustomCategory = () => {
    if (customCategory.value.trim() && !assignPointForm.value.categories.includes(customCategory.value.trim())) {
        assignPointForm.value.categories.push(customCategory.value.trim());
        customCategory.value = '';
        showAddCategory.value = false;
    }
};

const removeCategory = (category) => {
    const index = assignPointForm.value.categories.indexOf(category);
    if (index !== -1) {
        assignPointForm.value.categories.splice(index, 1);
    }
};

// Initialize on mount
onMounted(() => {
    loadDashboardData();
    
    // Cleanup function
    return () => {
        if (chartInstance.value) {
            chartInstance.value.destroy();
        }
    };
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-2 sm:p-4 overflow-x-auto">
            
            <!-- Top Grid - Enhanced with better stats -->
            <div class="grid auto-rows-min gap-4 sm:grid-cols-2 lg:grid-cols-3">
                
                <!-- Enhanced User Info with Point Stats & 2x2 Grid -->
                <div class="relative aspect-video overflow-hidden rounded-xl border border-gray-300 dark:border-gray-700 p-4">
                    <h2 class="text-xl font-semibold mb-2">Welcome, {{ user?.name }}</h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-3">
                        Role: <span class="capitalize">{{ user?.role }}</span>
                    </p>
                    
                    <div class="mb-3">
                        <p class="text-lg font-bold">
                            My Points: <span class="text-blue-600">{{ totalVerifiedPoints }}</span>
                        </p>
                    </div>

                    <!-- 2x2 Grid inside Welcome box -->
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <!-- Verified -->
                        <div class="bg-green-50 dark:bg-green-900/20 p-2 rounded border border-green-200 dark:border-green-800">
                            <div class="text-green-700 dark:text-green-400 font-medium">Verified: {{ totalVerifiedPoints }}</div>
                        </div>
                        
                        <!-- Pending -->
                        <div class="bg-yellow-50 dark:bg-yellow-900/20 p-2 rounded border border-yellow-200 dark:border-yellow-800">
                            <div class="text-yellow-700 dark:text-yellow-400 font-medium">Pending: {{ totalUnverifiedPoints }}</div>
                        </div>
                        
                        <!-- Lifetime Positive Points -->
                        <div class="bg-green-50 dark:bg-green-900/20 p-2 rounded border border-green-200 dark:border-green-800">
                            <div class="text-green-700 dark:text-green-400 font-medium">Positive: {{ pointStats.totalPositive }}</div>
                        </div>
                        
                        <!-- Lifetime Negative Points -->
                        <div class="bg-red-50 dark:bg-red-900/20 p-2 rounded border border-red-200 dark:border-red-800">
                            <div class="text-red-700 dark:text-red-400 font-medium">Negative: {{ pointStats.totalNegative }}</div>
                        </div>
                    </div>
                </div>

                <!-- Fixed: Doughnut Chart with perfectly centered number -->
<div class="relative aspect-video overflow-hidden rounded-xl border border-gray-300 dark:border-gray-700 p-4">
    <div v-if="loadingChart" class="flex items-center justify-center h-full">
        <div class="text-gray-500 text-center">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto mb-2"></div>
            <p>Loading chart...</p>
        </div>
    </div>
    <div v-else class="h-full flex items-center justify-center relative">
        <!-- Chart Container with proper positioning -->
        <div class="relative w-full h-full flex items-center justify-center">
            <div class="relative max-w-[200px] max-h-[200px] aspect-square">
                <canvas ref="chartCanvas" class="max-w-full max-h-full"></canvas>
                
                <!-- Perfectly centered number overlay -->
                <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                    <div class="text-center select-none">
                        <div class="text-4xl md:text-5xl font-bold text-gray-900 dark:text-gray-100 leading-none">
                            <!--{{ totalVerifiedPoints + totalUnverifiedPoints }}-->
                        </div>
                        <div class="text-xs md:text-sm text-gray-500 dark:text-gray-400 mt-1 font-medium">
                            <!--Total Points-->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

                <!-- Quick Actions - Enhanced -->
                <div class="relative aspect-video overflow-hidden rounded-xl border border-gray-300 dark:border-gray-700 p-4">
                    <h2 class="text-xl font-semibold mb-4">Quick Actions</h2>
                    <div class="flex flex-col gap-3">
                        <!-- Assign Point Button -->
                        <button 
                            @click="openAssignPointModal"
                            class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-4 py-3 rounded-lg font-medium hover:opacity-90 transition-opacity flex items-center justify-center gap-2"
                        >
                            <span class="text-lg">🎯</span>
                            Assign Points
                        </button>
                        
                        <!-- View Point Logs Button -->
                        <button 
                            @click="openPointLogsModal"
                            class="bg-gradient-to-r from-pink-500 to-red-500 text-white px-4 py-3 rounded-lg font-medium hover:opacity-90 transition-opacity flex items-center justify-center gap-2"
                        >
                            <span class="text-lg">📊</span>
                            View Points History
                        </button>
                        
                       
                    </div>
                </div>
            </div>

            <!-- Enhanced Audit Logs -->
            <div class="relative min-h-[100vh] flex-1 rounded-xl border border-gray-300 dark:border-gray-700 md:min-h-min p-4">
                <h2 class="text-xl font-semibold mb-4">Activity Feed</h2>
                
                <div v-if="auditLogs.length === 0" class="text-gray-500">
                    <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500 mx-auto mb-2"></div>
                    Loading activities...
                </div>
                
                <div v-else class="space-y-3">
                    <div 
                        v-for="log in auditLogs.slice(0, 5)" 
                        :key="log.id"
                        class="border-l-4 border-blue-500 pl-4 py-2"
                    >
                        <div class="flex items-center gap-2">
                            <span class="text-lg">{{ getActionIcon(log.action, log.points) }}</span>
                            <span class="font-medium">{{ log.action.replace('_', ' ').toUpperCase() }}</span>
                            <span v-if="log.points" :class="['font-semibold', getPointClass(log.points)]">
                                {{ log.points > 0 ? '+' : '' }}{{ log.points }}
                            </span>
                            <span class="text-gray-500 dark:text-gray-400">by {{ log.user_name }}</span>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            {{ formatDate(log.created_at) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Assign Point Modal -->
        <div 
            v-if="showAssignPointModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
            @click.self="closeAssignPointModal"
        >
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 w-full max-w-md m-4 animate-fade-in max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-semibold">Assign Points</h3>
                    <button 
                        @click="closeAssignPointModal"
                        class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        ✕
                    </button>
                </div>
                
                <form @submit.prevent="submitPointAssignment" class="space-y-3">
                    <!-- Recipient Selection -->
                    <div>
                        <label class="block text-sm font-medium mb-2">Recipient</label>
                        <div class="relative">
                            <input
                                v-model="searchQuery"
                                @input="onSearchInput"
                                @focus="onSearchFocus"
                                @blur="onSearchBlur"
                                type="text"
                                class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="Type to search users..."
                            >
                            <div 
                                v-if="showUserDropdown && filteredUsers.length > 0" 
                                class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg shadow-lg max-h-60 overflow-y-auto"
                            >
                                <div
                                    v-for="u in filteredUsers"
                                    :key="u.id"
                                    @mousedown.prevent="selectUser(u)"
                                    @click.prevent="selectUser(u)"
                                    class="p-3 hover:bg-gray-100 dark:hover:bg-gray-600 cursor-pointer border-b border-gray-200 dark:border-gray-600 last:border-b-0"
                                >
                                    <div class="font-medium">{{ u.name }}</div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ u.email || u.role || 'user' }}</div>
                                </div>
                            </div>
                            <div 
                                v-if="showUserDropdown && filteredUsers.length === 0 && searchQuery.trim()" 
                                class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg shadow-lg p-3 text-gray-500 dark:text-gray-400"
                            >
                                No users found matching "{{ searchQuery }}"
                            </div>
                        </div>
                    </div>

                    <!-- Point Presets -->
                    <div>
                        <label class="block text-sm font-medium mb-2">Quick Point Selection</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <button
                                v-for="preset in pointPresets"
                                :key="preset.value"
                                type="button"
                                @click="selectPointPreset(preset)"
                                :class="[
                                    'p-2 text-sm rounded-lg text-white font-medium hover:opacity-90 transition-opacity',
                                    preset.color,
                                    assignPointForm.points === preset.value ? 'ring-2 ring-blue-400' : ''
                                ]"
                            >
                                {{ preset.label }}
                            </button>
                        </div>
                    </div>

                    
                    <!-- Point Categories -->
                    <div>
                        <label class="block text-sm font-medium mb-2">Point Categories <span class="text-red-500">*</span></label>
                        <div class="space-y-3">
                            <!-- Selected categories -->
                            <div v-if="assignPointForm.categories.length > 0" class="flex flex-wrap gap-2">
                                <span 
                                    v-for="category in assignPointForm.categories" 
                                    :key="category"
                                    class="inline-flex items-center gap-1 px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm"
                                >
                                    {{ category }}
                                    <button 
                                        @click="removeCategory(category)"
                                        class="ml-1 text-blue-600 hover:text-blue-800"
                                        type="button"
                                    >
                                        ✕
                                    </button>
                                </span>
                            </div>
                            
                            <!-- Category selection -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                <button
                                    v-for="category in pointCategories"
                                    :key="category"
                                    type="button"
                                    @click="toggleCategory(category)"
                                    :class="[
                                        'px-3 py-2 text-sm border rounded-lg transition-colors text-left',
                                        assignPointForm.categories.includes(category)
                                            ? 'bg-blue-100 border-blue-500 text-blue-800'
                                            : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'
                                    ]"
                                >
                                    {{ category }}
                                </button>
                            </div>
                            
                        </div>
                    </div>
                    
                    <!-- Reason -->
                    <div>
                        <label class="block text-sm font-medium mb-2">
                            Reason 
                            <span :class="assignPointForm.point_type === 'negative' ? 'text-red-600' : 'text-green-600'">
                                ({{ assignPointForm.point_type === 'negative' ? 'for improvement area' : 'for recognition' }})
                            </span>
                        </label>
                        <textarea 
                            v-model="assignPointForm.reason"
                            class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                            rows="3"
                            :placeholder="assignPointForm.point_type === 'negative' ? 
                                'Explain what needs improvement and provide constructive feedback...' : 
                                'Describe what they did well or achieved...'"
                            maxlength="500"
                        ></textarea>
                        <div class="text-xs text-gray-500 mt-1 text-right">
                            {{ assignPointForm.reason ? assignPointForm.reason.length : 0 }}/500 characters
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="flex gap-3 pt-4">
                        <button 
                            type="button" 
                            @click="closeAssignPointModal"
                            class="flex-1 px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            :disabled="isSubmitting || !assignPointForm.recipient_id || !assignPointForm.reason || assignPointForm.points === 0"
                            :class="[
                                'flex-1 px-4 py-2 text-white rounded-lg hover:opacity-90 transition-opacity disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center',
                                assignPointForm.point_type === 'positive' 
                                    ? 'bg-gradient-to-r from-green-500 to-blue-600' 
                                    : 'bg-gradient-to-r from-red-500 to-orange-600'
                            ]"
                        >
                            <span v-if="!isSubmitting">
                                {{ assignPointForm.point_type === 'positive' ? 'Award' : 'Assign' }} 
                                {{ Math.abs(assignPointForm.points) }} Point{{ Math.abs(assignPointForm.points) !== 1 ? 's' : '' }}
                            </span>
                            <div v-else class="flex items-center gap-2">
                                <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-white"></div>
                                <span>Processing...</span>
                            </div>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Enhanced Point Logs Modal -->
        <div 
            v-if="showPointLogsModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
            @click.self="closePointLogsModal"
        >
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-4xl m-4 max-h-[80vh] overflow-hidden animate-fade-in">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-semibold">Point Assignment History</h3>
                    <button 
                        @click="closePointLogsModal"
                        class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        ✕
                    </button>
                </div>
                
                <!-- Enhanced Filter Tabs -->
                <div class="flex flex-wrap gap-2 mb-4">
                    <button 
                        v-for="filter in ['all', 'assigned', 'received', 'positive', 'negative']"
                        :key="filter"
                        @click="setLogFilter(filter)"
                        :class="[
                            'px-4 py-2 rounded-lg font-medium transition-colors',
                            activeLogFilter === filter 
                                ? 'bg-blue-600 text-white' 
                                : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600'
                        ]"
                    >
                        {{ filter === 'assigned' ? 'Assigned by Me' : 
                           filter === 'received' ? 'Received by Me' : 
                           filter === 'positive' ? 'Positive Points' :
                           filter === 'negative' ? 'Negative Points' : 'All' }}
                    </button>
                </div>

                <!-- Enhanced Logs Content -->
                <div class="overflow-y-auto max-h-96 space-y-3">
                    <div v-if="filteredPointLogs.length === 0" class="text-center py-8 text-gray-500">
                        No logs found for selected filter
                    </div>
                    
                    <div 
                        v-for="log in filteredPointLogs" 
                        :key="log.id"
                        :class="[
                            'border rounded-lg p-4',
                            log.points > 0 ? 'border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20' :
                            'border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20'
                        ]"
                    >
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <div class="flex items-center gap-2">
                                    <p class="font-medium">{{ log.assignor_name }} → {{ log.recipient_name }}</p>
                                    <span :class="['text-lg font-bold', getPointClass(log.points)]">
                                        {{ log.points > 0 ? '+' : '' }}{{ log.points }}
                                    </span>
                                </div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ formatDate(log.created_at) }}</p>
                            </div>
                            <span :class="['px-2 py-1 rounded text-xs font-medium', getStatusClass(log.status)]">
                                {{ log.status.toUpperCase() }}
                            </span>
                        </div>
                        <p class="text-gray-700 dark:text-gray-300">{{ log.reason }}</p>
                    </div>
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

/* Enhanced select styling */
select {
    background-image: none;
}

select option {
    padding: 8px 12px;
    background-color: white;
    color: black;
}

select option:checked {
    background-color: #3b82f6;
    color: white;
}

/* Dark mode select options */
.dark select option {
    background-color: #374151;
    color: white;
}

.dark select option:checked {
    background-color: #3b82f6;
    color: white;
}

/* Point preset button animations */
.grid button:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

/* Point input styling */
input[type="number"]::-webkit-outer-spin-button,
input[type="number"]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

input[type="number"] {
  -moz-appearance: textfield;  /* Firefox */
  -webkit-appearance: none;    /* Chrome, Safari, Edge */
  appearance: none;            /* Standard property */
}

/* Enhanced log cards */
.border-green-200 {
    border-left-width: 4px;
    border-left-color: #10b981;
}

.border-red-200 {
    border-left-width: 4px;
    border-left-color: #ef4444;
}
</style>