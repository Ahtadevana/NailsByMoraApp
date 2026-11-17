<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppIcon from '../../Components/AppIcon.vue';
import ConfirmDialog from '../../Components/ConfirmDialog.vue';
import ExpenseCategoryFormModal from '../../Components/ExpenseCategoryFormModal.vue';
import ExpenseDetailsModal from '../../Components/ExpenseDetailsModal.vue';
import ExpenseFormModal from '../../Components/ExpenseFormModal.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    expenses: { type: Object, required: true },
    categories: { type: Array, required: true },
    filters: { type: Object, required: true },
    summary: { type: Object, required: true },
});

const page = usePage();
const filters = reactive({ ...props.filters });
const selectedIds = ref([]);
const expenseForm = ref(null);
const categoryForm = ref(null);
const expenseDetail = ref(null);
const detailLoading = ref(false);
const confirmAction = ref(null);
const deleteForm = useForm({});
const bulkDeleteForm = useForm({ ids: [] });
const toast = ref(null);
const visibleCategories = computed(() => props.categories.filter((category) => (
    !filters.search
    || category.name.toLowerCase().includes(filters.search.toLowerCase())
    || (category.description ?? '').toLowerCase().includes(filters.search.toLowerCase())
)));
const allCurrentPageSelected = computed(() => (
    props.expenses.data.length > 0
    && props.expenses.data.every((expense) => selectedIds.value.includes(expense.id))
));
const someCurrentPageSelected = computed(() => (
    props.expenses.data.some((expense) => selectedIds.value.includes(expense.id))
    && !allCurrentPageSelected.value
));
const pageNumbers = computed(() => {
    const current = props.expenses.current_page;
    const last = props.expenses.last_page;

    return [...new Set([1, last, current - 1, current, current + 1])]
        .filter((number) => number >= 1 && number <= last)
        .sort((left, right) => left - right);
});

let syncingFilters = false;
let searchTimer;
let toastTimer;

watch(() => props.filters, async (updated) => {
    syncingFilters = true;
    Object.assign(filters, updated);
    await new Promise((resolve) => setTimeout(resolve, 0));
    syncingFilters = false;
}, { deep: true });
watch(() => page.props.flash?.toast, (message) => {
    if (message) showToast(message.type, message.message);
});
watch(() => props.expenses.data.map((expense) => expense.id), (visibleIds) => {
    selectedIds.value = selectedIds.value.filter((id) => visibleIds.includes(id));
});
watch(() => filters.search, () => {
    selectedIds.value = [];
    if (syncingFilters || filters.tab === 'categories') return;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters(), 300);
});
watch(() => [filters.category_id, filters.payment_method, filters.date_filter, filters.sort, filters.per_page], () => {
    selectedIds.value = [];
    if (syncingFilters || filters.tab !== 'expenses') return;
    if (filters.date_filter === 'custom' && (!filters.date_from || !filters.date_to)) return;
    applyFilters();
});
watch(() => [filters.date_from, filters.date_to], ([from, to], [previousFrom, previousTo]) => {
    selectedIds.value = [];
    if (syncingFilters || filters.tab !== 'expenses' || filters.date_filter !== 'custom') return;
    if (!from || !to || (from === previousFrom && to === previousTo)) return;
    applyFilters();
});
watch(() => filters.tab, () => {
    selectedIds.value = [];
    if (!syncingFilters) applyFilters();
});

onBeforeUnmount(() => {
    clearTimeout(searchTimer);
    clearTimeout(toastTimer);
});

onMounted(() => {
    if (new URLSearchParams(page.url.split('?')[1] ?? '').get('create') === '1') {
        expenseForm.value = {};
    }
});

function queryValues(pageNumber = 1) {
    const isCustomDateRange = filters.date_filter === 'custom' && filters.date_from && filters.date_to;

    return {
        tab: filters.tab === 'expenses' ? undefined : filters.tab,
        search: filters.tab === 'expenses' ? filters.search || undefined : undefined,
        category_id: filters.category_id || undefined,
        payment_method: filters.payment_method === 'all' ? undefined : filters.payment_method,
        date_filter: filters.date_filter === 'all' ? undefined : filters.date_filter,
        date_from: isCustomDateRange ? filters.date_from : undefined,
        date_to: isCustomDateRange ? filters.date_to : undefined,
        sort: filters.sort === 'newest' ? undefined : filters.sort,
        per_page: Number(filters.per_page) === 10 ? undefined : filters.per_page,
        page: pageNumber === 1 ? undefined : pageNumber,
    };
}

function applyFilters() {
    if (filters.date_filter === 'custom' && (!filters.date_from || !filters.date_to)) return;

    selectedIds.value = [];
    router.get('/expenses', queryValues(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['expenses', 'categories', 'filters', 'summary'],
        onSuccess: () => { selectedIds.value = []; },
    });
}

function goToPage(pageNumber) {
    selectedIds.value = [];
    router.get('/expenses', queryValues(pageNumber), {
        preserveState: true,
        preserveScroll: true,
        only: ['expenses', 'categories', 'filters', 'summary'],
        onSuccess: () => { selectedIds.value = []; },
    });
}

function toggleCurrentPage(event) {
    selectedIds.value = event.target.checked ? props.expenses.data.map((expense) => expense.id) : [];
}

function showToast(type, message) {
    clearTimeout(toastTimer);
    toast.value = { type, message };
    toastTimer = setTimeout(() => { toast.value = null; }, 3500);
}

function refreshExpenses() {
    expenseForm.value = null;
    categoryForm.value = null;
    expenseDetail.value = null;
    selectedIds.value = [];
    router.reload({ only: ['expenses', 'categories', 'summary'] });
}

async function openExpense(expense) {
    detailLoading.value = true;
    try {
        const response = await fetch(`/expenses/${expense.id}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!response.ok) throw new Error('Detail pengeluaran gagal dimuat. Silakan coba lagi.');
        expenseDetail.value = (await response.json()).expense;
    } catch (error) {
        showToast('error', error.message || 'Detail pengeluaran could not be loaded.');
    } finally {
        detailLoading.value = false;
    }
}

function editExpense(expense) {
    expenseDetail.value = null;
    expenseForm.value = { ...expense };
}

function askDeleteExpense(expense) {
    confirmAction.value = { type: 'expense', expense };
}

function askDeleteCategory(category) {
    confirmAction.value = { type: 'category', category };
}

function askBulkDelete() {
    confirmAction.value = { type: 'bulk', count: selectedIds.value.length };
}

function runAction(event, action) {
    event.currentTarget.closest('details').open = false;
    action();
}

function confirmDelete() {
    if (confirmAction.value?.type === 'bulk') {
        bulkDeleteForm.ids = [...selectedIds.value];
        bulkDeleteForm.delete('/expenses/bulk', {
            onSuccess: () => {
                confirmAction.value = null;
                expenseDetail.value = null;
                selectedIds.value = [];
                router.reload({ only: ['expenses', 'summary'] });
            },
        });
        return;
    }

    const isExpense = confirmAction.value?.type === 'expense';
    const url = isExpense
        ? `/expenses/${confirmAction.value.expense.id}`
        : `/expenses/categories/${confirmAction.value.category.id}`;

    deleteForm.delete(url, {
        onSuccess: () => {
            confirmAction.value = null;
            expenseDetail.value = null;
            selectedIds.value = [];
            router.reload({ only: ['expenses', 'categories', 'summary'] });
        },
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
    return value ? new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${value.slice(0, 10)}T00:00:00`)) : '—';
}
</script>

<template>
    <AppLayout>
        <main class="mx-auto flex w-full max-w-6xl flex-col gap-5 px-4 pb-10 pt-5 sm:px-6">
            <header class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-brand-burgundy">Keuangan studio</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-brand-ink">Manajemen Pengeluaran</h1>
                    <p class="mt-1 max-w-xl text-sm leading-5 text-brand-muted">Catat pengeluaran dan atur kategorinya.</p>
                </div>
                <button v-if="filters.tab === 'expenses'" type="button" :disabled="!categories.length" class="flex min-h-11 shrink-0 items-center gap-2 rounded-xl bg-brand-burgundy px-3.5 text-sm font-semibold text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.25)] hover:bg-brand-burgundy-dark disabled:opacity-50" @click="expenseForm = {}">
                    <AppIcon name="plus" /><span class="hidden xs:inline">Tambah Pengeluaran</span><span class="xs:hidden">Tambah</span>
                </button>
                <button v-else type="button" class="flex min-h-11 shrink-0 items-center gap-2 rounded-xl bg-brand-burgundy px-3.5 text-sm font-semibold text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.25)] hover:bg-brand-burgundy-dark" @click="categoryForm = {}">
                    <AppIcon name="plus" /><span class="hidden xs:inline">Tambah Kategori</span><span class="xs:hidden">Tambah</span>
                </button>
            </header>

            <p v-if="filters.tab === 'expenses' && !categories.length" role="alert" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-5 text-amber-900">
                Tambahkan kategori pengeluaran sebelum mencatat pengeluaran.
                <button type="button" class="font-semibold underline" @click="filters.tab = 'categories'; categoryForm = {}">Tambah kategori</button>
            </p>

            <section aria-label="Ringkasan pengeluaran" class="grid grid-cols-2 gap-3">
                <article class="rounded-2xl border border-brand-border bg-white p-4 shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)]">
                    <p class="text-xs font-medium text-brand-muted">Pengeluaran terfilter <span class="font-normal">({{ summary.count }})</span></p>
                    <p class="mt-2 truncate text-xl font-bold sm:text-2xl">{{ formatCurrency(summary.total) }}</p>
                </article>
                <article class="rounded-2xl border border-brand-border bg-white p-4 shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)]">
                    <p class="text-xs font-medium text-brand-muted">Bulan ini <span class="font-normal">(terfilter)</span></p>
                    <p class="mt-2 truncate text-xl font-bold text-brand-burgundy sm:text-2xl">{{ formatCurrency(summary.this_month) }}</p>
                </article>
            </section>

            <section class="rounded-2xl border border-brand-border bg-white p-1.5 shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)]">
                <div class="grid grid-cols-2 gap-1" role="tablist" aria-label="Bagian pengeluaran">
                    <button id="expenses-tab" type="button" role="tab" :aria-selected="filters.tab === 'expenses'" :class="filters.tab === 'expenses' ? 'bg-brand-burgundy text-white shadow-sm' : 'text-brand-muted hover:bg-brand-buff'" class="flex min-h-11 items-center justify-center gap-2 rounded-xl text-sm font-semibold" @click="filters.tab = 'expenses'"><AppIcon name="expenses" class="size-4" />Pengeluaran</button>
                    <button id="expense-categories-tab" type="button" role="tab" :aria-selected="filters.tab === 'categories'" :class="filters.tab === 'categories' ? 'bg-brand-burgundy text-white shadow-sm' : 'text-brand-muted hover:bg-brand-buff'" class="flex min-h-11 items-center justify-center gap-2 rounded-xl text-sm font-semibold" @click="filters.tab = 'categories'"><AppIcon name="services" class="size-4" />Kategori <span class="rounded-full bg-white/20 px-1.5 py-0.5 text-xs">{{ categories.length }}</span></button>
                </div>
            </section>

            <section class="flex flex-col gap-3">
                <label class="flex min-h-12 items-center gap-3 rounded-xl border border-brand-border bg-white px-3.5 focus-within:border-brand-burgundy">
                    <AppIcon name="search" class="text-brand-muted" />
                    <input v-model="filters.search" type="search" :placeholder="filters.tab === 'expenses' ? 'Cari nama pengeluaran…' : 'Cari kategori…'" class="w-full bg-transparent text-base outline-none placeholder:text-brand-muted">
                </label>
                <div v-if="filters.tab === 'expenses'" class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <label class="relative">
                        <span class="sr-only">Filter kategori pengeluaran</span>
                        <select v-model="filters.category_id" class="min-h-11 w-full appearance-none rounded-lg border border-brand-border bg-white py-2 pl-3 pr-8 text-sm font-medium">
                            <option value="">Semua kategori</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                        </select><AppIcon name="chevron-down" class="pointer-events-none absolute right-2 top-3 size-4 text-brand-muted" />
                    </label>
                    <label class="relative">
                        <span class="sr-only">Filter metode pembayaran</span>
                        <select v-model="filters.payment_method" class="min-h-11 w-full appearance-none rounded-lg border border-brand-border bg-white py-2 pl-3 pr-8 text-sm font-medium">
                            <option value="all">Pembayaran: Semua</option><option value="CASH">Tunai</option><option value="BANK TRANSFER">Transfer bank</option><option value="QRIS">QRIS</option><option value="OTHER">Lainnya</option>
                        </select><AppIcon name="chevron-down" class="pointer-events-none absolute right-2 top-3 size-4 text-brand-muted" />
                    </label>
                    <label class="relative">
                        <span class="sr-only">Filter tanggal pengeluaran</span>
                        <select v-model="filters.date_filter" class="min-h-11 w-full appearance-none rounded-lg border border-brand-border bg-white py-2 pl-3 pr-8 text-sm font-medium">
                            <option value="all">Semua tanggal</option><option value="today">Hari ini</option><option value="this_week">Minggu ini</option><option value="this_month">Bulan ini</option><option value="custom">Rentang khusus</option>
                        </select><AppIcon name="chevron-down" class="pointer-events-none absolute right-2 top-3 size-4 text-brand-muted" />
                    </label>
                    <label class="relative">
                        <span class="sr-only">Urutkan pengeluaran</span>
                        <select v-model="filters.sort" class="min-h-11 w-full appearance-none rounded-lg border border-brand-border bg-white py-2 pl-3 pr-8 text-sm font-medium">
                            <option value="newest">Urutkan: Terbaru</option><option value="oldest">Terlama</option><option value="name_asc">Nama A–Z</option><option value="name_desc">Nama Z–A</option><option value="amount_asc">Jumlah: Terendah</option><option value="amount_desc">Jumlah: Tertinggi</option><option value="date_asc">Tanggal: Terlama</option><option value="date_desc">Tanggal: Terbaru</option>
                        </select><AppIcon name="chevron-down" class="pointer-events-none absolute right-2 top-3 size-4 text-brand-muted" />
                    </label>
                </div>
                <div v-if="filters.tab === 'expenses' && filters.date_filter === 'custom'" class="grid grid-cols-2 gap-2">
                    <label class="flex flex-col gap-1 text-xs font-semibold text-brand-muted">Dari
                        <input v-model="filters.date_from" type="date" :max="filters.date_to || undefined" class="min-h-10 rounded-lg border border-brand-border bg-white px-2 text-sm font-normal text-brand-ink">
                    </label>
                    <label class="flex flex-col gap-1 text-xs font-semibold text-brand-muted">Sampai
                        <input v-model="filters.date_to" type="date" :min="filters.date_from || undefined" class="min-h-10 rounded-lg border border-brand-border bg-white px-2 text-sm font-normal text-brand-ink">
                    </label>
                </div>
            </section>

            <section v-if="filters.tab === 'expenses'" class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)]">
                <div v-if="selectedIds.length" class="flex min-h-12 items-center justify-between gap-3 border-b border-brand-border bg-brand-burgundy/5 px-3.5">
                    <p class="text-sm font-semibold">{{ selectedIds.length }} dipilih</p>
                    <div class="flex items-center gap-3">
                        <button type="button" class="text-sm font-semibold text-red-700 hover:underline" @click="askBulkDelete">Hapus pilihan</button>
                        <button type="button" class="text-sm font-medium text-brand-muted hover:underline" @click="selectedIds = []">Bersihkan</button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1020px] border-collapse text-left text-sm">
                        <thead class="bg-brand-buff text-xs uppercase tracking-wide text-brand-muted">
                            <tr>
                                <th class="w-12 px-4 py-3"><input aria-label="Pilih semua pengeluaran di halaman ini" type="checkbox" class="size-4 rounded accent-brand-burgundy" :checked="allCurrentPageSelected" :indeterminate="someCurrentPageSelected" @change="toggleCurrentPage"></th>
                                <th class="w-12 px-2 py-3">#</th><th class="px-3 py-3">Pengeluaran</th><th class="px-3 py-3">Kategori</th><th class="px-3 py-3 text-right">Jumlah</th><th class="px-3 py-3">Metode pembayaran</th><th class="px-3 py-3">Tanggal</th><th class="px-3 py-3">Catatan</th><th class="w-16 px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-border">
                            <tr v-for="(expense, index) in expenses.data" :key="expense.id" class="hover:bg-brand-buff/40">
                                <td class="px-4 py-3"><input v-model="selectedIds" :value="expense.id" :aria-label="`Pilih ${expense.name}`" type="checkbox" class="size-4 rounded accent-brand-burgundy"></td>
                                <td class="px-2 py-3 text-brand-muted">{{ (expenses.from ?? 1) + index }}</td>
                                <td class="max-w-52 px-3 py-3"><button type="button" class="max-w-full truncate text-left font-semibold text-brand-burgundy hover:underline" @click="openExpense(expense)">{{ expense.name }}</button></td>
                                <td class="px-3 py-3"><span class="inline-flex max-w-40 truncate rounded-full bg-brand-cream px-2.5 py-1 text-xs font-medium text-brand-ink">{{ expense.category.name }}</span></td>
                                <td class="whitespace-nowrap px-3 py-3 text-right font-semibold tabular-nums">{{ formatCurrency(expense.amount) }}</td>
                                <td class="px-3 py-3"><span class="inline-flex whitespace-nowrap rounded-full bg-brand-buff px-2.5 py-1 text-[0.68rem] font-semibold text-brand-muted">{{ { CASH: 'Tunai', 'BANK TRANSFER': 'Transfer bank', QRIS: 'QRIS', OTHER: 'Lainnya' }[expense.payment_method] }}</span></td>
                                <td class="whitespace-nowrap px-3 py-3">{{ formatDate(expense.expense_date) }}</td>
                                <td class="max-w-48 px-3 py-3"><span :title="expense.note || 'Tanpa catatan'" class="block truncate text-brand-muted">{{ expense.note || '—' }}</span></td>
                                <td class="px-4 py-3 text-right">
                                    <details class="group relative inline-block text-left">
                                        <summary :aria-label="`Aksi untuk ${expense.name}`" class="flex size-9 cursor-pointer list-none items-center justify-center rounded-lg text-brand-muted hover:bg-brand-buff hover:text-brand-ink focus-visible:outline-2 focus-visible:outline-brand-burgundy"><span aria-hidden="true" class="text-xl leading-none">⋮</span></summary>
                                        <div class="absolute right-0 z-20 mt-1 flex w-36 flex-col rounded-xl border border-brand-border bg-white p-1 shadow-lg">
                                            <button type="button" class="rounded-lg px-3 py-2 text-left text-sm hover:bg-brand-buff" @click="runAction($event, () => openExpense(expense))">Lihat</button>
                                            <button type="button" class="rounded-lg px-3 py-2 text-left text-sm hover:bg-brand-buff" @click="runAction($event, () => editExpense(expense))">Ubah</button>
                                            <button type="button" class="rounded-lg px-3 py-2 text-left text-sm text-red-700 hover:bg-red-50" @click="runAction($event, () => askDeleteExpense(expense))">Hapus</button>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                            <tr v-if="expenses.data.length === 0"><td colspan="9" class="px-4 py-12 text-center"><p class="font-semibold">Pengeluaran tidak ditemukan</p><p class="mt-1 text-sm text-brand-muted">{{ categories.length ? 'Ubah pencarian atau filter, atau tambahkan pengeluaran.' : 'Tambahkan kategori sebelum mencatat pengeluaran.' }}</p></td></tr>
                        </tbody>
                    </table>
                </div>
                <footer class="flex flex-col gap-4 border-t border-brand-border px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-brand-muted">Menampilkan {{ expenses.from ?? 0 }}–{{ expenses.to ?? 0 }} dari {{ expenses.total }} pengeluaran</p>
                    <div class="flex items-center justify-between gap-3 sm:justify-end">
                        <label class="flex items-center gap-2 text-xs text-brand-muted">Baris<select v-model="filters.per_page" class="min-h-9 rounded-lg border border-brand-border bg-white px-2 text-sm text-brand-ink"><option :value="10">10</option><option :value="25">25</option><option :value="50">50</option><option :value="100">100</option></select></label>
                        <nav aria-label="Pengeluaran pagination" class="flex items-center gap-1">
                            <button type="button" aria-label="Halaman sebelumnya" :disabled="expenses.current_page <= 1" class="flex size-9 items-center justify-center rounded-lg border border-brand-border disabled:opacity-40" @click="goToPage(expenses.current_page - 1)"><AppIcon name="chevron-left" /></button>
                            <template v-for="(number, index) in pageNumbers" :key="number"><span v-if="index && number - pageNumbers[index - 1] > 1" class="px-1 text-brand-muted">…</span><button type="button" :aria-current="number === expenses.current_page ? 'page' : undefined" :class="number === expenses.current_page ? 'border-brand-burgundy bg-brand-burgundy text-white' : 'border-brand-border hover:bg-brand-buff'" class="flex size-9 items-center justify-center rounded-lg border text-sm font-semibold" @click="goToPage(number)">{{ number }}</button></template>
                            <button type="button" aria-label="Halaman berikutnya" :disabled="expenses.current_page >= expenses.last_page" class="flex size-9 items-center justify-center rounded-lg border border-brand-border disabled:opacity-40" @click="goToPage(expenses.current_page + 1)"><AppIcon name="chevron-right" /></button>
                        </nav>
                    </div>
                </footer>
            </section>

            <section v-else class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)]">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[600px] border-collapse text-left text-sm">
                        <thead class="bg-brand-buff text-xs uppercase tracking-wide text-brand-muted"><tr><th class="w-12 px-4 py-3">#</th><th class="px-3 py-3">Kategori</th><th class="px-3 py-3">Deskripsi</th><th class="px-3 py-3">Pengeluaran</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead>
                        <tbody class="divide-y divide-brand-border">
                            <tr v-for="(category, index) in visibleCategories" :key="category.id" class="hover:bg-brand-buff/40">
                                <td class="px-4 py-3 text-brand-muted">{{ index + 1 }}</td><td class="px-3 py-3 font-semibold">{{ category.name }}</td><td class="max-w-sm px-3 py-3 text-brand-muted"><span :title="category.description || ''" class="line-clamp-2">{{ category.description || '—' }}</span></td><td class="px-3 py-3">{{ category.expenses_count }}</td>
                                <td class="px-4 py-3"><div class="flex justify-end gap-1"><button type="button" :aria-label="`Ubah ${category.name}`" class="flex size-9 items-center justify-center rounded-lg text-brand-muted hover:bg-brand-buff hover:text-brand-burgundy" @click="categoryForm = { ...category }"><AppIcon name="edit" class="size-4" /></button><button type="button" :aria-label="`Hapus ${category.name}`" class="flex size-9 items-center justify-center rounded-lg text-brand-muted hover:bg-red-50 hover:text-red-700" @click="askDeleteCategory(category)"><AppIcon name="trash" class="size-4" /></button></div></td>
                            </tr>
                            <tr v-if="visibleCategories.length === 0"><td colspan="5" class="px-4 py-12 text-center"><p class="font-semibold">Kategori tidak ditemukan</p><p class="mt-1 text-sm text-brand-muted">Ubah pencarian atau tambahkan kategori.</p></td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>

        <ExpenseFormModal v-if="expenseForm" :expense="expenseForm.id ? expenseForm : null" :categories="categories" @close="expenseForm = null" @saved="refreshExpenses" />
        <ExpenseCategoryFormModal v-if="categoryForm" :category="categoryForm.id ? categoryForm : null" @close="categoryForm = null" @saved="refreshExpenses" />
        <div v-if="detailLoading" role="status" class="fixed inset-0 z-[55] flex items-center justify-center bg-brand-ink/20 text-sm font-semibold">Memuat detail pengeluaran…</div>
        <ExpenseDetailsModal v-if="expenseDetail" :expense="expenseDetail" @close="expenseDetail = null" @edit="editExpense" @delete="askDeleteExpense" />
        <ConfirmDialog
            v-if="confirmAction"
            :title="confirmAction.type === 'bulk' ? 'Hapus pengeluaran terpilih?' : confirmAction.type === 'expense' ? 'Hapus pengeluaran?' : 'Hapus kategori pengeluaran?'"
            :message="confirmAction.type === 'bulk'
                ? `Hapus ${confirmAction.count} pengeluaran terpilih? Tindakan ini tidak dapat dibatalkan.`
                : confirmAction.type === 'expense'
                    ? `Hapus “${confirmAction.expense.name}”? Tindakan ini tidak dapat dibatalkan.`
                    : `Hapus “${confirmAction.category.name}”? Kategori harus kosong sebelum dihapus.`"
            :confirm-label="confirmAction.type === 'bulk' ? 'Hapus pilihan' : 'Hapus'"
            :processing="deleteForm.processing || bulkDeleteForm.processing"
            @cancel="confirmAction = null"
            @confirm="confirmDelete"
        />
        <div v-if="toast" role="status" aria-live="polite" :class="toast.type === 'error' ? 'border-red-200 bg-red-50 text-red-900' : 'border-emerald-200 bg-emerald-50 text-emerald-900'" class="fixed bottom-5 left-4 right-4 z-[100] mx-auto max-w-md rounded-xl border px-4 py-3 text-sm font-semibold shadow-lg">
            {{ toast.message }}
        </div>
    </AppLayout>
</template>
