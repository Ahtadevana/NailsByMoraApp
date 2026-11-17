<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppIcon from '../../Components/AppIcon.vue';
import ConfirmDialog from '../../Components/ConfirmDialog.vue';
import CustomerDetailsModal from '../../Components/CustomerDetailsModal.vue';
import CustomerFormModal from '../../Components/CustomerFormModal.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    customers: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    summary: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const filters = reactive({ ...props.filters });
const selectedIds = ref([]);
const customerForm = ref(null);
const customerDetail = ref(null);
const detailLoading = ref(false);
const confirmAction = ref(null);
const deleteForm = useForm({});
const bulkDeleteForm = useForm({ ids: [] });
const toast = ref(null);
let searchTimer;
let toastTimer;

const allCurrentPageSelected = computed(() => (
    props.customers.data.length > 0
    && props.customers.data.every((customer) => selectedIds.value.includes(customer.id))
));

const pageNumbers = computed(() => {
    const current = props.customers.current_page;
    const last = props.customers.last_page;
    const numbers = new Set([1, last, current - 1, current, current + 1]);

    return [...numbers].filter((number) => number >= 1 && number <= last).sort((left, right) => left - right);
});

watch(() => props.filters, (updated) => {
    Object.assign(filters, updated);
}, { deep: true });

watch(() => page.props.flash?.toast, (message) => {
    if (message) {
        showToast(message.type, message.message);
    }
});

watch(() => filters.search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters(), 300);
});

watch(() => [filters.contact, filters.activity, filters.created, filters.period, filters.sort, filters.per_page, filters.created_from, filters.created_to], () => {
    if (filters.created !== 'custom' || (filters.created_from && filters.created_to)) {
        applyFilters();
    }
});

onMounted(() => {
    if (new URLSearchParams(page.url.split('?')[1] ?? '').get('create') === '1') {
        customerForm.value = {};
    }
});

function queryValues(pageNumber = 1) {
    return {
        search: filters.search || undefined,
        contact: filters.contact === 'all' ? undefined : filters.contact,
        activity: filters.activity === 'all' ? undefined : filters.activity,
        created: filters.created === 'all' ? undefined : filters.created,
        created_from: filters.created === 'custom' ? filters.created_from || undefined : undefined,
        created_to: filters.created === 'custom' ? filters.created_to || undefined : undefined,
        period: filters.period === 'month' ? undefined : filters.period,
        sort: filters.sort === 'newest' ? undefined : filters.sort,
        per_page: Number(filters.per_page) === 10 ? undefined : filters.per_page,
        page: pageNumber === 1 ? undefined : pageNumber,
    };
}

function applyFilters() {
    router.get('/customers', queryValues(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['customers', 'filters', 'summary'],
        onSuccess: () => {
            selectedIds.value = [];
        },
    });
}

function goToPage(pageNumber) {
    router.get('/customers', queryValues(pageNumber), {
        preserveState: true,
        preserveScroll: true,
        only: ['customers', 'filters', 'summary'],
        onSuccess: () => {
            selectedIds.value = [];
        },
    });
}

function toggleCurrentPage(event) {
    selectedIds.value = event.target.checked
        ? props.customers.data.map((customer) => customer.id)
        : [];
}

function showToast(type, message) {
    clearTimeout(toastTimer);
    toast.value = { type, message };
    toastTimer = setTimeout(() => {
        toast.value = null;
    }, 3500);
}

async function openDetails(customer) {
    detailLoading.value = true;

    try {
        const response = await fetch(`/customers/${customer.id}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('Detail pelanggan gagal dimuat. Silakan coba lagi.');
        }

        customerDetail.value = await response.json();
    } catch (error) {
        showToast('error', error.message || 'Detail pelanggan gagal dimuat.');
    } finally {
        detailLoading.value = false;
    }
}

function startEdit(customer) {
    customerDetail.value = null;
    customerForm.value = { ...customer };
}

function startCreate() {
    customerForm.value = {};
}

function refreshCustomers() {
    customerForm.value = null;
    customerDetail.value = null;
    selectedIds.value = [];
    router.reload({ only: ['customers', 'summary'] });
}

function askDelete(customer) {
    confirmAction.value = { type: 'single', customer };
}

function askBulkDelete() {
    confirmAction.value = { type: 'bulk', count: selectedIds.value.length };
}

function confirmDelete() {
    if (confirmAction.value?.type === 'single') {
        const customer = confirmAction.value.customer;
        deleteForm.delete(`/customers/${customer.id}`, {
            onSuccess: () => {
                confirmAction.value = null;
                customerDetail.value = null;
                selectedIds.value = [];
                router.reload({ only: ['customers', 'summary'] });
            },
        });

        return;
    }

    bulkDeleteForm.ids = [...selectedIds.value];
    bulkDeleteForm.delete('/customers/bulk', {
        onSuccess: () => {
            confirmAction.value = null;
            selectedIds.value = [];
            router.reload({ only: ['customers', 'summary'] });
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
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
}

onBeforeUnmount(() => {
    clearTimeout(searchTimer);
    clearTimeout(toastTimer);
});
</script>

<template>
    <AppLayout>
        <div class="mx-auto flex w-full max-w-5xl flex-col gap-5 px-4 py-5 sm:px-6 sm:py-8">
            <header class="flex items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold tracking-tight sm:text-2xl">Kelola Pelanggan</h1>
                    <p class="mt-1 text-sm leading-5 text-brand-muted">Kelola data dan riwayat pelanggan studio.</p>
                </div>
                <button
                    type="button"
                    class="flex min-h-11 shrink-0 items-center gap-1.5 rounded-lg bg-brand-burgundy px-3 text-sm font-semibold text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.25)] transition hover:bg-brand-burgundy-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-burgundy"
                    @click="startCreate"
                >
                    <AppIcon name="plus" class="size-4" />
                    Tambah pelanggan
                </button>
            </header>

            <section aria-label="Ringkasan pelanggan" class="grid grid-cols-2 gap-3">
                <article class="min-h-24 rounded-xl border border-brand-border bg-white p-3.5 shadow-[0_4px_20px_-2px_rgba(139,38,62,0.04),0_2px_6px_-1px_rgba(43,38,39,0.03)]">
                    <p class="text-xs font-medium text-brand-muted">Total pelanggan</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums">{{ summary.total }}</p>
                </article>
                <article class="min-h-24 rounded-xl border border-brand-border bg-white p-3.5 shadow-[0_4px_20px_-2px_rgba(139,38,62,0.04),0_2px_6px_-1px_rgba(43,38,39,0.03)]">
                    <label for="new-customer-period" class="flex items-center justify-between gap-1 text-xs font-medium text-brand-muted">
                        <span>Pelanggan baru</span>
                        <select id="new-customer-period" v-model="filters.period" aria-label="Periode pelanggan baru" class="max-w-24 truncate rounded-md bg-brand-cream py-1 pl-2 pr-5 text-[0.68rem] font-semibold text-brand-burgundy outline-none focus:ring-2 focus:ring-brand-burgundy/20">
                            <option value="month">Bulan ini</option>
                            <option value="30days">30 hari terakhir</option>
                            <option value="90days">90 hari terakhir</option>
                            <option value="year">Tahun ini</option>
                        </select>
                    </label>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">{{ summary.new }}</p>
                </article>
                <article class="min-h-24 rounded-xl border border-brand-border bg-white p-3.5 shadow-[0_4px_20px_-2px_rgba(139,38,62,0.04),0_2px_6px_-1px_rgba(43,38,39,0.03)]">
                    <p class="flex items-center gap-2 text-xs font-medium text-brand-muted"><AppIcon name="mail" class="size-4 text-brand-burgundy" />Ada email</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums">{{ summary.has_email }}</p>
                </article>
                <article class="min-h-24 rounded-xl border border-brand-border bg-white p-3.5 shadow-[0_4px_20px_-2px_rgba(139,38,62,0.04),0_2px_6px_-1px_rgba(43,38,39,0.03)]">
                    <p class="flex items-center gap-2 text-xs font-medium text-brand-muted"><AppIcon name="phone" class="size-4 text-brand-burgundy" />Ada telepon</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums">{{ summary.has_phone }}</p>
                </article>
            </section>

            <section class="flex flex-col gap-3">
                <label for="customer-search" class="flex min-h-12 items-center gap-2.5 rounded-xl border border-brand-border bg-white px-3.5 text-brand-muted focus-within:border-brand-burgundy focus-within:ring-2 focus-within:ring-brand-burgundy/15">
                    <AppIcon name="search" class="size-5" />
                    <span class="sr-only">Cari pelanggan</span>
                    <input id="customer-search" v-model="filters.search" type="search" maxlength="100" placeholder="Cari pelanggan" class="min-w-0 flex-1 bg-transparent text-sm text-brand-ink outline-none placeholder:text-brand-muted/70">
                </label>

                <div class="-mx-4 flex snap-x gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:px-0" aria-label="Filter pelanggan">
                    <label class="relative flex h-10 shrink-0 snap-start items-center gap-1.5 rounded-lg border border-brand-border bg-white px-3 text-xs font-medium text-brand-ink">
                        <span>Kontak</span><AppIcon name="chevron-down" class="size-3.5" />
                        <select v-model="filters.contact" aria-label="Filter detail kontak" class="absolute inset-0 h-full w-full cursor-pointer opacity-0">
                            <option value="all">Semua</option><option value="has_phone">Ada telepon</option><option value="no_phone">Tanpa telepon</option><option value="has_email">Ada email</option><option value="no_email">Tanpa email</option>
                        </select>
                    </label>
                    <label class="relative flex h-10 shrink-0 snap-start items-center gap-1.5 rounded-lg border border-brand-border bg-white px-3 text-xs font-medium text-brand-ink">
                        <span>Aktivitas</span><AppIcon name="chevron-down" class="size-3.5" />
                        <select v-model="filters.activity" aria-label="Filter aktivitas" class="absolute inset-0 h-full w-full cursor-pointer opacity-0">
                            <option value="all">Semua</option><option value="appointments">Pernah janji temu</option><option value="no_appointments">Belum ada janji temu</option><option value="recent">Baru berkunjung</option><option value="no_recent">Belum berkunjung</option>
                        </select>
                    </label>
                    <label class="relative flex h-10 shrink-0 snap-start items-center gap-1.5 rounded-lg border border-brand-border bg-white px-3 text-xs font-medium text-brand-ink">
                        <span>Dibuat</span><AppIcon name="chevron-down" class="size-3.5" />
                        <select v-model="filters.created" aria-label="Filter tanggal dibuat" class="absolute inset-0 h-full w-full cursor-pointer opacity-0">
                            <option value="all">Kapan saja</option><option value="today">Hari ini</option><option value="week">Minggu ini</option><option value="month">Bulan ini</option><option value="year">Tahun ini</option><option value="custom">Rentang khusus</option>
                        </select>
                    </label>
                    <label class="relative flex h-10 shrink-0 snap-start items-center gap-1.5 rounded-lg border border-brand-border bg-white px-3 text-xs font-medium text-brand-ink">
                        <span>Urutkan: {{ { newest: 'terbaru', oldest: 'terlama', name_asc: 'nama A–Z', name_desc: 'nama Z–A', most_appointments: 'janji temu terbanyak', least_appointments: 'janji temu tersedikit', last_visit: 'kunjungan terakhir' }[filters.sort] }}</span>
                        <AppIcon name="chevron-down" class="size-3.5" />
                        <select v-model="filters.sort" aria-label="Urutkan pelanggan" class="absolute inset-0 h-full w-full cursor-pointer opacity-0">
                            <option value="newest">Pelanggan terbaru</option><option value="oldest">Pelanggan terlama</option><option value="name_asc">Nama A–Z</option><option value="name_desc">Nama Z–A</option><option value="most_appointments">Janji temu terbanyak</option><option value="least_appointments">Janji temu tersedikit</option><option value="last_visit">Kunjungan terakhir</option>
                        </select>
                    </label>
                </div>

                <div v-if="filters.created === 'custom'" class="grid grid-cols-2 gap-2 rounded-xl border border-brand-border bg-white p-3">
                    <label class="text-xs font-medium text-brand-muted">Dari<input v-model="filters.created_from" type="date" class="mt-1 min-h-10 w-full rounded-lg border border-brand-border bg-brand-buff px-2 text-sm text-brand-ink"></label>
                    <label class="text-xs font-medium text-brand-muted">Sampai<input v-model="filters.created_to" type="date" class="mt-1 min-h-10 w-full rounded-lg border border-brand-border bg-brand-buff px-2 text-sm text-brand-ink"></label>
                </div>
            </section>

            <div v-if="selectedIds.length" class="flex min-h-14 items-center gap-2 rounded-xl border border-brand-blush bg-[#f7e9e8] px-3">
                <p class="min-w-0 flex-1 text-xs font-semibold text-brand-burgundy">{{ selectedIds.length }} dipilih</p>
                <button type="button" class="min-h-10 rounded-lg px-2 text-xs font-semibold text-red-700 hover:bg-white/70" @click="askBulkDelete">Hapus pilihan</button>
                <button type="button" class="min-h-10 rounded-lg px-2 text-xs font-semibold text-brand-muted hover:bg-white/70" @click="selectedIds = []">Bersihkan</button>
            </div>

            <section aria-label="Data pelanggan" class="overflow-hidden rounded-xl border border-brand-border bg-white shadow-[0_4px_20px_-2px_rgba(139,38,62,0.04),0_2px_6px_-1px_rgba(43,38,39,0.03)]">
                <p class="border-b border-brand-border px-3 py-2 text-[0.68rem] text-brand-muted sm:hidden">Geser ke samping untuk melihat semua kolom</p>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] border-collapse text-left text-xs">
                        <thead class="bg-brand-buff/70 text-[0.68rem] font-semibold uppercase tracking-wide text-brand-muted">
                            <tr>
                                <th scope="col" class="w-11 px-3 py-3">
                                    <input type="checkbox" :checked="allCurrentPageSelected" class="size-4 rounded accent-brand-burgundy" aria-label="Pilih semua pelanggan di halaman ini" @change="toggleCurrentPage">
                                </th>
                                <th scope="col" class="w-14 px-2 py-3">#</th>
                                <th scope="col" class="min-w-40 px-3 py-3">Nama</th>
                                <th scope="col" class="min-w-32 px-3 py-3">Telepon</th>
                                <th scope="col" class="min-w-44 px-3 py-3">Email</th>
                                <th scope="col" class="min-w-28 px-3 py-3">Janji temu</th>
                                <th scope="col" class="w-24 px-3 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-border">
                            <tr v-for="(customer, index) in customers.data" :key="customer.id" class="transition hover:bg-brand-cream/70">
                                <td class="px-3 py-3">
                                    <input v-model="selectedIds" :value="customer.id" type="checkbox" class="size-4 rounded accent-brand-burgundy" :aria-label="`Pilih ${customer.name}`">
                                </td>
                                <td class="px-2 py-3 tabular-nums text-brand-muted">{{ customers.from + index }}</td>
                                <td class="px-3 py-3">
                                    <button type="button" class="max-w-40 truncate text-left text-xs font-semibold text-brand-burgundy underline decoration-brand-blush underline-offset-4 hover:text-brand-burgundy-dark" @click="openDetails(customer)">
                                        {{ customer.name }}
                                    </button>
                                </td>
                                <td class="px-3 py-3 text-brand-muted">{{ customer.phone || '—' }}</td>
                                <td class="px-3 py-3 text-brand-muted">
                                    <span class="block max-w-48 truncate">{{ customer.email || '—' }}</span>
                                </td>
                                <td class="px-3 py-3 font-medium tabular-nums">{{ customer.appointments_count }}</td>
                                <td class="px-3 py-2">
                                    <div class="flex justify-end gap-1">
                                        <button type="button" class="flex size-9 items-center justify-center rounded-lg text-brand-burgundy hover:bg-brand-buff" :aria-label="`Ubah ${customer.name}`" @click="startEdit(customer)"><AppIcon name="edit" class="size-4" /></button>
                                        <button type="button" class="flex size-9 items-center justify-center rounded-lg text-red-700 hover:bg-red-50" :aria-label="`Hapus ${customer.name}`" @click="askDelete(customer)"><AppIcon name="trash" class="size-4" /></button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="customers.data.length === 0" class="px-5 py-10 text-center">
                    <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-brand-buff text-brand-burgundy"><AppIcon name="users" class="size-6" /></div>
                    <h2 class="mt-3 text-sm font-semibold">Pelanggan tidak ditemukan</h2>
                    <p class="mt-1 text-xs text-brand-muted">Ubah pencarian atau filter, atau tambahkan pelanggan.</p>
                </div>
            </section>

            <footer class="flex flex-col gap-3 border-t border-brand-border pt-4">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs text-brand-muted">Menampilkan {{ customers.from ?? 0 }}–{{ customers.to ?? 0 }} dari {{ customers.total }}</p>
                    <label class="flex items-center gap-2 text-xs text-brand-muted">
                        Baris
                        <select v-model="filters.per_page" aria-label="Baris per halaman" class="min-h-9 rounded-lg border border-brand-border bg-white px-2 text-xs font-semibold text-brand-ink outline-none focus:border-brand-burgundy">
                            <option :value="10">10</option><option :value="25">25</option><option :value="50">50</option><option :value="100">100</option>
                        </select>
                    </label>
                </div>

                <nav v-if="customers.last_page > 1" aria-label="Halaman pelanggan" class="flex items-center justify-center gap-1">
                    <button type="button" aria-label="Halaman sebelumnya" :disabled="!customers.prev_page_url" class="flex size-9 items-center justify-center rounded-lg text-brand-muted hover:bg-white disabled:opacity-35" @click="goToPage(customers.current_page - 1)"><AppIcon name="chevron-left" class="size-4" /></button>
                    <template v-for="(number, index) in pageNumbers" :key="number">
                        <span v-if="index > 0 && number - pageNumbers[index - 1] > 1" class="flex size-8 items-center justify-center text-xs text-brand-muted">…</span>
                        <button type="button" :aria-label="`Halaman ${number}`" :aria-current="number === customers.current_page ? 'page' : undefined" class="flex size-9 items-center justify-center rounded-lg text-xs font-semibold" :class="number === customers.current_page ? 'bg-brand-burgundy text-white' : 'text-brand-muted hover:bg-white'" @click="goToPage(number)">{{ number }}</button>
                    </template>
                    <button type="button" aria-label="Halaman berikutnya" :disabled="!customers.next_page_url" class="flex size-9 items-center justify-center rounded-lg text-brand-muted hover:bg-white disabled:opacity-35" @click="goToPage(customers.current_page + 1)"><AppIcon name="chevron-right" class="size-4" /></button>
                </nav>
            </footer>
        </div>

        <Transition enter-active-class="transition duration-200" enter-from-class="translate-y-2 opacity-0" leave-active-class="transition duration-150" leave-to-class="translate-y-2 opacity-0">
            <div v-if="toast" role="status" aria-live="polite" class="fixed inset-x-4 bottom-5 z-[100] mx-auto flex max-w-md items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-white shadow-lg" :class="toast.type === 'error' ? 'bg-red-800' : 'bg-brand-ink'">
                <span class="min-w-0 flex-1">{{ toast.message }}</span>
                <button type="button" aria-label="Tutup notifikasi" class="flex size-8 shrink-0 items-center justify-center rounded-lg hover:bg-white/10" @click="toast = null"><AppIcon name="close" class="size-4" /></button>
            </div>
        </Transition>

        <CustomerFormModal v-if="customerForm" :customer="customerForm.id ? customerForm : null" @close="customerForm = null" @saved="refreshCustomers" />
        <CustomerDetailsModal v-if="customerDetail" :detail="customerDetail" @close="customerDetail = null" @edit="startEdit" @delete="askDelete" />
        <div v-if="detailLoading" class="fixed inset-0 z-[60] flex items-center justify-center bg-brand-ink/20" role="status" aria-live="polite">
            <p class="rounded-xl bg-white px-5 py-3 text-sm font-medium shadow-lg">Memuat pelanggan…</p>
        </div>
        <ConfirmDialog
            v-if="confirmAction"
            :title="confirmAction.type === 'bulk' ? 'Hapus pelanggan terpilih?' : 'Hapus pelanggan?'"
            :message="confirmAction.type === 'bulk'
                ? `Hapus ${confirmAction.count} pelanggan terpilih? Pelanggan dengan riwayat janji temu tetap disimpan.`
                : `Hapus ${confirmAction.customer.name}? Pelanggan dengan riwayat janji temu tidak dapat dihapus.`"
            :confirm-label="confirmAction.type === 'bulk' ? 'Hapus pilihan' : 'Hapus pelanggan'"
            :processing="deleteForm.processing || bulkDeleteForm.processing"
            @cancel="confirmAction = null"
            @confirm="confirmDelete"
        />
    </AppLayout>
</template>
