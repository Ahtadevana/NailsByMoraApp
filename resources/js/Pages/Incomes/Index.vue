<script setup>
import { computed, reactive, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppIcon from '../../Components/AppIcon.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    incomes: { type: Object, required: true },
    filters: { type: Object, required: true },
    summary: { type: Object, required: true },
});
const filters = reactive({ ...props.filters });
const pageNumbers = computed(() => [...new Set([
    1,
    props.incomes.last_page,
    props.incomes.current_page - 1,
    props.incomes.current_page,
    props.incomes.current_page + 1,
])].filter((number) => number >= 1 && number <= props.incomes.last_page).sort((a, b) => a - b));
let searchTimer;
let syncingFilters = false;

watch(() => props.filters, async (updated) => {
    syncingFilters = true;
    Object.assign(filters, updated);
    await new Promise((resolve) => setTimeout(resolve, 0));
    syncingFilters = false;
}, { deep: true });

watch(() => filters.search, () => {
    if (syncingFilters) return;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => visit(1), 300);
});
watch(() => [filters.date_from, filters.date_to, filters.per_page], ([from, to]) => {
    if (!syncingFilters && from && to) visit(1);
});

function visit(page = 1) {
    router.get('/incomes', {
        search: filters.search || undefined,
        date_from: filters.date_from || undefined,
        date_to: filters.date_to || undefined,
        per_page: Number(filters.per_page) === 10 ? undefined : filters.per_page,
        page: page === 1 ? undefined : page,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function formatCurrency(value) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
}

function formatDate(value) {
    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${String(value).slice(0, 10)}T00:00:00`));
}
</script>

<template>
    <AppLayout>
        <main class="mx-auto flex w-full max-w-6xl flex-col gap-5 px-4 pb-10 pt-5 sm:px-6">
            <header>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-brand-burgundy">Keuangan</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-brand-ink">Log Pemasukan</h1>
                <p class="mt-1 max-w-xl text-sm leading-5 text-brand-muted">Pemasukan dihitung dari layanan pada janji temu yang sudah selesai.</p>
            </header>

            <section class="grid gap-3 sm:grid-cols-2">
                <article class="rounded-2xl border border-brand-border bg-white p-4 shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)]">
                    <p class="text-sm text-brand-muted">Total pemasukan terfilter</p>
                    <p class="mt-2 text-2xl font-bold text-brand-ink">{{ formatCurrency(summary.amount) }}</p>
                </article>
                <article class="rounded-2xl border border-brand-border bg-white p-4 shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)]">
                    <p class="text-sm text-brand-muted">Layanan selesai</p>
                    <p class="mt-2 text-2xl font-bold text-brand-ink">{{ summary.count }}</p>
                </article>
            </section>

            <section class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)]">
                <div class="grid gap-3 border-b border-brand-border p-4 sm:grid-cols-2 lg:grid-cols-[minmax(14rem,1fr)_auto_auto_auto] lg:items-end">
                    <label class="flex flex-col gap-1.5 text-xs font-semibold text-brand-muted">Cari pelanggan atau layanan
                        <span class="relative">
                            <AppIcon name="search" class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-brand-muted" />
                            <input v-model="filters.search" type="search" placeholder="Nama pelanggan atau layanan" class="min-h-11 w-full rounded-lg border border-brand-border bg-white pl-9 pr-3 text-sm text-brand-ink outline-none focus:border-brand-burgundy">
                        </span>
                    </label>
                    <label class="flex flex-col gap-1.5 text-xs font-semibold text-brand-muted">Dari tanggal
                        <input v-model="filters.date_from" type="date" class="min-h-11 rounded-lg border border-brand-border bg-white px-3 text-sm text-brand-ink outline-none focus:border-brand-burgundy">
                    </label>
                    <label class="flex flex-col gap-1.5 text-xs font-semibold text-brand-muted">Sampai tanggal
                        <input v-model="filters.date_to" type="date" class="min-h-11 rounded-lg border border-brand-border bg-white px-3 text-sm text-brand-ink outline-none focus:border-brand-burgundy">
                    </label>
                    <label class="flex flex-col gap-1.5 text-xs font-semibold text-brand-muted">Baris
                        <select v-model="filters.per_page" class="min-h-11 rounded-lg border border-brand-border bg-white px-3 text-sm text-brand-ink outline-none focus:border-brand-burgundy">
                            <option :value="10">10</option><option :value="25">25</option><option :value="50">50</option><option :value="100">100</option>
                        </select>
                    </label>
                </div>
                <div v-if="incomes.data.length" class="overflow-x-auto">
                    <table class="w-full min-w-[680px] border-collapse text-left text-sm">
                        <thead class="bg-brand-buff text-xs uppercase tracking-wide text-brand-muted">
                            <tr><th class="w-14 px-4 py-3">#</th><th class="px-4 py-3">Tanggal</th><th class="px-4 py-3">Pelanggan</th><th class="px-4 py-3">Layanan</th><th class="px-4 py-3">Waktu</th><th class="px-4 py-3 text-right">Pemasukan</th></tr>
                        </thead>
                        <tbody class="divide-y divide-brand-border">
                            <tr v-for="(income, index) in incomes.data" :key="income.id" class="hover:bg-brand-buff/40">
                                <td class="px-4 py-3 text-brand-muted">{{ (incomes.from ?? 1) + index }}</td>
                                <td class="whitespace-nowrap px-4 py-3">{{ formatDate(income.appointment_date) }}</td>
                                <td class="px-4 py-3 font-medium">{{ income.customer_name }}</td>
                                <td class="px-4 py-3">{{ income.service_name }}</td>
                                <td class="whitespace-nowrap px-4 py-3">{{ String(income.start_time).slice(0, 5) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums">{{ formatCurrency(income.amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="px-4 py-12 text-center text-sm text-brand-muted">Belum ada pemasukan dari janji temu selesai pada periode ini.</p>
                <footer v-if="incomes.last_page > 1" class="flex flex-wrap items-center justify-between gap-3 border-t border-brand-border px-4 py-3">
                    <p class="text-xs text-brand-muted">Menampilkan {{ incomes.from }}–{{ incomes.to }} dari {{ incomes.total }}</p>
                    <nav aria-label="Halaman log pemasukan" class="flex items-center gap-2">
                        <button type="button" :disabled="incomes.current_page <= 1" class="min-h-9 rounded-lg border border-brand-border px-3 text-xs font-semibold disabled:opacity-40" @click="visit(incomes.current_page - 1)">Sebelumnya</button>
                        <span class="text-xs text-brand-muted">Halaman {{ incomes.current_page }} dari {{ incomes.last_page }}</span>
                        <button type="button" :disabled="incomes.current_page >= incomes.last_page" class="min-h-9 rounded-lg border border-brand-border px-3 text-xs font-semibold disabled:opacity-40" @click="visit(incomes.current_page + 1)">Berikutnya</button>
                    </nav>
                </footer>
            </section>
        </main>
    </AppLayout>
</template>
