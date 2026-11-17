<script setup>
import AppIcon from './AppIcon.vue';

defineProps({
    appointment: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['close', 'edit', 'delete', 'status']);

function formatCurrency(value) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
}

function formatDate(value) {
    return new Intl.DateTimeFormat('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(new Date(`${value}T00:00:00`));
}

function formatTimestamp(value) {
    return value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '—';
}

function statusLabel(status) {
    return {
        SCHEDULED: 'Terjadwal',
        COMPLETED: 'Selesai',
        CANCELLED: 'Dibatalkan',
        NO_SHOW: 'Tidak hadir',
    }[status] ?? status;
}
</script>

<template>
    <div class="fixed inset-0 z-[60] flex items-end justify-center bg-brand-ink/35 backdrop-blur-[2px] sm:items-center sm:p-5" @click.self="emit('close')">
        <section role="dialog" aria-modal="true" aria-label="Detail janji temu" class="flex max-h-[94svh] w-full flex-col rounded-t-3xl border border-brand-border bg-white shadow-[0_24px_48px_-8px_rgba(43,38,39,0.16)] sm:max-w-2xl sm:rounded-2xl">
            <header class="flex items-center justify-between border-b border-brand-border px-5 py-4">
                <div><p class="text-xs font-semibold uppercase tracking-[0.14em] text-brand-burgundy">Janji temu #{{ appointment.id }}</p><h2 class="mt-1 text-lg font-bold">Detail pemesanan</h2></div>
                <button type="button" aria-label="Tutup" class="flex size-10 items-center justify-center rounded-lg text-brand-muted hover:bg-brand-buff" @click="emit('close')"><AppIcon name="close" /></button>
            </header>
            <div class="overflow-y-auto px-5 py-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-xl font-bold">{{ appointment.customer.name }}</h3>
                        <div class="mt-1 flex flex-col gap-0.5 text-sm text-brand-muted">
                            <span>{{ appointment.customer.phone || 'Nomor telepon belum ditambahkan' }}</span>
                            <span>{{ appointment.customer.email || 'Email belum ditambahkan' }}</span>
                        </div>
                    </div>
                    <span :class="appointment.status === 'SCHEDULED' ? 'bg-amber-50 text-amber-800' : appointment.status === 'COMPLETED' ? 'bg-emerald-50 text-emerald-800' : appointment.status === 'NO_SHOW' ? 'bg-red-50 text-red-800' : 'bg-brand-buff text-brand-muted'" class="rounded-full px-3 py-1.5 text-xs font-semibold">{{ statusLabel(appointment.status) }}</span>
                </div>
                <section class="mt-5 grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-brand-cream p-3"><p class="text-xs text-brand-muted">Tanggal</p><p class="mt-1 text-sm font-semibold">{{ formatDate(appointment.date) }}</p></div>
                    <div class="rounded-xl bg-brand-cream p-3"><p class="text-xs text-brand-muted">Waktu</p><p class="mt-1 text-sm font-semibold">{{ appointment.start_time }}–{{ appointment.end_time }}</p></div>
                </section>
                <section class="mt-5">
                    <h3 class="text-xs font-bold uppercase tracking-[0.14em] text-brand-muted">Layanan</h3>
                    <div class="mt-2 divide-y divide-brand-border rounded-xl border border-brand-border px-3">
                        <article v-for="service in appointment.services" :key="service.id" class="flex items-center justify-between gap-3 py-3">
                            <div class="min-w-0"><p class="truncate text-sm font-semibold">{{ service.name }}</p><p class="mt-0.5 text-xs text-brand-muted">{{ service.duration_minutes }} menit</p></div>
                            <p class="shrink-0 text-sm font-semibold">{{ formatCurrency(service.price) }}</p>
                        </article>
                    </div>
                    <dl class="mt-2 flex justify-between rounded-xl bg-brand-buff px-3.5 py-3 text-sm"><div><dt class="font-semibold">Total</dt><dd class="mt-0.5 text-xs text-brand-muted">{{ appointment.total_duration }} menit</dd></div><dd class="self-center font-bold">{{ formatCurrency(appointment.total_price) }}</dd></dl>
                </section>
                <section class="mt-5"><h3 class="text-xs font-bold uppercase tracking-[0.14em] text-brand-muted">Catatan</h3><p class="mt-2 whitespace-pre-wrap rounded-xl bg-brand-cream p-3.5 text-sm leading-6">{{ appointment.notes || 'Belum ada catatan.' }}</p></section>
                <p class="mt-4 text-xs leading-5 text-brand-muted">Dibuat {{ formatTimestamp(appointment.created_at) }} · Diperbarui {{ formatTimestamp(appointment.updated_at) }}</p>
            </div>
            <footer class="flex flex-wrap justify-end gap-2 border-t border-brand-border px-5 py-4">
                <button v-if="appointment.status === 'SCHEDULED'" type="button" class="min-h-10 rounded-lg border border-brand-border px-3 text-xs font-semibold text-brand-muted hover:bg-brand-buff" @click="emit('status', 'CANCELLED')">Batalkan</button>
                <button v-if="appointment.status === 'SCHEDULED'" type="button" class="min-h-10 rounded-lg border border-brand-border px-3 text-xs font-semibold text-brand-muted hover:bg-brand-buff" @click="emit('status', 'NO_SHOW')">Tandai tidak hadir</button>
                <button v-if="appointment.status === 'SCHEDULED'" type="button" class="min-h-10 rounded-lg bg-emerald-700 px-3 text-xs font-semibold text-white hover:bg-emerald-800" @click="emit('status', 'COMPLETED')">Tandai selesai</button>
                <button type="button" class="flex min-h-10 items-center gap-1.5 rounded-lg border border-brand-border px-3 text-xs font-semibold text-brand-burgundy hover:bg-brand-buff" @click="emit('edit', appointment)"><AppIcon name="edit" class="size-4" />Ubah</button>
                <button type="button" class="flex min-h-10 items-center gap-1.5 rounded-lg border border-red-200 px-3 text-xs font-semibold text-red-700 hover:bg-red-50" @click="emit('delete', appointment)"><AppIcon name="trash" class="size-4" />Hapus</button>
            </footer>
        </section>
    </div>
</template>
