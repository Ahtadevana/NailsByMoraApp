<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppIcon from '../../Components/AppIcon.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    admin: { type: Object, required: true },
});
const page = usePage();
const profileForm = useForm({
    name: props.admin.name,
    email: props.admin.email,
});
const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});
const toast = ref(null);
let toastTimer;

watch(() => page.props.flash?.toast, (message) => {
    if (!message) return;
    clearTimeout(toastTimer);
    toast.value = message;
    toastTimer = setTimeout(() => { toast.value = null; }, 4000);
});

onBeforeUnmount(() => clearTimeout(toastTimer));

function updateProfile() {
    profileForm.put('/settings', {
        preserveScroll: true,
    });
}

function updatePassword() {
    passwordForm.put('/settings/password', {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 px-4 py-6 sm:px-6 lg:py-10">
            <header>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand-burgundy">Akun</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-brand-ink sm:text-3xl">Pengaturan</h1>
                <p class="mt-1 text-sm text-brand-muted">Kelola informasi akun dan kata sandi secara terpisah.</p>
            </header>

            <section aria-labelledby="profile-title" class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)]">
                <div class="flex items-start gap-3 border-b border-brand-border px-4 py-4 sm:px-6 sm:py-5">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-[#f7e9e8] text-brand-burgundy"><AppIcon name="settings" /></span>
                    <div><h2 id="profile-title" class="font-bold text-brand-ink">Nama pengguna dan email</h2><p class="mt-1 text-sm leading-5 text-brand-muted">Perbarui identitas masuk tanpa mengubah kata sandi.</p></div>
                </div>
                <form class="flex flex-col gap-5 p-4 sm:p-6" @submit.prevent="updateProfile">
                    <label class="flex flex-col gap-1.5 text-sm font-semibold text-brand-ink">Nama pengguna
                        <input v-model="profileForm.name" type="text" name="name" autocomplete="username" required maxlength="255" class="min-h-11 rounded-xl border border-brand-border bg-white px-3 font-normal outline-none transition focus:border-brand-burgundy focus:ring-2 focus:ring-brand-burgundy/10" :aria-invalid="Boolean(profileForm.errors.name)">
                        <span v-if="profileForm.errors.name" class="text-xs font-medium text-rose-700">{{ profileForm.errors.name }}</span>
                    </label>
                    <label class="flex flex-col gap-1.5 text-sm font-semibold text-brand-ink">Email
                        <input v-model="profileForm.email" type="email" name="email" autocomplete="email" required maxlength="255" class="min-h-11 rounded-xl border border-brand-border bg-white px-3 font-normal outline-none transition focus:border-brand-burgundy focus:ring-2 focus:ring-brand-burgundy/10" :aria-invalid="Boolean(profileForm.errors.email)">
                        <span v-if="profileForm.errors.email" class="text-xs font-medium text-rose-700">{{ profileForm.errors.email }}</span>
                    </label>
                    <div class="flex justify-end border-t border-brand-border pt-4">
                        <button type="submit" :disabled="profileForm.processing" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-brand-burgundy px-5 text-sm font-semibold text-white transition hover:bg-brand-burgundy-dark disabled:cursor-wait disabled:opacity-60">
                            <AppIcon name="edit" class="size-4" />{{ profileForm.processing ? 'Menyimpan…' : 'Simpan profil' }}
                        </button>
                    </div>
                </form>
            </section>

            <section aria-labelledby="password-title" class="overflow-hidden rounded-2xl border border-brand-border bg-white shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)]">
                <div class="flex items-start gap-3 border-b border-brand-border px-4 py-4 sm:px-6 sm:py-5">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-[#f7e9e8] text-brand-burgundy"><AppIcon name="settings" /></span>
                    <div><h2 id="password-title" class="font-bold text-brand-ink">Ubah kata sandi</h2><p class="mt-1 text-sm leading-5 text-brand-muted">Perubahan ini tidak mengubah nama pengguna atau email.</p></div>
                </div>
                <form class="flex flex-col gap-5 p-4 sm:p-6" @submit.prevent="updatePassword">
                    <label class="flex flex-col gap-1.5 text-sm font-semibold text-brand-ink">Kata sandi saat ini
                        <input v-model="passwordForm.current_password" type="password" name="current_password" autocomplete="current-password" required class="min-h-11 rounded-xl border border-brand-border bg-white px-3 font-normal outline-none transition focus:border-brand-burgundy focus:ring-2 focus:ring-brand-burgundy/10" :aria-invalid="Boolean(passwordForm.errors.current_password)">
                        <span v-if="passwordForm.errors.current_password" class="text-xs font-medium text-rose-700">{{ passwordForm.errors.current_password }}</span>
                    </label>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="flex flex-col gap-1.5 text-sm font-semibold text-brand-ink"><span class="inline-flex items-baseline gap-1 whitespace-nowrap">Kata sandi baru <span class="font-normal text-brand-muted">· wajib</span></span>
                            <input v-model="passwordForm.password" type="password" name="password" autocomplete="new-password" minlength="8" required class="min-h-11 rounded-xl border border-brand-border bg-white px-3 font-normal outline-none transition focus:border-brand-burgundy focus:ring-2 focus:ring-brand-burgundy/10" :aria-invalid="Boolean(passwordForm.errors.password)">
                            <span v-if="passwordForm.errors.password" class="text-xs font-medium text-rose-700">{{ passwordForm.errors.password }}</span>
                        </label>
                        <label class="flex flex-col gap-1.5 text-sm font-semibold text-brand-ink">Konfirmasi kata sandi baru
                            <input v-model="passwordForm.password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required class="min-h-11 rounded-xl border border-brand-border bg-white px-3 font-normal outline-none transition focus:border-brand-burgundy focus:ring-2 focus:ring-brand-burgundy/10">
                        </label>
                    </div>
                    <div class="flex justify-end border-t border-brand-border pt-4">
                        <button type="submit" :disabled="passwordForm.processing" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-brand-burgundy px-5 text-sm font-semibold text-white transition hover:bg-brand-burgundy-dark disabled:cursor-wait disabled:opacity-60">
                            <AppIcon name="edit" class="size-4" />{{ passwordForm.processing ? 'Menyimpan…' : 'Ubah kata sandi' }}
                        </button>
                    </div>
                </form>
            </section>
        </div>

        <Transition enter-active-class="transition duration-200" enter-from-class="-translate-y-2 opacity-0" leave-active-class="transition duration-150" leave-to-class="-translate-y-2 opacity-0">
            <div v-if="toast" role="status" aria-live="polite" class="fixed right-4 top-20 z-[100] flex max-w-sm items-start gap-3 rounded-xl border border-emerald-200 bg-white px-4 py-3 text-sm font-medium text-emerald-800 shadow-lg sm:right-6">
                <span class="grid size-6 shrink-0 place-items-center rounded-full bg-emerald-50">✓</span>
                <span>{{ toast.message }}</span>
                <button type="button" aria-label="Tutup notifikasi" class="ml-2 text-emerald-700 hover:text-emerald-950" @click="toast = null"><AppIcon name="close" class="size-4" /></button>
            </div>
        </Transition>
    </AppLayout>
</template>
