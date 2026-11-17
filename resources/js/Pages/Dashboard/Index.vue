<script setup>
import { reactive, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppIcon from '../../Components/AppIcon.vue';
import ReportChart from '../../Components/ReportChart.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    period: { type: Object, required: true },
    financial: { type: Object, required: true },
    chart: { type: Object, required: true },
    todayAppointments: { type: Array, required: true },
    todayAppointmentCount: { type: Number, required: true },
    today: { type: String, required: true },
    attention: { type: Object, required: true },
    activity: { type: Object, required: true },
});

const filters = reactive({
    period: props.period.value,
    start_date: props.period.start_date,
    end_date: props.period.end_date,
});
const periodError = ref('');
const refreshing = ref(false);

watch(() => props.period, (period) => Object.assign(filters, {
    period: period.value,
    start_date: period.start_date,
    end_date: period.end_date,
}), { deep: true });

function formatCurrency(value) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
}

function changePeriod(value) {
    filters.period = value;
    periodError.value = '';
    if (value === 'custom') return;

    router.get(`/dashboard?period=${value}`, {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => { refreshing.value = true; },
        onFinish: () => { refreshing.value = false; },
    });
}

function applyCustomPeriod() {
    periodError.value = '';
    if (!filters.start_date || !filters.end_date) {
        periodError.value = 'Pilih tanggal mulai dan tanggal akhir.';
        return;
    }
    if (filters.start_date > filters.end_date) {
        periodError.value = 'Tanggal akhir harus sama atau setelah tanggal mulai.';
        return;
    }

    router.get('/dashboard', {
        period: 'custom',
        start_date: filters.start_date,
        end_date: filters.end_date,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => { refreshing.value = true; },
        onFinish: () => { refreshing.value = false; },
    });
}

function statusLabel(status) {
    return status === 'NO_SHOW' ? 'Tidak hadir' : status.charAt(0) + status.slice(1).toLowerCase();
}

function statusClass(status) {
    return {
        SCHEDULED: 'bg-blue-50 text-blue-700',
        COMPLETED: 'bg-emerald-50 text-emerald-700',
        CANCELLED: 'bg-brand-cream text-brand-muted',
        NO_SHOW: 'bg-rose-50 text-rose-700',
    }[status] ?? 'bg-brand-cream text-brand-muted';
}

function appointmentHref(id) {
    return `/appointments?date=${props.today}&open=${id}`;
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6 lg:gap-8 lg:px-8">
            <header class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand-burgundy">Ringkasan bisnis</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-brand-ink sm:text-3xl">Dasbor</h1>
                    <p class="mt-1 text-sm text-brand-muted">Ringkasan aktivitas studio Anda.</p>
                </div>
                <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                    <Link href="/appointments?create=1" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl bg-brand-burgundy px-3 text-sm font-semibold text-white transition hover:bg-brand-burgundy-dark">
                        <AppIcon name="plus" class="size-4" />Janji Temu Baru
                    </Link>
                    <Link href="/customers?create=1" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-brand-border bg-white px-3 text-sm font-semibold text-brand-ink transition hover:bg-brand-buff">
                        <AppIcon name="customers" class="size-4" />Tambah Pelanggan
                    </Link>
                    <Link href="/expenses?create=1" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-brand-border bg-white px-3 text-sm font-semibold text-brand-ink transition hover:bg-brand-buff">
                        <AppIcon name="expenses" class="size-4" />Tambah Pengeluaran
                    </Link>
                    <Link href="/inventory?low_stock=1" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-brand-border bg-white px-3 text-sm font-semibold text-brand-ink transition hover:bg-brand-buff">
                        <AppIcon name="inventory" class="size-4" />Sesuaikan Stok
                    </Link>
                </div>
            </header>

            <section aria-label="Periode dasbor" class="flex flex-col gap-4 rounded-2xl border border-brand-border bg-white p-4 shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)] sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-brand-muted">Periode terpilih</p>
                    <p class="mt-1 text-sm font-semibold text-brand-ink">{{ period.label }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        v-for="option in [{ value: 'today', label: 'Hari ini' }, { value: 'week', label: 'Minggu ini' }, { value: 'month', label: 'Bulan ini' }, { value: 'custom', label: 'Khusus' }]"
                        :key="option.value"
                        type="button"
                        class="min-h-10 rounded-lg px-3 text-sm font-semibold transition focus-visible:outline-2 focus-visible:outline-brand-burgundy"
                        :class="filters.period === option.value ? 'bg-[#f7e9e8] text-brand-burgundy' : 'text-brand-muted hover:bg-brand-cream'"
                        :aria-pressed="filters.period === option.value"
                        @click="changePeriod(option.value)"
                    >
                        {{ option.label }}
                    </button>
                    <span v-if="refreshing" class="text-xs text-brand-muted" role="status">Memperbarui…</span>
                </div>
                <form v-if="filters.period === 'custom'" class="flex flex-wrap items-end gap-2" @submit.prevent="applyCustomPeriod">
                    <label class="flex flex-col gap-1 text-xs font-medium text-brand-muted">
                        Tanggal mulai
                        <input v-model="filters.start_date" type="date" class="min-h-10 rounded-lg border border-brand-border bg-white px-2 text-sm text-brand-ink focus:border-brand-burgundy focus:outline-none">
                    </label>
                    <label class="flex flex-col gap-1 text-xs font-medium text-brand-muted">
                        Tanggal akhir
                        <input v-model="filters.end_date" type="date" class="min-h-10 rounded-lg border border-brand-border bg-white px-2 text-sm text-brand-ink focus:border-brand-burgundy focus:outline-none">
                    </label>
                    <button type="submit" class="min-h-10 rounded-lg bg-brand-ink px-4 text-sm font-semibold text-white hover:bg-brand-burgundy" :disabled="refreshing">
                        Terapkan
                    </button>
                    <p v-if="periodError" class="w-full text-xs font-medium text-rose-700" role="alert">{{ periodError }}</p>
                </form>
            </section>

            <section aria-label="Ringkasan bisnis" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-2xl border border-brand-border bg-white p-4 shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)] sm:p-5">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-brand-muted">Pendapatan</p>
                        <span class="grid size-9 place-items-center rounded-xl bg-[#f7e9e8] text-brand-burgundy"><AppIcon name="reports" /></span>
                    </div>
                    <p class="mt-4 text-xl font-bold tabular-nums text-brand-ink sm:text-2xl">{{ formatCurrency(financial.revenue) }}</p>
                    <p class="mt-1 text-xs text-brand-muted">Layanan janji temu yang selesai</p>
                </article>
                <article class="rounded-2xl border border-brand-border bg-white p-4 shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)] sm:p-5">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-brand-muted">Pengeluaran</p>
                        <span class="grid size-9 place-items-center rounded-xl bg-brand-cream text-brand-muted"><AppIcon name="expenses" /></span>
                    </div>
                    <p class="mt-4 text-xl font-bold tabular-nums text-brand-ink sm:text-2xl">{{ formatCurrency(financial.expenses) }}</p>
                    <p class="mt-1 text-xs text-brand-muted">Tercatat pada periode terpilih</p>
                </article>
                <article class="rounded-2xl border border-brand-border bg-white p-4 shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)] sm:p-5">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-brand-muted">Pendapatan bersih</p>
                        <span class="grid size-9 place-items-center rounded-xl bg-emerald-50 text-emerald-700"><AppIcon name="reports" /></span>
                    </div>
                    <p class="mt-4 text-xl font-bold tabular-nums sm:text-2xl" :class="financial.net_income >= 0 ? 'text-emerald-700' : 'text-rose-700'">{{ formatCurrency(financial.net_income) }}</p>
                    <p class="mt-1 text-xs text-brand-muted">Pendapatan dikurangi pengeluaran</p>
                </article>
                <article class="rounded-2xl border border-brand-border bg-white p-4 shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)] sm:p-5">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-brand-muted">Janji temu</p>
                        <span class="grid size-9 place-items-center rounded-xl bg-brand-cream text-brand-burgundy"><AppIcon name="appointments" /></span>
                    </div>
                    <p class="mt-4 text-2xl font-bold tabular-nums text-brand-ink">{{ financial.appointments.total }}</p>
                    <p class="mt-1 text-xs leading-5 text-brand-muted">
                        <span class="text-emerald-700">{{ financial.appointments.completed }} selesai</span>
                        <span class="px-1">·</span>{{ financial.appointments.scheduled }} terjadwal
                        <span class="px-1">·</span>{{ financial.appointments.cancelled }} dibatalkan
                        <span class="px-1">·</span>{{ financial.appointments.no_show }} tidak hadir
                    </p>
                </article>
            </section>

            <section class="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,1.7fr)_minmax(18rem,0.9fr)]">
                <div class="min-w-0">
                    <ReportChart :chart="chart" />
                    <div class="mt-3 flex justify-end">
                        <Link href="/reports" class="inline-flex items-center gap-1 text-sm font-semibold text-brand-burgundy hover:underline">
                            Lihat laporan lengkap <AppIcon name="chevron-right" class="size-4" />
                        </Link>
                    </div>
                </div>

                <section aria-labelledby="attention-title" class="rounded-2xl border border-brand-border bg-white p-4 shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)] sm:p-5">
                    <header class="flex items-start justify-between gap-3">
                        <div>
                            <h2 id="attention-title" class="text-base font-bold text-brand-ink">Perlu perhatian</h2>
                            <p class="mt-1 text-xs text-brand-muted">Status stok saat ini</p>
                        </div>
                        <Link href="/inventory?low_stock=1" class="shrink-0 text-xs font-semibold text-brand-burgundy hover:underline">Lihat Inventaris →</Link>
                    </header>
                    <p v-if="attention.low_stock_count === 0" class="mt-5 rounded-xl bg-emerald-50 px-3 py-4 text-sm font-medium text-emerald-800">
                        Stok semua barang aman.
                    </p>
                    <template v-else>
                        <p class="mt-4 rounded-xl bg-amber-50 px-3 py-3 text-sm font-semibold text-amber-900">
                            {{ attention.low_stock_count }} barang di bawah stok minimum
                        </p>
                        <ul class="mt-3 flex flex-col divide-y divide-brand-border">
                            <li v-for="item in attention.low_stock_items" :key="item.id" class="flex items-start justify-between gap-3 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-brand-ink">{{ item.name }}</p>
                                    <p class="mt-0.5 truncate text-xs text-brand-muted">{{ item.category }}</p>
                                </div>
                                <p class="shrink-0 text-right text-xs font-semibold tabular-nums text-amber-800">
                                    {{ item.quantity }} {{ item.unit }}
                                    <span class="block font-normal text-brand-muted">Min. {{ item.minimum_stock }}</span>
                                </p>
                            </li>
                        </ul>
                        <p v-if="attention.low_stock_count > attention.low_stock_items.length" class="mt-2 text-xs text-brand-muted">
                            Menampilkan {{ attention.low_stock_items.length }} dari {{ attention.low_stock_count }} barang dengan stok menipis.
                        </p>
                    </template>
                </section>
            </section>

            <section class="grid gap-5 xl:grid-cols-[minmax(0,1.5fr)_minmax(18rem,0.9fr)]">
                <section aria-labelledby="today-title" class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)]">
                    <header class="flex items-center justify-between gap-3 border-b border-brand-border px-4 py-4 sm:px-5">
                        <div>
                            <h2 id="today-title" class="font-bold text-brand-ink">Janji temu hari ini</h2>
                                <p class="mt-1 text-xs text-brand-muted">{{ todayAppointmentCount }} terjadwal hari ini</p>
                        </div>
                        <Link href="/appointments" class="shrink-0 text-xs font-semibold text-brand-burgundy hover:underline">Lihat Semua →</Link>
                    </header>
                    <ul v-if="todayAppointments.length" class="divide-y divide-brand-border">
                        <li v-for="appointment in todayAppointments" :key="appointment.id">
                            <Link :href="appointmentHref(appointment.id)" class="flex min-h-16 items-center gap-3 px-4 py-3 transition hover:bg-brand-cream/70 sm:px-5">
                                <span class="w-14 shrink-0 text-sm font-bold tabular-nums text-brand-burgundy">{{ appointment.start_time.slice(0, 5) }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold text-brand-ink">{{ appointment.customer }}</span>
                                    <span class="mt-0.5 block truncate text-xs text-brand-muted">{{ appointment.services.join(' · ') || 'Belum ada layanan' }}</span>
                                </span>
                                <span class="shrink-0 rounded-full px-2 py-1 text-[10px] font-semibold sm:text-xs" :class="statusClass(appointment.status)">
                                    {{ statusLabel(appointment.status) }}
                                </span>
                            </Link>
                        </li>
                    </ul>
                    <p v-else class="px-5 py-8 text-center text-sm text-brand-muted">Tidak ada janji temu hari ini.</p>
                </section>

                <section aria-labelledby="activity-title" class="rounded-2xl border border-brand-border bg-white p-4 shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)] sm:p-5">
                    <header class="flex items-center justify-between gap-2">
                        <div>
                            <h2 id="activity-title" class="font-bold text-brand-ink">Aktivitas bisnis</h2>
                            <p class="mt-1 text-xs text-brand-muted">Total pada periode terpilih</p>
                        </div>
                        <Link href="/customers" class="shrink-0 text-xs font-semibold text-brand-burgundy hover:underline">Pelanggan →</Link>
                    </header>
                    <dl class="mt-4 grid grid-cols-2 gap-3">
                        <div class="rounded-xl bg-brand-cream/70 p-3">
                            <dt class="text-xs text-brand-muted">Pelanggan</dt>
                            <dd class="mt-1 text-xl font-bold tabular-nums text-brand-ink">{{ activity.customers }}</dd>
                            <p class="mt-0.5 text-[11px] text-brand-muted">{{ activity.new_customers }} baru pada periode ini</p>
                        </div>
                        <div class="rounded-xl bg-brand-cream/70 p-3">
                            <dt class="text-xs text-brand-muted">Layanan aktif</dt>
                            <dd class="mt-1 text-xl font-bold tabular-nums text-brand-ink">{{ activity.active_services }}</dd>
                        </div>
                        <div class="rounded-xl bg-brand-cream/70 p-3">
                            <dt class="text-xs text-brand-muted">Barang inventaris</dt>
                            <dd class="mt-1 text-xl font-bold tabular-nums text-brand-ink">{{ activity.inventory_items }}</dd>
                        </div>
                        <div class="rounded-xl bg-brand-cream/70 p-3">
                            <dt class="text-xs text-brand-muted">Janji temu mendatang</dt>
                            <dd class="mt-1 text-xl font-bold tabular-nums text-brand-ink">{{ activity.upcoming_appointments }}</dd>
                        </div>
                    </dl>
                    <div class="mt-4 flex flex-wrap gap-x-4 gap-y-2 border-t border-brand-border pt-4">
                        <Link href="/appointments" class="text-xs font-semibold text-brand-burgundy hover:underline">Janji temu →</Link>
                        <Link href="/inventory" class="text-xs font-semibold text-brand-burgundy hover:underline">Inventaris →</Link>
                        <Link href="/expenses" class="text-xs font-semibold text-brand-burgundy hover:underline">Pengeluaran →</Link>
                    </div>
                </section>
            </section>
        </div>
    </AppLayout>
</template>
