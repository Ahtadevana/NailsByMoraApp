<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppIcon from './AppIcon.vue';
import CustomerLookup from './CustomerLookup.vue';
import { createQuickRecord } from '../quickCreate.js';

const customerNamePattern = /^[\p{L}\p{M} ]+$/u;

const props = defineProps({
    appointment: {
        type: Object,
        default: null,
    },
    serviceCatalog: {
        type: Array,
        required: true,
    },
});

const emit = defineEmits(['close', 'saved']);
const selectedCustomer = ref(props.appointment?.customer ?? null);
const customerLookup = ref(null);
const serviceToAdd = ref('');
const serviceOptions = ref([...props.serviceCatalog]);
const quickCustomerOpen = ref(false);
const quickCustomerName = ref('');
const quickCustomerPhone = ref('');
const quickCustomerEmail = ref('');
const quickCustomerError = ref('');
const quickCustomerSaving = ref(false);
const quickServiceOpen = ref(false);
const quickServiceName = ref('');
const quickServicePrice = ref('');
const quickServiceDuration = ref('60');
const quickServiceError = ref('');
const quickServiceSaving = ref(false);
watch(() => props.serviceCatalog, (services) => { serviceOptions.value = [...services]; }, { deep: true });
const form = useForm({
    customer_id: props.appointment?.customer?.id ?? '',
    appointment_date: props.appointment?.date ?? new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10),
    start_time: props.appointment?.start_time ?? '10:00',
    status: props.appointment?.status ?? 'SCHEDULED',
    notes: props.appointment?.notes ?? '',
    services: (props.appointment?.services ?? []).map((service) => ({
        id: service.id,
        name: service.name,
        is_active: service.is_active,
        price: Number(service.price),
        duration_minutes: Number(service.duration_minutes),
    })),
});

const totalPrice = computed(() => form.services.reduce((total, service) => total + Number(service.price), 0));
const totalDuration = computed(() => form.services.reduce((total, service) => total + Number(service.duration_minutes), 0));
const scheduledTimeIsPast = computed(() => {
    if (form.status !== 'SCHEDULED' || !form.appointment_date || !form.start_time) return false;
    const [year, month, day] = form.appointment_date.split('-').map(Number);
    const [hour, minute] = form.start_time.split(':').map(Number);

    return new Date(year, month - 1, day, hour, minute) < new Date();
});
const today = new Date();
const minimumAppointmentDate = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
const minimumAppointmentTime = `${String(today.getHours()).padStart(2, '0')}:${String(today.getMinutes()).padStart(2, '0')}`;

function addService(serviceId = serviceToAdd.value) {
    const service = serviceOptions.value.find((item) => String(item.id) === String(serviceId));
    if (!service || form.services.some((item) => item.id === service.id)) return;

    form.services.push({
        id: service.id,
        name: service.name,
        is_active: true,
        price: Number(service.price),
        duration_minutes: Number(service.duration_minutes),
    });
    serviceToAdd.value = '';
}

async function createCustomer() {
    quickCustomerSaving.value = true;
    quickCustomerError.value = '';

    if (!customerNamePattern.test(quickCustomerName.value)) {
        quickCustomerError.value = 'Nama hanya boleh berisi huruf dan spasi; angka dan simbol tidak diperbolehkan.';
        quickCustomerSaving.value = false;
        return;
    }

    try {
        const result = await createQuickRecord('/appointments/customers/quick', {
            name: quickCustomerName.value,
            phone: quickCustomerPhone.value || null,
            email: quickCustomerEmail.value || null,
        });
        customerLookup.value?.selectCustomer(result.customer);
        selectedCustomer.value = result.customer;
        quickCustomerName.value = '';
        quickCustomerPhone.value = '';
        quickCustomerEmail.value = '';
        quickCustomerOpen.value = false;
    } catch (error) {
        quickCustomerError.value = error.message;
    } finally {
        quickCustomerSaving.value = false;
    }
}

async function createService() {
    quickServiceSaving.value = true;
    quickServiceError.value = '';

    try {
        const result = await createQuickRecord('/appointments/services/quick', {
            name: quickServiceName.value,
            price: quickServicePrice.value,
            duration_minutes: quickServiceDuration.value,
        });
        serviceOptions.value.push(result.service);
        addService(result.service.id);
        quickServiceName.value = '';
        quickServicePrice.value = '';
        quickServiceDuration.value = '60';
        quickServiceOpen.value = false;
    } catch (error) {
        quickServiceError.value = error.message;
    } finally {
        quickServiceSaving.value = false;
    }
}

function removeService(serviceId) {
    form.services = form.services.filter((service) => service.id !== serviceId);
}

function formatCurrency(value) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
}

function submit() {
    const options = { onSuccess: () => emit('saved') };
    form.transform((data) => ({
        ...data,
        services: data.services.map((service) => ({ id: service.id })),
    }));

    if (props.appointment) {
        form.put(`/appointments/${props.appointment.id}`, options);
        return;
    }

    form.post('/appointments', options);
}
</script>

<template>
    <div class="fixed inset-0 z-[70] flex items-end justify-center bg-brand-ink/35 backdrop-blur-[2px] sm:items-center sm:p-5" @click.self="emit('close')">
        <section role="dialog" aria-modal="true" :aria-label="appointment ? 'Ubah janji temu' : 'Janji temu baru'" class="flex max-h-[94svh] w-full flex-col rounded-t-3xl border border-brand-border bg-white shadow-[0_24px_48px_-8px_rgba(43,38,39,0.16)] sm:max-w-2xl sm:rounded-2xl">
            <header class="flex items-center justify-between border-b border-brand-border px-5 py-4">
                <div><p class="text-xs font-semibold uppercase tracking-[0.14em] text-brand-burgundy">Pemesanan janji temu</p><h2 class="mt-1 text-lg font-bold">{{ appointment ? 'Ubah janji temu' : 'Janji temu baru' }}</h2></div>
                <button type="button" aria-label="Tutup" class="flex size-10 items-center justify-center rounded-lg text-brand-muted hover:bg-brand-buff" @click="emit('close')"><AppIcon name="close" /></button>
            </header>

            <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="submit">
                <div class="flex-1 space-y-6 overflow-y-auto px-5 py-5">
                    <section>
                        <label class="mb-2 block text-sm font-semibold">Pelanggan <span class="text-brand-burgundy">*</span></label>
                        <CustomerLookup ref="customerLookup" v-model="form.customer_id" :initial-customer="selectedCustomer" @selected="selectedCustomer = $event" />
                        <button v-if="!quickCustomerOpen" type="button" class="mt-2 inline-flex min-h-8 items-center gap-1 text-xs font-semibold text-brand-burgundy hover:underline" @click="quickCustomerOpen = true"><AppIcon name="plus" class="size-3.5" />Tambah pelanggan baru</button>
                        <div v-else class="mt-2 flex flex-col gap-2 rounded-xl border border-brand-border bg-brand-cream p-3">
                            <input v-model="quickCustomerName" type="text" maxlength="255" pattern="[\p{L}\p{M} ]+" title="Gunakan huruf dan spasi saja; angka dan simbol tidak diperbolehkan." placeholder="Nama pelanggan *" class="min-h-10 rounded-lg border border-brand-border bg-white px-3 text-sm outline-none focus:border-brand-burgundy" @keydown.enter.prevent="createCustomer">
                            <div class="grid gap-2 sm:grid-cols-2"><input v-model="quickCustomerPhone" type="tel" inputmode="numeric" pattern="[0-9]*" title="Masukkan angka saja." maxlength="50" placeholder="Nomor telepon (opsional)" class="min-h-10 rounded-lg border border-brand-border bg-white px-3 text-sm outline-none focus:border-brand-burgundy" @input="quickCustomerPhone = $event.target.value.replace(/[^0-9]/g, '')"><input v-model="quickCustomerEmail" type="email" maxlength="255" placeholder="Email (opsional)" class="min-h-10 rounded-lg border border-brand-border bg-white px-3 text-sm outline-none focus:border-brand-burgundy"></div>
                            <div class="flex justify-end gap-2"><button type="button" class="min-h-9 rounded-lg border border-brand-border px-3 text-xs font-semibold" @click="quickCustomerOpen = false; quickCustomerError = ''">Batal</button><button type="button" :disabled="quickCustomerSaving || !quickCustomerName.trim()" class="min-h-9 rounded-lg bg-brand-burgundy px-3 text-xs font-semibold text-white disabled:opacity-50" @click="createCustomer">{{ quickCustomerSaving ? 'Menyimpan…' : 'Simpan pelanggan' }}</button></div>
                        </div>
                        <p v-if="quickCustomerError" role="alert" class="mt-1 text-xs text-red-700">{{ quickCustomerError }}</p>
                        <p v-if="form.errors.customer_id" class="mt-1 text-xs text-red-700">{{ form.errors.customer_id }}</p>
                    </section>

                    <section>
                        <h3 class="text-sm font-semibold">Jadwal</h3>
                        <div class="mt-2 grid grid-cols-2 gap-3">
                            <label class="flex flex-col gap-1.5 text-xs font-semibold text-brand-muted">Tanggal janji temu
                                <input v-model="form.appointment_date" type="date" :min="form.status === 'SCHEDULED' ? minimumAppointmentDate : undefined" required class="min-h-11 rounded-lg border border-brand-border bg-brand-buff px-3 text-base text-brand-ink outline-none focus:border-brand-burgundy">
                            </label>
                            <label class="flex flex-col gap-1.5 text-xs font-semibold text-brand-muted">Waktu mulai
                                <input v-model="form.start_time" type="time" :min="form.status === 'SCHEDULED' && form.appointment_date === minimumAppointmentDate ? minimumAppointmentTime : undefined" required class="min-h-11 rounded-lg border border-brand-border bg-brand-buff px-3 text-base text-brand-ink outline-none focus:border-brand-burgundy">
                            </label>
                        </div>
                        <p v-if="scheduledTimeIsPast" class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium leading-5 text-amber-900" role="alert">Janji temu berstatus terjadwal harus menggunakan waktu mendatang. Pilih waktu yang belum lewat atau ubah statusnya.</p>
                        <p v-if="form.errors.appointment_date || form.errors.start_time" class="mt-1 text-xs text-red-700">{{ form.errors.appointment_date || form.errors.start_time }}</p>
                        <label class="mt-3 flex flex-col gap-1.5 text-xs font-semibold text-brand-muted">Status
                            <select v-model="form.status" class="min-h-11 rounded-lg border border-brand-border bg-brand-buff px-3 text-base text-brand-ink outline-none focus:border-brand-burgundy">
                                <option value="SCHEDULED">Terjadwal</option><option value="COMPLETED">Selesai</option><option value="NO_SHOW">Tidak hadir</option><option value="CANCELLED">Dibatalkan</option>
                            </select>
                        </label>
                        <p v-if="form.errors.status" class="mt-1 text-xs text-red-700">{{ form.errors.status }}</p>
                    </section>

                    <section>
                        <div class="flex items-center justify-between gap-2">
                            <div><h3 class="text-sm font-semibold">Layanan <span class="text-brand-burgundy">*</span></h3><p class="mt-0.5 text-xs text-brand-muted">Harga dan durasi mengikuti saat pemesanan.</p></div>
                        </div>
                        <div v-if="form.services.length" class="mt-3 flex flex-col gap-2">
                            <article v-for="service in form.services" :key="service.id" class="flex items-center gap-3 rounded-xl border border-brand-border bg-brand-cream p-3">
                                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white text-brand-burgundy"><AppIcon name="services" class="size-4" /></div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold">{{ service.name }}<span v-if="!service.is_active" class="ml-1 text-xs font-medium text-brand-muted">(nonaktif)</span></p>
                                    <p class="mt-0.5 text-xs text-brand-muted">{{ formatCurrency(service.price) }} · {{ service.duration_minutes }} mnt</p>
                                </div>
                                <button type="button"                                 :aria-label="`Hapus ${service.name}`" class="flex size-9 shrink-0 items-center justify-center rounded-lg text-brand-muted hover:bg-red-50 hover:text-red-700" @click="removeService(service.id)"><AppIcon name="trash" class="size-4" /></button>
                            </article>
                        </div>
                        <div class="mt-3 flex gap-2">
                            <select v-model="serviceToAdd" class="min-h-11 min-w-0 flex-1 rounded-lg border border-brand-border bg-white px-3 text-sm outline-none focus:border-brand-burgundy">
                                <option value="">Pilih layanan untuk ditambahkan</option>
                                <option v-for="service in serviceOptions.filter((option) => !form.services.some((line) => line.id === option.id))" :key="service.id" :value="service.id">{{ service.name }} · {{ formatCurrency(service.price) }}</option>
                            </select>
                            <button type="button" :disabled="!serviceToAdd" class="flex min-h-11 shrink-0 items-center gap-1 rounded-lg border border-brand-border px-3 text-sm font-semibold text-brand-burgundy disabled:opacity-50" @click="addService()"><AppIcon name="plus" class="size-4" /><span class="hidden xs:inline">Tambah Layanan</span><span class="xs:hidden">Tambah</span></button>
                        </div>
                        <button v-if="!quickServiceOpen" type="button" class="mt-2 inline-flex min-h-8 items-center gap-1 text-xs font-semibold text-brand-burgundy hover:underline" @click="quickServiceOpen = true"><AppIcon name="plus" class="size-3.5" />Buat layanan baru</button>
                        <div v-else class="mt-2 flex flex-col gap-2 rounded-xl border border-brand-border bg-brand-cream p-3">
                            <input v-model="quickServiceName" type="text" maxlength="255" placeholder="Nama layanan *" class="min-h-10 rounded-lg border border-brand-border bg-white px-3 text-sm outline-none focus:border-brand-burgundy" @keydown.enter.prevent="createService">
                            <div class="grid gap-2 sm:grid-cols-2"><input v-model="quickServicePrice" type="number" min="0" step="0.01" placeholder="Harga (Rp) *" class="min-h-10 rounded-lg border border-brand-border bg-white px-3 text-sm outline-none focus:border-brand-burgundy"><input v-model="quickServiceDuration" type="number" min="1" step="1" placeholder="Durasi (menit) *" class="min-h-10 rounded-lg border border-brand-border bg-white px-3 text-sm outline-none focus:border-brand-burgundy"></div>
                            <div class="flex justify-end gap-2"><button type="button" class="min-h-9 rounded-lg border border-brand-border px-3 text-xs font-semibold" @click="quickServiceOpen = false; quickServiceError = ''">Batal</button><button type="button" :disabled="quickServiceSaving || !quickServiceName.trim() || quickServicePrice === '' || !quickServiceDuration" class="min-h-9 rounded-lg bg-brand-burgundy px-3 text-xs font-semibold text-white disabled:opacity-50" @click="createService">{{ quickServiceSaving ? 'Menyimpan…' : 'Simpan layanan' }}</button></div>
                        </div>
                        <p v-if="quickServiceError" role="alert" class="mt-1 text-xs text-red-700">{{ quickServiceError }}</p>
                        <p v-if="form.errors.services || form.errors['services.0.id']" class="mt-1 text-xs text-red-700">{{ form.errors.services || form.errors['services.0.id'] }}</p>
                        <div v-if="form.services.length" class="mt-3 flex justify-between rounded-xl bg-brand-buff px-3.5 py-3 text-sm">
                            <div><span class="font-semibold">Total</span><p class="mt-0.5 text-xs text-brand-muted">{{ totalDuration }} menit</p></div><span class="font-bold">{{ formatCurrency(totalPrice) }}</span>
                        </div>
                    </section>

                    <section>
                        <label for="appointment-notes" class="mb-1.5 block text-sm font-semibold">Catatan <span class="font-normal text-brand-muted">· opsional</span></label>
                        <textarea id="appointment-notes" v-model="form.notes" rows="3" maxlength="10000" placeholder="Tambahkan detail janji temu…" class="w-full resize-y rounded-lg border border-brand-border bg-brand-buff px-3 py-2.5 text-base outline-none focus:border-brand-burgundy" />
                        <p v-if="form.errors.notes" class="mt-1 text-xs text-red-700">{{ form.errors.notes }}</p>
                    </section>
                </div>
                <footer class="flex justify-end gap-3 border-t border-brand-border bg-white px-5 py-4">
                    <button type="button" class="min-h-11 rounded-lg border border-brand-border px-4 text-sm font-semibold hover:bg-brand-buff" @click="emit('close')">Batal</button>
                    <button type="submit" :disabled="form.processing || !form.customer_id || !form.services.length || scheduledTimeIsPast" class="min-h-11 rounded-lg bg-brand-burgundy px-5 text-sm font-semibold text-white hover:bg-brand-burgundy-dark disabled:opacity-60">{{ form.processing ? 'Menyimpan…' : appointment ? 'Simpan perubahan' : 'Tambah janji temu' }}</button>
                </footer>
            </form>
        </section>
    </div>
</template>
