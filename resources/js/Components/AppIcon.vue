<script setup>
defineProps({
    name: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <svg
        aria-hidden="true"
        viewBox="0 0 24 24"
        fill="none"
        class="size-5 shrink-0"
        stroke="currentColor"
        stroke-linecap="round"
        stroke-linejoin="round"
        stroke-width="1.7"
    >
        <template v-if="name === 'dashboard'">
            <rect x="3.5" y="3.5" width="7" height="7" rx="1.5" />
            <rect x="13.5" y="3.5" width="7" height="4" rx="1.5" />
            <rect x="13.5" y="10.5" width="7" height="10" rx="1.5" />
            <rect x="3.5" y="13.5" width="7" height="7" rx="1.5" />
        </template>
        <template v-else-if="name === 'customers'">
            <circle cx="9" cy="8" r="3.25" />
            <path d="M3.5 20v-1.25A5.5 5.5 0 0 1 9 13.25a5.5 5.5 0 0 1 5.5 5.5V20h-11Z" />
            <path d="M15 4.95a3.25 3.25 0 0 1 0 6.1m2.5 2.45a5.5 5.5 0 0 1 3 4.92V20h-3" />
        </template>
        <template v-else-if="name === 'services'">
            <path d="M7 3.5h10l1 17H6l1-17Z" />
            <path d="M9 3.5v3m6-3v3M8.5 11h7M10 15h4" />
        </template>
        <template v-else-if="name === 'appointments'">
            <rect x="3.5" y="5" width="17" height="15.5" rx="2" />
            <path d="M7.5 3.5v3M16.5 3.5v3M3.5 9.5h17M8 13h3m-3 3h6" />
        </template>
        <template v-else-if="name === 'inventory'">
            <path d="m12 3 8.5 4.5v9L12 21l-8.5-4.5v-9L12 3Z" />
            <path d="m3.8 7.7 8.2 4.5 8.2-4.5M12 12.2V21m-4.2-16.8 8.5 4.6v4.5" />
        </template>
        <template v-else-if="name === 'expenses'">
            <rect x="3.5" y="5" width="17" height="14" rx="2" />
            <path d="M3.5 9h17M7.5 14h4" />
        </template>
        <template v-else-if="name === 'reports'">
            <path d="M4 20.5V12m5.3 8.5V5m5.4 15.5v-6m5.3 6V3.5" />
            <path d="M2.75 20.5h18.5" />
        </template>
        <template v-else-if="name === 'income'">
            <path d="M12 20V4m-6.5 6.5L12 4l6.5 6.5" />
        </template>
        <template v-else-if="name === 'settings'">
            <path d="M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Z" />
            <path d="m19.4 13.5 1.1.85-1.5 2.6-1.35-.5a7.5 7.5 0 0 1-1.6.95L15.8 19h-3l-.25-1.6a7.5 7.5 0 0 1-1.7-.95l-1.3.5-1.5-2.6 1.1-.85a7.3 7.3 0 0 1 0-1.9l-1.1-.85 1.5-2.6 1.3.5a7.5 7.5 0 0 1 1.7-.95L12.8 6h3l.25 1.6a7.5 7.5 0 0 1 1.6.95l1.35-.5 1.5 2.6-1.1.85a7.3 7.3 0 0 1 0 2Z" transform="translate(-1 -0.5)" />
        </template>
        <template v-else-if="name === 'logout'">
            <path d="M10 4H5.5A1.5 1.5 0 0 0 4 5.5v13A1.5 1.5 0 0 0 5.5 20H10" />
            <path d="M14 16.5 18.5 12 14 7.5M18 12H9" />
        </template>
        <template v-else-if="name === 'menu'">
            <path d="M4 6.5h16M4 12h16M4 17.5h16" />
        </template>
        <template v-else-if="name === 'close'">
            <path d="m6 6 12 12M18 6 6 18" />
        </template>
        <template v-else-if="name === 'search'">
            <circle cx="10.8" cy="10.8" r="6.3" />
            <path d="m15.5 15.5 4.25 4.25" />
        </template>
        <template v-else-if="name === 'plus'">
            <path d="M12 5v14M5 12h14" />
        </template>
        <template v-else-if="name === 'edit'">
            <path d="m14 5 5 5M4 20l4.2-.9L19 8.3a2.12 2.12 0 0 0-3-3L5.2 16.1 4 20Z" />
        </template>
        <template v-else-if="name === 'trash'">
            <path d="M4 7h16M9 7V4.5h6V7m3 0-.8 13H6.8L6 7m3.5 3v6m5-6v6" />
        </template>
        <template v-else-if="name === 'chevron-down'">
            <path d="m6 9 6 6 6-6" />
        </template>
        <template v-else-if="name === 'chevron-left'">
            <path d="m15 18-6-6 6-6" />
        </template>
        <template v-else-if="name === 'chevron-right'">
            <path d="m9 18 6-6-6-6" />
        </template>
        <template v-else-if="name === 'mail'">
            <rect x="3" y="5" width="18" height="14" rx="2" />
            <path d="m4 7 8 6 8-6" />
        </template>
        <template v-else-if="name === 'phone'">
            <path d="M7 3.5h3l1.5 4-2 1.5a14 14 0 0 0 5.5 5.5l1.5-2 4 1.5v3a2 2 0 0 1-2.2 2A16.5 16.5 0 0 1 5 5.7 2 2 0 0 1 7 3.5Z" />
        </template>
        <template v-else-if="name === 'calendar'">
            <rect x="3.5" y="5" width="17" height="15.5" rx="2" />
            <path d="M7.5 3.5v3M16.5 3.5v3M3.5 9.5h17" />
        </template>
        <template v-else-if="name === 'users'">
            <circle cx="9" cy="8" r="3" />
            <path d="M3.5 20v-1a5.5 5.5 0 0 1 11 0v1h-11Zm11-14.5a3 3 0 0 1 0 5.5m2.5 2a5.5 5.5 0 0 1 3.5 5v2h-3" />
        </template>
        <template v-else-if="name === 'clock'">
            <circle cx="12" cy="12" r="8.5" />
            <path d="M12 7v5l3.5 2" />
        </template>
    </svg>
</template>
