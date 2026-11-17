<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    message: {
        type: String,
        required: true,
    },
    confirmLabel: {
        type: String,
        default: 'Hapus',
    },
    processing: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['cancel', 'confirm']);
</script>

<template>
    <div class="fixed inset-0 z-[80] flex items-end justify-center bg-brand-ink/35 backdrop-blur-[2px] sm:items-center sm:p-5" @click.self="emit('cancel')">
        <section role="alertdialog" aria-modal="true" :aria-label="title" class="w-full rounded-t-3xl border border-brand-border bg-white p-5 shadow-[0_24px_48px_-8px_rgba(43,38,39,0.16)] sm:max-w-sm sm:rounded-2xl">
            <h2 class="text-lg font-semibold">{{ title }}</h2>
            <p class="mt-2 text-sm leading-6 text-brand-muted">{{ message }}</p>
            <footer class="mt-6 flex justify-end gap-3">
                <button type="button" class="min-h-11 rounded-lg border border-brand-border px-4 text-sm font-semibold hover:bg-brand-buff" @click="emit('cancel')">Batal</button>
                <button type="button" :disabled="processing" class="min-h-11 rounded-lg bg-red-700 px-4 text-sm font-semibold text-white hover:bg-red-800 disabled:opacity-60" @click="emit('confirm')">
                    {{ processing ? 'Menghapus…' : confirmLabel }}
                </button>
            </footer>
        </section>
    </div>
</template>
