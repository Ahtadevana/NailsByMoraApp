<script setup>
import { useForm } from '@inertiajs/vue3';
import AppIcon from './AppIcon.vue';

const props = defineProps({
    service: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'saved']);

const form = useForm({
    name: props.service?.name ?? '',
    description: props.service?.description ?? '',
    price: props.service?.price ?? '',
    duration_minutes: props.service?.duration_minutes ?? '',
    is_active: props.service?.is_active ?? true,
});

function submit() {
    const options = {
        onSuccess: () => emit('saved'),
    };

    if (props.service) {
        form.put(`/services/${props.service.id}`, options);
        return;
    }

    form.post('/services', options);
}
</script>

<template>
    <div class="fixed inset-0 z-[70] flex items-end justify-center bg-brand-ink/35 backdrop-blur-[2px] sm:items-center sm:p-5" @click.self="emit('close')">
        <section
            role="dialog"
            aria-modal="true"
            :aria-label="service ? 'Ubah layanan' : 'Tambah layanan'"
            class="max-h-[92svh] w-full overflow-y-auto rounded-t-3xl border border-brand-border bg-white shadow-[0_24px_48px_-8px_rgba(43,38,39,0.16)] sm:max-w-lg sm:rounded-2xl"
        >
            <form @submit.prevent="submit">
                <header class="sticky top-0 z-10 flex items-center justify-between border-b border-brand-border bg-white px-5 py-4">
                    <h2 class="text-lg font-semibold tracking-tight">{{ service ? 'Ubah layanan' : 'Tambah layanan' }}</h2>
                    <button type="button" aria-label="Tutup" class="flex size-10 items-center justify-center rounded-lg text-brand-muted hover:bg-brand-buff" @click="emit('close')">
                        <AppIcon name="close" />
                    </button>
                </header>

                <div class="flex flex-col gap-4 p-5">
                    <div>
                        <label for="service-name" class="mb-1.5 block text-sm font-semibold">Nama <span class="text-brand-burgundy">*</span></label>
                        <input id="service-name" v-model="form.name" required maxlength="255" class="min-h-11 w-full rounded-lg border border-brand-border bg-brand-buff px-3 py-2.5 text-base text-brand-ink outline-none transition placeholder:text-brand-muted/70 focus:border-brand-burgundy focus:bg-white focus:ring-2 focus:ring-brand-burgundy/15" :aria-invalid="Boolean(form.errors.name)">
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-700">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label for="service-description" class="mb-1.5 block text-sm font-semibold">Deskripsi</label>
                        <textarea id="service-description" v-model="form.description" maxlength="10000" rows="3" class="min-h-11 w-full resize-y rounded-lg border border-brand-border bg-brand-buff px-3 py-2.5 text-base text-brand-ink outline-none transition placeholder:text-brand-muted/70 focus:border-brand-burgundy focus:bg-white focus:ring-2 focus:ring-brand-burgundy/15" :aria-invalid="Boolean(form.errors.description)" />
                        <p v-if="form.errors.description" class="mt-1 text-xs text-red-700">{{ form.errors.description }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="service-price" class="mb-1.5 block text-sm font-semibold">Harga (Rp) <span class="text-brand-burgundy">*</span></label>
                            <input id="service-price" v-model="form.price" required type="number" min="0" step="0.01" inputmode="decimal" class="min-h-11 w-full rounded-lg border border-brand-border bg-brand-buff px-3 py-2.5 text-base text-brand-ink outline-none transition placeholder:text-brand-muted/70 focus:border-brand-burgundy focus:bg-white focus:ring-2 focus:ring-brand-burgundy/15" :aria-invalid="Boolean(form.errors.price)">
                            <p v-if="form.errors.price" class="mt-1 text-xs text-red-700">{{ form.errors.price }}</p>
                        </div>
                        <div>
                            <label for="service-duration" class="mb-1.5 block text-sm font-semibold">Durasi (mnt) <span class="text-brand-burgundy">*</span></label>
                            <input id="service-duration" v-model="form.duration_minutes" required type="number" min="1" step="1" inputmode="numeric" class="min-h-11 w-full rounded-lg border border-brand-border bg-brand-buff px-3 py-2.5 text-base text-brand-ink outline-none transition placeholder:text-brand-muted/70 focus:border-brand-burgundy focus:bg-white focus:ring-2 focus:ring-brand-burgundy/15" :aria-invalid="Boolean(form.errors.duration_minutes)">
                            <p v-if="form.errors.duration_minutes" class="mt-1 text-xs text-red-700">{{ form.errors.duration_minutes }}</p>
                        </div>
                    </div>

                    <label class="flex min-h-11 items-center gap-3 text-sm font-medium">
                        <input v-model="form.is_active" type="checkbox" class="size-5 rounded accent-brand-burgundy">
                        <span>Layanan aktif dan tersedia untuk janji temu</span>
                    </label>
                </div>

                <footer class="sticky bottom-0 flex justify-end gap-3 border-t border-brand-border bg-white px-5 py-4">
                    <button type="button" class="min-h-11 rounded-lg border border-brand-border px-4 text-sm font-semibold text-brand-ink hover:bg-brand-buff" @click="emit('close')">Batal</button>
                    <button type="submit" :disabled="form.processing" class="min-h-11 rounded-lg bg-brand-burgundy px-5 text-sm font-semibold text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.25)] hover:bg-brand-burgundy-dark disabled:opacity-60">
                        {{ form.processing ? 'Menyimpan…' : service ? 'Simpan perubahan' : 'Tambah' }}
                    </button>
                </footer>
            </form>
        </section>
    </div>
</template>
