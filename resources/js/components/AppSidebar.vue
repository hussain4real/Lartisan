<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    BriefcaseBusiness,
    CalendarCheck,
    ClipboardCheck,
    CreditCard,
    FolderGit2,
    IdCard,
    LayoutGrid,
    Smartphone,
    WalletCards,
    Wrench,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { edit as phoneVerification } from '@/actions/App/Http/Controllers/Identity/PhoneVerificationController';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import TeamSwitcher from '@/components/TeamSwitcher.vue';
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
import { dashboard as artisanDashboard } from '@/routes/artisan';
import { index as artisanBookings } from '@/routes/artisan/bookings';
import { show as artisanKyc } from '@/routes/artisan/kyc';
import { create as artisanOnboarding } from '@/routes/artisan/onboarding';
import { edit as artisanProfile } from '@/routes/artisan/profile';
import { index as artisanServices } from '@/routes/artisan/services';
import { show as artisanSubscription } from '@/routes/artisan/subscription';
import { show as artisanWallet } from '@/routes/artisan/wallet';
import type { NavItem } from '@/types';

const page = usePage();

const dashboardUrl = computed(() =>
    page.props.currentTeam ? dashboard(page.props.currentTeam.slug).url : '/',
);

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Dashboard',
        href: dashboardUrl.value,
        icon: LayoutGrid,
    },
    {
        title: 'Artisan',
        href: page.props.currentTeam
            ? artisanDashboard(page.props.currentTeam.slug).url
            : '/',
        icon: BriefcaseBusiness,
    },
    {
        title: 'Artisan profile',
        href: page.props.currentTeam
            ? artisanProfile(page.props.currentTeam.slug).url
            : '/',
        icon: IdCard,
    },
    {
        title: 'Services',
        href: page.props.currentTeam
            ? artisanServices(page.props.currentTeam.slug).url
            : '/',
        icon: Wrench,
    },
    {
        title: 'Bookings',
        href: page.props.currentTeam
            ? artisanBookings(page.props.currentTeam.slug).url
            : '/',
        icon: CalendarCheck,
    },
    {
        title: 'KYC',
        href: page.props.currentTeam
            ? artisanKyc(page.props.currentTeam.slug).url
            : '/',
        icon: ClipboardCheck,
    },
    {
        title: 'Subscription',
        href: page.props.currentTeam
            ? artisanSubscription(page.props.currentTeam.slug).url
            : '/',
        icon: CreditCard,
    },
    {
        title: 'Wallet',
        href: page.props.currentTeam
            ? artisanWallet(page.props.currentTeam.slug).url
            : '/',
        icon: WalletCards,
    },
    {
        title: 'Onboarding',
        href: page.props.currentTeam
            ? artisanOnboarding(page.props.currentTeam.slug).url
            : '/',
        icon: BriefcaseBusiness,
    },
    {
        title: 'Phone verification',
        href: phoneVerification().url,
        icon: Smartphone,
    },
]);

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboardUrl">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <SidebarMenu>
                <SidebarMenuItem>
                    <TeamSwitcher />
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
