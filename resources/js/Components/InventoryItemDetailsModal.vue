<script setup>
import AppIcon from './AppIcon.vue';

defineProps({
    detail: { type: Object, required: true },
});
const emit = defineEmits(['close', 'adjust', 'edit', 'delete', 'history']);

function formatCurrency(value) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
}

function formatQuantity(value) {
    return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value ?? 0));
}

function formatDate(value) {
    return value ? new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(value)) : '—';
}
</script>

<template>
    <div class="fixed inset-0 z-[60] flex items-end justify-center bg-brand-ink/35 backdrop-blur-[2px] sm:items-center sm:p-5" @click.self="emit('close')">
        <section role="dialog" aria-modal="true" aria-label="Detail barang inventaris" class="flex max-h-[94svh] w-full flex-col rounded-t-3xl border border-brand-border bg-white shadow-[0_24px_48px_-8px_rgba(43,38,39,0.16)] sm:max-w-xl sm:rounded-2xl">
            <header class="flex items-center justify-between border-b border-brand-border px-5 py-4">
                <div><p class="text-xs font-semibold uppercase tracking-[0.14em] text-brand-burgundy">Ringkasan barang</p><h2 class="mt-1 text-lg font-bold">{{ detail.name }}</h2></div>
                <button type="button" aria-label="Tutup" class="flex size-10 items-center justify-center rounded-lg text-brand-muted hover:bg-brand-buff" @click="emit('close')"><AppIcon name="close" /></button>
            </header>
            <div class="overflow-y-auto p-5">
                <p class="text-sm font-semibold text-brand-burgundy">{{ detail.category.name }}</p>
                <p class="mt-1 text-sm leading-6 text-brand-muted">{{ detail.description || 'Belum ada deskripsi.' }}</p>
                <section class="mt-5 rounded-2xl border border-brand-border bg-brand-cream p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-brand-muted">Stok saat ini</p>
                    <p class="mt-1 text-4xl font-bold tracking-tight">{{ formatQuantity(detail.quantity) }} <span class="text-xl font-semibold">{{ detail.unit }}</span></p>
                    <span :class="detail.is_low_stock ? 'bg-amber-100 text-amber-900' : 'bg-emerald-100 text-emerald-900'" class="mt-3 inline-flex rounded-full px-3 py-1 text-xs font-semibold">{{ detail.is_low_stock ? 'Stok menipis' : 'Stok tersedia' }}</span>
                    <p v-if="detail.is_low_stock" class="mt-2 text-xs text-amber-900">Di bawah stok minimum {{ formatQuantity(detail.minimum_stock) }} {{ detail.unit }}. Stok tetap dapat disesuaikan.</p>
                </section>
                <dl class="mt-3 grid grid-cols-2 gap-3">
                    <div class="rounded-xl border border-brand-border p-3"><dt class="text-xs text-brand-muted">Stok minimum</dt><dd class="mt-1 font-semibold">{{ formatQuantity(detail.minimum_stock) }} {{ detail.unit }}</dd></div>
                    <div class="rounded-xl border border-brand-border p-3"><dt class="text-xs text-brand-muted">Harga satuan</dt><dd class="mt-1 font-semibold">{{ formatCurrency(detail.unit_cost) }}</dd></div>
                </dl>
                <section v-if="detail.movements" class="mt-5">
                    <div class="flex items-center justify-between"><h3 class="text-xs font-bold uppercase tracking-[0.14em] text-brand-muted">Riwayat pergerakan terbaru</h3><button type="button" class="text-xs font-semibold text-brand-burgundy hover:underline" @click="emit('history')">Lihat semua</button></div>
                    <div v-if="detail.movements.length" class="mt-2 divide-y divide-brand-border rounded-xl border border-brand-border px-3">
                        <article v-for="movement in detail.movements.slice(0, 3)" :key="movement.id" class="flex items-center justify-between gap-2 py-2.5">
                            <div class="flex min-w-0 items-center gap-2.5">
                                <span :class="movement.type === 'IN' ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800'" class="flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-bold">{{ movement.type === 'IN' ? '+' : '−' }}</span>
                                <div class="min-w-0"><p class="truncate text-sm font-semibold">{{ movement.reason }}</p><p class="text-xs text-brand-muted">{{ formatDate(movement.created_at) }}</p></div>
                            </div>
                            <p class="shrink-0 text-right text-xs font-semibold">{{ movement.type === 'IN' ? '+' : '−' }}{{ formatQuantity(movement.quantity) }} {{ detail.unit }}<span class="block font-normal text-brand-muted">Saldo {{ formatQuantity(movement.resulting_balance) }}</span></p>
                        </article>
                    </div>
                    <p v-else class="mt-2 rounded-xl bg-brand-cream p-3 text-sm text-brand-muted">Belum ada pergerakan stok.</p>
                </section>
            </div>
            <footer class="flex flex-wrap justify-end gap-2 border-t border-brand-border px-5 py-4">
                <button type="button" class="mr-auto flex min-h-10 items-center gap-1.5 rounded-lg border border-red-200 px-3 text-xs font-semibold text-red-700 hover:bg-red-50" @click="emit('delete', detail)">                <AppIcon name="trash" class="size-4" />Hapus</button>
                                <button type="button" class="min-h-10 rounded-lg border border-brand-border px-3 text-xs font-semibold text-brand-burgundy hover:bg-brand-buff" @click="emit('edit', detail)">Ubah barang</button>
                                <button type="button" class="min-h-10 rounded-lg bg-brand-burgundy px-3 text-xs font-semibold text-white hover:bg-brand-burgundy-dark" @click="emit('adjust', detail)">Sesuaikan stok</button>
            </footer>
        </section>
    </div>
</template>
