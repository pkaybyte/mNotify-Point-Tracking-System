<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, Folder, LayoutGrid, Trophy, Users, History } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

// Get user data from Inertia page props
const page = usePage();
const user = computed(() => page.props.auth?.user);

// Determine dashboard route based on user role
const dashboardRoute = computed(() => {
    const userRole = user.value?.role;
    
    switch (userRole) {
        case 'admin':
            return '/admin-dashboard';
        case 'supervisor':
            return '/supervisor-dashboard';
        default:
            return '/dashboard';
    }
});

const mainNavItems = computed((): NavItem[] => {
    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboardRoute.value,
            icon: LayoutGrid,
        },
        {
            title: 'Points League',
            href: '/leaderboard',
            icon: Trophy,
        },
        {
            title: 'Points History',
            href: '/points-history',
            icon: History,
        },
    ];

    // Add admin-only navigation items
    if (user.value?.role === 'admin') {
        items.push({
            title: 'User Management',
            href: '/user-management',
            icon: Users,
        });
    }

    return items;
});

/*To not show the github and the documentation code
const footerNavItems: NavItem[] = [
    {
        title: 'Github Repo',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];*/
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboardRoute">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
