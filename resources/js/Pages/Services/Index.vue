<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppIcon from '../../Components/AppIcon.vue';
import ConfirmDialog from '../../Components/ConfirmDialog.vue';
import ServiceFormModal from '../../Components/ServiceFormModal.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    services: { type: Object, required: true },
    filters: { type: Object, required: true },
    summary: { type: Object, required: true },
});

const page = usePage();
const filters = reactive({ ...props.filters });
const selectedIds = ref([]);
const serviceForm = ref(null);
const serviceDetail = ref(null);
const confirmAction = ref(null);
const deleteForm = useForm({});
const bulkDeleteForm = useForm({ ids: [] });
const toast = ref(null);
let searchTimer;
let toastTimer;

const allCurrentPageSelected = computed(() => (
    props.services.data.length > 0
    && props.services.data.every((service) => selectedIds.value.includes(service.id))
));
const pageNumbers = computed(() => {
    const current = props.services.current_page;
    const last = props.services.last_page;

    return [...new Set([1, last, current - 1, current, current + 1])]
        .filter((number) => number >= 1 && number <= last)
        .sort((left, right) => left - right);
});

watch(() => props.filters, (updated) => Object.assign(filters, updated), { deep: true });
watch(() => page.props.flash?.toast, (message) => {
    if (message) showToast(message.type, message.message);
});
watch(() => filters.search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters(), 300);
});
watch(() => [filters.status, filters.sort, filters.per_page], applyFilters);

onBeforeUnmount(() => {
    clearTimeout(searchTimer);
    clearTimeout(toastTimer);
});

function queryValues(pageNumber = 1) {
    return {
        search: filters.search || undefined,
        status: filters.status === 'all' ? undefined : filters.status,
        sort: filters.sort === 'newest' ? undefined : filters.sort,
        per_page: Number(filters.per_page) === 10 ? undefined : filters.per_page,
        page: pageNumber === 1 ? undefined : pageNumber,
    };
}

function applyFilters() {
    router.get('/services', queryValues(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['services', 'filters', 'summary'],
        onSuccess: () => { selectedIds.value = []; },
    });
}

function goToPage(pageNumber) {
    router.get('/services', queryValues(pageNumber), {
        preserveState: true,
        preserveScroll: true,
        only: ['services', 'filters', 'summary'],
        onSuccess: () => { selectedIds.value = []; },
    });
}

function toggleCurrentPage(event) {
    selectedIds.value = event.target.checked ? props.services.data.map((service) => service.id) : [];
}

function showToast(type, message) {
    clearTimeout(toastTimer);
    toast.value = { type, message };
    toastTimer = setTimeout(() => { toast.value = null; }, 3500);
}

function refreshServices() {
    serviceForm.value = null;
    serviceDetail.value = null;
    selectedIds.value = [];
    router.reload({ only: ['services', 'summary'] });
}

function startEdit(service) {
    serviceDetail.value = null;
    serviceForm.value = { ...service };
}

function askDelete(service) {
    confirmAction.value = { type: 'single', service };
}

function askBulkDelete() {
    confirmAction.value = { type: 'bulk', count: selectedIds.value.length };
}

function confirmDelete() {
    if (confirmAction.value?.type === 'single') {
        deleteForm.delete(`/services/${confirmAction.value.service.id}`, {
            onSuccess: () => {
                confirmAction.value = null;
                serviceDetail.value = null;
                selectedIds.value = [];
                router.reload({ only: ['services', 'summary'] });
            },
        });
        return;
    }

    bulkDeleteForm.ids = [...selectedIds.value];
    bulkDeleteForm.delete('/services/bulk', {
        onSuccess: () => {
            confirmAction.value = null;
            selectedIds.value = [];
            router.reload({ only: ['services', 'summary'] });
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
</script>

<template>
    <AppLayout>
        <main class="mx-auto flex w-full max-w-6xl flex-col gap-5 px-4 pb-10 pt-5 sm:px-6">
            <header class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-brand-burgundy">Daftar layanan</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-brand-ink">Kelola Layanan</h1>
                    <p class="mt-1 text-sm text-brand-muted">Perbarui daftar layanan dan harganya.</p>
                </div>
                <button type="button" class="flex min-h-11 shrink-0 items-center gap-2 rounded-xl bg-brand-burgundy px-3.5 text-sm font-semibold text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.25)] hover:bg-brand-burgundy-dark" @click="serviceForm = {}">
                    <AppIcon name="plus" /><span class="hidden xs:inline">Tambah Layanan</span><span class="xs:hidden">Tambah</span>
                </button>
            </header>

            <section aria-label="Ringkasan layanan" class="grid grid-cols-2 gap-3">
                <article class="rounded-2xl border border-brand-border bg-white p-4 shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)]">
                    <p class="text-xs font-medium text-brand-muted">Total layanan</p><p class="mt-2 text-2xl font-bold">{{ summary.total }}</p>
                </article>
                <article class="rounded-2xl border border-brand-border bg-white p-4 shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)]">
                    <p class="text-xs font-medium text-brand-muted">Layanan aktif</p><p class="mt-2 text-2xl font-bold text-emerald-800">{{ summary.active }}</p>
                </article>
                <article class="rounded-2xl border border-brand-border bg-white p-4 shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)]">
                    <p class="text-xs font-medium text-brand-muted">Nonaktif</p><p class="mt-2 text-2xl font-bold text-brand-muted">{{ summary.inactive }}</p>
                </article>
                <article class="rounded-2xl border border-brand-border bg-white p-4 shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)]">
                    <p class="text-xs font-medium text-brand-muted">Harga rata-rata</p><p class="mt-2 truncate text-xl font-bold">{{ formatCurrency(summary.average_price) }}</p>
                </article>
            </section>

            <section class="flex flex-col gap-3">
                <label class="flex min-h-12 items-center gap-3 rounded-xl border border-brand-border bg-white px-3.5 focus-within:border-brand-burgundy">
                    <AppIcon name="search" class="text-brand-muted" />
                    <input v-model="filters.search" type="search" placeholder="Cari layanan…" class="w-full bg-transparent text-base outline-none placeholder:text-brand-muted">
                </label>
                <div class="flex gap-2 overflow-x-auto pb-1">
                    <label class="relative shrink-0">
                        <span class="sr-only">Filter status</span>
                        <select v-model="filters.status" class="min-h-10 appearance-none rounded-lg border border-brand-border bg-white py-2 pl-3 pr-9 text-sm font-medium">
                            <option value="all">Status: Semua</option><option value="active">Aktif</option><option value="inactive">Nonaktif</option>
                        </select><AppIcon name="chevron-down" class="pointer-events-none absolute right-2.5 top-2.5 size-4 text-brand-muted" />
                    </label>
                    <label class="relative shrink-0">
                        <span class="sr-only">Urutkan layanan</span>
                        <select v-model="filters.sort" class="min-h-10 appearance-none rounded-lg border border-brand-border bg-white py-2 pl-3 pr-9 text-sm font-medium">
                            <option value="newest">Urutkan: Terbaru</option><option value="oldest">Terlama</option>
                            <option value="name_asc">Nama A → Z</option><option value="name_desc">Nama Z → A</option>
                            <option value="price_asc">Harga: Termurah</option><option value="price_desc">Harga: Termahal</option>
                            <option value="duration_asc">Durasi: Terpendek</option><option value="duration_desc">Durasi: Terpanjang</option>
                        </select><AppIcon name="chevron-down" class="pointer-events-none absolute right-2.5 top-2.5 size-4 text-brand-muted" />
                    </label>
                </div>
            </section>

            <section v-if="selectedIds.length" class="flex min-h-12 items-center justify-between gap-3 rounded-xl border border-brand-burgundy/20 bg-brand-burgundy/5 px-3.5">
                <p class="text-sm font-semibold">{{ selectedIds.length }} layanan dipilih</p>
                <div class="flex items-center gap-3">
                    <button type="button" class="text-sm font-semibold text-red-700 hover:underline" @click="askBulkDelete">Hapus pilihan</button>
                    <button type="button" class="text-sm font-medium text-brand-muted hover:underline" @click="selectedIds = []">Bersihkan</button>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)]">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[690px] border-collapse text-left text-sm">
                        <thead class="bg-brand-buff text-xs uppercase tracking-wide text-brand-muted">
                            <tr>
                                <th class="w-12 px-4 py-3"><input aria-label="Pilih semua layanan di halaman ini" type="checkbox" class="size-4 rounded accent-brand-burgundy" :checked="allCurrentPageSelected" @change="toggleCurrentPage"></th>
                                <th class="w-12 px-2 py-3">#</th><th class="px-3 py-3">Nama</th><th class="px-3 py-3">Harga</th>
                                <th class="px-3 py-3">Durasi</th><th class="px-3 py-3">Status</th><th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-border">
                            <tr v-for="(service, index) in services.data" :key="service.id" class="hover:bg-brand-buff/50">
                                <td class="px-4 py-3"><input v-model="selectedIds" :value="service.id" :aria-label="`Pilih ${service.name}`" type="checkbox" class="size-4 rounded accent-brand-burgundy"></td>
                                <td class="px-2 py-3 text-brand-muted">{{ (services.from ?? 1) + index }}</td>
                                <td class="max-w-56 px-3 py-3">
                                    <button type="button" class="max-w-full truncate text-left font-semibold text-brand-burgundy hover:underline" @click="serviceDetail = service">{{ service.name }}</button>
                                    <p v-if="service.description" class="mt-0.5 truncate text-xs text-brand-muted">{{ service.description }}</p>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3 font-medium">{{ formatCurrency(service.price) }}</td>
                                <td class="whitespace-nowrap px-3 py-3">{{ service.duration_minutes }} mnt</td>
                                <td class="px-3 py-3"><span :class="service.is_active ? 'bg-emerald-50 text-emerald-800' : 'bg-brand-buff text-brand-muted'" class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold">{{ service.is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1">
                                        <button type="button" :aria-label="`Ubah ${service.name}`" class="flex size-9 items-center justify-center rounded-lg text-brand-muted hover:bg-brand-buff hover:text-brand-burgundy" @click="startEdit(service)"><AppIcon name="edit" /></button>
                                        <button type="button" :aria-label="`Hapus ${service.name}`" class="flex size-9 items-center justify-center rounded-lg text-brand-muted hover:bg-red-50 hover:text-red-700" @click="askDelete(service)"><AppIcon name="trash" /></button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="services.data.length === 0"><td colspan="7" class="px-4 py-12 text-center"><p class="font-semibold">Layanan tidak ditemukan</p><p class="mt-1 text-sm text-brand-muted">Ubah pencarian atau tambahkan layanan.</p></td></tr>
                        </tbody>
                    </table>
                </div>
                <footer class="flex flex-col gap-4 border-t border-brand-border px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-brand-muted">Menampilkan {{ services.from ?? 0 }}–{{ services.to ?? 0 }} dari {{ services.total }} layanan</p>
                    <div class="flex items-center justify-between gap-3 sm:justify-end">
                        <label class="flex items-center gap-2 text-xs text-brand-muted">Baris
                            <select v-model="filters.per_page" class="min-h-9 rounded-lg border border-brand-border bg-white px-2 text-sm text-brand-ink"><option :value="10">10</option><option :value="25">25</option><option :value="50">50</option><option :value="100">100</option></select>
                        </label>
                        <nav aria-label="Halaman layanan" class="flex items-center gap-1">
                            <button type="button" aria-label="Halaman sebelumnya" :disabled="services.current_page <= 1" class="flex size-9 items-center justify-center rounded-lg border border-brand-border disabled:opacity-40" @click="goToPage(services.current_page - 1)"><AppIcon name="chevron-left" /></button>
                            <template v-for="(number, index) in pageNumbers" :key="number">
                                <span v-if="index && number - pageNumbers[index - 1] > 1" class="px-1 text-brand-muted">…</span>
                                <button type="button" :aria-current="number === services.current_page ? 'page' : undefined" :class="number === services.current_page ? 'border-brand-burgundy bg-brand-burgundy text-white' : 'border-brand-border hover:bg-brand-buff'" class="flex size-9 items-center justify-center rounded-lg border text-sm font-semibold" @click="goToPage(number)">{{ number }}</button>
                            </template>
                            <button type="button" aria-label="Halaman berikutnya" :disabled="services.current_page >= services.last_page" class="flex size-9 items-center justify-center rounded-lg border border-brand-border disabled:opacity-40" @click="goToPage(services.current_page + 1)"><AppIcon name="chevron-right" /></button>
                        </nav>
                    </div>
                </footer>
            </section>
        </main>

        <ServiceFormModal v-if="serviceForm" :service="serviceForm.id ? serviceForm : null" @close="serviceForm = null" @saved="refreshServices" />

        <div v-if="serviceDetail" class="fixed inset-0 z-[60] flex items-end justify-center bg-brand-ink/35 backdrop-blur-[2px] sm:items-center sm:p-5" @click.self="serviceDetail = null">
            <section role="dialog" aria-modal="true" aria-label="Detail layanan" class="w-full rounded-t-3xl border border-brand-border bg-white p-5 shadow-[0_24px_48px_-8px_rgba(43,38,39,0.16)] sm:max-w-lg sm:rounded-2xl">
                <header class="flex items-start justify-between gap-3">
                    <div><p class="text-xs font-semibold uppercase tracking-[0.14em] text-brand-burgundy">Detail layanan</p><h2 class="mt-1 text-xl font-bold">{{ serviceDetail.name }}</h2></div>
                    <button type="button" aria-label="Tutup" class="flex size-10 items-center justify-center rounded-lg text-brand-muted hover:bg-brand-buff" @click="serviceDetail = null"><AppIcon name="close" /></button>
                </header>
                <p class="mt-4 whitespace-pre-wrap text-sm leading-6 text-brand-muted">{{ serviceDetail.description || 'Belum ada deskripsi.' }}</p>
                <dl class="mt-5 grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-brand-buff p-3"><dt class="text-xs text-brand-muted">Harga</dt><dd class="mt-1 font-semibold">{{ formatCurrency(serviceDetail.price) }}</dd></div>
                    <div class="rounded-xl bg-brand-buff p-3"><dt class="text-xs text-brand-muted">Durasi</dt><dd class="mt-1 font-semibold">{{ serviceDetail.duration_minutes }} menit</dd></div>
                    <div class="col-span-2 rounded-xl bg-brand-buff p-3"><dt class="text-xs text-brand-muted">Status</dt><dd class="mt-1 font-semibold">{{ serviceDetail.is_active ? 'Aktif' : 'Nonaktif' }}</dd></div>
                </dl>
                <footer class="mt-5 flex justify-end gap-2">
                    <button type="button" class="min-h-11 rounded-lg border border-brand-border px-4 text-sm font-semibold" @click="askDelete(serviceDetail)">Hapus</button>
                    <button type="button" class="min-h-11 rounded-lg bg-brand-burgundy px-4 text-sm font-semibold text-white" @click="startEdit(serviceDetail)">Ubah layanan</button>
                </footer>
            </section>
        </div>

        <ConfirmDialog
            v-if="confirmAction"
            :title="confirmAction.type === 'single' ? 'Hapus layanan?' : 'Hapus layanan terpilih?'"
            :message="confirmAction.type === 'single'
                ? `Hapus “${confirmAction.service.name}”? Layanan dengan riwayat janji temu tidak dapat dihapus.`
                : `Hapus ${confirmAction.count} layanan terpilih? Layanan dengan riwayat janji temu tetap disimpan.`"
            :processing="deleteForm.processing || bulkDeleteForm.processing"
            @cancel="confirmAction = null"
            @confirm="confirmDelete"
        />

        <div v-if="toast" role="status" aria-live="polite" :class="toast.type === 'error' ? 'border-red-200 bg-red-50 text-red-900' : 'border-emerald-200 bg-emerald-50 text-emerald-900'" class="fixed bottom-5 left-4 right-4 z-[100] mx-auto max-w-md rounded-xl border px-4 py-3 text-sm font-semibold shadow-lg">
            {{ toast.message }}
        </div>
    </AppLayout>
</template>
