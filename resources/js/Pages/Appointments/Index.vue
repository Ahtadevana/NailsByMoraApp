<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppointmentDetailsModal from '../../Components/AppointmentDetailsModal.vue';
import AppointmentFormModal from '../../Components/AppointmentFormModal.vue';
import AppIcon from '../../Components/AppIcon.vue';
import ConfirmDialog from '../../Components/ConfirmDialog.vue';
import CustomerLookup from '../../Components/CustomerLookup.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    appointments: { type: Object, required: true },
    calendar: { type: Object, required: true },
    filters: { type: Object, required: true },
    selectedCustomer: { type: Object, default: null },
    serviceCatalog: { type: Array, required: true },
    summary: { type: Object, required: true },
});

const page = usePage();
const filters = reactive({ ...props.filters });
const appointmentForm = ref(null);
const appointmentDetail = ref(null);
const detailLoading = ref(false);
const confirmAppointment = ref(null);
const confirmBulkDelete = ref(false);
const selectedAppointmentIds = ref([]);
const deleteForm = useForm({});
const bulkDeleteForm = useForm({ ids: [] });
const toast = ref(null);
let syncingFilters = false;
let searchTimer;
let toastTimer;

const weekdays = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
const monthTitle = computed(() => new Intl.DateTimeFormat('id-ID', {
    month: 'long',
    year: 'numeric',
}).format(new Date(`${props.calendar.month}-01T00:00:00`)));
const calendarCells = computed(() => {
    const [year, month] = props.calendar.month.split('-').map(Number);
    const daysInMonth = new Date(year, month, 0).getDate();
    const firstDay = (new Date(year, month - 1, 1).getDay() + 6) % 7;
    const cells = Array.from({ length: firstDay }, (_, index) => ({ key: `blank-${index}`, date: null }));

    for (let day = 1; day <= daysInMonth; day += 1) {
        const date = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        cells.push({ key: date, date, day, count: Number(props.calendar.counts[date] ?? 0) });
    }

    while (cells.length % 7) cells.push({ key: `end-${cells.length}`, date: null });
    return cells;
});
const pageNumbers = computed(() => {
    const current = props.appointments.current_page;
    const last = props.appointments.last_page;

    return [...new Set([1, last, current - 1, current, current + 1])]
        .filter((number) => number >= 1 && number <= last)
        .sort((left, right) => left - right);
});
const allCurrentPageSelected = computed(() => (
    props.appointments.data.length > 0
    && props.appointments.data.every((appointment) => selectedAppointmentIds.value.includes(appointment.id))
));
const appointmentHeading = computed(() => {
    if (filters.date) return `Janji temu tanggal ${formatDate(filters.date)}`;
    if (filters.date_from && filters.date_to) return 'Janji temu pada rentang terpilih';
    return `Janji temu bulan ${monthTitle.value}`;
});

watch(() => props.filters, async (updated) => {
    syncingFilters = true;
    Object.assign(filters, updated);
    await nextTick();
    syncingFilters = false;
}, { deep: true });

watch(() => page.props.flash?.toast, (message) => {
    if (message) showToast(message.type, message.message);
});

watch(() => filters.search, () => {
    if (syncingFilters) return;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters(), 300);
});
watch(() => [filters.status, filters.customer_id, filters.sort, filters.per_page], () => {
    if (!syncingFilters) applyFilters();
});
watch(() => [filters.date_from, filters.date_to], ([from, to], [oldFrom, oldTo]) => {
    if (syncingFilters || (from && !to) || (!from && to)) return;
    if (from !== oldFrom || to !== oldTo) applyFilters();
});

onBeforeUnmount(() => {
    clearTimeout(searchTimer);
    clearTimeout(toastTimer);
});

onMounted(() => {
    const query = new URLSearchParams(page.url.split('?')[1] ?? '');
    if (query.get('create') === '1') {
        startCreate();
        return;
    }

    const appointmentId = Number(query.get('open'));
    const appointment = props.appointments.data.find((row) => row.id === appointmentId);
    if (appointment) openDetails(appointment);
});

function queryValues(pageNumber = 1, overrides = {}) {
    const next = { ...filters, ...overrides };
    const hasDateRange = !next.date && next.date_from && next.date_to;

    return {
        month: next.month,
        date: next.date || undefined,
        date_from: hasDateRange ? next.date_from : undefined,
        date_to: hasDateRange ? next.date_to : undefined,
        search: next.search || undefined,
        status: next.status === 'all' ? undefined : next.status,
        customer_id: next.customer_id || undefined,
        sort: next.sort === 'soonest' ? undefined : next.sort,
        per_page: Number(next.per_page) === 10 ? undefined : next.per_page,
        page: pageNumber === 1 ? undefined : pageNumber,
    };
}

function visitAppointments(pageNumber = 1, overrides = {}) {
    router.get('/appointments', queryValues(pageNumber, overrides), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['appointments', 'calendar', 'filters', 'selectedCustomer', 'summary'],
        onSuccess: () => {
            selectedAppointmentIds.value = [];
            appointmentDetail.value = null;
        },
    });
}

function applyFilters() {
    visitAppointments();
}

function goToPage(pageNumber) {
    visitAppointments(pageNumber);
}

function toggleCurrentPage(event) {
    selectedAppointmentIds.value = event.target.checked
        ? props.appointments.data.map((appointment) => appointment.id)
        : [];
}

function selectDate(date) {
    visitAppointments(1, { date, date_from: '', date_to: '' });
}

function clearDate() {
    visitAppointments(1, { date: '', date_from: '', date_to: '' });
}

function changeMonth(offset) {
    const [year, month] = props.calendar.month.split('-').map(Number);
    const changedMonth = new Date(year, month - 1 + offset, 1);
    const monthValue = `${changedMonth.getFullYear()}-${String(changedMonth.getMonth() + 1).padStart(2, '0')}`;

    visitAppointments(1, { month: monthValue, date: '', date_from: '', date_to: '' });
}

function showToast(type, message) {
    clearTimeout(toastTimer);
    toast.value = { type, message };
    toastTimer = setTimeout(() => { toast.value = null; }, 3500);
}

async function openDetails(row) {
    detailLoading.value = true;

    try {
        const response = await fetch(`/appointments/${row.id}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!response.ok) throw new Error('Detail janji temu gagal dimuat. Silakan coba lagi.');
        appointmentDetail.value = (await response.json()).appointment;
    } catch (error) {
        showToast('error', error.message || 'Detail janji temu gagal dimuat.');
    } finally {
        detailLoading.value = false;
    }
}

function startCreate() {
    appointmentForm.value = {};
}

function startEdit(appointment) {
    appointmentDetail.value = null;
    appointmentForm.value = appointment;
}

function refreshAppointments() {
    appointmentForm.value = null;
    appointmentDetail.value = null;
    router.reload({ only: ['appointments', 'calendar', 'summary'] });
}

function markStatus(appointment, status) {
    router.patch(`/appointments/${appointment.id}/status`, { status }, {
        preserveScroll: true,
        onSuccess: () => {
            appointmentDetail.value = null;
            router.reload({ only: ['appointments', 'calendar', 'summary'] });
        },
    });
}

function askDelete(appointment) {
    confirmAppointment.value = appointment;
}

function deleteAppointment() {
    if (!confirmAppointment.value) return;

    deleteForm.delete(`/appointments/${confirmAppointment.value.id}`, {
        onSuccess: () => {
            confirmAppointment.value = null;
            appointmentDetail.value = null;
            router.reload({ only: ['appointments', 'calendar', 'summary'] });
        },
    });
}

function deleteSelectedAppointments() {
    bulkDeleteForm.ids = [...selectedAppointmentIds.value];
    bulkDeleteForm.delete('/appointments/bulk', {
        onSuccess: () => {
            confirmBulkDelete.value = false;
            selectedAppointmentIds.value = [];
            appointmentDetail.value = null;
            router.reload({ only: ['appointments', 'calendar', 'summary'] });
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
    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${value}T00:00:00`));
}

function statusLabel(status) {
    return status === 'NO_SHOW' ? 'Tidak hadir' : status.charAt(0) + status.slice(1).toLowerCase();
}

function statusClass(status) {
    return status === 'SCHEDULED'
        ? 'bg-amber-50 text-amber-800'
        : status === 'COMPLETED'
            ? 'bg-emerald-50 text-emerald-800'
            : status === 'NO_SHOW'
                ? 'bg-red-50 text-red-800'
                : 'bg-brand-buff text-brand-muted';
}
</script>

<template>
    <AppLayout>
        <main class="mx-auto flex w-full max-w-6xl flex-col gap-5 px-4 pb-10 pt-5 sm:px-6">
            <header class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-brand-burgundy">Jadwal studio</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-brand-ink">Janji temu</h1>
                    <p class="mt-1 max-w-xl text-sm leading-5 text-brand-muted">Kelola jadwal dan riwayat janji temu di satu tempat.</p>
                </div>
                <button type="button" class="flex min-h-11 shrink-0 items-center gap-2 rounded-xl bg-brand-burgundy px-3.5 text-sm font-semibold text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.25)] hover:bg-brand-burgundy-dark" @click="startCreate">
                    <AppIcon name="plus" /><span class="hidden xs:inline">Janji Temu Baru</span><span class="xs:hidden">Baru</span>
                </button>
            </header>

            <section aria-label="Ringkasan janji temu" class="grid grid-cols-2 gap-3">
                <article class="rounded-2xl border border-brand-border bg-white p-4 shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)]">
                    <p class="text-xs font-medium text-brand-muted">Janji temu hari ini</p><p class="mt-2 text-2xl font-bold">{{ summary.today }}</p>
                </article>
                <article class="rounded-2xl border border-brand-border bg-white p-4 shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)]">
                    <p class="text-xs font-medium text-brand-muted">Terjadwal <span class="font-normal">(bulan ini)</span></p><p class="mt-2 text-2xl font-bold text-amber-800">{{ summary.scheduled }}</p>
                </article>
                <article class="rounded-2xl border border-brand-border bg-white p-4 shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)]">
                    <p class="text-xs font-medium text-brand-muted">Selesai <span class="font-normal">(bulan ini)</span></p><p class="mt-2 text-2xl font-bold text-emerald-800">{{ summary.completed }}</p>
                </article>
                <article class="rounded-2xl border border-brand-border bg-white p-4 shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)]">
                    <p class="text-xs font-medium text-brand-muted">Tidak hadir <span class="font-normal">(bulan ini)</span></p><p class="mt-2 text-2xl font-bold text-red-800">{{ summary.no_show }}</p>
                </article>
            </section>

            <section aria-label="Kalender janji temu bulanan" class="rounded-2xl border border-brand-border bg-white p-4 shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)] sm:p-5">
                <header class="flex items-center justify-between gap-3">
                    <div><h2 class="text-base font-bold">Kalender</h2><p class="mt-0.5 text-xs text-brand-muted">Pilih tanggal untuk melihat janji temu.</p></div>
                    <div class="flex items-center gap-2">
                        <button type="button" aria-label="Bulan sebelumnya" class="flex size-10 items-center justify-center rounded-lg border border-brand-border hover:bg-brand-buff" @click="changeMonth(-1)"><AppIcon name="chevron-left" class="size-4" /></button>
                        <p class="min-w-28 text-center text-sm font-semibold">{{ monthTitle }}</p>
                        <button type="button" aria-label="Bulan berikutnya" class="flex size-10 items-center justify-center rounded-lg border border-brand-border hover:bg-brand-buff" @click="changeMonth(1)"><AppIcon name="chevron-right" class="size-4" /></button>
                    </div>
                </header>
                <div class="mt-4 grid grid-cols-7 gap-1 text-center">
                    <div v-for="weekday in weekdays" :key="weekday" class="py-2 text-[0.68rem] font-semibold uppercase tracking-wide text-brand-muted">{{ weekday }}</div>
                    <div v-for="cell in calendarCells" :key="cell.key" class="flex justify-center">
                        <button
                            v-if="cell.date"
                            type="button"
                            :aria-label="`${cell.date}${cell.count ? `, ${cell.count} janji temu` : ''}`"
                            :aria-pressed="filters.date === cell.date"
                            :class="filters.date === cell.date ? 'bg-brand-burgundy text-white' : 'text-brand-ink hover:bg-brand-buff'"
                            class="relative flex size-10 flex-col items-center justify-center rounded-xl text-sm font-medium transition sm:size-11"
                            @click="selectDate(cell.date)"
                        >
                            <span>{{ cell.day }}</span>
                            <span v-if="cell.count" :class="filters.date === cell.date ? 'bg-white' : 'bg-brand-burgundy'" class="absolute bottom-1 size-1 rounded-full" />
                        </button>
                        <span v-else class="size-10 sm:size-11" />
                    </div>
                </div>
                <div class="mt-3 flex items-center justify-between border-t border-brand-border pt-3">
                    <p class="text-xs text-brand-muted"><span class="mr-1 inline-block size-1.5 rounded-full bg-brand-burgundy" />Tanggal dengan janji temu</p>
                    <button v-if="filters.date" type="button" class="text-xs font-semibold text-brand-burgundy hover:underline" @click="clearDate">Hapus pilihan tanggal</button>
                </div>
            </section>

            <section class="flex flex-col gap-3">
                <header class="flex items-center justify-between gap-2">
                    <div><h2 class="text-lg font-bold">{{ appointmentHeading }}</h2><p class="mt-0.5 text-xs text-brand-muted">{{ appointments.total }} janji temu</p></div>
                    <button v-if="filters.date || filters.date_from || filters.date_to" type="button" class="text-xs font-semibold text-brand-burgundy hover:underline" @click="clearDate">Hapus filter tanggal</button>
                </header>
                <label class="flex min-h-12 items-center gap-3 rounded-xl border border-brand-border bg-white px-3.5 focus-within:border-brand-burgundy">
                    <AppIcon name="search" class="text-brand-muted" />
                    <input v-model="filters.search" type="search" placeholder="Cari pelanggan, layanan, atau catatan…" class="w-full bg-transparent text-base outline-none placeholder:text-brand-muted">
                </label>
                <div class="flex flex-col gap-2">
                    <div class="grid grid-cols-2 gap-2">
                        <label class="relative">
                            <span class="sr-only">Filter janji temu berdasarkan status</span>
                            <select v-model="filters.status" class="min-h-11 w-full appearance-none rounded-lg border border-brand-border bg-white py-2 pl-3 pr-8 text-sm font-medium">
                                <option value="all">Status: Semua</option><option value="SCHEDULED">Terjadwal</option><option value="COMPLETED">Selesai</option><option value="NO_SHOW">Tidak hadir</option><option value="CANCELLED">Dibatalkan</option>
                            </select><AppIcon name="chevron-down" class="pointer-events-none absolute right-2 top-3 size-4 text-brand-muted" />
                        </label>
                        <label class="relative">
                            <span class="sr-only">Urutkan janji temu</span>
                            <select v-model="filters.sort" class="min-h-11 w-full appearance-none rounded-lg border border-brand-border bg-white py-2 pl-3 pr-8 text-sm font-medium">
                                <option value="soonest">Urutkan: Terdekat</option><option value="latest">Terbaru</option><option value="customer_asc">Pelanggan A–Z</option><option value="customer_desc">Pelanggan Z–A</option>
                            </select><AppIcon name="chevron-down" class="pointer-events-none absolute right-2 top-3 size-4 text-brand-muted" />
                        </label>
                    </div>
                    <CustomerLookup
                        v-model="filters.customer_id"
                        :initial-customer="selectedCustomer"
                        placeholder="Filter pelanggan"
                    />
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex flex-col gap-1 text-[0.68rem] font-semibold text-brand-muted">Dari
                            <input v-model="filters.date_from" type="date" :max="filters.date_to || undefined" class="min-h-10 rounded-lg border border-brand-border bg-white px-2 text-sm font-normal text-brand-ink" @input="filters.date = ''">
                        </label>
                        <label class="flex flex-col gap-1 text-[0.68rem] font-semibold text-brand-muted">Sampai
                            <input v-model="filters.date_to" type="date" :min="filters.date_from || undefined" class="min-h-10 rounded-lg border border-brand-border bg-white px-2 text-sm font-normal text-brand-ink" @input="filters.date = ''">
                        </label>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)]">
                <div v-if="selectedAppointmentIds.length" class="flex min-h-12 items-center justify-between gap-3 border-b border-brand-border bg-brand-burgundy/5 px-3.5">
                    <p class="text-sm font-semibold">{{ selectedAppointmentIds.length }} janji temu dipilih</p>
                    <div class="flex items-center gap-3">
                        <button type="button" class="text-sm font-semibold text-red-700 hover:underline" @click="confirmBulkDelete = true">Hapus pilihan</button>
                        <button type="button" class="text-sm font-medium text-brand-muted hover:underline" @click="selectedAppointmentIds = []">Bersihkan</button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] border-collapse text-left text-sm">
                        <thead class="bg-brand-buff text-xs uppercase tracking-wide text-brand-muted">
                            <tr><th class="w-12 px-4 py-3"><input aria-label="Pilih semua janji temu di halaman ini" type="checkbox" class="size-4 rounded accent-brand-burgundy" :checked="allCurrentPageSelected" @change="toggleCurrentPage"></th><th class="w-12 px-2 py-3">#</th><th class="px-3 py-3">Waktu</th><th class="px-3 py-3">Pelanggan</th><th class="px-3 py-3">Layanan</th><th class="px-3 py-3">Status</th><th class="px-4 py-3 text-right">Aksi</th></tr>
                        </thead>
                        <tbody class="divide-y divide-brand-border">
                            <tr v-for="(appointment, index) in appointments.data" :key="appointment.id" class="align-top hover:bg-brand-buff/40">
                                <td class="px-4 py-3"><input v-model="selectedAppointmentIds" :value="appointment.id" :aria-label="`Pilih janji temu ${appointment.customer.name}`" type="checkbox" class="size-4 rounded accent-brand-burgundy"></td>
                                <td class="px-2 py-3 text-brand-muted">{{ (appointments.from ?? 1) + index }}</td>
                                <td class="whitespace-nowrap px-3 py-3">
                                    <button type="button" class="font-semibold text-brand-burgundy hover:underline" @click="openDetails(appointment)">{{ appointment.start_time }}</button>
                                    <p class="mt-0.5 text-xs text-brand-muted">{{ formatDate(appointment.date) }}</p>
                                </td>
                                <td class="px-3 py-3">
                                    <button type="button" class="max-w-48 truncate text-left font-semibold hover:text-brand-burgundy hover:underline" @click="openDetails(appointment)">{{ appointment.customer.name }}</button>
                                    <p class="mt-0.5 text-xs text-brand-muted">{{ appointment.customer.phone || appointment.customer.email || 'Tidak ada info kontak' }}</p>
                                </td>
                                <td class="max-w-64 px-3 py-3">
                                    <p class="line-clamp-2 text-sm">{{ appointment.services.map((service) => service.name).join(', ') }}</p>
                                    <p class="mt-0.5 text-xs text-brand-muted">{{ formatCurrency(appointment.services.reduce((total, service) => total + Number(service.price), 0)) }} · {{ appointment.services.reduce((total, service) => total + Number(service.duration_minutes), 0) }} mnt</p>
                                </td>
                                <td class="px-3 py-3"><span :class="statusClass(appointment.status)" class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold">{{ statusLabel(appointment.status) }}</span></td>
                                <td class="px-4 py-3">
                                    <div class="flex min-w-64 flex-wrap justify-end gap-1">
                                        <button type="button" class="min-h-9 rounded-lg px-2 text-xs font-semibold text-brand-burgundy hover:bg-brand-buff" @click="openDetails(appointment)">Lihat</button>
                                        <button type="button" :aria-label="`Ubah janji temu ${appointment.customer.name}`" class="flex size-9 items-center justify-center rounded-lg text-brand-muted hover:bg-brand-buff hover:text-brand-burgundy" @click="startEdit(appointment)"><AppIcon name="edit" class="size-4" /></button>
                                        <template v-if="appointment.status === 'SCHEDULED'">
                                            <button type="button" class="min-h-9 rounded-lg px-2 text-xs font-semibold text-emerald-800 hover:bg-emerald-50" @click="markStatus(appointment, 'COMPLETED')">Selesai</button>
                                            <button type="button" class="min-h-9 rounded-lg px-2 text-xs font-semibold text-brand-muted hover:bg-brand-buff" @click="markStatus(appointment, 'NO_SHOW')">Tidak hadir</button>
                                            <button type="button" class="min-h-9 rounded-lg px-2 text-xs font-semibold text-brand-muted hover:bg-brand-buff" @click="markStatus(appointment, 'CANCELLED')">Batal</button>
                                        </template>
                                        <button type="button" :aria-label="`Hapus janji temu ${appointment.customer.name}`" class="flex size-9 items-center justify-center rounded-lg text-brand-muted hover:bg-red-50 hover:text-red-700" @click="askDelete(appointment)"><AppIcon name="trash" class="size-4" /></button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="appointments.data.length === 0"><td colspan="7" class="px-4 py-12 text-center"><p class="font-semibold">Janji temu tidak ditemukan</p><p class="mt-1 text-sm text-brand-muted">Pilih tanggal lain atau ubah filter.</p></td></tr>
                        </tbody>
                    </table>
                </div>
                <footer class="flex flex-col gap-4 border-t border-brand-border px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-brand-muted">Menampilkan {{ appointments.from ?? 0 }}–{{ appointments.to ?? 0 }} dari {{ appointments.total }} janji temu</p>
                    <div class="flex items-center justify-between gap-3 sm:justify-end">
                        <label class="flex items-center gap-2 text-xs text-brand-muted">Baris
                            <select v-model="filters.per_page" class="min-h-9 rounded-lg border border-brand-border bg-white px-2 text-sm text-brand-ink"><option :value="10">10</option><option :value="25">25</option><option :value="50">50</option><option :value="100">100</option></select>
                        </label>
                        <nav aria-label="Halaman janji temu" class="flex items-center gap-1">
                            <button type="button" aria-label="Halaman sebelumnya" :disabled="appointments.current_page <= 1" class="flex size-9 items-center justify-center rounded-lg border border-brand-border disabled:opacity-40" @click="goToPage(appointments.current_page - 1)"><AppIcon name="chevron-left" /></button>
                            <template v-for="(number, index) in pageNumbers" :key="number">
                                <span v-if="index && number - pageNumbers[index - 1] > 1" class="px-1 text-brand-muted">…</span>
                                <button type="button" :aria-current="number === appointments.current_page ? 'page' : undefined" :class="number === appointments.current_page ? 'border-brand-burgundy bg-brand-burgundy text-white' : 'border-brand-border hover:bg-brand-buff'" class="flex size-9 items-center justify-center rounded-lg border text-sm font-semibold" @click="goToPage(number)">{{ number }}</button>
                            </template>
                            <button type="button" aria-label="Halaman berikutnya" :disabled="appointments.current_page >= appointments.last_page" class="flex size-9 items-center justify-center rounded-lg border border-brand-border disabled:opacity-40" @click="goToPage(appointments.current_page + 1)"><AppIcon name="chevron-right" /></button>
                        </nav>
                    </div>
                </footer>
            </section>
        </main>

        <AppointmentFormModal v-if="appointmentForm" :appointment="appointmentForm.id ? appointmentForm : null" :service-catalog="serviceCatalog" @close="appointmentForm = null" @saved="refreshAppointments" />

        <div v-if="detailLoading" role="status" class="fixed inset-0 z-[55] flex items-center justify-center bg-brand-ink/20 text-sm font-semibold">Memuat detail janji temu…</div>
        <AppointmentDetailsModal
            v-if="appointmentDetail"
            :appointment="appointmentDetail"
            @close="appointmentDetail = null"
            @edit="startEdit"
            @delete="askDelete"
            @status="markStatus(appointmentDetail, $event)"
        />
        <ConfirmDialog
            v-if="confirmAppointment"
            title="Hapus janji temu?"
            :message="`Hapus janji temu ${confirmAppointment.customer.name} pada ${formatDate(confirmAppointment.date)}? Layanan yang terkait juga akan dihapus.`"
            confirm-label="Hapus janji temu"
            :processing="deleteForm.processing"
            @cancel="confirmAppointment = null"
            @confirm="deleteAppointment"
        />
        <ConfirmDialog
            v-if="confirmBulkDelete"
            title="Hapus janji temu terpilih?"
            :message="`Hapus ${selectedAppointmentIds.length} janji temu beserta layanan terkait? Tindakan ini tidak dapat dibatalkan.`"
            confirm-label="Hapus janji temu"
            :processing="bulkDeleteForm.processing"
            @cancel="confirmBulkDelete = false"
            @confirm="deleteSelectedAppointments"
        />
        <div v-if="toast" role="status" aria-live="polite" :class="toast.type === 'error' ? 'border-red-200 bg-red-50 text-red-900' : 'border-emerald-200 bg-emerald-50 text-emerald-900'" class="fixed bottom-5 left-4 right-4 z-[100] mx-auto max-w-md rounded-xl border px-4 py-3 text-sm font-semibold shadow-lg">
            {{ toast.message }}
        </div>
    </AppLayout>
</template>
