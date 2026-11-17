<script setup>
import AppIcon from './AppIcon.vue';

defineProps({
    detail: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['close', 'edit', 'delete']);

function statusLabel(status) {
    return {
        SCHEDULED: 'Terjadwal',
        COMPLETED: 'Selesai',
        CANCELLED: 'Dibatalkan',
        NO_SHOW: 'Tidak hadir',
    }[status] ?? status;
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${value}T00:00:00`));
}

function formatCurrency(value) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
}
</script>

<template>
    <div class="fixed inset-0 z-[60] flex items-end justify-center bg-brand-ink/35 backdrop-blur-[2px] sm:items-center sm:p-5" @click.self="emit('close')">
        <section
            role="dialog"
            aria-modal="true"
            aria-label="Detail pelanggan"
            class="flex max-h-[94svh] w-full flex-col rounded-t-3xl border border-brand-border bg-white shadow-[0_24px_48px_-8px_rgba(43,38,39,0.16)] sm:max-w-2xl sm:rounded-2xl"
        >
            <header class="flex items-center gap-2 border-b border-brand-border px-4 py-3">
                <button type="button" class="flex min-h-11 items-center gap-1 rounded-lg px-2 text-sm font-semibold text-brand-burgundy hover:bg-brand-buff" @click="emit('close')">
                    <AppIcon name="chevron-left" class="size-4" />
                    Pelanggan
                </button>
                <div class="flex-1" />
                <button type="button" aria-label="Tutup detail pelanggan" class="flex size-10 items-center justify-center rounded-lg text-brand-muted hover:bg-brand-buff" @click="emit('close')">
                    <AppIcon name="close" />
                </button>
            </header>

            <div class="overflow-y-auto px-5 py-5 sm:px-6">
                <h2 class="text-2xl font-semibold tracking-tight">{{ detail.customer.name }}</h2>
                <p class="mt-1 text-sm text-brand-muted">Pelanggan sejak {{ formatDate(detail.customer.created_at?.slice(0, 10)) }}</p>

                <section class="mt-6">
                    <h3 class="text-xs font-bold uppercase tracking-[0.14em] text-brand-muted">Kontak</h3>
                    <div class="mt-3 grid gap-3 rounded-xl bg-brand-cream p-4 sm:grid-cols-2">
                        <p class="flex items-center gap-2 text-sm"><AppIcon name="phone" class="size-4 text-brand-burgundy" />{{ detail.customer.phone || 'Nomor telepon belum ditambahkan' }}</p>
                        <p class="flex min-w-0 items-center gap-2 text-sm"><AppIcon name="mail" class="size-4 text-brand-burgundy" /><span class="truncate">{{ detail.customer.email || 'Email belum ditambahkan' }}</span></p>
                    </div>
                </section>

                <section class="mt-5">
                    <h3 class="text-xs font-bold uppercase tracking-[0.14em] text-brand-muted">Catatan</h3>
                    <p class="mt-3 min-h-12 whitespace-pre-wrap rounded-xl bg-brand-cream p-4 text-sm leading-6 text-brand-muted">{{ detail.customer.notes || 'Belum ada catatan.' }}</p>
                </section>

                <section class="mt-5">
                    <h3 class="text-xs font-bold uppercase tracking-[0.14em] text-brand-muted">Ringkasan</h3>
                    <dl class="mt-3 grid grid-cols-3 gap-2">
                        <div class="rounded-xl border border-brand-border p-3">
                            <dt class="text-[0.68rem] leading-4 text-brand-muted">Janji temu</dt>
                            <dd class="mt-1 text-lg font-semibold">{{ detail.summary.appointments }}</dd>
                        </div>
                        <div class="rounded-xl border border-brand-border p-3">
                            <dt class="text-[0.68rem] leading-4 text-brand-muted">Total belanja</dt>
                            <dd class="mt-1 text-sm font-semibold leading-6">{{ formatCurrency(detail.summary.total_spent) }}</dd>
                        </div>
                        <div class="rounded-xl border border-brand-border p-3">
                            <dt class="text-[0.68rem] leading-4 text-brand-muted">Kunjungan terakhir</dt>
                            <dd class="mt-1 text-sm font-semibold leading-6">{{ formatDate(detail.summary.last_visit) }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="mt-5">
                    <h3 class="text-xs font-bold uppercase tracking-[0.14em] text-brand-muted">Riwayat janji temu</h3>
                    <div v-if="detail.appointments.length" class="mt-3 flex flex-col gap-2">
                        <article v-for="appointment in detail.appointments" :key="appointment.id" class="grid grid-cols-[5.5rem_minmax(0,1fr)_auto] items-center gap-2 rounded-xl bg-brand-cream px-3 py-3 text-xs">
                            <span class="font-medium">{{ formatDate(appointment.date) }}</span>
                            <span class="truncate text-brand-muted">{{ appointment.services.join(', ') || 'Belum ada layanan' }}</span>
                            <span class="rounded-full bg-white px-2 py-1 font-semibold text-brand-burgundy">{{ statusLabel(appointment.status) }}</span>
                        </article>
                    </div>
                    <p v-else class="mt-3 rounded-xl bg-brand-cream p-4 text-sm text-brand-muted">Belum ada janji temu.</p>
                </section>
            </div>

            <footer class="flex justify-end gap-3 border-t border-brand-border px-5 py-4">
                <button type="button" class="flex min-h-11 items-center gap-2 rounded-lg border border-brand-border px-4 text-sm font-semibold text-brand-burgundy hover:bg-brand-buff" @click="emit('edit', detail.customer)">
                    <AppIcon name="edit" class="size-4" />Ubah
                </button>
                <button type="button" class="flex min-h-11 items-center gap-2 rounded-lg border border-red-200 px-4 text-sm font-semibold text-red-700 hover:bg-red-50" @click="emit('delete', detail.customer)">
                    <AppIcon name="trash" class="size-4" />Hapus
                </button>
            </footer>
        </section>
    </div>
</template>
