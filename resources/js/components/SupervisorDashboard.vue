<script setup>
import { onMounted, ref, computed, watchEffect } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import axios from 'axios';

// Chart.js imports
import { Chart as ChartJS, Title, Tooltip, Legend, ArcElement } from 'chart.js';
ChartJS.register(Title, Tooltip, Legend, ArcElement);

const breadcrumbs = [
    { title: 'Supervisor Dashboard', href: '/supervisor-dashboard' }
];

// Supervisor-specific refs
const pendingAssignments = ref([]);
const myAssignments = ref([]);
const myReceivedAssignments = ref([]);
const currentUser = ref({ name: 'Supervisor' }); // Store current user info
const teamStats = ref({
    totalTeamMembers: 0,
    pendingReviews: 0,
    approvedThisWeek: 0,
    averageTeamPoints: 0
});
const loading = ref(true);
const processingAssignment = ref(null);

// Chart refs
const chartCanvas = ref(null);
const loadingChart = ref(true);

// Modals
const showAssignPointsModal = ref(false);
const showPointLogsModal = ref(false);
const showPendingPointsModal = ref(false);

// Point Assignment Form - Enhanced like user dashboard
const assignPointsForm = ref({
    recipient_id: '',
    points: 1,
    reason: '',
    point_type: 'positive'
});
const isSubmitting = ref(false);

// Point presets for quick assignment (from user dashboard)
const pointPresets = [
    { value: 1, label: '+1 (Good work)', type: 'positive', color: 'bg-green-500' },
    { value: 2, label: '+2 (Great job)', type: 'positive', color: 'bg-green-600' },
    { value: 5, label: '+5 (Excellent)', type: 'positive', color: 'bg-green-700' },
    { value: -1, label: '-1 (Minor issue)', type: 'negative', color: 'bg-red-500' },
    { value: -2, label: '-2 (Needs improvement)', type: 'negative', color: 'bg-red-600' },
    { value: -5, label: '-5 (Significant issue)', type: 'negative', color: 'bg-red-700' },
];

// Point History
const pointLogs = ref([]);
const activeLogFilter = ref('all');

// Users for assignment
const users = ref([]);

// Filters and sorting - Simplified
const sortBy = ref('newest');

// Chart data & options
const chartData = {
    labels: ['Verified Points', 'Unverified Points'],
    datasets: [
        {
            label: 'Points',
            data: [0, 0],
            backgroundColor: ['#36A2EB', '#FF6384']
        }
    ]
};

const chartOptions = {
    responsive: true,
    plugins: {
        legend: { position: 'bottom' }
    }
};

// Computed properties for supervisor stats - Updated to show positive/negative
const totalReceivedPoints = computed(() => {
    return myReceivedAssignments.value
        .filter(assignment => assignment.status === 'verified')
        .reduce((total, assignment) => total + assignment.points, 0);
});

const positivePointsCount = computed(() => {
    return myReceivedAssignments.value
        .filter(assignment => assignment.status === 'verified' && assignment.points > 0)
        .reduce((total, assignment) => total + assignment.points, 0);
});

const negativePointsCount = computed(() => {
    return Math.abs(myReceivedAssignments.value
        .filter(assignment => assignment.status === 'verified' && assignment.points < 0)
        .reduce((total, assignment) => total + assignment.points, 0));
});

const pendingReceivedPoints = computed(() => {
    return myReceivedAssignments.value
        .filter(assignment => assignment.status === 'pending')
        .reduce((total, assignment) => total + assignment.points, 0);
});

// Computed for supervisor's personal chart data
const supervisorChartData = computed(() => {
    const verifiedPoints = Math.abs(totalReceivedPoints.value);
    const unverifiedPoints = Math.abs(pendingReceivedPoints.value);
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

const filteredAssignments = computed(() => {
    const assignments = pendingAssignments.value;
    
    // Sort assignments
    assignments.sort((a, b) => {
        switch (sortBy.value) {
            case 'newest':
                return new Date(b.created_at) - new Date(a.created_at);
            case 'oldest':
                return new Date(a.created_at) - new Date(b.created_at);
            case 'highest':
                return Math.abs(b.points) - Math.abs(a.points);
            case 'lowest':
                return Math.abs(a.points) - Math.abs(b.points);
            default:
                return 0;
        }
    });
    
    return assignments;
});

const pendingCount = computed(() => pendingAssignments.value.length);

const urgentAssignments = computed(() => {
    const threeDaysAgo = new Date(Date.now() - 3 * 24 * 60 * 60 * 1000);
    return pendingAssignments.value.filter(assignment => 
        new Date(assignment.created_at) < threeDaysAgo
    );
});

// Methods
const loadSupervisorData = async () => {
    try {
        loading.value = true;
        
        // Load current user information
        try {
            const currentUserRes = await axios.get('/api/user');
            currentUser.value = currentUserRes.data;
        } catch (error) {
            console.error('Error loading current user:', error);
            currentUser.value = { name: 'Supervisor' };
        }
        
        // Load users for point assignment
        try {
            const usersRes = await axios.get('/api/admin/users');
            users.value = usersRes.data;
        } catch (error) {
            console.error('Error loading users:', error);
            users.value = [];
        }
        
        // Load pending assignments for review
        try {
            const pendingRes = await axios.get('/api/point-assignments/pending');
            pendingAssignments.value = pendingRes.data.pending_assignments || [];
        } catch (error) {
            console.error('Error loading pending assignments:', error);
            pendingAssignments.value = [];
        }
        
        // Load supervisor's own assignments
        try {
            const myRes = await axios.get('/api/point-assignments/my');
            myAssignments.value = myRes.data;
        } catch (error) {
            console.error('Error loading my assignments:', error);
            myAssignments.value = [];
        }
        
        // Load points received by supervisor
        try {
            const receivedRes = await axios.get('/api/point-assignments/received');
            myReceivedAssignments.value = receivedRes.data || [];
        } catch (error) {
            console.error('Error loading received assignments:', error);
            myReceivedAssignments.value = [];
        }
        
        // Load team statistics
        try {
            const statsRes = await axios.get('/api/supervisor/stats');
            teamStats.value = statsRes.data;
        } catch (error) {
            console.error('Error loading stats:', error);
            teamStats.value = {
                totalTeamMembers: 0,
                pendingReviews: pendingAssignments.value.length,
                approvedThisWeek: 0,
                averageTeamPoints: 0
            };
        }
        
        loadingChart.value = false;
        loading.value = false;
        
        // Render chart with supervisor's personal data
        setTimeout(() => {
            if (chartCanvas.value) {
                const chartDataValues = supervisorChartData.value;
                const personalChartData = {
                    labels: ['Verified Points', 'Unverified Points'],
                    datasets: [
                        {
                            label: 'My Points',
                            data: [chartDataValues.verified, chartDataValues.unverified],
                            backgroundColor: ['#10B981', '#F59E0B']
                        }
                    ]
                };
                
                new ChartJS(chartCanvas.value, {
                    type: 'pie',
                    data: personalChartData,
                    options: chartOptions
                });
            }
        }, 100);
        
    } catch (error) {
        console.error('Error loading supervisor data:', error);
        loading.value = false;
        loadingChart.value = false;
    }
};

// Fixed processPointAssignment function
const processPointAssignment = async (assignment, action) => {
    if (processingAssignment.value === assignment.id) return;
    
    processingAssignment.value = assignment.id;
    
    try {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        const endpoint = action === 'approve' ? 'approve' : 'reject';
        const response = await axios.patch(`/api/point-assignments/${assignment.id}/${endpoint}`, {}, {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token
            }
        });
        
        // Update local state only
        pendingAssignments.value = pendingAssignments.value.filter(p => p.id !== assignment.id);
        
        // Update stats locally instead of reloading
        if (action === 'approve') {
            teamStats.value.approvedThisWeek++;
        }
        teamStats.value.pendingReviews = Math.max(0, teamStats.value.pendingReviews - 1);
        
        alert(`Point assignment ${action}d successfully!`);
        
    } catch (error) {
        console.error(`Error ${action}ing assignment:`, error);
        const errorMessage = error.response?.data?.message || `Error ${action}ing assignment. Please try again.`;
        alert(errorMessage);
    } finally {
        processingAssignment.value = null;
    }
};

// Assign Points functionality - Enhanced like user dashboard
const openAssignPoints = () => {
    assignPointsForm.value = {
        recipient_id: '',
        points: 1,
        reason: '',
        point_type: 'positive'
    };
    showAssignPointsModal.value = true;
};

const closeAssignPoints = () => {
    showAssignPointsModal.value = false;
    assignPointsForm.value = { 
        recipient_id: '', 
        points: 1, 
        reason: '',
        point_type: 'positive'
    };
};

// Handle preset selection (from user dashboard)
const selectPointPreset = (preset) => {
    assignPointsForm.value.points = preset.value;
    assignPointsForm.value.point_type = preset.type;
};

// Handle manual point input (from user dashboard)
const handlePointsChange = () => {
    const points = assignPointsForm.value.points;
    assignPointsForm.value.point_type = points >= 0 ? 'positive' : 'negative';
};

const submitPointAssignment = async () => {
    if (!assignPointsForm.value.recipient_id || !assignPointsForm.value.reason.trim()) {
        alert('Please fill in all required fields');
        return;
    }

    if (assignPointsForm.value.reason.trim().length < 10) {
        alert('Please provide a more detailed reason (at least 10 characters)');
        return;
    }

    if (assignPointsForm.value.points < -10 || assignPointsForm.value.points > 10) {
        alert('Points must be between -10 and +10');
        return;
    }

    if (assignPointsForm.value.points === 0) {
        alert('Points cannot be zero');
        return;
    }

    isSubmitting.value = true;

    try {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        const response = await axios.post('/api/point-assignments', {
            recipient_id: parseInt(assignPointsForm.value.recipient_id),
            points: parseInt(assignPointsForm.value.points),
            reason: assignPointsForm.value.reason.trim()
        }, {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token
            }
        });

        if (response.status === 201) {
            const pointText = assignPointsForm.value.points > 0 ? 'points' : 'penalty points';
            alert(`${Math.abs(assignPointsForm.value.points)} ${pointText} assigned successfully!`);
            closeAssignPoints();
            // Add to local assignments instead of full reload
            myAssignments.value.unshift({
                ...response.data,
                assignor: { name: 'You' },
                recipient: users.value.find(u => u.id === parseInt(assignPointsForm.value.recipient_id))
            });
        }
    } catch (error) {
        console.error('Error assigning points:', error);
        const errorMessage = error.response?.data?.message || 
                           error.response?.data?.errors || 
                           'Please try again.';
        alert('Error assigning points: ' + JSON.stringify(errorMessage));
    } finally {
        isSubmitting.value = false;
    }
};

// Point Logs functionality
const loadPointLogs = async () => {
    try {
        const response = await axios.get('/api/point-assignments/logs');
        pointLogs.value = response.data;
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

// Pending Points Modal
const openPendingPoints = () => {
    showPendingPointsModal.value = true;
};

const closePendingPoints = () => {
    showPendingPointsModal.value = false;
    processingAssignment.value = null;
};

const quickApproveAll = async () => {
    if (!confirm(`Are you sure you want to approve all ${pendingCount.value} pending assignments?`)) {
        return;
    }
    
    try {
        for (const assignment of pendingAssignments.value) {
            await processPointAssignment(assignment, 'approve');
        }
        alert('All pending assignments approved successfully!');
    } catch (error) {
        console.error('Error in bulk approval:', error);
        alert('Some assignments could not be processed. Please review manually.');
    }
};

// Navigate to review pending points - Simplified
const goToReviewPending = () => {
    setTimeout(() => {
        const assignmentsSection = document.querySelector('.assignments-section');
        if (assignmentsSection) {
            assignmentsSection.scrollIntoView({ behavior: 'smooth' });
        }
    }, 100);
};

// Utility functions
const formatDate = (dateString) => {
    const date = new Date(dateString);
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

const getUrgencyLevel = (dateString) => {
    const date = new Date(dateString);
    const now = new Date();
    const diffInDays = Math.floor((now - date) / (1000 * 60 * 60 * 24));
    
    if (diffInDays >= 3) return 'urgent';
    if (diffInDays >= 1) return 'attention';
    return 'normal';
};

const getUrgencyClass = (urgency) => {
    switch (urgency) {
        case 'urgent': return 'border-red-500 bg-red-50 dark:bg-red-900/20';
        case 'attention': return 'border-yellow-500 bg-yellow-50 dark:bg-yellow-900/20';
        default: return 'border-gray-200 bg-white dark:bg-gray-800';
    }
};

const getPriorityIcon = (points) => {
    const absPoints = Math.abs(points);
    if (absPoints >= 8) return '🔥';
    if (absPoints >= 5) return '⭐';
    if (absPoints >= 3) return '📌';
    return '📝';
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

onMounted(() => {
    loadSupervisorData();
});
</script>

<template>
    <Head title="Supervisor Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
            
            <!-- Top Grid - Matching Admin Dashboard Layout -->
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                
                <!-- Supervisor Info with Stats -->
                <div class="relative aspect-video overflow-hidden rounded-xl border border-gray-300 dark:border-gray-700 p-4">
                    <h2 class="text-xl font-semibold mb-2">Welcome, {{ currentUser.name }}</h2>
                    <p class="text-gray-600 dark:text-gray-400">
                        Role: <span class="capitalize">Supervisor</span>
                    </p>
                    <div class="mt-4 space-y-2">
                        <p class="text-lg font-bold">
                            My Points: <span class="text-blue-600">{{ totalReceivedPoints }}</span>
                        </p>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div class="bg-green-50 dark:bg-green-900/20 p-2 rounded">
                                <span class="text-green-700 dark:text-green-400">Positive: +{{ positivePointsCount }}</span>
                            </div>
                            <div class="bg-red-50 dark:bg-red-900/20 p-2 rounded">
                                <span class="text-red-700 dark:text-red-400">Negative: -{{ negativePointsCount }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pie Chart -->
                <div class="relative aspect-video overflow-hidden rounded-xl border border-gray-300 dark:border-gray-700 p-4 flex items-center justify-center">
                    <canvas v-if="!loadingChart" ref="chartCanvas"></canvas>
                    <div v-else class="text-gray-500">Loading chart...</div>
                </div>

                <!-- Quick Actions (2 buttons only) -->
                <div class="relative aspect-video overflow-hidden rounded-xl border border-gray-300 dark:border-gray-700 p-4">
                    <h2 class="text-xl font-semibold mb-4">Quick Actions</h2>
                    <div class="flex flex-col gap-3">
                        <!-- Assign Point Button -->
                        <button 
                            @click="openAssignPoints"
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
                            View Point History
                        </button>
                    </div>
                </div>
            </div>

            <!-- Review Pending Alert -->
            <div v-if="pendingCount > 0" class="bg-gradient-to-r from-yellow-400 to-orange-500 text-white rounded-xl p-4 md:p-6 shadow-lg">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-3 flex-1">
                        <div class="bg-white/20 p-3 rounded-full">
                            <span class="text-white text-2xl">⏳</span>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-lg md:text-xl font-bold">
                                {{ pendingCount }} Assignment{{ pendingCount !== 1 ? 's' : '' }} Awaiting Review
                            </h3>
                            <p class="text-yellow-100 text-sm md:text-base mt-1">
                                Team members are waiting for your approval
                            </p>
                        </div>
                    </div>
                    <button 
                        @click="goToReviewPending"
                        class="w-full sm:w-auto bg-white text-yellow-600 px-6 py-3 rounded-lg hover:bg-yellow-50 transition-all duration-200 font-bold flex items-center justify-center gap-2 shadow-md hover:shadow-lg transform hover:scale-105"
                    >
                        <span class="text-lg">👀</span>
                        <span class="whitespace-nowrap">Review Now</span>
                    </button>
                </div>
            </div>

            <!-- Urgent Alerts -->
            <div v-if="urgentAssignments.length > 0" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-4">
                <div class="flex items-center gap-2 mb-2">
                    <span class="text-red-600 text-xl">🚨</span>
                    <h3 class="text-lg font-semibold text-red-800 dark:text-red-200">Urgent Attention Required</h3>
                </div>
                <p class="text-red-700 dark:text-red-300 text-sm">
                    {{ urgentAssignments.length }} assignment{{ urgentAssignments.length !== 1 ? 's' : '' }} 
                    pending for more than 3 days
                </p>
            </div>

            <!-- Controls and Filters - Simplified -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-300 dark:border-gray-700 p-4">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div class="flex flex-wrap gap-2 w-full sm:w-auto">
                        <div class="px-4 py-2 bg-yellow-600 text-white rounded-lg font-medium">
                            Pending Assignments ({{ pendingCount }})
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full sm:w-auto">
                        <select 
                            v-model="sortBy"
                            class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                        >
                            <option value="newest">Newest First</option>
                            <option value="oldest">Oldest First</option>
                            <option value="highest">Highest Points</option>
                            <option value="lowest">Lowest Points</option>
                        </select>

                        <button 
                            v-if="pendingCount > 0"
                            @click="quickApproveAll"
                            class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors font-medium whitespace-nowrap"
                        >
                            Approve All ({{ pendingCount }})
                        </button>
                    </div>
                </div>
            </div>

            <!-- Assignments List - Simplified -->
            <div class="assignments-section bg-white dark:bg-gray-800 rounded-xl border border-gray-300 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold mb-4">Pending Point Assignments</h3>

                <div v-if="loading" class="text-center py-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto"></div>
                    <p class="mt-2 text-gray-500">Loading assignments...</p>
                </div>

                <div v-else-if="filteredAssignments.length === 0" class="text-center py-8 text-gray-500">
                    <span class="text-4xl mb-4 block">📋</span>
                    <p>No pending assignments to review</p>
                </div>

                <div v-else class="space-y-4">
                    <div 
                        v-for="assignment in filteredAssignments" 
                        :key="assignment.id"
                        :class="[
                            'border rounded-xl p-5 transition-all hover:shadow-md',
                            getUrgencyClass(getUrgencyLevel(assignment.created_at))
                        ]"
                    >
                        <div class="flex flex-col sm:flex-row items-start justify-between mb-4 gap-3">
                            <div class="flex items-start gap-3 flex-1">
                                <span class="text-2xl">{{ getPriorityIcon(assignment.points) }}</span>
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-base md:text-lg break-words">
                                        {{ assignment.assignor.name }} → {{ assignment.recipient.name }}
                                    </p>
                                    <div class="flex flex-wrap items-center gap-2 text-sm text-gray-500 mt-1">
                                        <span>{{ getRelativeTime(assignment.created_at) }}</span>
                                        <span v-if="getUrgencyLevel(assignment.created_at) === 'urgent'" 
                                              class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-medium">
                                            URGENT
                                        </span>
                                        <span v-else-if="getUrgencyLevel(assignment.created_at) === 'attention'" 
                                              class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs font-medium">
                                            NEEDS ATTENTION
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center sm:text-right">
                                <span :class="['text-xl md:text-2xl font-bold', getPointClass(assignment.points)]">
                                    {{ assignment.points > 0 ? '+' : '' }}{{ assignment.points }}
                                </span>
                                <p class="text-sm text-gray-500">points</p>
                            </div>
                        </div>

                        <div class="mb-4">
                            <p class="text-gray-700 dark:text-gray-300 leading-relaxed text-sm md:text-base">
                                {{ assignment.reason }}
                            </p>
                        </div>

                        <div class="flex flex-col sm:flex-row justify-end gap-3">
                            <button 
                                @click="processPointAssignment(assignment, 'reject')"
                                :disabled="processingAssignment === assignment.id"
                                class="bg-red-500 text-white px-6 py-3 rounded-lg hover:bg-red-600 disabled:opacity-50 transition-colors flex items-center justify-center gap-2 font-medium"
                            >
                                <span v-if="processingAssignment === assignment.id">⏳</span>
                                <span v-else>❌</span>
                                {{ processingAssignment === assignment.id ? 'Processing...' : 'Reject' }}
                            </button>
                            <button 
                                @click="processPointAssignment(assignment, 'approve')"
                                :disabled="processingAssignment === assignment.id"
                                class="bg-green-500 text-white px-6 py-3 rounded-lg hover:bg-green-600 disabled:opacity-50 transition-colors flex items-center justify-center gap-2 font-medium"
                            >
                                <span v-if="processingAssignment === assignment.id">⏳</span>
                                <span v-else>✅</span>
                                {{ processingAssignment === assignment.id ? 'Processing...' : 'Approve' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Assign Point Modal (User Dashboard Style) -->
        <div 
            v-if="showAssignPointsModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
            @click.self="closeAssignPoints"
        >
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-lg m-4 animate-fade-in">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-semibold">Assign Points</h3>
                    <button 
                        @click="closeAssignPoints"
                        class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        ✕
                    </button>
                </div>
                
                <form @submit.prevent="submitPointAssignment" class="space-y-4">
                    <!-- Recipient Selection -->
                    <div>
                        <label class="block text-sm font-medium mb-2">Recipient</label>
                        <select 
                            v-model="assignPointsForm.recipient_id"
                            class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                            <option value="" disabled>Select a user...</option>
                            <option 
                                v-for="user in eligibleUsers" 
                                :key="user.id" 
                                :value="user.id"
                            >
                                {{ user.name }} ({{ user.role || 'user' }})
                            </option>
                        </select>
                    </div>

                    <!-- Point Presets -->
                    <div>
                        <label class="block text-sm font-medium mb-2">Quick Point Selection</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                v-for="preset in pointPresets"
                                :key="preset.value"
                                type="button"
                                @click="selectPointPreset(preset)"
                                :class="[
                                    'p-2 text-sm rounded-lg text-white font-medium hover:opacity-90 transition-opacity',
                                    preset.color,
                                    assignPointsForm.points === preset.value ? 'ring-2 ring-blue-400' : ''
                                ]"
                            >
                                {{ preset.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Manual Point Entry -->
                    <div>
                        <label class="block text-sm font-medium mb-2">Custom Points</label>
                        <div class="relative">
                            <input
                                v-model.number="assignPointsForm.points"
                                @input="handlePointsChange"
                                type="number"
                                min="-10"
                                max="10"
                                class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="Enter point value"
                            >
                            <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                                <span :class="['text-sm font-medium', assignPointsForm.points >= 0 ? 'text-green-600' : 'text-red-600']">
                                    {{ assignPointsForm.point_type === 'positive' ? '👍' : '👎' }}
                                </span>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            Positive points reward good behavior, negative points indicate areas for improvement
                        </p>
                    </div>
                    
                    <!-- Reason -->
                    <div>
                        <label class="block text-sm font-medium mb-2">
                            Reason 
                            <span :class="assignPointsForm.point_type === 'negative' ? 'text-red-600' : 'text-green-600'">
                                ({{ assignPointsForm.point_type === 'negative' ? 'for improvement area' : 'for recognition' }})
                            </span>
                        </label>
                        <textarea 
                            v-model="assignPointsForm.reason"
                            class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                            rows="3"
                            :placeholder="assignPointsForm.point_type === 'negative' ? 
                                'Explain what needs improvement and provide constructive feedback...' : 
                                'Describe what they did well or achieved...'"
                            maxlength="500"
                        ></textarea>
                        <div class="text-xs text-gray-500 mt-1 text-right">
                            {{ assignPointsForm.reason ? assignPointsForm.reason.length : 0 }}/500 characters
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="flex gap-3 pt-4">
                        <button 
                            type="button" 
                            @click="closeAssignPoints"
                            class="flex-1 px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            :disabled="isSubmitting || !assignPointsForm.recipient_id || !assignPointsForm.reason || assignPointsForm.points === 0"
                            :class="[
                                'flex-1 px-4 py-2 text-white rounded-lg hover:opacity-90 transition-opacity disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center',
                                assignPointsForm.point_type === 'positive' 
                                    ? 'bg-gradient-to-r from-green-500 to-blue-600' 
                                    : 'bg-gradient-to-r from-red-500 to-orange-600'
                            ]"
                        >
                            <span v-if="!isSubmitting">
                                {{ assignPointsForm.point_type === 'positive' ? 'Award' : 'Assign' }} 
                                {{ Math.abs(assignPointsForm.points) }} Point{{ Math.abs(assignPointsForm.points) !== 1 ? 's' : '' }}
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

        <!-- Point History Modal -->
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
                
                <div class="flex flex-wrap gap-2 mb-4">
                    <button 
                        v-for="filter in ['all', 'pending', 'verified', 'rejected', 'positive', 'negative']"
                        :key="filter"
                        @click="setLogFilter(filter)"
                        :class="[
                            'px-4 py-2 rounded-lg font-medium transition-colors',
                            activeLogFilter === filter 
                                ? 'bg-blue-600 text-white' 
                                : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600'
                        ]"
                    >
                        {{ filter === 'all' ? 'All' : 
                           filter === 'pending' ? 'Pending' : 
                           filter === 'verified' ? 'Verified' :
                           filter === 'rejected' ? 'Rejected' :
                           filter === 'positive' ? 'Positive Points' :
                           'Negative Points' }}
                    </button>
                </div>

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

.hover\:shadow-md:hover {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.border-red-500 {
    animation: urgentPulse 2s ease-in-out infinite;
}

@keyframes urgentPulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.8; }
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

input[type="number"]::-webkit-outer-spin-button,
input[type="number"]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

input[type="number"] {
  -moz-appearance: textfield;
  -webkit-appearance: none;
  appearance: none;
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