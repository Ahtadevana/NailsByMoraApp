<script setup>
import AppIcon from './AppIcon.vue';

defineProps({
    expense: { type: Object, required: true },
});
const emit = defineEmits(['close', 'edit', 'delete']);

function formatCurrency(value) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
}

function formatDate(value, options = { day: 'numeric', month: 'long', year: 'numeric' }) {
    if (!value) return '—';

    const date = options.timeStyle
        ? new Date(value)
        : new Date(`${value.slice(0, 10)}T00:00:00`);

    return new Intl.DateTimeFormat('id-ID', options).format(date);
}
</script>

<template>
    <div class="fixed inset-0 z-[60] flex items-end justify-center bg-brand-ink/35 backdrop-blur-[2px] sm:items-center sm:p-5" @click.self="emit('close')">
        <section role="dialog" aria-modal="true" aria-label="Detail pengeluaran" class="flex max-h-[94svh] w-full flex-col rounded-t-3xl border border-brand-border bg-white shadow-[0_24px_48px_-8px_rgba(43,38,39,0.16)] sm:max-w-xl sm:rounded-2xl">
            <header class="flex items-center justify-between border-b border-brand-border px-5 py-4">
                <div><p class="text-xs font-semibold uppercase tracking-[0.14em] text-brand-burgundy">Detail pengeluaran</p><h2 class="mt-1 text-lg font-bold">{{ expense.name }}</h2></div>
                <button type="button" aria-label="Tutup" class="flex size-10 items-center justify-center rounded-lg text-brand-muted hover:bg-brand-buff" @click="emit('close')"><AppIcon name="close" /></button>
            </header>
            <div class="overflow-y-auto p-5">
                <section class="rounded-2xl border border-brand-border bg-brand-cream p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-brand-muted">Jumlah</p>
                    <p class="mt-1 text-3xl font-bold tracking-tight text-brand-ink">{{ formatCurrency(expense.amount) }}</p>
                    <p class="mt-2 text-sm font-semibold text-brand-burgundy">{{ expense.category.name }}</p>
                </section>
                <dl class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-brand-border p-3"><dt class="text-xs text-brand-muted">Metode pembayaran</dt><dd class="mt-1 font-semibold">{{ { CASH: 'Tunai', 'BANK TRANSFER': 'Transfer bank', QRIS: 'QRIS', OTHER: 'Lainnya' }[expense.payment_method] }}</dd></div>
                    <div class="rounded-xl border border-brand-border p-3"><dt class="text-xs text-brand-muted">Tanggal pengeluaran</dt><dd class="mt-1 font-semibold">{{ formatDate(expense.expense_date) }}</dd></div>
                    <div class="rounded-xl border border-brand-border p-3 sm:col-span-2"><dt class="text-xs text-brand-muted">Catatan</dt><dd class="mt-1 whitespace-pre-wrap text-sm">{{ expense.note || 'Tidak ada catatan.' }}</dd></div>
                    <div class="rounded-xl border border-brand-border p-3"><dt class="text-xs text-brand-muted">Dibuat</dt><dd class="mt-1 text-sm font-medium">{{ formatDate(expense.created_at, { dateStyle: 'medium', timeStyle: 'short' }) }}</dd></div>
                    <div class="rounded-xl border border-brand-border p-3"><dt class="text-xs text-brand-muted">Terakhir diperbarui</dt><dd class="mt-1 text-sm font-medium">{{ formatDate(expense.updated_at, { dateStyle: 'medium', timeStyle: 'short' }) }}</dd></div>
                </dl>
            </div>
            <footer class="flex justify-end gap-2 border-t border-brand-border px-5 py-4">
                <button type="button" class="mr-auto flex min-h-10 items-center gap-1.5 rounded-lg border border-red-200 px-3 text-xs font-semibold text-red-700 hover:bg-red-50" @click="emit('delete', expense)">                <AppIcon name="trash" class="size-4" />Hapus</button>
                                <button type="button" class="min-h-10 rounded-lg border border-brand-border px-4 text-sm font-semibold text-brand-burgundy hover:bg-brand-buff" @click="emit('edit', expense)">Ubah</button>
            </footer>
        </section>
    </div>
</template>
