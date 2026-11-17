<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppIcon from './AppIcon.vue';
import { createQuickRecord } from '../quickCreate.js';

const props = defineProps({
    expense: { type: Object, default: null },
    categories: { type: Array, required: true },
});
const emit = defineEmits(['close', 'saved']);
const categoryOptions = ref([...props.categories]);
const quickCategoryOpen = ref(false);
const quickCategoryName = ref('');
const quickCategoryError = ref('');
const quickCategorySaving = ref(false);
watch(() => props.categories, (categories) => { categoryOptions.value = [...categories]; }, { deep: true });
const paymentMethods = [
    { value: 'CASH', label: 'Tunai' },
    { value: 'BANK TRANSFER', label: 'Transfer bank' },
    { value: 'QRIS', label: 'QRIS' },
    { value: 'OTHER', label: 'Lainnya' },
];
const form = useForm({
    name: props.expense?.name ?? '',
    expense_category_id: props.expense?.expense_category_id ?? '',
    amount: props.expense?.amount ?? '',
    payment_method: props.expense?.payment_method ?? '',
    expense_date: props.expense?.expense_date ?? new Date().toISOString().slice(0, 10),
    note: props.expense?.note ?? '',
});

function submit() {
    const options = { onSuccess: () => emit('saved') };
    if (props.expense) {
        form.put(`/expenses/${props.expense.id}`, options);
        return;
    }
    form.post('/expenses', options);
}

async function createCategory() {
    quickCategorySaving.value = true;
    quickCategoryError.value = '';

    try {
        const result = await createQuickRecord('/expenses/categories/quick', {
            name: quickCategoryName.value,
            description: null,
        });
        categoryOptions.value.push(result.category);
        form.expense_category_id = result.category.id;
        quickCategoryName.value = '';
        quickCategoryOpen.value = false;
    } catch (error) {
        quickCategoryError.value = error.message;
    } finally {
        quickCategorySaving.value = false;
    }
}
</script>

<template>
    <div class="fixed inset-0 z-[70] flex items-end justify-center bg-brand-ink/35 backdrop-blur-[2px] sm:items-center sm:p-5" @click.self="emit('close')">
        <section role="dialog" aria-modal="true" :aria-label="expense ? 'Ubah pengeluaran' : 'Tambah pengeluaran'" class="flex max-h-[94svh] w-full flex-col rounded-t-3xl border border-brand-border bg-white shadow-[0_24px_48px_-8px_rgba(43,38,39,0.16)] sm:max-w-xl sm:rounded-2xl">
            <header class="flex items-center justify-between border-b border-brand-border px-5 py-4">
                <div><p class="text-xs font-semibold uppercase tracking-[0.14em] text-brand-burgundy">Data pengeluaran</p><h2 class="mt-1 text-lg font-bold">{{ expense ? 'Ubah pengeluaran' : 'Tambah pengeluaran' }}</h2></div>
                <button type="button" aria-label="Tutup" class="flex size-10 items-center justify-center rounded-lg text-brand-muted hover:bg-brand-buff" @click="emit('close')"><AppIcon name="close" /></button>
            </header>
            <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="submit">
                <div class="flex-1 space-y-4 overflow-y-auto px-5 py-5">
                    <div>
                        <label for="expense-name" class="mb-1.5 block text-sm font-semibold">Nama pengeluaran <span class="text-brand-burgundy">*</span></label>
                        <input id="expense-name" v-model="form.name" required maxlength="255" autocomplete="off" class="min-h-11 w-full rounded-lg border border-brand-border bg-brand-buff px-3 text-base outline-none focus:border-brand-burgundy" :aria-invalid="Boolean(form.errors.name)">
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-700">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label for="expense-category" class="mb-1.5 block text-sm font-semibold">Kategori <span class="text-brand-burgundy">*</span></label>
                        <select id="expense-category" v-model="form.expense_category_id" required class="min-h-11 w-full rounded-lg border border-brand-border bg-brand-buff px-3 text-base outline-none focus:border-brand-burgundy" :aria-invalid="Boolean(form.errors.expense_category_id)">
                            <option value="" disabled>Pilih kategori</option>
                            <option v-for="category in categoryOptions" :key="category.id" :value="category.id">{{ category.name }}</option>
                        </select>
                        <button v-if="!quickCategoryOpen" type="button" class="mt-2 inline-flex min-h-8 items-center gap-1 text-xs font-semibold text-brand-burgundy hover:underline" @click="quickCategoryOpen = true"><AppIcon name="plus" class="size-3.5" />Tambah kategori baru</button>
                        <div v-else class="mt-2 flex flex-col gap-2 rounded-xl border border-brand-border bg-brand-cream p-3 sm:flex-row">
                            <input v-model="quickCategoryName" type="text" maxlength="255" placeholder="Nama kategori baru" class="min-h-10 min-w-0 flex-1 rounded-lg border border-brand-border bg-white px-3 text-sm outline-none focus:border-brand-burgundy" @keydown.enter.prevent="createCategory">
                            <div class="flex gap-2"><button type="button" class="min-h-10 rounded-lg border border-brand-border px-3 text-xs font-semibold" @click="quickCategoryOpen = false; quickCategoryError = ''">Batal</button><button type="button" :disabled="quickCategorySaving || !quickCategoryName.trim()" class="min-h-10 rounded-lg bg-brand-burgundy px-3 text-xs font-semibold text-white disabled:opacity-50" @click="createCategory">{{ quickCategorySaving ? 'Menyimpan…' : 'Simpan kategori' }}</button></div>
                        </div>
                        <p v-if="quickCategoryError" role="alert" class="mt-1 text-xs text-red-700">{{ quickCategoryError }}</p>
                        <p v-if="!categoryOptions.length" class="mt-1 text-xs text-brand-muted">Buat kategori di sini sebelum mencatat pengeluaran.</p>
                        <p v-if="form.errors.expense_category_id" class="mt-1 text-xs text-red-700">{{ form.errors.expense_category_id }}</p>
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="expense-amount" class="mb-1.5 block text-sm font-semibold">Jumlah (Rp) <span class="text-brand-burgundy">*</span></label>
                            <input id="expense-amount" v-model="form.amount" required type="number" min="0.01" step="0.01" inputmode="decimal" class="min-h-11 w-full rounded-lg border border-brand-border bg-brand-buff px-3 text-base outline-none focus:border-brand-burgundy" :aria-invalid="Boolean(form.errors.amount)">
                            <p v-if="form.errors.amount" class="mt-1 text-xs text-red-700">{{ form.errors.amount }}</p>
                        </div>
                        <div>
                            <label for="expense-method" class="mb-1.5 block text-sm font-semibold">Metode pembayaran <span class="text-brand-burgundy">*</span></label>
                            <select id="expense-method" v-model="form.payment_method" required class="min-h-11 w-full rounded-lg border border-brand-border bg-brand-buff px-3 text-base outline-none focus:border-brand-burgundy" :aria-invalid="Boolean(form.errors.payment_method)">
                                <option value="" disabled>Pilih metode</option>
                                <option v-for="method in paymentMethods" :key="method.value" :value="method.value">{{ method.label }}</option>
                            </select>
                            <p v-if="form.errors.payment_method" class="mt-1 text-xs text-red-700">{{ form.errors.payment_method }}</p>
                        </div>
                    </div>
                    <div>
                        <label for="expense-date" class="mb-1.5 block text-sm font-semibold">Tanggal pengeluaran <span class="text-brand-burgundy">*</span></label>
                        <input id="expense-date" v-model="form.expense_date" required type="date" class="min-h-11 w-full rounded-lg border border-brand-border bg-brand-buff px-3 text-base outline-none focus:border-brand-burgundy" :aria-invalid="Boolean(form.errors.expense_date)">
                        <p v-if="form.errors.expense_date" class="mt-1 text-xs text-red-700">{{ form.errors.expense_date }}</p>
                    </div>
                    <div>
                        <label for="expense-note" class="mb-1.5 block text-sm font-semibold">Catatan <span class="font-normal text-brand-muted">· opsional</span></label>
                        <textarea id="expense-note" v-model="form.note" rows="3" maxlength="10000" class="w-full resize-y rounded-lg border border-brand-border bg-brand-buff px-3 py-2.5 text-base outline-none focus:border-brand-burgundy" :aria-invalid="Boolean(form.errors.note)" />
                        <p v-if="form.errors.note" class="mt-1 text-xs text-red-700">{{ form.errors.note }}</p>
                    </div>
                </div>
                <footer class="flex justify-end gap-3 border-t border-brand-border px-5 py-4">
                    <button type="button" class="min-h-11 rounded-lg border border-brand-border px-4 text-sm font-semibold hover:bg-brand-buff" @click="emit('close')">Batal</button>
                    <button type="submit" :disabled="form.processing || !categoryOptions.length" class="min-h-11 rounded-lg bg-brand-burgundy px-5 text-sm font-semibold text-white hover:bg-brand-burgundy-dark disabled:opacity-60">{{ form.processing ? 'Menyimpan…' : expense ? 'Simpan perubahan' : 'Tambah pengeluaran' }}</button>
                </footer>
            </form>
        </section>
    </div>
</template>
