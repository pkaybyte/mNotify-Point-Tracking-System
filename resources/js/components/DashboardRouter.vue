// router/index.js - Vue Router Configuration
import { createRouter, createWebHistory } from 'vue-router'
import { usePage } from '@inertiajs/vue3'

// Import dashboard components
import Dashboard from '@/Pages/Dashboard.vue'
import AdminDashboard from '@/Pages/AdminDashboard.vue'
import SupervisorDashboard from '@/Pages/SupervisorDashboard.vue'

// Import other pages
import Login from '@/Pages/Auth/Login.vue'
import Register from '@/Pages/Auth/Register.vue'

const routes = [
    // Authentication routes
    {
        path: '/login',
        name: 'Login',
        component: Login,
        meta: { 
            requiresGuest: true,
            title: 'Login'
        }
    },
    {
        path: '/register',
        name: 'Register',
        component: Register,
        meta: { 
            requiresGuest: true,
            title: 'Register'
        }
    },

    // Dashboard routes
    {
        path: '/',
        redirect: '/dashboard'
    },
    {
        path: '/dashboard',
        name: 'Dashboard',
        component: Dashboard,
        meta: { 
            requiresAuth: true,
            roles: ['user', 'supervisor', 'admin'],
            title: 'Dashboard',
            icon: '🏠',
            description: 'Personal dashboard and point tracking'
        }
    },
    {
        path: '/supervisor-dashboard',
        name: 'SupervisorDashboard',
        component: SupervisorDashboard,
        meta: { 
            requiresAuth: true,
            roles: ['supervisor', 'admin'],
            title: 'Supervisor Dashboard',
            icon: '👥',
            description: 'Review pending points and manage team',
            badge: 'Supervisor'
        }
    },
    {
        path: '/admin-dashboard',
        name: 'AdminDashboard',
        component: AdminDashboard,
        meta: { 
            requiresAuth: true,
            roles: ['admin'],
            title: 'Admin Dashboard',
            icon: '⚙️',
            description: 'User management and system administration',
            badge: 'Admin'
        }
    },

    // Redirect based on user role
    {
        path: '/dashboard-redirect',
        name: 'DashboardRedirect',
        beforeEnter: (to, from, next) => {
            const user = getCurrentUser()
            if (user) {
                // Redirect to appropriate dashboard based on role
                switch (user.role) {
                    case 'admin':
                        next('/admin-dashboard')
                        break
                    case 'supervisor':
                        next('/supervisor-dashboard')
                        break
                    default:
                        next('/dashboard')
                }
            } else {
                next('/login')
            }
        }
    },

    // Catch all route - redirect to appropriate dashboard
    {
        path: '/:pathMatch(.*)*',
        redirect: '/dashboard-redirect'
    }
]

const router = createRouter({
    history: createWebHistory(),
    routes
})

// Helper function to get current user
function getCurrentUser() {
    try {
        // If using Inertia.js
        const page = usePage()
        return page.props.auth?.user || null
    } catch (error) {
        // Fallback - get from localStorage or API
        const userStr = localStorage.getItem('user')
        return userStr ? JSON.parse(userStr) : null
    }
}

// Navigation guards
router.beforeEach(async (to, from, next) => {
    const user = getCurrentUser()
    
    // Handle guest-only routes (login, register)
    if (to.meta.requiresGuest && user) {
        // User is already logged in, redirect to their dashboard
        switch (user.role) {
            case 'admin':
                next('/admin-dashboard')
                break
            case 'supervisor':
                next('/supervisor-dashboard')
                break
            default:
                next('/dashboard')
        }
        return
    }
    
    // Handle auth-required routes
    if (to.meta.requiresAuth && !user) {
        next('/login')
        return
    }
    
    // Handle role-based access
    if (to.meta.roles && user && !to.meta.roles.includes(user.role)) {
        // User doesn't have required role, redirect to their appropriate dashboard
        console.warn(`Access denied to ${to.path}. User role: ${user.role}, Required: ${to.meta.roles}`)
        
        switch (user.role) {
            case 'admin':
                next('/admin-dashboard')
                break
            case 'supervisor':
                next('/supervisor-dashboard')
                break
            default:
                next('/dashboard')
        }
        return
    }
    
    // Set page title
    if (to.meta.title) {
        document.title = `${to.meta.title} - Point Tracking System`
    }
    
    next()
})

// After navigation
router.afterEach((to, from) => {
    // You can add analytics tracking here
    // gtag('config', 'GA_MEASUREMENT_ID', { page_path: to.path })
})

export default router

// ==============================================================================
// composables/useDashboardNavigation.js - Dashboard Navigation Composable
// ==============================================================================

import { computed, ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { usePage } from '@inertiajs/vue3'

export function useDashboardNavigation() {
    const router = useRouter()
    const route = useRoute()
    const page = usePage()
    
    const user = computed(() => page.props.auth?.user)
    
    // Available dashboard routes based on user role
    const availableRoutes = computed(() => {
        const routes = [
            {
                name: 'Dashboard',
                path: '/dashboard',
                title: 'Dashboard',
                icon: '🏠',
                description: 'Personal dashboard and point tracking',
                available: true
            }
        ]
        
        // Add supervisor dashboard if user is supervisor or admin
        if (user.value?.role === 'supervisor' || user.value?.role === 'admin') {
            routes.push({
                name: 'SupervisorDashboard',
                path: '/supervisor-dashboard',
                title: 'Supervisor Dashboard',
                icon: '👥',
                description: 'Review pending points and manage team',
                available: true,
                badge: 'Supervisor'
            })
        }
        
        // Add admin dashboard if user is admin
        if (user.value?.role === 'admin') {
            routes.push({
                name: 'AdminDashboard',
                path: '/admin-dashboard',
                title: 'Admin Dashboard',
                icon: '⚙️',
                description: 'User management and system administration',
                available: true,
                badge: 'Admin'
            })
        }
        
        return routes
    })
    
    // Current active route
    const currentRoute = computed(() => {
        return availableRoutes.value.find(r => r.path === route.path)
    })
    
    // Navigation functions
    const navigateTo = (path) => {
        router.push(path)
    }
    
    const getDefaultDashboard = () => {
        switch (user.value?.role) {
            case 'admin':
                return '/admin-dashboard'
            case 'supervisor':
                return '/supervisor-dashboard'
            default:
                return '/dashboard'
        }
    }
    
    const goToDefaultDashboard = () => {
        navigateTo(getDefaultDashboard())
    }
    
    return {
        user,
        availableRoutes,
        currentRoute,
        navigateTo,
        getDefaultDashboard,
        goToDefaultDashboard
    }
}

// ==============================================================================
// components/DashboardNavigation.vue - Reusable Navigation Component
// ==============================================================================