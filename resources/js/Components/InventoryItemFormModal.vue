<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppIcon from './AppIcon.vue';
import { createQuickRecord } from '../quickCreate.js';

const props = defineProps({
    item: { type: Object, default: null },
    categories: { type: Array, required: true },
});

const emit = defineEmits(['close', 'saved']);
const categoryOptions = ref([...props.categories]);
const quickCategoryOpen = ref(false);
const quickCategoryName = ref('');
const quickCategoryError = ref('');
const quickCategorySaving = ref(false);
watch(() => props.categories, (categories) => { categoryOptions.value = [...categories]; }, { deep: true });
const form = useForm({
    category_id: props.item?.category_id ?? '',
    name: props.item?.name ?? '',
    description: props.item?.description ?? '',
    unit: props.item?.unit ?? '',
    unit_cost: props.item?.unit_cost ?? '',
    minimum_stock: props.item?.minimum_stock ?? '0.00',
});

function submit() {
    const options = { onSuccess: () => emit('saved') };
    if (props.item) {
        form.put(`/inventory/${props.item.id}`, options);
        return;
    }
    form.post('/inventory', options);
}

async function createCategory() {
    quickCategorySaving.value = true;
    quickCategoryError.value = '';

    try {
        const result = await createQuickRecord('/inventory/categories/quick', {
            name: quickCategoryName.value,
            description: null,
        });
        categoryOptions.value.push(result.category);
        form.category_id = result.category.id;
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
        <section role="dialog" aria-modal="true" :aria-label="item ? 'Ubah barang inventaris' : 'Tambah barang inventaris'" class="flex max-h-[94svh] w-full flex-col rounded-t-3xl border border-brand-border bg-white shadow-[0_24px_48px_-8px_rgba(43,38,39,0.16)] sm:max-w-xl sm:rounded-2xl">
            <header class="flex items-center justify-between border-b border-brand-border px-5 py-4">
                <div><p class="text-xs font-semibold uppercase tracking-[0.14em] text-brand-burgundy">Barang inventaris</p><h2 class="mt-1 text-lg font-bold">{{ item ? 'Ubah detail barang' : 'Tambah barang inventaris' }}</h2></div>
                <button type="button" aria-label="Tutup" class="flex size-10 items-center justify-center rounded-lg text-brand-muted hover:bg-brand-buff" @click="emit('close')"><AppIcon name="close" /></button>
            </header>
            <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="submit">
                <div class="flex-1 space-y-4 overflow-y-auto px-5 py-5">
                    <div>
                        <label for="inventory-item-name" class="mb-1.5 block text-sm font-semibold">Nama barang <span class="text-brand-burgundy">*</span></label>
                        <input id="inventory-item-name" v-model="form.name" required maxlength="255" class="min-h-11 w-full rounded-lg border border-brand-border bg-brand-buff px-3 text-base outline-none focus:border-brand-burgundy">
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-700">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label for="inventory-category" class="mb-1.5 block text-sm font-semibold">Kategori <span class="text-brand-burgundy">*</span></label>
                        <select id="inventory-category" v-model="form.category_id" required class="min-h-11 w-full rounded-lg border border-brand-border bg-brand-buff px-3 text-base outline-none focus:border-brand-burgundy">
                            <option value="" disabled>Pilih kategori</option>
                            <option v-for="category in categoryOptions" :key="category.id" :value="category.id">{{ category.name }}</option>
                        </select>
                        <button v-if="!quickCategoryOpen" type="button" class="mt-2 inline-flex min-h-8 items-center gap-1 text-xs font-semibold text-brand-burgundy hover:underline" @click="quickCategoryOpen = true"><AppIcon name="plus" class="size-3.5" />Tambah kategori baru</button>
                        <div v-else class="mt-2 flex flex-col gap-2 rounded-xl border border-brand-border bg-brand-cream p-3 sm:flex-row">
                            <input v-model="quickCategoryName" type="text" maxlength="255" placeholder="Nama kategori baru" class="min-h-10 min-w-0 flex-1 rounded-lg border border-brand-border bg-white px-3 text-sm outline-none focus:border-brand-burgundy" @keydown.enter.prevent="createCategory">
                            <div class="flex gap-2"><button type="button" class="min-h-10 rounded-lg border border-brand-border px-3 text-xs font-semibold" @click="quickCategoryOpen = false; quickCategoryError = ''">Batal</button><button type="button" :disabled="quickCategorySaving || !quickCategoryName.trim()" class="min-h-10 rounded-lg bg-brand-burgundy px-3 text-xs font-semibold text-white disabled:opacity-50" @click="createCategory">{{ quickCategorySaving ? 'Menyimpan…' : 'Simpan kategori' }}</button></div>
                        </div>
                        <p v-if="quickCategoryError" role="alert" class="mt-1 text-xs text-red-700">{{ quickCategoryError }}</p>
                        <p v-if="!categoryOptions.length" class="mt-1 text-xs text-brand-muted">Buat kategori di sini sebelum menambahkan barang.</p>
                        <p v-if="form.errors.category_id" class="mt-1 text-xs text-red-700">{{ form.errors.category_id }}</p>
                    </div>
                    <div>
                        <label for="inventory-description" class="mb-1.5 block text-sm font-semibold">Deskripsi <span class="font-normal text-brand-muted">· opsional</span></label>
                        <textarea id="inventory-description" v-model="form.description" rows="3" maxlength="10000" class="w-full resize-y rounded-lg border border-brand-border bg-brand-buff px-3 py-2.5 text-base outline-none focus:border-brand-burgundy" />
                        <p v-if="form.errors.description" class="mt-1 text-xs text-red-700">{{ form.errors.description }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="inventory-unit" class="mb-1.5 block text-sm font-semibold">Satuan <span class="text-brand-burgundy">*</span></label>
                            <input id="inventory-unit" v-model="form.unit" required maxlength="50" placeholder="mis. botol, ml, kotak" class="min-h-11 w-full rounded-lg border border-brand-border bg-brand-buff px-3 text-base outline-none focus:border-brand-burgundy">
                            <p v-if="form.errors.unit" class="mt-1 text-xs text-red-700">{{ form.errors.unit }}</p>
                        </div>
                        <div>
                            <label for="inventory-cost" class="mb-1.5 block text-sm font-semibold">Harga satuan (Rp) <span class="text-brand-burgundy">*</span></label>
                            <input id="inventory-cost" v-model="form.unit_cost" required type="number" min="0" step="0.01" inputmode="decimal" class="min-h-11 w-full rounded-lg border border-brand-border bg-brand-buff px-3 text-base outline-none focus:border-brand-burgundy">
                            <p v-if="form.errors.unit_cost" class="mt-1 text-xs text-red-700">{{ form.errors.unit_cost }}</p>
                        </div>
                    </div>
                    <div>
                        <label for="inventory-minimum-stock" class="mb-1.5 block text-sm font-semibold">Stok minimum <span class="text-brand-burgundy">*</span></label>
                        <input id="inventory-minimum-stock" v-model="form.minimum_stock" required type="number" min="0" step="0.01" inputmode="decimal" class="min-h-11 w-full rounded-lg border border-brand-border bg-brand-buff px-3 text-base outline-none focus:border-brand-burgundy">
                        <p v-if="form.errors.minimum_stock" class="mt-1 text-xs text-red-700">{{ form.errors.minimum_stock }}</p>
                    </div>
                    <div class="flex items-start gap-3 rounded-xl border border-brand-border bg-brand-cream p-3.5">
                        <AppIcon name="inventory" class="mt-0.5 size-4 text-brand-burgundy" />
                        <p class="text-xs leading-5 text-brand-muted">{{ item ? 'Stok dikelola melalui Sesuaikan Stok dan tidak dapat diubah di sini.' : 'Barang baru dimulai dengan stok 0. Catat stok masuk melalui Sesuaikan Stok agar riwayatnya tercatat.' }}</p>
                    </div>
                </div>
                <footer class="flex justify-end gap-3 border-t border-brand-border px-5 py-4">
                    <button type="button" class="min-h-11 rounded-lg border border-brand-border px-4 text-sm font-semibold hover:bg-brand-buff" @click="emit('close')">Batal</button>
                    <button type="submit" :disabled="form.processing || !categoryOptions.length" class="min-h-11 rounded-lg bg-brand-burgundy px-5 text-sm font-semibold text-white hover:bg-brand-burgundy-dark disabled:opacity-60">{{ form.processing ? 'Menyimpan…' : item ? 'Simpan perubahan' : 'Tambah barang' }}</button>
                </footer>
            </form>
        </section>
    </div>
</template>
