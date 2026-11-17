<script setup>
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    customer: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'saved']);

const form = useForm({
    name: props.customer?.name ?? '',
    phone: props.customer?.phone ?? '',
    email: props.customer?.email ?? '',
    notes: props.customer?.notes ?? '',
});

function submit() {
    const options = {
        onSuccess: () => emit('saved'),
    };

    if (props.customer) {
        form.put(`/customers/${props.customer.id}`, options);
        return;
    }

    form.post('/customers', options);
}
</script>

<template>
    <div class="fixed inset-0 z-[70] flex items-end justify-center bg-brand-ink/35 backdrop-blur-[2px] sm:items-center sm:p-5" @click.self="emit('close')">
        <section
            role="dialog"
            aria-modal="true"
            :aria-label="customer ? 'Ubah pelanggan' : 'Tambah pelanggan'"
            class="max-h-[92svh] w-full overflow-y-auto rounded-t-3xl border border-brand-border bg-white shadow-[0_24px_48px_-8px_rgba(43,38,39,0.16)] sm:max-w-lg sm:rounded-2xl"
        >
            <form @submit.prevent="submit">
                <header class="sticky top-0 z-10 flex items-center justify-between border-b border-brand-border bg-white px-5 py-4">
                    <h2 class="text-lg font-semibold tracking-tight">{{ customer ? 'Ubah pelanggan' : 'Tambah pelanggan' }}</h2>
                    <button
                        type="button"
                        aria-label="Tutup"
                        class="flex size-10 items-center justify-center rounded-lg text-brand-muted hover:bg-brand-buff"
                        @click="emit('close')"
                    >
                        <AppIcon name="close" />
                    </button>
                </header>

                <div class="flex flex-col gap-4 p-5">
                    <div>
                        <label for="customer-name" class="mb-1.5 block text-sm font-semibold">Nama <span class="text-brand-burgundy">*</span></label>
                        <input id="customer-name" v-model="form.name" required maxlength="255" autocomplete="name" pattern="[\p{L}\p{M} ]+" title="Gunakan huruf dan spasi saja; angka dan simbol tidak diperbolehkan." class="min-h-11 w-full rounded-lg border border-brand-border bg-brand-buff px-3 py-2.5 text-base text-brand-ink outline-none transition focus:border-brand-burgundy focus:bg-white focus:ring-2 focus:ring-brand-burgundy/15" :aria-invalid="Boolean(form.errors.name)">
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-700">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label for="customer-phone" class="mb-1.5 block text-sm font-semibold">Telepon</label>
                        <input id="customer-phone" v-model="form.phone" maxlength="50" type="tel" inputmode="numeric" pattern="[0-9]*" title="Masukkan angka saja." autocomplete="tel" class="min-h-11 w-full rounded-lg border border-brand-border bg-brand-buff px-3 py-2.5 text-base text-brand-ink outline-none transition focus:border-brand-burgundy focus:bg-white focus:ring-2 focus:ring-brand-burgundy/15" :aria-invalid="Boolean(form.errors.phone)" @input="form.phone = $event.target.value.replace(/[^0-9]/g, '')">
                        <p v-if="form.errors.phone" class="mt-1 text-xs text-red-700">{{ form.errors.phone }}</p>
                    </div>

                    <div>
                        <label for="customer-email" class="mb-1.5 block text-sm font-semibold">Email</label>
                        <input id="customer-email" v-model="form.email" maxlength="255" type="email" autocomplete="email" class="min-h-11 w-full rounded-lg border border-brand-border bg-brand-buff px-3 py-2.5 text-base text-brand-ink outline-none transition focus:border-brand-burgundy focus:bg-white focus:ring-2 focus:ring-brand-burgundy/15" :aria-invalid="Boolean(form.errors.email)">
                        <p v-if="form.errors.email" class="mt-1 text-xs text-red-700">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <label for="customer-notes" class="mb-1.5 block text-sm font-semibold">Catatan</label>
                        <textarea id="customer-notes" v-model="form.notes" maxlength="10000" rows="4" class="min-h-11 w-full resize-y rounded-lg border border-brand-border bg-brand-buff px-3 py-2.5 text-base text-brand-ink outline-none transition focus:border-brand-burgundy focus:bg-white focus:ring-2 focus:ring-brand-burgundy/15" :aria-invalid="Boolean(form.errors.notes)" />
                        <p v-if="form.errors.notes" class="mt-1 text-xs text-red-700">{{ form.errors.notes }}</p>
                    </div>
                </div>

                <footer class="sticky bottom-0 flex justify-end gap-3 border-t border-brand-border bg-white px-5 py-4">
                    <button type="button" class="min-h-11 rounded-lg border border-brand-border px-4 text-sm font-semibold text-brand-ink hover:bg-brand-buff" @click="emit('close')">
                        Batal
                    </button>
                    <button type="submit" :disabled="form.processing" class="min-h-11 rounded-lg bg-brand-burgundy px-5 text-sm font-semibold text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.25)] hover:bg-brand-burgundy-dark disabled:opacity-60">
                        {{ form.processing ? 'Menyimpan…' : customer ? 'Simpan perubahan' : 'Tambah' }}
                    </button>
                </footer>
            </form>
        </section>
    </div>
</template>
