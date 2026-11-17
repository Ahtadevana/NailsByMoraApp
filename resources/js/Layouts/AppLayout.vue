<script setup>
import { onBeforeUnmount, watch } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppIcon from '../Components/AppIcon.vue';
import logoUrl from '../../images/LOGO.jpg';

const page = usePage();
const sidebarOpen = ref(false);
const logoutForm = useForm({});

const navigationItems = [
    { label: 'Dasbor', href: '/dashboard', icon: 'dashboard' },
    { label: 'Pelanggan', href: '/customers', icon: 'customers' },
    { label: 'Layanan', href: '/services', icon: 'services' },
    { label: 'Janji Temu', href: '/appointments', icon: 'appointments' },
    { label: 'Inventaris', href: '/inventory', icon: 'inventory' },
    { label: 'Pengeluaran', href: '/expenses', icon: 'expenses' },
    { label: 'Pemasukan', href: '/incomes', icon: 'income' },
    { label: 'Laporan', href: '/reports', icon: 'reports' },
];

const currentPath = () => page.url.split('?')[0];

function isActive(href) {
    return currentPath() === href;
}

function closeSidebar() {
    sidebarOpen.value = false;
}

function logout() {
    logoutForm.post('/logout');
}

watch(sidebarOpen, (isOpen) => {
    if (typeof document !== 'undefined') {
        document.body.style.overflow = isOpen ? 'hidden' : '';
    }
});

onBeforeUnmount(() => {
    if (typeof document !== 'undefined') {
        document.body.style.overflow = '';
    }
});
</script>

<template>
    <div class="min-h-svh bg-brand-cream text-brand-ink">
        <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-brand-border bg-white/95 px-4 backdrop-blur sm:px-6">
            <button
                type="button"
                class="flex size-11 items-center justify-center rounded-xl text-brand-ink transition hover:bg-brand-buff focus-visible:outline-2 focus-visible:outline-brand-burgundy"
                aria-label="Buka menu navigasi"
                aria-controls="app-sidebar"
                :aria-expanded="sidebarOpen"
                @click="sidebarOpen = true"
            >
                <AppIcon name="menu" class="size-6" />
            </button>

            <Link
                href="/dashboard"
                aria-label="Beranda NailsByMora"
                class="flex size-10 items-center justify-center overflow-hidden rounded-xl shadow-[inset_0_1px_0_rgba(255,255,255,0.25)]"
            >
                <img :src="logoUrl" alt="NailsByMora" class="size-full object-cover">
            </Link>
        </header>

        <main class="min-h-[calc(100svh-4rem)]">
            <slot />
        </main>

        <Transition name="sidebar">
            <div v-if="sidebarOpen" class="fixed inset-0 z-50">
                <button
                    type="button"
                    aria-label="Tutup menu navigasi"
                    class="absolute inset-0 size-full cursor-default bg-brand-ink/35 backdrop-blur-[2px]"
                    @click="closeSidebar"
                />

                <aside
                    id="app-sidebar"
                    role="dialog"
                    aria-modal="true"
                    aria-label="Navigasi utama"
                    class="absolute inset-y-0 left-0 flex w-[min(19rem,88vw)] flex-col border-r border-brand-border bg-white shadow-[0_24px_48px_-8px_rgba(43,38,39,0.16)]"
                    @keydown.esc="closeSidebar"
                >
                    <div class="flex items-center gap-3 border-b border-brand-border px-5 py-5">
                        <div class="size-12 shrink-0 overflow-hidden rounded-xl">
                            <img :src="logoUrl" alt="NailsByMora" class="size-full object-cover">
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold tracking-wide text-brand-ink">NailsByMora</p>
                            <p class="mt-0.5 truncate text-xs text-brand-muted">{{ page.props.auth?.admin?.name ?? 'Admin studio' }}</p>
                        </div>
                        <button
                            type="button"
                            aria-label="Tutup menu navigasi"
                            class="flex size-10 shrink-0 items-center justify-center rounded-lg text-brand-muted hover:bg-brand-buff hover:text-brand-ink"
                            @click="closeSidebar"
                        >
                            <AppIcon name="close" />
                        </button>
                    </div>

                    <nav aria-label="Bagian studio" class="flex flex-1 flex-col gap-1 overflow-y-auto px-3 py-5">
                        <Link
                            v-for="item in navigationItems"
                            :key="item.href"
                            :href="item.href"
                            class="flex min-h-12 items-center gap-3 rounded-xl px-3 text-sm font-medium transition"
                            :class="isActive(item.href)
                                ? 'bg-[#f7e9e8] text-brand-burgundy'
                                : 'text-brand-muted hover:bg-brand-buff hover:text-brand-ink'"
                            :aria-current="isActive(item.href) ? 'page' : undefined"
                            @click="closeSidebar"
                        >
                            <AppIcon :name="item.icon" />
                            <span>{{ item.label }}</span>
                        </Link>

                        <div class="mt-auto flex flex-col gap-1 border-t border-brand-border pt-4">
                            <Link
                                href="/settings"
                                class="flex min-h-12 items-center gap-3 rounded-xl px-3 text-sm font-medium text-brand-muted transition hover:bg-brand-buff hover:text-brand-ink"
                                :class="isActive('/settings') ? 'bg-[#f7e9e8] text-brand-burgundy' : ''"
                                :aria-current="isActive('/settings') ? 'page' : undefined"
                                @click="closeSidebar"
                            >
                                <AppIcon name="settings" />
                                <span>Pengaturan</span>
                            </Link>
                            <button
                                type="button"
                                class="flex min-h-12 items-center gap-3 rounded-xl px-3 text-sm font-medium text-brand-muted transition hover:bg-brand-buff hover:text-brand-burgundy disabled:opacity-60"
                                :disabled="logoutForm.processing"
                                @click="logout"
                            >
                                <AppIcon name="logout" />
                                <span>{{ logoutForm.processing ? 'Keluar…' : 'Keluar' }}</span>
                            </button>
                        </div>
                    </nav>
                </aside>
            </div>
        </Transition>
    </div>
</template>

<style>
.sidebar-enter-active,
.sidebar-leave-active {
    transition: opacity 180ms ease;
}

.sidebar-enter-active aside,
.sidebar-leave-active aside {
    transition: transform 180ms ease;
}

.sidebar-enter-from,
.sidebar-leave-to {
    opacity: 0;
}

.sidebar-enter-from aside,
.sidebar-leave-to aside {
    transform: translateX(-100%);
}

@media (prefers-reduced-motion: reduce) {
    .sidebar-enter-active,
    .sidebar-leave-active,
    .sidebar-enter-active aside,
    .sidebar-leave-active aside {
        transition-duration: 0.01ms;
    }
}
</style>
