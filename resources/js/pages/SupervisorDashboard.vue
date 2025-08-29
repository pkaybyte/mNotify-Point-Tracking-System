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
const chartInstance = ref(null); // Store chart instance

// Modals
const showAssignPointsModal = ref(false);
const showPointLogsModal = ref(false);
const showPendingPointsModal = ref(false);
const showRejectModal = ref(false);
const rejectAssignment = ref(null);
const rejectReason = ref('');

// Point Assignment Form
const assignPointsForm = ref({
    recipient_id: '',
    points: 1,
    reason: '',
    categories: [],
    point_type: 'positive'
});
const isSubmitting = ref(false);
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
// Point presets for quick assignment (from user dashboard)
const pointPresets = [
    { value: 1, label: '+1 (Good work)', type: 'positive', color: 'bg-green-500' },
    { value: 3, label: '+3 (Great job)', type: 'positive', color: 'bg-green-600' },
    { value: 5, label: '+5 (Excellent)', type: 'positive', color: 'bg-green-700' },
    { value: -1, label: '-1 (Minor issue)', type: 'negative', color: 'bg-red-500' },
    { value: -3, label: '-3 (Needs improvement)', type: 'negative', color: 'bg-red-600' },
    { value: -5, label: '-5 (Significant issue)', type: 'negative', color: 'bg-red-700' },
];

// Point History
const pointLogs = ref([]);
const activeLogFilter = ref('all');

// Users for assignment - Fixed: Added loading state and error handling
const users = ref([]);
const loadingUsers = ref(false);
const usersError = ref('');

// Filters and sorting
const assignmentFilter = ref('pending');
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
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom' }
    }
};

// Computed properties for supervisor stats
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
    return myReceivedAssignments.value
        .filter(assignment => assignment.status === 'verified' && assignment.points < 0)
        .reduce((total, assignment) => total + Math.abs(assignment.points), 0);
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

// Fixed: Better user filtering and fallback data
const eligibleUsers = computed(() => {
    return users.value.filter(user => {
        // Only exclude current user, allow assigning points to all other users including supervisors
        return user.id !== currentUser.value.id;
    });
});

const filteredPointLogs = computed(() => {
    if (!pointLogs.value || pointLogs.value.length === 0) {
        return [];
    }
    
    switch (activeLogFilter.value) {
        case 'pending':
            return pointLogs.value.filter(log => log.status?.toLowerCase() === 'pending');
        case 'verified':
            return pointLogs.value.filter(log => log.status?.toLowerCase() === 'verified');
        case 'rejected':
            return pointLogs.value.filter(log => log.status?.toLowerCase() === 'rejected');
        case 'positive':
            return pointLogs.value.filter(log => Number(log.points) > 0);
        case 'negative':
            return pointLogs.value.filter(log => Number(log.points) < 0);
        default:
            return pointLogs.value;
    }
});

// Helper computed for rejected count
const rejectedLogsCount = computed(() => {
    return pointLogs.value.filter(log => log.status?.toLowerCase() === 'rejected').length;
});

// Filtered users for search
const filteredUsers = computed(() => {
    if (!searchQuery.value.trim()) {
        return eligibleUsers.value;
    }
    return eligibleUsers.value.filter(u => 
        u.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        u.email?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        u.role?.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
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

// Fixed: Load users function with better error handling
const loadUsers = async () => {
    try {
        loadingUsers.value = true;
        usersError.value = '';
        
        // Try endpoints in order of preference
        let response;
        const endpoints = ['/api/users', '/api/admin/users', '/api/supervisor/team-members'];
        
        for (const endpoint of endpoints) {
            try {
                console.log(`Trying endpoint: ${endpoint}`);
                response = await axios.get(endpoint);
                
                if (response && response.data) {
                    const userData = Array.isArray(response.data) ? response.data : 
                                   response.data.users || response.data.data || [];
                    
                    if (userData.length > 0) {
                        users.value = userData;
                        console.log(`Successfully loaded ${users.value.length} users from ${endpoint}:`, users.value);
                        break; // Stop trying other endpoints
                    }
                }
            } catch (error) {
                console.warn(`Failed to load from ${endpoint}:`, error.response?.status || error.message);
                continue; // Try next endpoint
            }
        }
        
        // If no endpoint worked or returned empty data
        if (!users.value || users.value.length === 0) {
            throw new Error('No users could be loaded from any endpoint');
        }
        
    } catch (error) {
        console.error('Error loading users from all endpoints:', error);
        usersError.value = 'Failed to load users from database';
        users.value = [];
    } finally {
        loadingUsers.value = false;
    }
};

// Fixed: Chart rendering function
const renderChart = () => {
    if (!chartCanvas.value) return;
    
    // Destroy existing chart
    if (chartInstance.value) {
        chartInstance.value.destroy();
        chartInstance.value = null;
    }
    
    const chartDataValues = supervisorChartData.value;
    const personalChartData = {
        labels: ['Verified Points', 'Unverified Points'],
        datasets: [
            {
                label: 'My Points',
                data: [chartDataValues.verified, chartDataValues.unverified],
                backgroundColor: ['#10B981', '#F59E0B'],
                borderWidth: 2,
                borderColor: '#ffffff'
            }
        ]
    };
    
    try {
        chartInstance.value = new ChartJS(chartCanvas.value, {
            type: 'doughnut', // Changed from 'pie' to 'doughnut'
            data: personalChartData,
            options: {
                ...chartOptions,
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        position: 'bottom',
                        labels: {
                            padding: 10,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                return `${label}: ${value} points`;
                            }
                        }
                    }
                }
            }
        });
    } catch (error) {
        console.error('Error creating chart:', error);
    }
};

// Methods
const loadSupervisorData = async () => {
    try {
        loading.value = true;
        
        // Load current user information first
        try {
            const currentUserRes = await axios.get('/api/users/current');
            currentUser.value = currentUserRes.data;
            console.log('Current user loaded:', currentUser.value);
        } catch (error) {
            console.error('Error loading current user:', error);
            currentUser.value = { id: 3, name: 'Supervisor', role: 'supervisor' };
        }
        
        // Load users after current user is loaded
        await loadUsers();
        
        // Load pending assignments for review
        try {
            const pendingRes = await axios.get('/api/point-assignments/pending');
            pendingAssignments.value = pendingRes.data.pending_assignments || pendingRes.data || [];
        } catch (error) {
            console.error('Error loading pending assignments:', error);
            pendingAssignments.value = [];
        }
        
        // Load supervisor's own assignments
        try {
            const myRes = await axios.get('/api/point-assignments/my');
            myAssignments.value = myRes.data || [];
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
            teamStats.value = statsRes.data || {
                totalTeamMembers: users.value.length,
                pendingReviews: pendingAssignments.value.length,
                approvedThisWeek: 0,
                averageTeamPoints: 0
            };
        } catch (error) {
            console.error('Error loading stats:', error);
            teamStats.value = {
                totalTeamMembers: users.value.length,
                pendingReviews: pendingAssignments.value.length,
                approvedThisWeek: 0,
                averageTeamPoints: 0
            };
        }
        
        loading.value = false;
        loadingChart.value = false;
        
        // Render chart after data is loaded
        setTimeout(() => {
            renderChart();
        }, 100);
        
    } catch (error) {
        console.error('Error loading supervisor data:', error);
        loading.value = false;
        loadingChart.value = false;
    }
};

// Fixed processPointAssignment function
const processPointAssignment = async (assignment, action, reason = null) => {
    if (processingAssignment.value === assignment.id) return;
    
    processingAssignment.value = assignment.id;
    
    try {
        const endpoint = action === 'approve' ? 'approve' : 'reject';
        const payload = action === 'reject' && reason ? { rejection_reason: reason } : {};
        
        const response = await axios.patch(`/api/point-assignments/${assignment.id}/${endpoint}`, payload);
        
        // Update local state only
        pendingAssignments.value = pendingAssignments.value.filter(p => p.id !== assignment.id);
        
        // Update stats locally instead of reloading
        if (action === 'approve') {
            teamStats.value.approvedThisWeek++;
            alert('Point assignment approved successfully!');
        } else {
            alert('Point assignment rejected successfully!');
        }
        teamStats.value.pendingReviews = Math.max(0, teamStats.value.pendingReviews - 1);
        
        // Auto-refresh the page after successful action
        setTimeout(() => {
            window.location.reload();
        }, 1000);
        
    } catch (error) {
        console.error(`Error ${action}ing assignment:`, error);
        const errorMessage = error.response?.data?.message || `Error ${action}ing assignment. Please try again.`;
        alert(errorMessage);
    } finally {
        processingAssignment.value = null;
    }
};

const submitRejection = async () => {
    if (!rejectReason.value.trim()) {
        alert('Please provide a reason for rejection');
        return;
    }
    
    await processPointAssignment(rejectAssignment.value, 'reject', rejectReason.value);
    closeRejectModal();
};

// Assign Points functionality
const openAssignPoints = async () => {
    // Always refresh users when opening modal to ensure we have latest data
    await loadUsers();
    
    assignPointsForm.value = {
        recipient_id: '',
        points: 1,
        reason: '',
        categories: [],
        point_type: 'positive'
    };
    showAssignPointsModal.value = true;
};

const selectPointPreset = (preset) => {
    assignPointsForm.value.points = preset.value;
    assignPointsForm.value.point_type = preset.type;
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
    if (!assignPointsForm.value.recipient_id || !assignPointsForm.value.reason.trim()) {
        alert('Please fill in all required fields');
        return;
    }

    if (assignPointsForm.value.reason.trim().length < 10) {
        alert('Please provide a more detailed reason (at least 10 characters)');
        return;
    }


    if (!assignPointsForm.value.categories || assignPointsForm.value.categories.length === 0) {
        alert('Please select at least one category');
        return;
    }

    isSubmitting.value = true;

    try {
        const response = await axios.post('/api/point-assignments', {
            recipient_id: parseInt(assignPointsForm.value.recipient_id),
            points: parseInt(assignPointsForm.value.points),
            reason: assignPointsForm.value.reason.trim(),
            categories: assignPointsForm.value.categories
        });

        if (response.status === 201 || response.status === 200) {
            const pointText = assignPointsForm.value.points > 0 ? 'points' : 'penalty points';
            alert(`${Math.abs(assignPointsForm.value.points)} ${pointText} assigned successfully!`);
            closeAssignPoints();
            
            // Auto-reload the page after successful point assignment
            setTimeout(() => {
                window.location.reload();
            }, 1000);
            
            // Add to local assignments instead of full reload
            const newAssignment = {
                id: response.data?.id || Date.now(),
                assignor: { name: currentUser.value.name || 'You' },
                recipient: eligibleUsers.value.find(u => u.id === parseInt(assignPointsForm.value.recipient_id)) || 
                          { name: 'Unknown User' },
                points: parseInt(assignPointsForm.value.points),
                reason: assignPointsForm.value.reason.trim(),
                status: 'pending',
                created_at: new Date().toISOString(),
                ...response.data
            };
            myAssignments.value.unshift(newAssignment);
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

// Pending Points Modal
const openPendingPoints = () => {
    showPendingPointsModal.value = true;
};

const closePendingPoints = () => {
    showPendingPointsModal.value = false;
    processingAssignment.value = null;
};

const openRejectModal = (assignment) => {
    rejectAssignment.value = assignment;
    rejectReason.value = '';
    showRejectModal.value = true;
};

const closeRejectModal = () => {
    showRejectModal.value = false;
    rejectAssignment.value = null;
    rejectReason.value = '';
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

// Navigate to review pending points
const goToReviewPending = () => {
    assignmentFilter.value = 'pending';
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

// Watch for data changes to re-render chart
const updateChart = () => {
    if (chartInstance.value && !loadingChart.value) {
        const chartDataValues = supervisorChartData.value;
        chartInstance.value.data.datasets[0].data = [chartDataValues.verified, chartDataValues.unverified];
        chartInstance.value.update();
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
    if (!assignPointsForm.value.categories) {
        assignPointsForm.value.categories = [];
    }
    const index = assignPointsForm.value.categories.indexOf(category);
    if (index === -1) {
        assignPointsForm.value.categories.push(category);
    } else {
        assignPointsForm.value.categories.splice(index, 1);
    }
};

const addCustomCategory = () => {
    if (!assignPointsForm.value.categories) {
        assignPointsForm.value.categories = [];
    }
    if (customCategory.value.trim() && !assignPointsForm.value.categories.includes(customCategory.value.trim())) {
        assignPointsForm.value.categories.push(customCategory.value.trim());
        customCategory.value = '';
        showAddCategory.value = false;
    }
};

const removeCategory = (category) => {
    if (!assignPointsForm.value.categories) {
        assignPointsForm.value.categories = [];
        return;
    }
    const index = assignPointsForm.value.categories.indexOf(category);
    if (index !== -1) {
        assignPointsForm.value.categories.splice(index, 1);
    }
};

// Initialize on mount
onMounted(() => {
    loadSupervisorData();
    
    // Cleanup function
    return () => {
        if (chartInstance.value) {
            chartInstance.value.destroy();
        }
    };
});
</script>

<template>
    <Head title="Supervisor Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-2 sm:p-4 overflow-x-auto">
            
            <!-- Top Grid - Matching Admin Dashboard Layout -->
            <div class="grid auto-rows-min gap-4 sm:grid-cols-2 lg:grid-cols-3">
                
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
                        <!-- 2x2 Grid for supervisor points -->
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <!-- Verified -->
                            <div class="bg-green-50 dark:bg-green-900/20 p-2 rounded border border-green-200 dark:border-green-800">
                                <div class="text-green-700 dark:text-green-400 font-medium">Verified: {{ supervisorChartData.verified }}</div>
                            </div>
                            
                            <!-- Pending -->
                            <div class="bg-yellow-50 dark:bg-yellow-900/20 p-2 rounded border border-yellow-200 dark:border-yellow-800">
                                <div class="text-yellow-700 dark:text-yellow-400 font-medium">Pending: {{ supervisorChartData.unverified }}</div>
                            </div>
                            
                            <!-- Lifetime Positive Points -->
                            <div class="bg-green-50 dark:bg-green-900/20 p-2 rounded border border-green-200 dark:border-green-800">
                                <div class="text-green-700 dark:text-green-400 font-medium">Positive: {{ positivePointsCount }}</div>
                            </div>
                            
                            <!-- Lifetime Negative Points -->
                            <div class="bg-red-50 dark:bg-red-900/20 p-2 rounded border border-red-200 dark:border-red-800">
                                <div class="text-red-700 dark:text-red-400 font-medium">Negative: {{ negativePointsCount }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fixed: Pie Chart with proper sizing -->
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
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions (Rectangular buttons laid out vertically) -->
<div class="relative aspect-video overflow-hidden rounded-xl border border-gray-300 dark:border-gray-700 p-4">
    <h2 class="text-xl font-semibold mb-4">Quick Actions</h2>
    <div class="flex flex-col gap-2">
        <!-- Assign Point Button -->
        <button 
            @click="openAssignPoints"
            class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-4 py-2 rounded-lg font-medium hover:opacity-90 transition-opacity flex items-center gap-3 w-full text-left"
        >
            <span class="text-lg">🎯</span>
            <span>Assign Points</span>
        </button>
        
        <!-- View Points History Page Button -->
        <a 
            href="/points-history"
            class="bg-gradient-to-r from-pink-500 to-red-500 text-white px-4 py-2 rounded-lg font-medium hover:opacity-90 transition-opacity flex items-center gap-3 w-full text-left"
        >
            <span class="text-lg">📊</span>
            <span>View Points History</span>
        </a>

        <!-- Review Pending Button -->
        <button 
            @click="goToReviewPending"
            class="bg-gradient-to-r from-yellow-500 to-orange-500 text-white px-4 py-2 rounded-lg font-medium hover:opacity-90 transition-opacity flex items-center gap-3 w-full text-left"
        >
            <span class="text-lg">👀</span>
            <span>Review Pending</span>
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

            <!-- Controls and Filters -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-300 dark:border-gray-700 p-4">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div class="flex flex-wrap gap-2 w-full sm:w-auto">
                        <button 
                            @click="assignmentFilter = 'pending'"
                            :class="[
                                'px-4 py-2 rounded-lg font-medium transition-colors flex-1 sm:flex-none',
                                assignmentFilter === 'pending' 
                                    ? 'bg-yellow-600 text-white' 
                                    : 'bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 text-gray-700 dark:text-gray-300'
                            ]"
                        >
                            Pending ({{ pendingCount }})
                        </button>
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

            <!-- Assignments List -->
            <div class="assignments-section bg-white dark:bg-gray-800 rounded-xl border border-gray-300 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold mb-4">
                    Pending Point Assignments
                </h3>

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
                                @click="openRejectModal(assignment)"
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

        <!-- Fixed: Assign Point Modal with better user loading -->
        <div 
            v-if="showAssignPointsModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
            @click.self="closeAssignPoints"
        >
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 w-full max-w-md m-4 animate-fade-in max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-semibold">Assign Points</h3>
                    <button 
                        @click="closeAssignPoints"
                        class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        ✕
                    </button>
                </div>
                
                <form @submit.prevent="submitPointAssignment" class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium mb-2">Recipient</label>
                        <div v-if="loadingUsers" class="flex items-center justify-center py-3">
                            <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-500 mr-2"></div>
                            <span class="text-sm text-gray-500">Loading users...</span>
                        </div>
                        <div v-else-if="usersError" class="text-red-600 text-sm mb-2">
                            {{ usersError }} - Using fallback users
                        </div>
                        <div class="relative">
                            <input
                                v-model="searchQuery"
                                @input="onSearchInput"
                                @focus="onSearchFocus"
                                @blur="onSearchBlur"
                                type="text"
                                class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="Type to search users..."
                                :disabled="loadingUsers"
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
                                    class="p-3 hover:bg-gray-100 dark:hover:bg-gray-600 cursor-pointer border-b border-gray-200 dark:border-gray-600 last:border-b-0"
                                >
                                    <div class="font-medium">{{ user.name }}</div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ user.email || user.role || 'user' }}</div>
                                </div>
                            </div>
                            <div 
                                v-if="showUserDropdown && filteredUsers.length === 0 && searchQuery.trim()" 
                                class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg shadow-lg p-3 text-gray-500 dark:text-gray-400"
                            >
                                No users found matching "{{ searchQuery }}"
                            </div>
                        </div>
                        <p v-if="eligibleUsers.length === 0 && !loadingUsers" class="text-sm text-gray-500 mt-1">
                            No users available for point assignment.
                        </p>
                    </div>

                    
                    <!-- Point Categories -->
                    <div>
                        <label class="block text-sm font-medium mb-2">Point Categories <span class="text-red-500">*</span></label>
                        <div class="space-y-3">
                            <!-- Selected categories -->
                            <div v-if="assignPointsForm.categories && assignPointsForm.categories.length > 0" class="flex flex-wrap gap-2">
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
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                <button
                                    v-for="category in pointCategories"
                                    :key="category"
                                    type="button"
                                    @click="toggleCategory(category)"
                                    :class="[
                                        'px-3 py-2 text-sm border rounded-lg transition-colors text-left',
                                        (assignPointsForm.categories && assignPointsForm.categories.includes(category))
                                            ? 'bg-blue-100 border-blue-500 text-blue-800'
                                            : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'
                                    ]"
                                >
                                    {{ category }}
                                </button>
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
                                    assignPointsForm.points === preset.value ? 'ring-2 ring-blue-400' : ''
                                ]"
                            >
                                {{ preset.label }}
                            </button>
                        </div>
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
                           filter === 'rejected' ? `Rejected (${rejectedLogsCount})` :
                           filter === 'positive' ? 'Positive Points' :
                           'Negative Points' }}
                    </button>
                </div>

                <div class="overflow-y-auto max-h-96 space-y-3">
                    <div v-if="filteredPointLogs.length === 0" class="text-center py-8 text-gray-500">
                        <span v-if="activeLogFilter === 'rejected'" class="text-4xl mb-4 block">❌</span>
                        <span v-else class="text-4xl mb-4 block">📝</span>
                        <p v-if="activeLogFilter === 'rejected'">
                            No rejected assignments found.<br>
                            <span class="text-sm">Assignments appear here when you reject point assignments.</span>
                        </p>
                        <p v-else>
                            No logs found for selected filter
                        </p>
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
                                @click="openRejectModal(assignment)"
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

        <!-- Rejection Reason Modal -->
        <div 
            v-if="showRejectModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
            @click.self="closeRejectModal"
        >
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-md m-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-semibold text-red-600">Reject Point Assignment</h3>
                    <button @click="closeRejectModal" class="text-gray-500 hover:text-gray-700">✕</button>
                </div>
                
                <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">You are rejecting:</p>
                    <p class="font-medium">{{ rejectAssignment?.points }} points from {{ rejectAssignment?.assignor?.name }} to {{ rejectAssignment?.recipient?.name }}</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ rejectAssignment?.reason }}</p>
                </div>
                
                <form @submit.prevent="submitRejection">
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2">Reason for Rejection <span class="text-red-500">*</span></label>
                        <textarea 
                            v-model="rejectReason"
                            class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 focus:ring-2 focus:ring-red-500 focus:border-transparent resize-none"
                            rows="4"
                            placeholder="Please explain why this point assignment is being rejected..."
                            required
                        ></textarea>
                    </div>
                    
                    <div class="flex gap-3">
                        <button 
                            type="button" 
                            @click="closeRejectModal"
                            class="flex-1 px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            :disabled="!rejectReason.trim()"
                            class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            Reject Assignment
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