<script setup>
import { ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import axios from 'axios';

const breadcrumbs = [
    { title: 'User Management', href: '/user-management' }
];

// Reactive data
const users = ref([]);
const loading = ref(true);
const selectedUser = ref(null);
const showUserModal = ref(false);
const showAddUserModal = ref(false);
const userAction = ref(''); // 'role', 'delete'
const userRoleUpdate = ref('');

// Add user form
const newUserForm = ref({
    name: '',
    email: '',
    password: '',
    role: 'user'
});

// Load users
const loadUsers = async () => {
    try {
        loading.value = true;
        const response = await axios.get('/api/admin/users');
        users.value = response.data || [];
    } catch (error) {
        console.error('Error loading users:', error);
        users.value = [];
    } finally {
        loading.value = false;
    }
};

// Open user action modal
const openUserModal = (user, action) => {
    selectedUser.value = user;
    userAction.value = action;
    userRoleUpdate.value = user.role;
    showUserModal.value = true;
};

// Close user modal
const closeUserModal = () => {
    showUserModal.value = false;
    selectedUser.value = null;
    userAction.value = '';
    userRoleUpdate.value = '';
};

// Open add user modal
const openAddUserModal = () => {
    newUserForm.value = {
        name: '',
        email: '',
        password: '',
        role: 'user'
    };
    showAddUserModal.value = true;
};

// Close add user modal
const closeAddUserModal = () => {
    showAddUserModal.value = false;
};

// Update user role
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
        
        alert('User role updated successfully!');
        closeUserModal();
    } catch (error) {
        console.error('Error updating user role:', error);
        alert('Error updating user role. Please try again.');
    }
};

// Removed verification functionality

// Delete user
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
        closeUserModal();
    } catch (error) {
        console.error('Error deleting user:', error);
        alert('Error deleting user. Please try again.');
    }
};

// Submit add user
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
            await loadUsers(); // Refresh data
        }
    } catch (error) {
        console.error('Error adding user:', error);
        const errorMessage = error.response?.data?.message || 'Error adding user. Please try again.';
        alert(errorMessage);
    }
};

// Execute user action
const executeUserAction = async () => {
    if (userAction.value === 'role') {
        await updateUserRole();
    } else if (userAction.value === 'delete') {
        await deleteUser();
    }
};

// Removed status badge functions

// Initialize
onMounted(() => {
    loadUsers();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="User Management" />
        
        <div class="p-6">
            <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">User Management</h1>
                
                <button 
                    @click="openAddUserModal"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors font-medium"
                >
                    Add New User
                </button>
            </div>

            <div v-if="loading" class="text-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto mb-2"></div>
                <p class="text-gray-500 dark:text-gray-400">Loading users...</p>
            </div>

            <div v-else class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Role</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Points</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                            <tr 
                                v-for="user in users" 
                                :key="user.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                            >
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ user.name }}</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ user.email }}</p>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="capitalize px-3 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded-full text-sm font-medium">
                                        {{ user.role }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-semibold text-gray-900 dark:text-gray-100">{{ user.total_verified_points || 0 }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex gap-2">
                                        <button 
                                            @click="openUserModal(user, 'role')"
                                            class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm transition-colors font-medium"
                                        >
                                            Change Role
                                        </button>
                                        
                                        <button 
                                            @click="openUserModal(user, 'delete')"
                                            class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm transition-colors font-medium"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- User Action Modal -->
        <div 
            v-if="showUserModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
            @click.self="closeUserModal"
        >
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-md m-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ userAction === 'role' ? 'Change User Role' : 'Delete User' }}
                    </h3>
                    <button @click="closeUserModal" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">✕</button>
                </div>

                <div v-if="selectedUser">
                    <p class="mb-4 text-gray-700 dark:text-gray-300">
                        <strong>User:</strong> {{ selectedUser.name }} ({{ selectedUser.email }})
                    </p>

                    <div v-if="userAction === 'role'" class="mb-4">
                        <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Select New Role:</label>
                        <select 
                            v-model="userRoleUpdate"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                            <option value="user">User</option>
                            <option value="supervisor">Supervisor</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>

                    <div v-else-if="userAction === 'delete'" class="mb-4">
                        <p class="text-sm text-red-600 dark:text-red-400">⚠️ This action cannot be undone. Are you sure you want to delete this user?</p>
                    </div>

                    <div class="flex justify-end gap-4">
                        <button 
                            @click="closeUserModal"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                        >
                            Cancel
                        </button>
                        <button 
                            @click="executeUserAction"
                            :class="[
                                'px-4 py-2 rounded-lg font-medium transition-colors',
                                userAction === 'delete' 
                                    ? 'bg-red-600 hover:bg-red-700 text-white' 
                                    : 'bg-blue-600 hover:bg-blue-700 text-white'
                            ]"
                        >
                            {{ userAction === 'role' ? 'Update Role' : 'Delete User' }}
                        </button>
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
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-md m-4 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-semibold text-green-600 dark:text-green-400">Add New User</h3>
                    <button @click="closeAddUserModal" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">✕</button>
                </div>
                
                <form @submit.prevent="submitAddUser" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Full Name <span class="text-red-500 dark:text-red-400">*</span></label>
                        <input
                            v-model="newUserForm.name"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-green-500 focus:border-transparent"
                            placeholder="Enter full name"
                            required
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Email Address <span class="text-red-500 dark:text-red-400">*</span></label>
                        <input
                            v-model="newUserForm.email"
                            type="email"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-green-500 focus:border-transparent"
                            placeholder="Enter email address"
                            required
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Password <span class="text-red-500 dark:text-red-400">*</span></label>
                        <input
                            v-model="newUserForm.password"
                            type="password"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-green-500 focus:border-transparent"
                            placeholder="Enter password (min 8 characters)"
                            required
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Role</label>
                        <select 
                            v-model="newUserForm.role"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-green-500 focus:border-transparent"
                        >
                            <option value="user">User</option>
                            <option value="supervisor">Supervisor</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    
                    <div class="flex justify-end gap-4 pt-4">
                        <button 
                            type="button"
                            @click="closeAddUserModal"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit"
                            class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition-colors"
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