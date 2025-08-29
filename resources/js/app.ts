import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import { initializeTheme } from './composables/useAppearance';
import axios from 'axios';

// Function to get fresh CSRF token
const getCsrfToken = () => {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
};

// Function to refresh CSRF token
const refreshCsrfToken = async () => {
    try {
        const response = await fetch('/refresh-csrf', {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        if (response.ok) {
            const data = await response.json();
            if (data.token) {
                // Update the meta tag
                const metaTag = document.querySelector('meta[name="csrf-token"]');
                if (metaTag) {
                    metaTag.setAttribute('content', data.token);
                }
                // Update axios default header
                axios.defaults.headers.common['X-CSRF-TOKEN'] = data.token;
                return data.token;
            }
        }
    } catch (error) {
        console.error('Failed to refresh CSRF token:', error);
    }
    return null;
};

// Configure axios defaults for CSRF protection
const token = getCsrfToken();
if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
    axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
    axios.defaults.headers.common['Accept'] = 'application/json';
    axios.defaults.headers.common['Content-Type'] = 'application/json';
}

// Add axios interceptor to handle CSRF token refresh
axios.interceptors.response.use(
    (response) => response,
    async (error) => {
        // Handle 419 CSRF token mismatch
        if (error.response?.status === 419) {
            // For logout requests, just redirect to home without retrying
            if (error.config?.url?.includes('/logout')) {
                console.log('CSRF error on logout - redirecting to home');
                window.location.href = '/';
                return Promise.reject(error);
            }
            
            // For other requests, try to refresh token once
            if (!error.config?._retry) {
                error.config._retry = true;
                console.log('CSRF error - attempting to refresh token');
                
                // Try to refresh the CSRF token
                const newToken = await refreshCsrfToken();
                if (newToken) {
                    console.log('CSRF token refreshed successfully, retrying request');
                    error.config.headers['X-CSRF-TOKEN'] = newToken;
                    return axios.request(error.config);
                } else {
                    // If refresh failed, get token from meta tag as fallback
                    const fallbackToken = getCsrfToken();
                    if (fallbackToken) {
                        console.log('Using fallback CSRF token, retrying request');
                        axios.defaults.headers.common['X-CSRF-TOKEN'] = fallbackToken;
                        error.config.headers['X-CSRF-TOKEN'] = fallbackToken;
                        return axios.request(error.config);
                    }
                }
            }
            
            // If all token refresh attempts failed, redirect to login
            console.log('CSRF token refresh failed - redirecting to login');
            window.location.href = '/login';
            return Promise.reject(error);
        }
        
        // Handle 401 Unauthorized - redirect to login
        if (error.response?.status === 401 && !error.config?.url?.includes('/login')) {
            console.log('Unauthorized - redirecting to login');
            window.location.href = '/login';
            return Promise.reject(error);
        }
        
        return Promise.reject(error);
    }
);

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
