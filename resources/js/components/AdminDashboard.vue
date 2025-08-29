<script setup>
import { onMounted, ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import axios from 'axios';

// Chart.js imports
import { Chart as ChartJS, Title, Tooltip, Legend, ArcElement } from 'chart.js';
ChartJS.register(Title, Tooltip, Legend, ArcElement);

const breadcrumbs = [
    { title: 'Admin Dashboard', href: '/admin-dashboard' }
];

// Admin-specific refs
const users = ref([]);
const pendingAssignments = ref([]);
const recentActivity = ref([]);
const systemStats = ref({
    totalUsers: 0,
    totalSupervisors: 0,
    totalPendingPoints: 0,
    totalPointsAssigned: 0
});
const loading = ref(true);

// Chart refs
const chartCanvas = ref(null);
const chartData = ref({
    labels: ['Verified Points', 'Pending Points'],
    datasets: [
        {
            label: 'Points Distribution',
            data: [0, 0],
            backgroundColor: ['#10B981', '#F59E0B'],
            borderWidth: 2,
            borderColor: ['#059669', '#D97706']
        }
    ]
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { 
            position: 'bottom',
            labels: {
                padding: 20,
                usePointStyle: true
            }
        },
        tooltip: {
            callbacks: {
                label: function(context) {
                    const label = context.label || '';
                    const value = context.parsed || 0;
                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                    return `${label}: ${value} points (${percentage}%)`;
                }
            }
        }
    }
};

// Point History Modal
const showPointLogsModal = ref(false);
const pointLogs = ref([]);
const activeLogFilter = ref('all');

// Enhanced User Management
const showUserManagementModal = ref(false);
const selectedUser = ref(null);
const userRoleUpdate = ref('');
const userAction = ref(''); // 'role', 'verify', 'delete'

// Point Assignment Modal (NEW)
const showAssignPointsModal = ref(false);
const assignPointsForm = ref({
    recipient_id: '',
    points: 1,
    reason: ''
});

// Pending Points Management
const showPendingPointsModal = ref(false);
const processingAssignment = ref(null);

// System Reports
const showReportsModal = ref(false);
const reportData = ref([]);
const reportFilter = ref('all');

// Computed properties
const pendingCount = computed(() => pendingAssignments.value.length);

const usersByRole = computed(() => {
    return {
        admins: users.value.filter(u => u.role === 'admin'),
        supervisors: users.value.filter(u => u.role === 'supervisor'),
        users: users.value.filter(u => u.role === 'user')
    };
});

const filteredReports = computed(() => {
    if (reportFilter.value === 'all') return reportData.value;
    return reportData.value.filter(user => user.role === reportFilter.value);
});

const filteredPointLogs = computed(() => {
    switch (activeLogFilter.value) {
        case 'pending':
            return pointLogs.value.filter(log => log.status === 'pending');
        case 'verified':
            return pointLogs.value.filter(log => log.status === 'verified');
        case 'rejected':
            return pointLogs.value.filter(log => log.status === 'rejected');
        case 'positive':
            return pointLogs.value.filter(log => log.points > 0);
        case 'negative':
            return pointLogs.value.filter(log => log.points < 0);
        default:
            return pointLogs.value;
    }
});

const eligibleUsers = computed(() => {
    return users.value.filter(user => user.role !== 'admin' && user.id !== 3); // Exclude admins and current user
});

// Methods
const loadAdminData = async () => {
    try {
        loading.value = true;
        
        // Load all users
        const usersRes = await axios.get('/api/admin/users');
        users.value = usersRes.data;
        
        // Load pending assignments
        const pendingRes = await axios.get('/api/point-assignments/pending');
        pendingAssignments.value = pendingRes.data.pending_assignments || [];
        
        // Load system statistics
        const statsRes = await axios.get('/api/admin/stats');
        systemStats.value = statsRes.data;
        recentActivity.value = statsRes.data.recentActivity || [];
        
        // Load chart data
        await loadChartData();
        
        loading.value = false;
    } catch (error) {
        console.error('Error loading admin data:', error);
        // Fallback demo data
        users.value = [
            { id: 1, name: 'John Admin', email: 'admin@mnotify.com', role: 'admin', total_verified_points: 0, email_verified_at: new Date() },
            { id: 2, name: 'Jane Supervisor', email: 'jane@mnotify.com', role: 'supervisor', total_verified_points: 45, email_verified_at: new Date() },
            { id: 3, name: 'Mike User', email: 'mike@mnotify.com', role: 'user', total_verified_points: 23, email_verified_at: null },
            { id: 4, name: 'Sarah Manager', email: 'sarah@mnotify.com', role: 'supervisor', total_verified_points: 67, email_verified_at: new Date() },
            { id: 5, name: 'Tom Employee', email: 'tom@mnotify.com', role: 'user', total_verified_points: 12, email_verified_at: new Date() }
        ];
        
        pendingAssignments.value = [
            {
                id: 1,
                assignor: { name: 'Mike User' },
                recipient: { name: 'Sarah Manager' },
                points: 5,
                reason: 'Excellent teamwork on the project',
                created_at: new Date().toISOString()
            }
        ];
        
        systemStats.value = {
            totalUsers: 5,
            totalSupervisors: 2,
            totalPendingPoints: 2,
            totalPointsAssigned: 147
        };
        
        // Load fallback chart data
        chartData.value.datasets[0].data = [120, 27];
        renderChart();
        
        loading.value = false;
    }
};

const loadChartData = async () => {
    try {
        const response = await axios.get('/api/point-assignments/points');
        const data = response.data;
        
        chartData.value.datasets[0].data = [
            data.verified_points || 0,
            data.unverified_points || 0
        ];
        
        renderChart();
    } catch (error) {
        console.error('Error loading chart data:', error);
        // Fallback chart data
        chartData.value.datasets[0].data = [120, 27];
        renderChart();
    }
};

const renderChart = () => {
    if (chartCanvas.value) {
        // Destroy existing chart if it exists
        if (chartCanvas.value.chart) {
            chartCanvas.value.chart.destroy();
        }
        
        chartCanvas.value.chart = new ChartJS(chartCanvas.value, {
            type: 'pie',
            data: chartData.value,
            options: chartOptions
        });
    }
};

// NEW: Assign Points functionality
const openAssignPoints = () => {
    assignPointsForm.value = {
        recipient_id: '',
        points: 1,
        reason: ''
    };
    showAssignPointsModal.value = true;
};

const closeAssignPoints = () => {
    showAssignPointsModal.value = false;
};

const submitPointAssignment = async () => {
    try {
        if (!assignPointsForm.value.recipient_id || !assignPointsForm.value.reason.trim()) {
            alert('Please fill in all required fields');
            return;
        }

        const response = await axios.post('/api/point-assignments', assignPointsForm.value);
        
        alert('Points assigned successfully!');
        closeAssignPoints();
        
        // Refresh data
        await loadAdminData();
        
    } catch (error) {
        console.error('Error assigning points:', error);
        alert('Error assigning points. Please try again.');
    }
};

const loadPointLogs = async () => {
    try {
        const response = await axios.get('/api/point-assignments/logs');
        pointLogs.value = response.data;
    } catch (error) {
        console.error('Error loading point logs:', error);
        // Fallback demo data
        pointLogs.value = [
            {
                id: 1,
                assignor_name: 'John Admin',
                recipient_name: 'Jane Supervisor',
                points: 5,
                reason: 'Excellent system management',
                status: 'verified',
                created_at: new Date().toISOString()
            },
            {
                id: 2,
                assignor_name: 'Mike User',
                recipient_name: 'Tom Employee',
                points: -2,
                reason: 'Attendance improvement needed',
                status: 'pending',
                created_at: new Date(Date.now() - 86400000).toISOString()
            }
        ];
    }
};

const openPointLogsModal = async () => {
    await loadPointLogs();
    showPointLogsModal.value = true;
};

const closePointLogsModal = () => {
    showPointLogsModal.value = false;
    activeLogFilter.value = 'all';
};

const setLogFilter = (filter) => {
    activeLogFilter.value = filter;
};

const openUserManagement = () => {
    showUserManagementModal.value = true;
};

const closeUserManagement = () => {
    showUserManagementModal.value = false;
    selectedUser.value = null;
    userRoleUpdate.value = '';
    userAction.value = '';
};

const selectUserForAction = (user, action) => {
    selectedUser.value = user;
    userAction.value = action;
    if (action === 'role') {
        userRoleUpdate.value = user.role;
    }
};

const executeUserAction = async () => {
    if (!selectedUser.value || !userAction.value) return;
    
    try {
        if (userAction.value === 'role') {
            await updateUserRole();
        } else if (userAction.value === 'verify') {
            await verifyUser();
        } else if (userAction.value === 'delete') {
            await deleteUser();
        }
    } catch (error) {
        console.error(`Error executing ${userAction.value} action:`, error);
        alert(`Error executing ${userAction.value} action. Please try again.`);
    }
};

const updateUserRole = async () => {
    if (!selectedUser.value || !userRoleUpdate.value) return;
    
    try {
        const response = await axios.patch(`/api/admin/users/${selectedUser.value.id}/role`, {
            role: userRoleUpdate.value
        });
        
        // Update local data
        const userIndex = users.value.findIndex(u => u.id === selectedUser.value.id);
        if (userIndex !== -1) {
            users.value[userIndex].role = userRoleUpdate.value;
        }
        
        alert(`User role updated to ${userRoleUpdate.value} successfully!`);
        selectedUser.value = null;
        userAction.value = '';
        userRoleUpdate.value = '';
        
    } catch (error) {
        console.error('Error updating user role:', error);
        alert('Error updating user role. Please try again.');
    }
};

// NEW: Verify User
const verifyUser = async () => {
    if (!selectedUser.value) return;
    
    try {
        const response = await axios.patch(`/api/admin/users/${selectedUser.value.id}/verify`);
        
        // Update local data
        const userIndex = users.value.findIndex(u => u.id === selectedUser.value.id);
        if (userIndex !== -1) {
            users.value[userIndex].email_verified_at = new Date();
        }
        
        alert(`${selectedUser.value.name} has been verified successfully!`);
        selectedUser.value = null;
        userAction.value = '';
        
    } catch (error) {
        console.error('Error verifying user:', error);
        alert('Error verifying user. Please try again.');
    }
};

// NEW: Delete User
const deleteUser = async () => {
    if (!selectedUser.value) return;
    
    if (!confirm(`Are you sure you want to delete ${selectedUser.value.name}? This action cannot be undone.`)) {
        return;
    }
    
    try {
        const response = await axios.delete(`/api/admin/users/${selectedUser.value.id}`);
        
        // Remove from local data
        users.value = users.value.filter(u => u.id !== selectedUser.value.id);
        
        alert(`${selectedUser.value.name} has been deleted successfully!`);
        selectedUser.value = null;
        userAction.value = '';
        
    } catch (error) {
        console.error('Error deleting user:', error);
        alert('Error deleting user. Please try again.');
    }
};

const openPendingPoints = () => {
    showPendingPointsModal.value = true;
};

const closePendingPoints = () => {
    showPendingPointsModal.value = false;
    processingAssignment.value = null;
};

const processPointAssignment = async (assignment, action) => {
    if (processingAssignment.value === assignment.id) return;
    
    processingAssignment.value = assignment.id;
    
    try {
        const endpoint = action === 'approve' ? 'approve' : 'reject';
        await axios.patch(`/api/point-assignments/${assignment.id}/${endpoint}`);
        
        // Remove from pending list
        pendingAssignments.value = pendingAssignments.value.filter(p => p.id !== assignment.id);
        
        alert(`Point assignment ${action}d successfully!`);
        
        // Refresh chart data
        await loadChartData();
        
    } catch (error) {
        console.error(`Error ${action}ing assignment:`, error);
        alert(`Error ${action}ing assignment. Please try again.`);
    } finally {
        processingAssignment.value = null;
    }
};

const openReports = async () => {
    try {
        const response = await axios.get('/api/admin/reports');
        reportData.value = response.data;
        showReportsModal.value = true;
    } catch (error) {
        console.error('Error loading reports:', error);
        // Use current users as fallback
        reportData.value = users.value;
        showReportsModal.value = true;
    }
};

const closeReports = () => {
    showReportsModal.value = false;
    reportFilter.value = 'all';
};

const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
};

const getPointClass = (points) => {
    if (points > 0) {
        return points >= 5 ? 'text-green-700 font-bold' : 'text-green-600';
    } else {
        return points <= -3 ? 'text-red-700 font-bold' : 'text-red-600';
    }
};

const getRoleColor = (role) => {
    switch (role) {
        case 'admin': return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300';
        case 'supervisor': return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300';
        case 'user': return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
        default: return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
    }
};

const getStatusClass = (status) => {
    switch (status) {
        case 'verified': return 'text-green-600 bg-green-100 dark:bg-green-900 dark:text-green-400';
        case 'pending': return 'text-yellow-600 bg-yellow-100 dark:bg-yellow-900 dark:text-yellow-400';
        case 'rejected': return 'text-red-600 bg-red-100 dark:bg-red-900 dark:text-red-400';
        default: return 'text-gray-600 bg-gray-100 dark:bg-gray-900 dark:text-gray-400';
    }
};

const getActionIcon = (assignment) => {
    if (assignment.points > 0) return '➕';
    if (assignment.points < 0) return '➖';
    return '📝';
};

const isUserVerified = (user) => {
    return user.email_verified_at !== null;
};

onMounted(() => {
    loadAdminData();
});
</script>

<template>
    <Head title="Admin Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
            
            <!-- Admin Header -->
            <div class="bg-gradient-to-r from-purple-600 to-blue-600 text-white rounded-xl p-6">
                <h1 class="text-2xl font-bold">Admin Dashboard</h1>
                <p class="text-purple-100">Manage users, review points, and oversee system operations</p>
            </div>

            <!-- TESTING - HUGE OBVIOUS CHANGE -->
            <div class="bg-red-500 text-white text-center p-8 rounded-xl mb-4">
                <h1 class="text-4xl font-bold">🚨 TESTING CHANGES 🚨</h1>
                <p class="text-xl">If you see this RED BOX, you're editing the RIGHT FILE!</p>
                <p class="text-lg">If you don't see this, you're editing the WRONG FILE!</p>
            </div>

            <!-- Admin Header - CHANGED TEXT -->
            <div class="bg-gradient-to-r from-purple-600 to-blue-600 text-white rounded-xl p-6">
                <h1 class="text-2xl font-bold">🔥 TEST DASHBOARD - CHANGES WORK! 🔥</h1>
                <p class="text-purple-100">THIS TEXT WAS CHANGED TO TEST IF UPDATES ARE WORKING</p>
            </div>

            <!-- COMPLETELY DIFFERENT LAYOUT FOR TESTING -->
            <div class="bg-yellow-400 text-black p-8 rounded-xl text-center">
                <h2 class="text-3xl font-bold mb-4">⚡ LAYOUT COMPLETELY CHANGED ⚡</h2>
                <p class="text-xl mb-4">Old layout removed - this yellow box should be obvious!</p>
                
                <!-- Test buttons in a single row -->
                <div class="flex gap-4 justify-center">
                    <button 
                        @click="openAssignPoints"
                        class="bg-blue-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-blue-700"
                    >
                        🎯 ASSIGN POINTS
                    </button>
                    <button 
                        @click="openPointLogsModal"
                        class="bg-green-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-green-700"
                    >
                        📊 VIEW HISTORY
                    </button>
                    <button 
                        @click="openPendingPoints"
                        class="bg-yellow-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-yellow-700"
                    >
                        ⏳ REVIEW PENDING
                    </button>
                    <button 
                        @click="openUserManagement"
                        class="bg-purple-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-purple-700"
                    >
                        👑 MANAGE USERS
                    </button>
                </div>
            </div>

            <!-- Enhanced Recent Activity Feed -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-300 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold mb-4">Recent System Activity</h3>
                <div v-if="loading" class="text-center py-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto"></div>
                    <p class="mt-2 text-gray-500">Loading system data...</p>
                </div>
                <div v-else class="space-y-3">
                    <div 
                        v-for="activity in recentActivity.slice(0, 8)" 
                        :key="activity.id"
                        :class="[
                            'border-l-4 pl-4 py-3 rounded-r-lg',
                            activity.status === 'verified' ? 'border-green-500 bg-green-50 dark:bg-green-900/20' :
                            activity.status === 'pending' ? 'border-yellow-500 bg-yellow-50 dark:bg-yellow-900/20' :
                            'border-red-500 bg-red-50 dark:bg-red-900/20'
                        ]"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-lg">{{ getActionIcon(activity) }}</span>
                                    <p class="font-medium text-sm">
                                        {{ activity.assignor?.name || 'Unknown' }} → {{ activity.recipient?.name || 'Unknown' }}
                                    </p>
                                    <span :class="['font-bold text-sm', getPointClass(activity.points)]">
                                        {{ activity.points > 0 ? '+' : '' }}{{ activity.points }}
                                    </span>
                                </div>
                                <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">{{ activity.reason }}</p>
                                <p class="text-xs text-gray-500">{{ formatDate(activity.created_at) }}</p>
                            </div>
                            <span :class="['px-2 py-1 rounded text-xs font-medium ml-4', getStatusClass(activity.status)]">
                                {{ activity.status?.toUpperCase() }}
                            </span>
                        </div>
                    </div>
                    <div v-if="recentActivity.length === 0" class="text-center py-8 text-gray-500">
                        <span class="text-4xl mb-4 block">📋</span>
                        <p>No recent activity to display</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- NEW: Assign Points Modal -->
        <div 
            v-if="showAssignPointsModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
            @click.self="closeAssignPoints"
        >
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-md m-4">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-semibold">Assign Points</h3>
                    <button @click="closeAssignPoints" class="text-gray-500 hover:text-gray-700">✕</button>
                </div>

                <form @submit.prevent="submitPointAssignment" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Recipient</label>
                        <select 
                            v-model="assignPointsForm.recipient_id" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600"
                            required
                        >
                            <option value="">Select a user...</option>
                            <option v-for="user in eligibleUsers" :key="user.id" :value="user.id">
                                {{ user.name }} ({{ user.role }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Points (-5 to +5)</label>
                        <input 
                            v-model.number="assignPointsForm.points" 
                            type="number" 
                            min="-5" 
                            max="5" 
                            step="1"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600"
                            required
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Reason</label>
                        <textarea 
                            v-model="assignPointsForm.reason" 
                            rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600"
                            placeholder="Explain why you're assigning these points..."
                            required
                        ></textarea>
                    </div>

                    <div class="flex gap-3 justify-end">
                        <button 
                            type="button"
                            @click="closeAssignPoints"
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                        >
                            Assign Points
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Point History Modal -->
        <div 
            v-if="showPointLogsModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
            @click.self="closePointLogsModal"
        >
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-5xl m-4 max-h-[80vh] overflow-hidden animate-fade-in">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-semibold">System Point Assignment History</h3>
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
                        v-for="filter in ['all', 'pending', 'verified', 'rejected', 'positive', 'negative']"
                        :key="filter"
                        @click="setLogFilter(filter)"
                        :class="[
                            'px-4 py-2 rounded-lg font-medium transition-colors text-sm',
                            activeLogFilter === filter 
                                ? 'bg-blue-600 text-white' 
                                : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600'
                        ]"
                    >
                        {{ filter === 'all' ? 'All Assignments' : 
                           filter === 'pending' ? 'Pending' : 
                           filter === 'verified' ? 'Verified' :
                           filter === 'rejected' ? 'Rejected' :
                           filter === 'positive' ? 'Positive Points' :
                           'Negative Points' }}
                    </button>
                </div>

                <!-- Logs Content -->
                <div class="overflow-y-auto max-h-96 space-y-3">
                    <div v-if="filteredPointLogs.length === 0" class="text-center py-8 text-gray-500">
                        <span class="text-4xl mb-4 block">🔍</span>
                        <p>No assignments found for selected filter</p>
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
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <p class="font-medium">{{ log.assignor_name }} → {{ log.recipient_name }}</p>
                                    <span :class="['text-lg font-bold', getPointClass(log.points)]">
                                        {{ log.points > 0 ? '+' : '' }}{{ log.points }}
                                    </span>
                                </div>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">{{ formatDate(log.created_at) }}</p>
                                <p class="text-gray-700 dark:text-gray-300 text-sm">{{ log.reason }}</p>
                            </div>
                            <span :class="['px-2 py-1 rounded text-xs font-medium ml-4', getStatusClass(log.status)]">
                                {{ log.status?.toUpperCase() }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced User Management Modal -->
        <div 
            v-if="showUserManagementModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
            @click.self="closeUserManagement"
        >
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-6xl m-4 max-h-[80vh] overflow-hidden">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-semibold">🚀 ENHANCED User Management</h3>
                    <button @click="closeUserManagement" class="text-gray-500 hover:text-gray-700">✕</button>
                </div>

                <div class="overflow-y-auto max-h-96">
                    <table class="w-full">
                        <thead class="bg-gray-50 dark:bg-gray-700 sticky top-0">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Points</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                            <tr v-for="user in users" :key="user.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-4 py-4">
                                    <div>
                                        <p class="font-medium">{{ user.name }}</p>
                                        <p class="text-sm text-gray-500">{{ user.email }}</p>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <span :class="['px-2 py-1 rounded text-xs font-medium', getRoleColor(user.role)]">
                                        {{ user.role.toUpperCase() }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <span :class="[
                                        'px-2 py-1 rounded text-xs font-medium',
                                        isUserVerified(user) 
                                            ? 'bg-green-100 text-green-800' 
                                            : 'bg-yellow-100 text-yellow-800'
                                    ]">
                                        {{ isUserVerified(user) ? '✅ VERIFIED' : '❌ UNVERIFIED' }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="font-semibold">{{ user.total_verified_points || 0 }}</span>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex gap-2">
                                        <button 
                                            @click="selectUserForAction(user, 'role')"
                                            class="bg-blue-500 text-white px-3 py-1 rounded text-sm hover:bg-blue-600 font-medium"
                                        >
                                            📝 Role
                                        </button>
                                        <button 
                                            v-if="!isUserVerified(user)"
                                            @click="selectUserForAction(user, 'verify')"
                                            class="bg-green-500 text-white px-3 py-1 rounded text-sm hover:bg-green-600 font-medium"
                                        >
                                            ✅ Verify
                                        </button>
                                        <button 
                                            @click="selectUserForAction(user, 'delete')"
                                            class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600 font-medium"
                                        >
                                            🗑️ Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Enhanced Action Section -->
                <div v-if="selectedUser" class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                    <!-- Role Change -->
                    <div v-if="userAction === 'role'">
                        <h4 class="font-medium mb-3">🔄 Change Role for {{ selectedUser.name }}</h4>
                        <div class="flex items-center gap-4">
                            <select v-model="userRoleUpdate" class="px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                                <option value="user">👤 User</option>
                                <option value="supervisor">👨‍💼 Supervisor</option>
                                <option value="admin">👑 Admin</option>
                            </select>
                            <button 
                                @click="executeUserAction"
                                class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 font-medium"
                            >
                                ✅ Update Role
                            </button>
                            <button 
                                @click="selectedUser = null; userAction = ''"
                                class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600"
                            >
                                ❌ Cancel
                            </button>
                        </div>
                    </div>

                    <!-- Verify User -->
                    <div v-else-if="userAction === 'verify'">
                        <h4 class="font-medium mb-3">✅ Verify {{ selectedUser.name }}</h4>
                        <p class="text-sm text-gray-600 mb-4">This will mark the user as email verified and grant full access.</p>
                        <div class="flex items-center gap-4">
                            <button 
                                @click="executeUserAction"
                                class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 font-medium"
                            >
                                ✅ Verify User
                            </button>
                            <button 
                                @click="selectedUser = null; userAction = ''"
                                class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600"
                            >
                                ❌ Cancel
                            </button>
                        </div>
                    </div>

                    <!-- Delete User -->
                    <div v-else-if="userAction === 'delete'">
                        <h4 class="font-medium mb-3 text-red-600">🗑️ Delete {{ selectedUser.name }}</h4>
                        <p class="text-sm text-red-600 mb-4">⚠️ This action cannot be undone. All user data and point history will be permanently deleted.</p>
                        <div class="flex items-center gap-4">
                            <button 
                                @click="executeUserAction"
                                class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 font-medium"
                            >
                                🗑️ Delete User
                            </button>
                            <button 
                                @click="selectedUser = null; userAction = ''"
                                class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600"
                            >
                                ❌ Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Points Modal -->
        <div 
            v-if="showPendingPointsModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
            @click.self="closePendingPoints"
        >
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-4xl m-4 max-h-[80vh] overflow-hidden">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-semibold">⏳ Pending Point Assignments</h3>
                    <button @click="closePendingPoints" class="text-gray-500 hover:text-gray-700">✕</button>
                </div>

                <div class="overflow-y-auto max-h-96 space-y-4">
                    <div v-if="pendingAssignments.length === 0" class="text-center py-8 text-gray-500">
                        <span class="text-4xl mb-4 block">✅</span>
                        <p>No pending assignments to review</p>
                    </div>
                    
                    <div 
                        v-for="assignment in pendingAssignments" 
                        :key="assignment.id"
                        class="border rounded-lg p-4 bg-gray-50 dark:bg-gray-700"
                    >
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <p class="font-medium">
                                    {{ assignment.assignor.name }} → {{ assignment.recipient.name }}
                                </p>
                                <p class="text-sm text-gray-500">{{ formatDate(assignment.created_at) }}</p>
                            </div>
                            <span :class="['text-lg font-bold', getPointClass(assignment.points)]">
                                {{ assignment.points > 0 ? '+' : '' }}{{ assignment.points }} points
                            </span>
                        </div>
                        
                        <p class="text-gray-700 dark:text-gray-300 mb-4">{{ assignment.reason }}</p>
                        
                        <div class="flex gap-3">
                            <button 
                                @click="processPointAssignment(assignment, 'approve')"
                                :disabled="processingAssignment === assignment.id"
                                class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 disabled:opacity-50 font-medium"
                            >
                                {{ processingAssignment === assignment.id ? '⏳ Processing...' : '✅ Approve' }}
                            </button>
                            <button 
                                @click="processPointAssignment(assignment, 'reject')"
                                :disabled="processingAssignment === assignment.id"
                                class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 disabled:opacity-50 font-medium"
                            >
                                {{ processingAssignment === assignment.id ? '⏳ Processing...' : '❌ Reject' }}
                            </button>
                        </div>
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

/* Enhanced hover effects for action boxes */
.hover\:scale-105:hover {
    transform: scale(1.05);
}
</style>