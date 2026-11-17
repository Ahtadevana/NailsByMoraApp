<script setup>
import { useForm } from '@inertiajs/vue3';
import AppIcon from './AppIcon.vue';

const props = defineProps({
    category: { type: Object, default: null },
});
const emit = defineEmits(['close', 'saved']);
const form = useForm({
    name: props.category?.name ?? '',
    description: props.category?.description ?? '',
});

function submit() {
    const options = { onSuccess: () => emit('saved') };
    if (props.category) {
        form.put(`/expenses/categories/${props.category.id}`, options);
        return;
    }
    form.post('/expenses/categories', options);
}
</script>

<template>
    <div class="fixed inset-0 z-[70] flex items-end justify-center bg-brand-ink/35 backdrop-blur-[2px] sm:items-center sm:p-5" @click.self="emit('close')">
        <section role="dialog" aria-modal="true"         :aria-label="category ? 'Ubah kategori pengeluaran' : 'Tambah kategori pengeluaran'" class="w-full rounded-t-3xl border border-brand-border bg-white shadow-[0_24px_48px_-8px_rgba(43,38,39,0.16)] sm:max-w-lg sm:rounded-2xl">
            <form @submit.prevent="submit">
                <header class="flex items-center justify-between border-b border-brand-border px-5 py-4">
                    <h2 class="text-lg font-bold">{{ category ? 'Ubah kategori' : 'Tambah kategori' }}</h2>
                    <button type="button" aria-label="Tutup" class="flex size-10 items-center justify-center rounded-lg text-brand-muted hover:bg-brand-buff" @click="emit('close')"><AppIcon name="close" /></button>
                </header>
                <div class="flex flex-col gap-4 p-5">
                    <div>
                        <label for="expense-category-name" class="mb-1.5 block text-sm font-semibold">Nama kategori <span class="text-brand-burgundy">*</span></label>
                        <input id="expense-category-name" v-model="form.name" required maxlength="255" class="min-h-11 w-full rounded-lg border border-brand-border bg-brand-buff px-3 text-base outline-none focus:border-brand-burgundy" :aria-invalid="Boolean(form.errors.name)">
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-700">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label for="expense-category-description" class="mb-1.5 block text-sm font-semibold">Deskripsi <span class="font-normal text-brand-muted">· opsional</span></label>
                        <textarea id="expense-category-description" v-model="form.description" rows="3" maxlength="10000" class="w-full resize-y rounded-lg border border-brand-border bg-brand-buff px-3 py-2.5 text-base outline-none focus:border-brand-burgundy" :aria-invalid="Boolean(form.errors.description)" />
                        <p v-if="form.errors.description" class="mt-1 text-xs text-red-700">{{ form.errors.description }}</p>
                    </div>
                </div>
                <footer class="flex justify-end gap-3 border-t border-brand-border px-5 py-4">
                    <button type="button" class="min-h-11 rounded-lg border border-brand-border px-4 text-sm font-semibold hover:bg-brand-buff" @click="emit('close')">Batal</button>
                    <button type="submit" :disabled="form.processing" class="min-h-11 rounded-lg bg-brand-burgundy px-5 text-sm font-semibold text-white hover:bg-brand-burgundy-dark disabled:opacity-60">{{ form.processing ? 'Menyimpan…' : category ? 'Simpan perubahan' : 'Tambah kategori' }}</button>
                </footer>
            </form>
        </section>
    </div>
</template>
