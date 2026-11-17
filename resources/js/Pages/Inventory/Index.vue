<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppIcon from '../../Components/AppIcon.vue';
import ConfirmDialog from '../../Components/ConfirmDialog.vue';
import InventoryCategoryFormModal from '../../Components/InventoryCategoryFormModal.vue';
import InventoryItemDetailsModal from '../../Components/InventoryItemDetailsModal.vue';
import InventoryItemFormModal from '../../Components/InventoryItemFormModal.vue';
import StockAdjustmentModal from '../../Components/StockAdjustmentModal.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    items: { type: Object, required: true },
    categories: { type: Array, required: true },
    filters: { type: Object, required: true },
    summary: { type: Object, required: true },
});

const page = usePage();
const filters = reactive({ ...props.filters });
const visibleCategories = computed(() => props.categories.filter((category) => (
    !filters.search
    || category.name.toLowerCase().includes(filters.search.toLowerCase())
    || (category.description ?? '').toLowerCase().includes(filters.search.toLowerCase())
)));
const pageNumbers = computed(() => {
    const current = props.items.current_page;
    const last = props.items.last_page;

    return [...new Set([1, last, current - 1, current, current + 1])]
        .filter((number) => number >= 1 && number <= last)
        .sort((left, right) => left - right);
});
const selectedItemIds = ref([]);
const allCurrentPageSelected = computed(() => (
    props.items.data.length > 0
    && props.items.data.every((item) => selectedItemIds.value.includes(item.id))
));

const itemForm = ref(null);
const categoryForm = ref(null);
const stockItem = ref(null);
const itemDetail = ref(null);
const detailLoading = ref(false);
const historyDetail = ref(null);
const confirmAction = ref(null);
const deleteForm = useForm({});
const bulkDeleteForm = useForm({ ids: [] });
const toast = ref(null);
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
watch(() => filters.search, () => {
    if (syncingFilters || filters.tab === 'categories') return;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters(), 300);
});
watch(() => [filters.category_id, filters.low_stock, filters.sort, filters.per_page], () => {
    if (!syncingFilters && filters.tab === 'items') applyFilters();
});
watch(() => filters.tab, () => {
    selectedItemIds.value = [];
    if (!syncingFilters) applyFilters();
});

onBeforeUnmount(() => {
    clearTimeout(searchTimer);
    clearTimeout(toastTimer);
});

function queryValues(pageNumber = 1, overrides = {}) {
    const next = { ...filters, ...overrides };
    return {
        tab: next.tab === 'items' ? undefined : next.tab,
        search: next.tab === 'items' ? next.search || undefined : undefined,
        category_id: next.category_id || undefined,
        low_stock: next.low_stock ? 1 : undefined,
        sort: next.sort === 'newest' ? undefined : next.sort,
        per_page: Number(next.per_page) === 10 ? undefined : next.per_page,
        page: pageNumber === 1 ? undefined : pageNumber,
    };
}

function applyFilters() {
    router.get('/inventory', queryValues(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['items', 'categories', 'filters', 'summary'],
        onSuccess: () => { selectedItemIds.value = []; },
    });
}

function goToPage(pageNumber) {
    router.get('/inventory', queryValues(pageNumber), {
        preserveState: true,
        preserveScroll: true,
        only: ['items', 'categories', 'filters', 'summary'],
        onSuccess: () => { selectedItemIds.value = []; },
    });
}

function toggleCurrentPage(event) {
    selectedItemIds.value = event.target.checked
        ? props.items.data.map((item) => item.id)
        : [];
}

function showToast(type, message) {
    clearTimeout(toastTimer);
    toast.value = { type, message };
    toastTimer = setTimeout(() => { toast.value = null; }, 3500);
}

function refreshInventory() {
    itemForm.value = null;
    categoryForm.value = null;
    stockItem.value = null;
    itemDetail.value = null;
    historyDetail.value = null;
    selectedItemIds.value = [];
    router.reload({ only: ['items', 'categories', 'summary'] });
}

async function openItem(item) {
    detailLoading.value = true;
    try {
        const response = await fetch(`/inventory/${item.id}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!response.ok) throw new Error('Detail barang gagal dimuat. Silakan coba lagi.');
        itemDetail.value = (await response.json()).item;
    } catch (error) {
        showToast('error', error.message || 'Barang details could not be loaded.');
    } finally {
        detailLoading.value = false;
    }
}

async function openHistory(item) {
    detailLoading.value = true;
    try {
        const response = await fetch(`/inventory/${item.id}/history`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!response.ok) throw new Error('Riwayat pergerakan gagal dimuat. Silakan coba lagi.');
        historyDetail.value = await response.json();
        itemDetail.value = null;
    } catch (error) {
        showToast('error', error.message || 'Movement history could not be loaded.');
    } finally {
        detailLoading.value = false;
    }
}

function editItem(item) {
    itemDetail.value = null;
    itemForm.value = { ...item };
}

function adjustStock(item) {
    itemDetail.value = null;
    stockItem.value = { ...item };
}

function askDeleteItem(item) {
    confirmAction.value = { type: 'item', item };
}

function askDeleteCategory(category) {
    confirmAction.value = { type: 'category', category };
}

function askBulkDeleteItems() {
    confirmAction.value = { type: 'bulk-items', count: selectedItemIds.value.length };
}

function confirmDelete() {
    if (confirmAction.value?.type === 'item') {
        deleteForm.delete(`/inventory/${confirmAction.value.item.id}`, {
            onSuccess: () => {
                confirmAction.value = null;
                itemDetail.value = null;
                router.reload({ only: ['items', 'summary'] });
            },
        });
        return;
    }

    if (confirmAction.value?.type === 'bulk-items') {
        bulkDeleteForm.ids = [...selectedItemIds.value];
        bulkDeleteForm.delete('/inventory/bulk', {
            onSuccess: () => {
                confirmAction.value = null;
                selectedItemIds.value = [];
                router.reload({ only: ['items', 'summary'] });
            },
        });
        return;
    }

    deleteForm.delete(`/inventory/categories/${confirmAction.value.category.id}`, {
        onSuccess: () => {
            confirmAction.value = null;
            router.reload({ only: ['items', 'categories', 'summary'] });
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

function formatQuantity(value) {
    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0));
}

function formatDateTime(value) {
    return value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '—';
}
</script>

<template>
    <AppLayout>
        <main class="mx-auto flex w-full max-w-6xl flex-col gap-5 px-4 pb-10 pt-5 sm:px-6">
            <header class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-brand-burgundy">Stok dan perlengkapan</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-brand-ink">Manajemen Inventaris</h1>
                    <p class="mt-1 max-w-xl text-sm leading-5 text-brand-muted">Kelola barang, kategori, dan riwayat perubahan stok.</p>
                </div>
                <button v-if="filters.tab === 'items'" type="button" :disabled="!categories.length" class="flex min-h-11 shrink-0 items-center gap-2 rounded-xl bg-brand-burgundy px-3.5 text-sm font-semibold text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.25)] hover:bg-brand-burgundy-dark disabled:opacity-50" @click="itemForm = {}">
                    <AppIcon name="plus" /><span class="hidden xs:inline">Tambah Barang</span><span class="xs:hidden">Tambah</span>
                </button>
                <button v-else type="button" class="flex min-h-11 shrink-0 items-center gap-2 rounded-xl bg-brand-burgundy px-3.5 text-sm font-semibold text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.25)] hover:bg-brand-burgundy-dark" @click="categoryForm = {}">
                    <AppIcon name="plus" /><span class="hidden xs:inline">Tambah Kategori</span><span class="xs:hidden">Tambah</span>
                </button>
            </header>

            <p v-if="filters.tab === 'items' && !categories.length" role="alert" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-5 text-amber-900">
                Tambahkan kategori inventaris sebelum membuat barang pertama.
                <button type="button" class="font-semibold underline" @click="filters.tab = 'categories'; categoryForm = {}">Tambah kategori</button>
            </p>

            <section aria-label="Ringkasan inventaris" class="grid grid-cols-2 gap-3">
                <article class="rounded-2xl border border-brand-border bg-white p-4 shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)]"><p class="text-xs font-medium text-brand-muted">Barang inventaris</p><p class="mt-2 text-2xl font-bold">{{ summary.total }}</p></article>
                <article class="rounded-2xl border border-brand-border bg-white p-4 shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)]"><p class="text-xs font-medium text-brand-muted">Stok menipis</p><p class="mt-2 text-2xl font-bold" :class="summary.low_stock ? 'text-amber-800' : 'text-emerald-800'">{{ summary.low_stock }}</p></article>
                <article class="rounded-2xl border border-brand-border bg-white p-4 shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)]"><p class="text-xs font-medium text-brand-muted">Kategori</p><p class="mt-2 text-2xl font-bold">{{ summary.categories }}</p></article>
                <article class="rounded-2xl border border-brand-border bg-white p-4 shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)]"><p class="text-xs font-medium text-brand-muted">Nilai stok</p><p class="mt-2 truncate text-xl font-bold">{{ formatCurrency(summary.stock_value) }}</p></article>
            </section>

            <section class="rounded-2xl border border-brand-border bg-white p-1.5 shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)]">
                <div class="grid grid-cols-2 gap-1" role="tablist" aria-label="Bagian inventaris">
                    <button id="inventory-items-tab" type="button" role="tab" :aria-selected="filters.tab === 'items'" :class="filters.tab === 'items' ? 'bg-brand-burgundy text-white shadow-sm' : 'text-brand-muted hover:bg-brand-buff'" class="flex min-h-11 items-center justify-center gap-2 rounded-xl text-sm font-semibold" @click="filters.tab = 'items'"><AppIcon name="inventory" class="size-4" />Barang</button>
                    <button id="inventory-categories-tab" type="button" role="tab" :aria-selected="filters.tab === 'categories'" :class="filters.tab === 'categories' ? 'bg-brand-burgundy text-white shadow-sm' : 'text-brand-muted hover:bg-brand-buff'" class="flex min-h-11 items-center justify-center gap-2 rounded-xl text-sm font-semibold" @click="filters.tab = 'categories'"><AppIcon name="services" class="size-4" />Kategori <span class="rounded-full bg-white/20 px-1.5 py-0.5 text-xs">{{ categories.length }}</span></button>
                </div>
            </section>

            <section class="flex flex-col gap-3">
                <label class="flex min-h-12 items-center gap-3 rounded-xl border border-brand-border bg-white px-3.5 focus-within:border-brand-burgundy">
                    <AppIcon name="search" class="text-brand-muted" />
                    <input v-model="filters.search" type="search" :placeholder="filters.tab === 'items' ? 'Cari barang inventaris…' : 'Cari kategori…'" class="w-full bg-transparent text-base outline-none placeholder:text-brand-muted">
                </label>
                <div v-if="filters.tab === 'items'" class="flex flex-wrap items-center gap-2">
                    <label class="relative min-w-40 flex-1">
                        <span class="sr-only">Filter kategori</span>
                        <select v-model="filters.category_id" class="min-h-11 w-full appearance-none rounded-lg border border-brand-border bg-white py-2 pl-3 pr-9 text-sm font-medium">
                            <option value="">Semua kategori</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                        </select><AppIcon name="chevron-down" class="pointer-events-none absolute right-2.5 top-3 size-4 text-brand-muted" />
                    </label>
                    <label class="flex min-h-11 items-center gap-2 rounded-lg border border-brand-border bg-white px-3 text-sm font-medium">
                        <input v-model="filters.low_stock" type="checkbox" class="size-4 rounded accent-brand-burgundy">
                        Stok menipis saja
                    </label>
                    <label class="relative min-w-36 flex-1">
                        <span class="sr-only">Urutkan barang inventaris</span>
                        <select v-model="filters.sort" class="min-h-11 w-full appearance-none rounded-lg border border-brand-border bg-white py-2 pl-3 pr-9 text-sm font-medium">
                            <option value="newest">Urutkan: Terbaru</option><option value="oldest">Terlama</option><option value="name_asc">Nama A–Z</option><option value="name_desc">Nama Z–A</option><option value="quantity_asc">Stok: Sedikit–Banyak</option><option value="quantity_desc">Stok: Banyak–Sedikit</option>
                        </select><AppIcon name="chevron-down" class="pointer-events-none absolute right-2.5 top-3 size-4 text-brand-muted" />
                    </label>
                </div>
            </section>

            <section v-if="filters.tab === 'items'" aria-labelledby="inventory-items-tab" class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)]">
                <div v-if="selectedItemIds.length" class="flex min-h-12 items-center justify-between gap-3 border-b border-brand-border bg-brand-burgundy/5 px-3.5">
                    <p class="text-sm font-semibold">{{ selectedItemIds.length }} barang dipilih</p>
                    <div class="flex items-center gap-3">
                        <button type="button" class="text-sm font-semibold text-red-700 hover:underline" @click="askBulkDeleteItems">Hapus pilihan</button>
                        <button type="button" class="text-sm font-medium text-brand-muted hover:underline" @click="selectedItemIds = []">Bersihkan</button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[880px] border-collapse text-left text-sm">
                        <thead class="bg-brand-buff text-xs uppercase tracking-wide text-brand-muted"><tr><th class="w-12 px-4 py-3"><input aria-label="Pilih semua barang di halaman ini" type="checkbox" class="size-4 rounded accent-brand-burgundy" :checked="allCurrentPageSelected" @change="toggleCurrentPage"></th><th class="w-12 px-2 py-3">#</th><th class="px-3 py-3">Barang</th><th class="px-3 py-3">Kategori</th><th class="px-3 py-3">Stok saat ini</th><th class="px-3 py-3">Harga satuan</th><th class="px-3 py-3">Stok minimum</th><th class="px-3 py-3">Status</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead>
                        <tbody class="divide-y divide-brand-border">
                            <tr v-for="(item, index) in items.data" :key="item.id" class="hover:bg-brand-buff/40">
                                <td class="px-4 py-3"><input v-model="selectedItemIds" :value="item.id" :aria-label="`Pilih ${item.name}`" type="checkbox" class="size-4 rounded accent-brand-burgundy"></td>
                                <td class="px-2 py-3 text-brand-muted">{{ (items.from ?? 1) + index }}</td>
                                <td class="max-w-52 px-3 py-3"><button type="button" class="max-w-full truncate text-left font-semibold text-brand-burgundy hover:underline" @click="openItem(item)">{{ item.name }}</button><p v-if="item.description" class="mt-0.5 truncate text-xs text-brand-muted">{{ item.description }}</p></td>
                                <td class="px-3 py-3">{{ item.category.name }}</td>
                                <td class="whitespace-nowrap px-3 py-3 font-semibold">{{ formatQuantity(item.quantity) }} {{ item.unit }}</td>
                                <td class="whitespace-nowrap px-3 py-3">{{ formatCurrency(item.unit_cost) }}</td>
                                <td class="whitespace-nowrap px-3 py-3">{{ formatQuantity(item.minimum_stock) }} {{ item.unit }}</td>
                                <td class="px-3 py-3"><span :class="item.is_low_stock ? 'bg-amber-50 text-amber-900' : 'bg-emerald-50 text-emerald-900'" class="inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold">{{ item.is_low_stock ? 'Stok menipis' : 'Stok tersedia' }}</span></td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1">
                                        <button type="button" :aria-label="`Sesuaikan stok ${item.name}`" title="Sesuaikan stok" class="flex size-9 items-center justify-center rounded-lg text-brand-burgundy hover:bg-brand-buff" @click="adjustStock(item)"><AppIcon name="inventory" class="size-4" /></button>
                                        <button type="button" :aria-label="`Lihat riwayat pergerakan ${item.name}`" title="Lihat riwayat pergerakan" class="flex size-9 items-center justify-center rounded-lg text-brand-muted hover:bg-brand-buff hover:text-brand-ink" @click="openHistory(item)"><AppIcon name="calendar" class="size-4" /></button>
                                        <button type="button" :aria-label="`Ubah ${item.name}`" title="Ubah item" class="flex size-9 items-center justify-center rounded-lg text-brand-muted hover:bg-brand-buff hover:text-brand-burgundy" @click="editItem(item)"><AppIcon name="edit" class="size-4" /></button>
                                        <button type="button" :aria-label="`Hapus ${item.name}`" title="Hapus item" class="flex size-9 items-center justify-center rounded-lg text-brand-muted hover:bg-red-50 hover:text-red-700" @click="askDeleteItem(item)"><AppIcon name="trash" class="size-4" /></button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="items.data.length === 0"><td colspan="9" class="px-4 py-12 text-center"><p class="font-semibold">Barang inventaris tidak ditemukan</p><p class="mt-1 text-sm text-brand-muted">{{ categories.length ? 'Ubah pencarian atau filter, atau tambahkan barang.' : 'Tambahkan kategori inventaris sebelum menambahkan barang.' }}</p></td></tr>
                        </tbody>
                    </table>
                </div>
                <footer class="flex flex-col gap-4 border-t border-brand-border px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-brand-muted">Menampilkan {{ items.from ?? 0 }}–{{ items.to ?? 0 }} dari {{ items.total }} barang</p>
                    <div class="flex items-center justify-between gap-3 sm:justify-end">
                        <label class="flex items-center gap-2 text-xs text-brand-muted">Baris<select v-model="filters.per_page" class="min-h-9 rounded-lg border border-brand-border bg-white px-2 text-sm text-brand-ink"><option :value="10">10</option><option :value="25">25</option><option :value="50">50</option><option :value="100">100</option></select></label>
                        <nav aria-label="Halaman inventaris" class="flex items-center gap-1">
                            <button type="button" aria-label="Halaman sebelumnya" :disabled="items.current_page <= 1" class="flex size-9 items-center justify-center rounded-lg border border-brand-border disabled:opacity-40" @click="goToPage(items.current_page - 1)"><AppIcon name="chevron-left" /></button>
                            <template v-for="(number, index) in pageNumbers" :key="number"><span v-if="index && number - pageNumbers[index - 1] > 1" class="px-1 text-brand-muted">…</span><button type="button" :aria-current="number === items.current_page ? 'page' : undefined" :class="number === items.current_page ? 'border-brand-burgundy bg-brand-burgundy text-white' : 'border-brand-border hover:bg-brand-buff'" class="flex size-9 items-center justify-center rounded-lg border text-sm font-semibold" @click="goToPage(number)">{{ number }}</button></template>
                            <button type="button" aria-label="Halaman berikutnya" :disabled="items.current_page >= items.last_page" class="flex size-9 items-center justify-center rounded-lg border border-brand-border disabled:opacity-40" @click="goToPage(items.current_page + 1)"><AppIcon name="chevron-right" /></button>
                        </nav>
                    </div>
                </footer>
            </section>

            <section v-if="filters.tab === 'categories'" class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)]">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[580px] border-collapse text-left text-sm">
                        <thead class="bg-brand-buff text-xs uppercase tracking-wide text-brand-muted"><tr><th class="px-4 py-3">Kategori</th><th class="px-3 py-3">Deskripsi</th><th class="px-3 py-3">Barang</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead>
                        <tbody class="divide-y divide-brand-border">
                            <tr v-for="category in visibleCategories" :key="category.id" class="hover:bg-brand-buff/40">
                                <td class="px-4 py-3 font-semibold">{{ category.name }}</td><td class="max-w-xs px-3 py-3 text-brand-muted"><span class="line-clamp-2">{{ category.description || '—' }}</span></td><td class="px-3 py-3">{{ category.items_count }}</td>
                                <td class="px-4 py-3"><div class="flex justify-end gap-1"><button type="button" :aria-label="`Ubah ${category.name}`" class="flex size-9 items-center justify-center rounded-lg text-brand-muted hover:bg-brand-buff hover:text-brand-burgundy" @click="categoryForm = { ...category }"><AppIcon name="edit" class="size-4" /></button><button type="button" :aria-label="`Hapus ${category.name}`" class="flex size-9 items-center justify-center rounded-lg text-brand-muted hover:bg-red-50 hover:text-red-700" @click="askDeleteCategory(category)"><AppIcon name="trash" class="size-4" /></button></div></td>
                            </tr>
                            <tr v-if="visibleCategories.length === 0"><td colspan="4" class="px-4 py-12 text-center"><p class="font-semibold">Kategori tidak ditemukan</p><p class="mt-1 text-sm text-brand-muted">Tambahkan kategori untuk mengatur perlengkapan.</p></td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

        </main>

        <InventoryItemFormModal v-if="itemForm" :item="itemForm.id ? itemForm : null" :categories="categories" @close="itemForm = null" @saved="refreshInventory" />
        <InventoryCategoryFormModal v-if="categoryForm" :category="categoryForm.id ? categoryForm : null" @close="categoryForm = null" @saved="refreshInventory" />
        <StockAdjustmentModal v-if="stockItem" :item="stockItem" @close="stockItem = null" @saved="refreshInventory" />
        <InventoryItemDetailsModal
            v-if="itemDetail"
            :detail="itemDetail"
            @close="itemDetail = null"
            @adjust="adjustStock"
            @edit="editItem"
            @delete="askDeleteItem"
            @history="openHistory(itemDetail)"
        />

        <div v-if="historyDetail" class="fixed inset-0 z-[65] flex items-end justify-center bg-brand-ink/35 backdrop-blur-[2px] sm:items-center sm:p-5" @click.self="historyDetail = null">
            <section role="dialog" aria-modal="true" aria-label="Riwayat pergerakan inventaris" class="flex max-h-[92svh] w-full flex-col rounded-t-3xl border border-brand-border bg-white shadow-[0_24px_48px_-8px_rgba(43,38,39,0.16)] sm:max-w-xl sm:rounded-2xl">
                <header class="flex items-center justify-between border-b border-brand-border px-5 py-4">
                    <div><p class="text-xs font-semibold uppercase tracking-[0.14em] text-brand-burgundy">Riwayat stok</p><h2 class="mt-1 text-lg font-bold">{{ historyDetail.item.name }}</h2><p class="mt-0.5 text-xs text-brand-muted">Stok saat ini {{ formatQuantity(historyDetail.item.quantity) }} {{ historyDetail.item.unit }}</p></div>
                    <button type="button" aria-label="Tutup riwayat" class="flex size-10 items-center justify-center rounded-lg text-brand-muted hover:bg-brand-buff" @click="historyDetail = null"><AppIcon name="close" /></button>
                </header>
                <div class="overflow-y-auto p-5">
                    <div v-if="historyDetail.movements.length" class="divide-y divide-brand-border">
                        <article v-for="movement in historyDetail.movements" :key="movement.id" class="grid grid-cols-[2.5rem_minmax(0,1fr)_auto] items-center gap-3 py-3 first:pt-0">
                            <span :class="movement.type === 'IN' ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800'" class="flex size-9 items-center justify-center rounded-full text-lg font-bold">{{ movement.type === 'IN' ? '+' : '−' }}</span>
                            <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><p class="text-sm font-semibold">{{ movement.reason }}</p><span :class="movement.type === 'IN' ? 'text-emerald-800' : 'text-amber-800'" class="text-[0.68rem] font-bold uppercase">{{ movement.type === 'IN' ? 'Masuk' : 'Keluar' }}</span></div><p class="mt-0.5 text-xs text-brand-muted">{{ formatDateTime(movement.created_at) }}</p><p v-if="movement.note" class="mt-1 whitespace-pre-wrap text-xs leading-5 text-brand-muted">{{ movement.note }}</p></div>
                            <div class="text-right"><p :class="movement.type === 'IN' ? 'text-emerald-800' : 'text-amber-800'" class="whitespace-nowrap text-sm font-bold">{{ movement.type === 'IN' ? '+' : '−' }}{{ formatQuantity(movement.quantity) }} {{ historyDetail.item.unit }}</p><p class="mt-0.5 whitespace-nowrap text-[0.68rem] text-brand-muted">Saldo {{ formatQuantity(movement.resulting_balance) }}</p></div>
                        </article>
                    </div>
                    <p v-else class="rounded-xl bg-brand-cream p-5 text-center text-sm text-brand-muted">Belum ada pergerakan stok.</p>
                </div>
                <footer class="flex justify-end border-t border-brand-border px-5 py-4"><button type="button" class="min-h-11 rounded-lg bg-brand-burgundy px-5 text-sm font-semibold text-white" @click="historyDetail = null">Selesai</button></footer>
            </section>
        </div>

        <div v-if="detailLoading" role="status" class="fixed inset-0 z-[55] flex items-center justify-center bg-brand-ink/20 text-sm font-semibold">Memuat detail inventaris…</div>
        <ConfirmDialog
            v-if="confirmAction"
            :title="confirmAction.type === 'item' ? 'Hapus barang inventaris?' : 'Hapus kategori inventaris?'"
            :message="confirmAction.type === 'bulk-items'
                ? `Hapus ${confirmAction.count} barang terpilih? Barang dengan riwayat pergerakan akan tetap disimpan.`
                : confirmAction.type === 'item'
                ? `Hapus “${confirmAction.item.name}”? Barang dengan riwayat pergerakan stok tidak dapat dihapus.`
                : `Hapus “${confirmAction.category.name}”? Kategori harus kosong sebelum dihapus.`"
            :processing="deleteForm.processing || bulkDeleteForm.processing"
            @cancel="confirmAction = null"
            @confirm="confirmDelete"
        />
        <div v-if="toast" role="status" aria-live="polite" :class="toast.type === 'error' ? 'border-red-200 bg-red-50 text-red-900' : 'border-emerald-200 bg-emerald-50 text-emerald-900'" class="fixed bottom-5 left-4 right-4 z-[100] mx-auto max-w-md rounded-xl border px-4 py-3 text-sm font-semibold shadow-lg">{{ toast.message }}</div>
    </AppLayout>
</template>
