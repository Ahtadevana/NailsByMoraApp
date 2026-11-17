<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from './AppIcon.vue';

const props = defineProps({
    modelValue: {
        type: [Number, String],
        default: '',
    },
    initialCustomer: {
        type: Object,
        default: null,
    },
    placeholder: {
        type: String,
        default: 'Cari pelanggan lewat nama, telepon, atau email',
    },
});

const emit = defineEmits(['update:modelValue', 'selected']);
const selectedCustomer = ref(props.initialCustomer);
const searchTerm = ref(props.initialCustomer?.name ?? '');
const customers = ref([]);
const loading = ref(false);
const lookupError = ref('');
const listOpen = ref(false);
let searchTimer;
let activeRequest;

watch(() => props.initialCustomer, (customer) => {
    if (customer && String(customer.id) === String(props.modelValue)) {
        selectedCustomer.value = customer;
        searchTerm.value = customer.name;
    } else if (!props.modelValue && selectedCustomer.value && searchTerm.value === selectedCustomer.value.name) {
        selectedCustomer.value = null;
        searchTerm.value = '';
    }
}, { deep: true });

watch(searchTerm, (value) => {
    if (selectedCustomer.value && value === selectedCustomer.value.name) {
        return;
    }

    selectedCustomer.value = null;
    emit('update:modelValue', '');
    emit('selected', null);
    lookupError.value = '';
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadCustomers(value), 250);
});

onBeforeUnmount(() => {
    clearTimeout(searchTimer);
    activeRequest?.abort();
});

async function loadCustomers(search = '') {
    activeRequest?.abort();
    activeRequest = new AbortController();
    loading.value = true;
    lookupError.value = '';

    try {
        const params = new URLSearchParams();
        if (search.trim()) params.set('search', search.trim());
        const response = await fetch(`/appointments/lookups?${params.toString()}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            signal: activeRequest.signal,
        });

        if (!response.ok) {
            throw new Error('Daftar pelanggan gagal dimuat. Silakan coba lagi.');
        }

        customers.value = (await response.json()).customers;
    } catch (error) {
        if (error.name !== 'AbortError') {
            lookupError.value = error.message || 'Daftar pelanggan gagal dimuat.';
        }
    } finally {
        loading.value = false;
    }
}

function selectCustomer(customer) {
    selectedCustomer.value = customer;
    searchTerm.value = customer.name;
    emit('update:modelValue', customer.id);
    emit('selected', customer);
    listOpen.value = false;
}

function clearCustomer() {
    selectedCustomer.value = null;
    searchTerm.value = '';
    customers.value = [];
    emit('update:modelValue', '');
    emit('selected', null);
    listOpen.value = true;
    loadCustomers();
}

defineExpose({ selectCustomer });
</script>

<template>
    <div class="relative">
        <label class="flex min-h-12 items-center gap-2 rounded-lg border border-brand-border bg-brand-buff px-3 focus-within:border-brand-burgundy focus-within:bg-white focus-within:ring-2 focus-within:ring-brand-burgundy/15">
            <AppIcon name="search" class="size-4 text-brand-muted" />
            <input
                v-model="searchTerm"
                type="search"
                :placeholder="placeholder"
                class="w-full bg-transparent text-base text-brand-ink outline-none placeholder:text-brand-muted/80"
                :aria-expanded="listOpen"
                aria-haspopup="listbox"
                @focus="listOpen = true; loadCustomers(searchTerm)"
                @keydown.esc="listOpen = false"
            >
            <button v-if="selectedCustomer" type="button" aria-label="Hapus pilihan pelanggan" class="flex size-8 items-center justify-center rounded-md text-brand-muted hover:bg-white" @click="clearCustomer">
                <AppIcon name="close" class="size-4" />
            </button>
        </label>

        <div v-if="listOpen" class="absolute inset-x-0 top-full z-30 mt-1 max-h-56 overflow-y-auto rounded-xl border border-brand-border bg-white p-1 shadow-lg" role="listbox" aria-label="Daftar pelanggan">
            <p v-if="loading" class="px-3 py-3 text-sm text-brand-muted">Mencari pelanggan…</p>
            <p v-else-if="lookupError" role="alert" class="px-3 py-3 text-sm text-red-700">{{ lookupError }}</p>
            <p v-else-if="customers.length === 0" class="px-3 py-3 text-sm text-brand-muted">Pelanggan tidak ditemukan.</p>
            <button
                v-for="customer in customers"
                :key="customer.id"
                type="button"
                role="option"
                :aria-selected="selectedCustomer?.id === customer.id"
                class="flex w-full flex-col rounded-lg px-3 py-2.5 text-left hover:bg-brand-buff"
                @click="selectCustomer(customer)"
            >
                <span class="text-sm font-semibold">{{ customer.name }}</span>
                <span class="mt-0.5 text-xs text-brand-muted">{{ customer.phone || customer.email || 'Tidak ada info kontak' }}</span>
            </button>
            <button type="button" class="w-full border-t border-brand-border px-3 py-2 text-xs font-medium text-brand-muted hover:text-brand-ink" @click="listOpen = false">Tutup</button>
        </div>
    </div>
</template>
