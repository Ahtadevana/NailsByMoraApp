<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppIcon from '../../Components/AppIcon.vue';
import ReportChart from '../../Components/ReportChart.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    report: { type: Object, default: null },
    generatedFilters: { type: Object, default: null },
    filters: { type: Object, required: true },
    options: { type: Object, required: true },
});
const page = usePage();
const filters = reactive({ ...props.filters });
const reportError = ref('');
const exporting = ref('');
const generating = ref(false);
const toast = ref('');
const reportTypes = [
    { value: 'financial_summary', label: 'Ringkasan keuangan', group: 'Keuangan' },
    { value: 'revenue', label: 'Laporan pendapatan', group: 'Keuangan' },
    { value: 'expenses', label: 'Laporan pengeluaran', group: 'Keuangan' },
    { value: 'appointments', label: 'Laporan janji temu', group: 'Janji temu & layanan' },
    { value: 'service_performance', label: 'Kinerja layanan', group: 'Janji temu & layanan' },
    { value: 'customers', label: 'Laporan pelanggan', group: 'Pelanggan' },
    { value: 'inventory_status', label: 'Status inventaris', group: 'Inventaris' },
    { value: 'stock_movements', label: 'Pergerakan stok', group: 'Inventaris' },
];
const selectedType = computed(() => filters.report_type);
const isCurrentStock = computed(() => selectedType.value === 'inventory_status');
const visibleFilters = computed(() => ({
    expense: selectedType.value === 'expenses',
    service: ['revenue', 'service_performance'].includes(selectedType.value),
    status: selectedType.value === 'appointments',
    customer: selectedType.value === 'customers',
    inventoryCategory: ['inventory_status', 'stock_movements'].includes(selectedType.value),
    stockStatus: selectedType.value === 'inventory_status',
    inventoryItem: selectedType.value === 'stock_movements',
    movementType: selectedType.value === 'stock_movements',
}));
const selectedInventoryItems = computed(() => (
    filters.inventory_category_id
        ? props.options.inventory_items.filter((item) => String(item.category_id) === String(filters.inventory_category_id))
        : props.options.inventory_items
));
const generatedQuery = computed(() => {
    const source = props.generatedFilters;
    if (!source) return '';

    const allowed = [
        'report_type',
        'start_date',
        'end_date',
        'expense_category_id',
        'payment_method',
        'service_id',
        'appointment_status',
        'customer_id',
        'inventory_category_id',
        'stock_status',
        'inventory_item_id',
        'movement_type',
    ];
    const query = new URLSearchParams();
    for (const key of allowed) {
        const value = source[key];
        if (value !== undefined && value !== null && value !== '' && value !== 'all') query.set(key, value);
    }

    return query.toString();
});
const firstTable = computed(() => props.report?.tables?.[0] ?? null);
const reportStateKey = 'nailsbymora:generated-report-filters';

watch(() => props.filters, (updated) => Object.assign(filters, updated), { deep: true });
watch(generatedQuery, (query) => {
    if (query) localStorage.setItem(reportStateKey, query);
});
watch(() => page.props.errors, (errors) => {
    if (errors?.start_date) reportError.value = errors.start_date;
    else if (errors?.end_date) reportError.value = errors.end_date;
    else if (errors && Object.keys(errors).length) reportError.value = Object.values(errors)[0];
}, { deep: true });

onMounted(() => {
    if (props.generatedFilters) return;

    const savedQuery = localStorage.getItem(reportStateKey);
    if (!savedQuery) return;

    const savedFilters = new URLSearchParams(savedQuery);
    if (!reportTypes.some((type) => type.value === savedFilters.get('report_type'))) return;
    if (savedFilters.get('report_type') !== 'inventory_status'
        && (!savedFilters.has('start_date') || !savedFilters.has('end_date'))) return;

    router.get(`/reports?${savedQuery}`, {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
});

function localDate(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function setQuickPeriod(period) {
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    if (period === 'today') {
        filters.start_date = localDate(today);
        filters.end_date = localDate(today);
    } else if (period === 'this_week') {
        const monday = new Date(today);
        monday.setDate(today.getDate() - ((today.getDay() + 6) % 7));
        const sunday = new Date(monday);
        sunday.setDate(monday.getDate() + 6);
        filters.start_date = localDate(monday);
        filters.end_date = localDate(sunday);
    } else if (period === 'this_month') {
        filters.start_date = localDate(new Date(today.getFullYear(), today.getMonth(), 1));
        filters.end_date = localDate(new Date(today.getFullYear(), today.getMonth() + 1, 0));
    } else if (period === 'this_year') {
        filters.start_date = localDate(new Date(today.getFullYear(), 0, 1));
        filters.end_date = localDate(new Date(today.getFullYear(), 11, 31));
    }
}

function queryParams(includePaging = false) {
    const query = new URLSearchParams();
    const relevantKeys = ['report_type'];
    if (!isCurrentStock.value) relevantKeys.push('start_date', 'end_date');
    if (visibleFilters.value.expense) relevantKeys.push('expense_category_id', 'payment_method');
    if (visibleFilters.value.service) relevantKeys.push('service_id');
    if (visibleFilters.value.status) relevantKeys.push('appointment_status');
    if (visibleFilters.value.customer) relevantKeys.push('customer_id');
    if (visibleFilters.value.inventoryCategory) relevantKeys.push('inventory_category_id');
    if (visibleFilters.value.stockStatus) relevantKeys.push('stock_status');
    if (visibleFilters.value.inventoryItem) relevantKeys.push('inventory_item_id', 'movement_type');

    for (const key of relevantKeys) {
        const value = filters[key];
        if (value !== undefined && value !== null && value !== '' && value !== 'all') query.set(key, value);
    }
    if (includePaging) query.set('per_page', filters.per_page);

    return query.toString();
}

function generateReport() {
    reportError.value = '';
    if (!filters.report_type) {
        reportError.value = 'Pilih jenis laporan.';
        return;
    }
    if (!isCurrentStock.value) {
        if (!filters.start_date || !filters.end_date) {
            reportError.value = 'Pilih tanggal mulai dan tanggal akhir.';
            return;
        }
        if (filters.start_date > filters.end_date) {
            reportError.value = 'Tanggal akhir harus sama atau setelah tanggal mulai.';
            return;
        }
    }

    router.get(`/reports?${queryParams(true)}`, {}, {
        preserveState: true,
        preserveScroll: true,
        onStart: () => { generating.value = true; },
        onFinish: () => { generating.value = false; },
        onError: (errors) => {
            reportError.value = errors.end_date || errors.start_date || 'Laporan gagal dibuat. Periksa filter dan coba lagi.';
        },
        onSuccess: () => { reportError.value = ''; },
    });
}

function visitReportPage(number) {
    const query = new URLSearchParams(generatedQuery.value);
    if (props.generatedFilters?.per_page) query.set('per_page', props.generatedFilters.per_page);
    query.set('page', number);
    router.get(`/reports?${query.toString()}`, {}, {
        preserveState: true,
        preserveScroll: true,
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
    }).format(new Date(`${String(value).slice(0, 10)}T00:00:00`)) : '—';
}

function formatDateTime(value) {
    return value ? new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value)) : '—';
}

function displayValue(value, column) {
    if (column.format === 'currency') return formatCurrency(value);
    if (column.format === 'date') return formatDate(value);
    if (column.format === 'datetime') return formatDateTime(value);
    if (column.format === 'percent') return `${Number(value ?? 0).toFixed(1)}%`;
    if (column.key === 'quantity' || column.key === 'minimum_stock') {
        return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(Number(value ?? 0));
    }
    return value ?? '—';
}

function formatGeneratedAt(value) {
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

function showToast(message) {
    toast.value = message;
    setTimeout(() => {
        if (toast.value === message) toast.value = '';
    }, 3500);
}

async function exportReport(format) {
    if (exporting.value || !generatedQuery.value) return;
    if (format === 'pdf') {
        const printWindow = window.open(`/reports/print?${generatedQuery.value}`, '_blank');
        if (!printWindow) {
            reportError.value = 'Tampilan cetak gagal dibuka. Izinkan pop-up lalu coba lagi.';
        } else {
            printWindow.opener = null;
        }
        return;
    }

    exporting.value = format;
    reportError.value = '';
    try {
        const response = await fetch(`/reports/export/${format}?${generatedQuery.value}`, {
            headers: { Accept: 'application/octet-stream', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!response.ok) throw new Error('Laporan gagal dibuat. Silakan coba lagi.');
        const blob = await response.blob();
        const disposition = response.headers.get('Content-Disposition') ?? '';
        const filename = disposition.match(/filename="?([^";]+)"?/i)?.[1] ?? `laporan.${format}`;
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.append(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
        showToast(`Laporan ${format.toUpperCase()} berhasil diunduh.`);
    } catch (error) {
        reportError.value = error.message || 'Laporan gagal dibuat. Silakan coba lagi.';
    } finally {
        exporting.value = '';
    }
}
</script>

<template>
    <AppLayout>
        <main class="mx-auto flex w-full max-w-6xl flex-col gap-5 px-4 pb-10 pt-5 sm:px-6">
            <header>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-brand-burgundy">Wawasan bisnis</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-brand-ink">Laporan</h1>
                <p class="mt-1 max-w-xl text-sm leading-5 text-brand-muted">Atur laporan, tinjau aktivitas bisnis, dan unduh hasilnya.</p>
            </header>

            <section aria-labelledby="report-configuration-title" class="rounded-2xl border border-brand-border bg-white p-4 shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)] sm:p-5">
                <header class="mb-4 flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-xl bg-brand-cream text-brand-burgundy"><AppIcon name="reports" /></span>
                    <div><h2 id="report-configuration-title" class="font-bold">Pengaturan laporan</h2><p class="text-xs text-brand-muted">Pilih data yang ingin dianalisis.</p></div>
                </header>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="flex flex-col gap-1.5 text-sm font-semibold">
                        Jenis laporan <span class="sr-only">(wajib)</span>
                        <select v-model="filters.report_type" class="min-h-11 w-full rounded-lg border border-brand-border bg-brand-buff px-3 text-base font-normal outline-none focus:border-brand-burgundy">
                            <option value="" disabled>Pilih jenis laporan</option>
                            <optgroup v-for="group in [...new Set(reportTypes.map((type) => type.group))]" :key="group" :label="group">
                                <option v-for="type in reportTypes.filter((entry) => entry.group === group)" :key="type.value" :value="type.value">{{ type.label }}</option>
                            </optgroup>
                        </select>
                    </label>
                    <div v-if="!isCurrentStock" class="flex flex-col gap-1.5 text-sm font-semibold sm:col-span-1">
                        <span>Periode <span class="font-normal text-brand-muted">(wajib)</span></span>
                        <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-2">
                            <input v-model="filters.start_date" aria-label="Tanggal mulai" type="date" class="min-h-11 min-w-0 rounded-lg border border-brand-border bg-brand-buff px-2 text-sm font-normal outline-none focus:border-brand-burgundy">
                            <span class="text-brand-muted">sampai</span>
                            <input v-model="filters.end_date" aria-label="Tanggal akhir" type="date" :min="filters.start_date || undefined" class="min-h-11 min-w-0 rounded-lg border border-brand-border bg-brand-buff px-2 text-sm font-normal outline-none focus:border-brand-burgundy">
                        </div>
                    </div>
                    <template v-if="visibleFilters.expense">
                        <label class="flex flex-col gap-1.5 text-sm font-semibold">Kategori pengeluaran
                            <select v-model="filters.expense_category_id" class="min-h-11 rounded-lg border border-brand-border bg-brand-buff px-3 text-sm font-normal"><option value="">Semua kategori</option><option v-for="category in options.expense_categories" :key="category.id" :value="category.id">{{ category.name }}</option></select>
                        </label>
                        <label class="flex flex-col gap-1.5 text-sm font-semibold">Metode pembayaran
                            <select v-model="filters.payment_method" class="min-h-11 rounded-lg border border-brand-border bg-brand-buff px-3 text-sm font-normal"><option value="all">Semua metode</option><option value="CASH">Tunai</option><option value="BANK TRANSFER">Transfer bank</option><option value="QRIS">QRIS</option><option value="OTHER">Lainnya</option></select>
                        </label>
                    </template>
                    <label v-if="visibleFilters.service" class="flex flex-col gap-1.5 text-sm font-semibold">Layanan
                        <select v-model="filters.service_id" class="min-h-11 rounded-lg border border-brand-border bg-brand-buff px-3 text-sm font-normal"><option value="">Semua layanan</option><option v-for="service in options.services" :key="service.id" :value="service.id">{{ service.name }}</option></select>
                    </label>
                    <label v-if="visibleFilters.status" class="flex flex-col gap-1.5 text-sm font-semibold">Status janji temu
                        <select v-model="filters.appointment_status" class="min-h-11 rounded-lg border border-brand-border bg-brand-buff px-3 text-sm font-normal"><option value="all">Semua status</option><option value="SCHEDULED">Terjadwal</option><option value="COMPLETED">Selesai</option><option value="CANCELLED">Dibatalkan</option><option value="NO_SHOW">Tidak hadir</option></select>
                    </label>
                    <label v-if="visibleFilters.customer" class="flex flex-col gap-1.5 text-sm font-semibold">Pelanggan
                        <select v-model="filters.customer_id" class="min-h-11 rounded-lg border border-brand-border bg-brand-buff px-3 text-sm font-normal"><option value="">Semua pelanggan</option><option v-for="customer in options.customers" :key="customer.id" :value="customer.id">{{ customer.name }}</option></select>
                    </label>
                    <label v-if="visibleFilters.inventoryCategory" class="flex flex-col gap-1.5 text-sm font-semibold">Kategori inventaris
                        <select v-model="filters.inventory_category_id" class="min-h-11 rounded-lg border border-brand-border bg-brand-buff px-3 text-sm font-normal"><option value="">Semua kategori</option><option v-for="category in options.inventory_categories" :key="category.id" :value="category.id">{{ category.name }}</option></select>
                    </label>
                    <label v-if="visibleFilters.stockStatus" class="flex flex-col gap-1.5 text-sm font-semibold">Status stok
                        <select v-model="filters.stock_status" class="min-h-11 rounded-lg border border-brand-border bg-brand-buff px-3 text-sm font-normal"><option value="all">Semua stok</option><option value="low_stock">Stok menipis</option><option value="in_stock">Stok tersedia</option></select>
                    </label>
                    <label v-if="visibleFilters.inventoryItem" class="flex flex-col gap-1.5 text-sm font-semibold">Barang inventaris
                        <select v-model="filters.inventory_item_id" class="min-h-11 rounded-lg border border-brand-border bg-brand-buff px-3 text-sm font-normal"><option value="">Semua barang</option><option v-for="item in selectedInventoryItems" :key="item.id" :value="item.id">{{ item.name }} · {{ item.unit }}</option></select>
                    </label>
                    <label v-if="visibleFilters.movementType" class="flex flex-col gap-1.5 text-sm font-semibold">Jenis pergerakan
                        <select v-model="filters.movement_type" class="min-h-11 rounded-lg border border-brand-border bg-brand-buff px-3 text-sm font-normal"><option value="all">Masuk dan keluar</option><option value="IN">Masuk</option><option value="OUT">Keluar</option></select>
                    </label>
                </div>
                <div v-if="!isCurrentStock" class="mt-4 flex flex-col gap-2">
                    <p class="text-xs font-semibold text-brand-muted">Periode cepat</p>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="period in [{ id: 'today', label: 'Hari ini' }, { id: 'this_week', label: 'Minggu ini' }, { id: 'this_month', label: 'Bulan ini' }, { id: 'this_year', label: 'Tahun ini' }]" :key="period.id" type="button" class="min-h-9 rounded-lg border border-brand-border px-3 text-xs font-semibold hover:border-brand-burgundy hover:text-brand-burgundy" @click="setQuickPeriod(period.id)">{{ period.label }}</button>
                    </div>
                </div>
                <div v-if="reportError" role="alert" class="mt-4 rounded-xl border border-red-200 bg-red-50 px-3.5 py-3 text-sm text-red-800">{{ reportError }}</div>
                <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-brand-border pt-4">
                    <p v-if="isCurrentStock" class="text-xs text-brand-muted">Menampilkan kondisi stok saat ini.</p>
                    <p v-else class="text-xs text-brand-muted">Data mengikuti rentang tanggal terpilih.</p>
                    <button type="button" :disabled="generating" class="flex min-h-11 items-center justify-center gap-2 rounded-xl bg-brand-burgundy px-4 text-sm font-semibold text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.25)] hover:bg-brand-burgundy-dark disabled:cursor-wait disabled:opacity-60" @click="generateReport"><AppIcon name="reports" class="size-4" />{{ generating ? 'Membuat…' : 'Buat laporan' }}</button>
                </div>
            </section>

            <template v-if="report">
                <section aria-labelledby="generated-report-title" class="rounded-2xl border border-brand-border bg-white p-4 shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)] sm:p-5">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.14em] text-brand-burgundy">NailsByMora · Laporan</p>
                            <h2 id="generated-report-title" class="mt-1 text-xl font-bold">{{ report.title }}</h2>
                            <p class="mt-1 text-sm text-brand-muted">Periode: <span class="font-semibold text-brand-ink">{{ report.period }}</span></p>
                            <p class="mt-1 text-xs text-brand-muted">Dibuat {{ formatGeneratedAt(report.generated_at) }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2 print:hidden">
                            <button v-for="format in [{ id: 'csv', label: 'Ekspor CSV' }, { id: 'xlsx', label: 'Ekspor Excel' }, { id: 'pdf', label: 'Ekspor PDF' }]" :key="format.id" type="button" :disabled="Boolean(exporting)" class="flex min-h-10 items-center gap-2 rounded-lg border border-brand-border px-3 text-xs font-semibold hover:bg-brand-cream disabled:cursor-wait disabled:opacity-60" @click="exportReport(format.id)">
                                <AppIcon v-if="exporting === format.id" name="clock" class="size-4 animate-spin" />{{ exporting === format.id ? 'Menyiapkan…' : format.label }}
                            </button>
                        </div>
                    </div>
                </section>

                <section aria-label="Ringkasan laporan" class="grid grid-cols-2 gap-3 xl:grid-cols-4">
                    <article v-for="metric in report.metrics" :key="metric.label" class="min-w-0 rounded-2xl border border-brand-border bg-white p-4 shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)]">
                        <p class="text-xs font-medium text-brand-muted">{{ metric.label }}</p>
                        <template v-if="metric.format === 'quantities'">
                            <p v-if="!metric.value.length" class="mt-2 text-lg font-bold">0</p>
                            <p v-for="quantity in metric.value" :key="quantity.unit" class="mt-1 text-lg font-bold">{{ quantity.quantity }} <span class="text-sm font-medium text-brand-muted">{{ quantity.unit }}</span></p>
                        </template>
                        <p v-else class="mt-2 break-words text-xl font-bold" :class="metric.label === 'Stok menipis' && Number(metric.value) > 0 ? 'text-amber-800' : ''">{{ metric.format === 'currency' ? formatCurrency(metric.value) : metric.value }}</p>
                    </article>
                </section>

                <section v-if="report.charts.length" aria-label="Grafik laporan" class="grid gap-4 xl:grid-cols-2">
                    <ReportChart v-for="chart in report.charts" :key="chart.title" :chart="chart" />
                </section>

                <section v-for="(table, tableIndex) in report.tables" :key="table.title" class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)]">
                    <header class="flex items-center justify-between gap-3 border-b border-brand-border px-4 py-4 sm:px-5">
                        <div><h3 class="font-bold">{{ table.title }}</h3><p class="mt-0.5 text-xs text-brand-muted">{{ table.pagination.total }} baris</p></div>
                        <label v-if="tableIndex === 0 && table.pagination.total > 10" class="flex items-center gap-2 text-xs text-brand-muted print:hidden">Baris
                            <select v-model="filters.per_page" class="min-h-9 rounded-lg border border-brand-border bg-white px-2 text-sm text-brand-ink" @change="generateReport"><option :value="10">10</option><option :value="25">25</option><option :value="50">50</option><option :value="100">100</option></select>
                        </label>
                    </header>
                    <div v-if="table.rows.length" class="overflow-x-auto">
                        <table class="w-full min-w-[640px] border-collapse text-left text-sm">
                            <thead class="bg-brand-buff text-xs uppercase tracking-wide text-brand-muted"><tr><th class="w-12 px-4 py-3">#</th><th v-for="column in table.columns" :key="column.key" class="px-3 py-3" :class="column.format === 'currency' ? 'text-right' : ''">{{ column.label }}</th></tr></thead>
                            <tbody class="divide-y divide-brand-border">
                                <tr v-for="(row, rowIndex) in table.rows" :key="row.id ?? `${table.title}-${rowIndex}`" class="hover:bg-brand-buff/40">
                                    <td class="px-4 py-3 text-brand-muted">{{ (table.pagination.from ?? 1) + rowIndex }}</td>
                                    <td v-for="column in table.columns" :key="column.key" class="max-w-64 px-3 py-3" :class="[column.format === 'currency' ? 'whitespace-nowrap text-right tabular-nums' : '', column.key === 'status' && row.status === 'Stok menipis' ? 'font-semibold text-amber-800' : '']"><span class="block truncate" :title="String(displayValue(row[column.key], column))">{{ displayValue(row[column.key], column) }}</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="px-4 py-10 text-center text-sm text-brand-muted">Tidak ada data untuk periode yang dipilih.</p>
                    <footer v-if="tableIndex === 0 && table.pagination.last_page > 1" class="flex flex-col gap-3 border-t border-brand-border px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs text-brand-muted">Menampilkan {{ table.pagination.from }}–{{ table.pagination.to }} dari {{ table.pagination.total }}</p>
                        <nav aria-label="Halaman tabel laporan" class="flex items-center gap-2">
                            <button type="button" :disabled="table.pagination.current_page <= 1" class="min-h-9 rounded-lg border border-brand-border px-3 text-xs font-semibold disabled:opacity-40" @click="visitReportPage(table.pagination.current_page - 1)">Sebelumnya</button>
                            <span class="text-xs text-brand-muted">Halaman {{ table.pagination.current_page }} dari {{ table.pagination.last_page }}</span>
                            <button type="button" :disabled="table.pagination.current_page >= table.pagination.last_page" class="min-h-9 rounded-lg border border-brand-border px-3 text-xs font-semibold disabled:opacity-40" @click="visitReportPage(table.pagination.current_page + 1)">Berikutnya</button>
                        </nav>
                    </footer>
                </section>
            </template>

            <section v-else class="rounded-2xl border border-dashed border-brand-border bg-white px-5 py-12 text-center">
                <span class="mx-auto grid size-12 place-items-center rounded-2xl bg-brand-cream text-brand-burgundy"><AppIcon name="reports" /></span>
                <h2 class="mt-3 font-bold">Laporan akan muncul di sini</h2>
                <p class="mx-auto mt-1 max-w-md text-sm text-brand-muted">Pilih jenis laporan dan periode, atur filter, lalu buat laporan untuk melihat data bisnis.</p>
            </section>
        </main>
        <div v-if="toast" role="status" aria-live="polite" class="fixed bottom-5 left-4 right-4 z-[100] mx-auto max-w-md rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-900 shadow-lg">{{ toast }}</div>
    </AppLayout>
</template>
