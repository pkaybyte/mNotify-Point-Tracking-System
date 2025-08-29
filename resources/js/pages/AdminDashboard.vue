<script setup>
import { onMounted, ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import axios from 'axios';

// Chart.js imports
import { Chart as ChartJS, Title, Tooltip, Legend, ArcElement } from 'chart.js';
import { Doughnut } from 'vue-chartjs';
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
    totalPointsAssigned: 0,
    verifiedPoints: 0,
    unverifiedPoints: 0
});
const loading = ref(true);

// Chart data for verified vs unverified points
const chartData = computed(() => {
    const verified = Math.abs(systemStats.value.verifiedPoints);
    const unverified = Math.abs(systemStats.value.unverifiedPoints); 
    const total = verified + unverified;
    
    if (total === 0) {
        return {
            labels: ['No Data'],
            datasets: [{
                data: [1],
                backgroundColor: ['#e5e7eb'],
                borderWidth: 0
            }],
            isEmpty: true
        };
    }

    const verifiedPercentage = Math.round((verified / total) * 100);
    const unverifiedPercentage = Math.round((unverified / total) * 100);

    return {
        labels: [`Verified (${verifiedPercentage}%)`, `Unverified (${unverifiedPercentage}%)`],
        datasets: [{
            data: [verified, unverified],
            backgroundColor: [
                '#10b981', // green for verified
                '#f59e0b'  // yellow for unverified
            ],
            borderWidth: 0,
            hoverOffset: 4
        }],
        isEmpty: false,
        stats: {
            verified,
            unverified,
            verifiedPercentage,
            unverifiedPercentage,
            total
        }
    };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'bottom',
            labels: {
                padding: 20,
                usePointStyle: true,
                font: {
                    size: 12
                }
            }
        },
        tooltip: {
            callbacks: {
                label: (context) => {
                    const value = context.parsed;
                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                    const percentage = Math.round((value / total) * 100);
                    return `${context.label}: ${value} points (${percentage}%)`;
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
const showAddUserModal = ref(false);
const newUserForm = ref({
    name: '',
    email: '',
    password: '',
    role: 'user'
});

// Point Assignment Modal
const showAssignPointsModal = ref(false);
const assignPointsForm = ref({
    recipient_id: '',
    points: 1,
    reason: '',
    categories: []
});
const searchQuery = ref('');
const selectedAssignUser = ref(null);
const showUserDropdown = ref(false);

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

// Pending Points Management
const showPendingPointsModal = ref(false);
const processingAssignment = ref(null);

// Computed properties
const pendingCount = computed(() => pendingAssignments.value.length);

const eligibleUsers = computed(() => {
    return users.value.filter(user => user.role !== 'admin' && user.id !== 3); // Exclude admins and current user
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

// Filtered users for search
const filteredUsers = computed(() => {
    if (!searchQuery.value.trim()) {
        return eligibleUsers.value;
    }
    return eligibleUsers.value.filter(u => 
        u.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        u.email.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
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
        
        loading.value = false;
    } catch (error) {
        console.error('Error loading admin data:', error);
        // Set empty arrays if API calls fail
        users.value = [];
        pendingAssignments.value = [];
        systemStats.value = {
            totalUsers: 0,
            totalSupervisors: 0,
            totalPendingPoints: 0,
            totalPointsAssigned: 0,
            verifiedPoints: 0,
            unverifiedPoints: 0
        };
        recentActivity.value = [];
        
        loading.value = false;
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
    assignPointsForm.value = { 
        recipient_id: '', 
        points: 1, 
        reason: '',
        categories: []
    };
    searchQuery.value = '';
    selectedAssignUser.value = null;
    showUserDropdown.value = false;
    customCategory.value = '';
    showAddCategory.value = false;
};

const submitPointAssignment = async () => {
    try {
        if (!assignPointsForm.value.recipient_id || !assignPointsForm.value.reason.trim()) {
            alert('Please fill in all required fields');
            return;
        }

        if (assignPointsForm.value.categories.length === 0) {
            alert('Please select at least one category');
            return;
        }

        const response = await axios.post('/api/point-assignments', assignPointsForm.value);
        
        alert('Points assigned successfully!');
        closeAssignPoints();
        
        // Auto-reload the page after successful point assignment
        setTimeout(() => {
            window.location.reload();
        }, 1000);
        
    } catch (error) {
        console.error('Error assigning points:', error);
        alert('Error assigning points. Please try again.');
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
        
    } catch (error) {
        console.error(`Error ${action}ing assignment:`, error);
        alert(`Error ${action}ing assignment. Please try again.`);
    } finally {
        processingAssignment.value = null;
    }
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

// Add User functionality
const openAddUserModal = () => {
    newUserForm.value = {
        name: '',
        email: '',
        password: '',
        role: 'user'
    };
    showAddUserModal.value = true;
};

const closeAddUserModal = () => {
    showAddUserModal.value = false;
    newUserForm.value = {
        name: '',
        email: '',
        password: '',
        role: 'user'
    };
};

const submitAddUser = async () => {
    try {
        if (!newUserForm.value.name.trim() || !newUserForm.value.email.trim() || !newUserForm.value.password.trim()) {
            alert('Please fill in all required fields');
            return;
        }

        if (newUserForm.value.password.length < 8) {
            alert('Password must be at least 8 characters long');
            return;
        }

        const response = await axios.post('/api/admin/users', {
            name: newUserForm.value.name.trim(),
            email: newUserForm.value.email.trim(),
            password: newUserForm.value.password,
            role: newUserForm.value.role
        });

        if (response.status === 201 || response.status === 200) {
            alert('User added successfully!');
            closeAddUserModal();
            await loadAdminData(); // Refresh data
        }
    } catch (error) {
        console.error('Error adding user:', error);
        const errorMessage = error.response?.data?.message || 'Error adding user. Please try again.';
        alert(errorMessage);
    }
};

// Search functionality
const selectUser = (user) => {
    selectedAssignUser.value = user;
    assignPointsForm.value.recipient_id = user.id;
    searchQuery.value = user.name;
    showUserDropdown.value = false;
};

const onSearchInput = () => {
    showUserDropdown.value = true;
    selectedAssignUser.value = null;
    assignPointsForm.value.recipient_id = '';
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
    const index = assignPointsForm.value.categories.indexOf(category);
    if (index === -1) {
        assignPointsForm.value.categories.push(category);
    } else {
        assignPointsForm.value.categories.splice(index, 1);
    }
};

const addCustomCategory = () => {
    if (customCategory.value.trim() && !assignPointsForm.value.categories.includes(customCategory.value.trim())) {
        assignPointsForm.value.categories.push(customCategory.value.trim());
        customCategory.value = '';
        showAddCategory.value = false;
    }
};

const removeCategory = (category) => {
    const index = assignPointsForm.value.categories.indexOf(category);
    if (index !== -1) {
        assignPointsForm.value.categories.splice(index, 1);
    }
};

onMounted(() => {
    loadAdminData();
});
</script>

<template>
    <Head title="Admin Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-2 sm:p-4 overflow-x-auto">
            
            <!-- Clean Layout - Similar to Normal Dashboard -->
            <div class="grid auto-rows-min gap-4 sm:grid-cols-2 lg:grid-cols-3">
                
                <!-- Welcome Section - Admin Style -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-300 dark:border-gray-700 p-6">
                    <h1 class="text-2xl font-bold mb-2">Welcome, Admin</h1>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">Role: Admin</p>
                    
                    <div class="mb-4">
                        <p class="text-xl font-semibold">System Overview: {{ systemStats.totalUsers }} Users</p>
                    </div>
                    
                    <!-- Admin Stats - Like Positive/Negative -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <span class="text-green-600 font-medium">Active: {{ systemStats.totalUsers - pendingCount }}</span>
                        </div>
                        <div>
                            <span class="text-red-600 font-medium">Pending: {{ pendingCount }}</span>
                        </div>
                    </div>
                </div>

                <!-- Points Distribution Chart -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-300 dark:border-gray-700 p-6">
                    <h2 class="text-xl font-semibold mb-4">Points Distribution</h2>
                    
                    <div v-if="loading" class="flex items-center justify-center h-48">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
                    </div>
                    
                    <div v-else-if="chartData.isEmpty" class="flex flex-col items-center justify-center h-48 text-gray-500">
                        <span class="text-4xl mb-2">📊</span>
                        <p>No point data available</p>
                    </div>
                    
                    <div v-else class="space-y-4">
                        <!-- Chart -->
                        <div class="h-48">
                            <Doughnut :data="chartData" :options="chartOptions" />
                        </div>
                        
                        <!-- Stats Summary -->
                        <div class="grid grid-cols-2 gap-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                            <div class="text-center">
                                <div class="text-2xl font-bold text-green-600">
                                    {{ chartData.stats?.verified || 0 }}
                                </div>
                                <div class="text-sm text-gray-500">Verified Points</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-yellow-600">
                                    {{ chartData.stats?.unverified || 0 }}
                                </div>
                                <div class="text-sm text-gray-500">Unverified Points</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions - 2x2 Grid -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-300 dark:border-gray-700 p-6">
                    <h2 class="text-xl font-semibold mb-4">Quick Actions</h2>
                    
                    <!-- 2x2 Button Grid -->
                    <div class="grid grid-cols-2 gap-3">
                        <button 
                            @click="openAssignPoints"
                            class="bg-gradient-to-r from-blue-500 to-blue-600 text-white p-4 rounded-lg font-medium hover:opacity-90 transition-opacity flex flex-col items-center gap-2"
                        >
                            <span class="text-2xl">🎯</span>
                            <span class="text-sm">Assign Points</span>
                        </button>
                        
                        <button 
                            @click="openPointLogsModal"
                            class="bg-gradient-to-r from-green-500 to-green-600 text-white p-4 rounded-lg font-medium hover:opacity-90 transition-opacity flex flex-col items-center gap-2"
                        >
                            <span class="text-2xl">📊</span>
                            <span class="text-sm">Point History</span>
                        </button>
                        
                        <button 
                            @click="openPendingPoints"
                            class="bg-gradient-to-r from-yellow-500 to-yellow-600 text-white p-4 rounded-lg font-medium hover:opacity-90 transition-opacity flex flex-col items-center gap-2 relative"
                        >
                            <span class="text-2xl">⏳</span>
                            <span class="text-sm">Review Pending</span>
                            <span v-if="pendingCount > 0" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                {{ pendingCount > 9 ? '9+' : pendingCount }}
                            </span>
                        </button>
                        
                        <button 
                            @click="openUserManagement"
                            class="bg-gradient-to-r from-purple-500 to-purple-600 text-white p-4 rounded-lg font-medium hover:opacity-90 transition-opacity flex flex-col items-center gap-2"
                        >
                            <span class="text-2xl">👥</span>
                            <span class="text-sm">Manage Users</span>
                        </button>
                    </div>
                </div>

            </div>

            <!-- Activity Feed - Same Style as Normal Dashboard -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-300 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold mb-4">Activity Feed</h3>
                <div v-if="loading" class="text-center py-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto"></div>
                    <p class="mt-2 text-gray-500">Loading admin data...</p>
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
                                        ASSIGNED POINT by {{ activity.assignor?.name || 'Unknown' }}
                                    </p>
                                </div>
                                <p class="text-xs text-gray-500">{{ formatDate(activity.created_at) }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-if="recentActivity.length === 0" class="text-center py-8 text-gray-500">
                        <span class="text-4xl mb-4 block">📋</span>
                        <p>No recent activity to display</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- All the existing modals remain the same... -->
        
        <!-- NEW: Assign Points Modal -->
        <div 
            v-if="showAssignPointsModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
            @click.self="closeAssignPoints"
        >
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 w-full max-w-md m-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-semibold">Assign Points</h3>
                    <button @click="closeAssignPoints" class="text-gray-500 hover:text-gray-700">✕</button>
                </div>

                <form @submit.prevent="submitPointAssignment" class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium mb-2">Recipient</label>
                        <div class="relative">
                            <input
                                v-model="searchQuery"
                                @input="onSearchInput"
                                @focus="onSearchFocus"
                                @blur="onSearchBlur"
                                type="text"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="Type to search users..."
                                required
                            >
                            <div 
                                v-if="showUserDropdown && filteredUsers.length > 0" 
                                class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg shadow-lg max-h-60 overflow-y-auto"
                            >
                                <div
                                    v-for="user in filteredUsers"
                                    :key="user.id"
                                    @mousedown.prevent="selectUser(user)"
                                    @click.prevent="selectUser(user)"
                                    class="px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-600 cursor-pointer border-b border-gray-200 dark:border-gray-600 last:border-b-0"
                                >
                                    <div class="font-medium">{{ user.name }}</div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ user.email || user.role }}</div>
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


                    <!-- Point Categories -->
                    <div>
                        <label class="block text-sm font-medium mb-2">Point Categories <span class="text-red-500">*</span></label>
                        <div class="space-y-3">
                            <!-- Selected categories -->
                            <div v-if="assignPointsForm.categories.length > 0" class="flex flex-wrap gap-2">
                                <span 
                                    v-for="category in assignPointsForm.categories" 
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
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <button
                                    v-for="category in pointCategories"
                                    :key="category"
                                    type="button"
                                    @click="toggleCategory(category)"
                                    :class="[
                                        'px-3 py-2 text-sm border rounded-lg transition-colors text-left',
                                        assignPointsForm.categories.includes(category)
                                            ? 'bg-blue-100 border-blue-500 text-blue-800'
                                            : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-600'
                                    ]"
                                >
                                    {{ category }}
                                </button>
                            </div>
                            
                        </div>
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
                    <div class="flex items-center gap-4">
                        <h3 class="text-xl font-semibold">User Management</h3>
                        <button 
                            @click="openAddUserModal"
                            class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors font-medium flex items-center gap-2"
                        >
                            <span>➕</span>
                            Add User
                        </button>
                    </div>
                    <button @click="closeUserManagement" class="text-gray-500 hover:text-gray-700">✕</button>
                </div>

                <div class="overflow-y-auto max-h-96">
                    <table class="w-full">
                        <thead class="bg-gray-50 dark:bg-gray-700 sticky top-0">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
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
                                        {{ isUserVerified(user) ? '✅ VERIFIED' : '✅ VERIFIED' }}
                                    </span>
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

        <!-- Add User Modal -->
        <div 
            v-if="showAddUserModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
            @click.self="closeAddUserModal"
        >
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 w-full max-w-md m-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-semibold text-green-600">Add New User</h3>
                    <button @click="closeAddUserModal" class="text-gray-500 hover:text-gray-700">✕</button>
                </div>
                
                <form @submit.prevent="submitAddUser" class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium mb-2">Full Name <span class="text-red-500">*</span></label>
                        <input
                            v-model="newUserForm.name"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 focus:ring-2 focus:ring-green-500 focus:border-transparent"
                            placeholder="Enter full name"
                            required
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">Email Address <span class="text-red-500">*</span></label>
                        <input
                            v-model="newUserForm.email"
                            type="email"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 focus:ring-2 focus:ring-green-500 focus:border-transparent"
                            placeholder="Enter email address"
                            required
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">Password <span class="text-red-500">*</span></label>
                        <input
                            v-model="newUserForm.password"
                            type="password"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 focus:ring-2 focus:ring-green-500 focus:border-transparent"
                            placeholder="Enter password (min 8 characters)"
                            minlength="8"
                            required
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">Role</label>
                        <select 
                            v-model="newUserForm.role"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 focus:ring-2 focus:ring-green-500 focus:border-transparent"
                        >
                            <option value="user">👤 User</option>
                            <option value="supervisor">👨‍💼 Supervisor</option>
                            <option value="admin">👑 Admin</option>
                        </select>
                    </div>
                    
                    <div class="flex gap-3 pt-4">
                        <button 
                            type="button" 
                            @click="closeAddUserModal"
                            class="flex-1 px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors font-medium"
                        >
                            Add User
                        </button>
                    </div>
                </form>
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