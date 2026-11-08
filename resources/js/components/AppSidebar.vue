<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { type NavGroup } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    Flag,
    LayoutGrid,
    MessageSquare,
    MessagesSquare,
    Settings,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage<{ openReports?: number }>();

const groups = computed<NavGroup[]>(() => [
    {
        title: 'Overview',
        items: [{ title: 'Dashboard', href: dashboard(), icon: LayoutGrid }],
    },
    {
        title: 'Community',
        items: [
            { title: 'Members', href: '/dashboard/members', icon: Users },
            {
                title: 'Comments',
                href: '/dashboard/comments',
                icon: MessageSquare,
            },
            { title: 'Chat', href: '/dashboard/chat', icon: MessagesSquare },
            {
                title: 'Reports',
                href: '/dashboard/reports',
                icon: Flag,
                badge: page.props.openReports ?? 0,
            },
        ],
    },
    {
        title: 'System',
        items: [
            {
                title: 'Site settings',
                href: '/dashboard/site-settings',
                icon: Settings,
            },
        ],
    },
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :groups="groups" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
