<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppIcon from './AppIcon.vue';

const props = defineProps({
    item: { type: Object, required: true },
});
const emit = defineEmits(['close', 'saved']);
const form = useForm({ type: 'IN', quantity: '', reason: '', note: '' });
const currentMinor = computed(() => toMinor(props.item.quantity));
const requestedMinor = computed(() => toMinor(form.quantity));
const minimumMinor = computed(() => toMinor(props.item.minimum_stock));
const resultingMinor = computed(() => currentMinor.value + (form.type === 'IN' ? requestedMinor.value : -requestedMinor.value));
const insufficient = computed(() => form.type === 'OUT' && requestedMinor.value > currentMinor.value);
const valid = computed(() => requestedMinor.value > 0 && !insufficient.value && resultingMinor.value <= 9999999999 && Boolean(form.reason.trim()));

function toMinor(value) {
    const [whole = '0', fraction = ''] = String(value ?? '0').split('.');
    return (Number.parseInt(whole, 10) || 0) * 100
        + (Number.parseInt(fraction.padEnd(2, '0').slice(0, 2), 10) || 0);
}

function fromMinor(value) {
    const absolute = Math.abs(value);
    return `${value < 0 ? '-' : ''}${Math.floor(absolute / 100)}.${String(absolute % 100).padStart(2, '0')}`;
}

function formatQuantity(value) {
    return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value ?? 0));
}

function submit() {
    if (!valid.value || form.processing) return;
    form.post(`/inventory/${props.item.id}/adjustments`, {
        onSuccess: () => emit('saved'),
    });
}
</script>

<template>
    <div class="fixed inset-0 z-[70] flex items-end justify-center bg-brand-ink/35 backdrop-blur-[2px] sm:items-center sm:p-5" @click.self="emit('close')">
        <section role="dialog" aria-modal="true" aria-label="Sesuaikan stok inventaris" class="flex max-h-[94svh] w-full flex-col rounded-t-3xl border border-brand-border bg-white shadow-[0_24px_48px_-8px_rgba(43,38,39,0.16)] sm:max-w-lg sm:rounded-2xl">
            <header class="flex items-center justify-between border-b border-brand-border px-5 py-4">
                <div><p class="text-xs font-semibold uppercase tracking-[0.14em] text-brand-burgundy">Pergerakan stok</p><h2 class="mt-1 text-lg font-bold">Sesuaikan stok</h2></div>
                <button type="button" aria-label="Tutup" class="flex size-10 items-center justify-center rounded-lg text-brand-muted hover:bg-brand-buff" @click="emit('close')"><AppIcon name="close" /></button>
            </header>
            <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="submit">
                <div class="flex-1 space-y-4 overflow-y-auto px-5 py-5">
                    <div class="rounded-xl bg-brand-cream p-3.5">
                        <p class="font-semibold">{{ item.name }}</p>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-brand-muted">
                            <span>Saat ini: <strong class="text-brand-ink">{{ formatQuantity(item.quantity) }} {{ item.unit }}</strong></span>
                            <span>Minimum: <strong class="text-brand-ink">{{ formatQuantity(item.minimum_stock) }} {{ item.unit }}</strong></span>
                        </div>
                    </div>
                    <fieldset>
                        <legend class="mb-2 text-sm font-semibold">Jenis pergerakan <span class="text-brand-burgundy">*</span></legend>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" :aria-pressed="form.type === 'IN'" :class="form.type === 'IN' ? 'border-emerald-700 bg-emerald-50 text-emerald-800 ring-1 ring-emerald-700' : 'border-brand-border text-brand-muted'" class="flex min-h-12 items-center justify-center gap-2 rounded-xl border font-semibold" @click="form.type = 'IN'"><span class="text-lg">+</span>Stok masuk</button>
                            <button type="button" :aria-pressed="form.type === 'OUT'" :class="form.type === 'OUT' ? 'border-brand-burgundy bg-[#f7e9e8] text-brand-burgundy ring-1 ring-brand-burgundy' : 'border-brand-border text-brand-muted'" class="flex min-h-12 items-center justify-center gap-2 rounded-xl border font-semibold" @click="form.type = 'OUT'"><span class="text-lg">−</span>Stok keluar</button>
                        </div>
                    </fieldset>
                    <div>
                        <label for="stock-adjustment-quantity" class="mb-1.5 block text-sm font-semibold">Jumlah ({{ item.unit }}) <span class="text-brand-burgundy">*</span></label>
                        <input id="stock-adjustment-quantity" v-model="form.quantity" type="number" min="0.01" max="99999999.99" step="0.01" inputmode="decimal" required class="min-h-12 w-full rounded-lg border border-brand-border bg-white px-3 text-lg outline-none focus:border-brand-burgundy" :aria-invalid="Boolean(form.errors.quantity || insufficient)">
                        <p v-if="form.errors.quantity" role="alert" class="mt-1 text-sm font-medium text-red-700">{{ form.errors.quantity }}</p>
                        <p v-else-if="insufficient" role="alert" class="mt-1 text-sm font-medium text-red-700">Stok tidak mencukupi. Stok saat ini {{ formatQuantity(item.quantity) }} {{ item.unit }}.</p>
                    </div>
                    <div>
                        <label for="stock-adjustment-reason" class="mb-1.5 block text-sm font-semibold">Alasan <span class="text-brand-burgundy">*</span></label>
                        <input id="stock-adjustment-reason" v-model="form.reason" required maxlength="255" placeholder="mis. Pembelian baru, digunakan untuk janji temu" class="min-h-11 w-full rounded-lg border border-brand-border bg-brand-buff px-3 text-base outline-none focus:border-brand-burgundy">
                        <p v-if="form.errors.reason" class="mt-1 text-xs text-red-700">{{ form.errors.reason }}</p>
                    </div>
                    <div>
                        <label for="stock-adjustment-note" class="mb-1.5 block text-sm font-semibold">Catatan <span class="font-normal text-brand-muted">· opsional</span></label>
                        <textarea id="stock-adjustment-note" v-model="form.note" rows="2" maxlength="10000" class="w-full resize-y rounded-lg border border-brand-border bg-brand-buff px-3 py-2.5 text-base outline-none focus:border-brand-burgundy" />
                    </div>
                    <div class="rounded-xl border border-brand-border bg-brand-buff p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-brand-muted">Stok setelah penyesuaian</p>
                        <p class="mt-2 text-lg font-semibold">{{ formatQuantity(item.quantity) }} {{ form.type === 'IN' ? '+' : '−' }} {{ formatQuantity(form.quantity) }} {{ item.unit }}</p>
                        <p class="mt-1 text-2xl font-bold" :class="insufficient ? 'text-red-700' : resultingMinor < minimumMinor ? 'text-amber-800' : 'text-brand-ink'">= {{ fromMinor(resultingMinor) }} {{ item.unit }}</p>
                        <p v-if="!insufficient && resultingMinor < minimumMinor" role="status" class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-xs font-semibold leading-5 text-amber-900">Peringatan: Stok barang ini akan berada di bawah batas minimum. Penyesuaian tetap dapat dilakukan.</p>
                    </div>
                </div>
                <footer class="flex justify-end gap-3 border-t border-brand-border px-5 py-4">
                    <button type="button" class="min-h-11 rounded-lg border border-brand-border px-4 text-sm font-semibold hover:bg-brand-buff" @click="emit('close')">Batal</button>
                    <button type="submit" :disabled="!valid || form.processing" class="min-h-11 rounded-lg bg-brand-burgundy px-5 text-sm font-semibold text-white hover:bg-brand-burgundy-dark disabled:cursor-not-allowed disabled:opacity-50">{{ form.processing ? 'Mencatat…' : 'Konfirmasi penyesuaian' }}</button>
                </footer>
            </form>
        </section>
    </div>
</template>
