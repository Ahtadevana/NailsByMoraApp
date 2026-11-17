<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import logoUrl from '../../../images/LOGO.jpg';

const passwordVisible = ref(false);

const form = useForm({
    username: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <main class="flex min-h-svh flex-col bg-brand-cream px-5 pb-6 pt-10 text-brand-ink sm:px-8">
        <div class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center">
            <header class="mb-8 flex flex-col items-center text-center">
                <div class="mb-4 size-[4.5rem] overflow-hidden rounded-2xl shadow-[0_8px_24px_-8px_rgba(139,38,62,0.42)]">
                    <img :src="logoUrl" alt="NailsByMora" class="size-full object-cover">
                </div>
                <p class="text-[0.7rem] font-bold uppercase tracking-[0.3em] text-brand-burgundy">Manajemen studio kuku</p>
                <h1 class="mt-1 font-sans text-[2rem] font-bold leading-tight tracking-[-0.06em]">NailsByMora</h1>
                <p class="mt-1 text-sm text-brand-muted">Seni kecil, sepenuh hati.</p>
            </header>

            <section class="mb-5 flex items-center gap-4 rounded-2xl border border-brand-border bg-white p-4 shadow-[0_4px_20px_-2px_rgba(139,38,62,0.04),0_2px_6px_-1px_rgba(43,38,39,0.03)]">
                <div class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-brand-buff">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" class="size-8 text-brand-burgundy">
                        <path d="M8 11V5.75a1.25 1.25 0 0 1 2.5 0v4.5-5.5a1.25 1.25 0 0 1 2.5 0v5-4a1.25 1.25 0 0 1 2.5 0v5-2a1.25 1.25 0 0 1 2.5 0v6.5A5.75 5.75 0 0 1 12.25 21h-.5a5.75 5.75 0 0 1-4.6-2.3l-2.4-3.2a1.4 1.4 0 0 1 2.1-1.85L8 15V11Z" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" />
                        <path d="M3.5 7.5c.45-.9 1.1-1.55 2-2M2.75 11.5c.1-.65.3-1.25.6-1.8" stroke="currentColor" stroke-linecap="round" stroke-width="1.5" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <h2 class="text-base font-bold leading-6 tracking-[-0.02em]">Selamat datang kembali</h2>
                    <p class="mt-1 text-sm leading-5 text-brand-muted">Masuk untuk mengelola studio Anda.</p>
                </div>
            </section>

            <form
                class="rounded-2xl border border-brand-border bg-white p-5 shadow-[0_4px_20px_-2px_rgba(139,38,62,0.04),0_2px_6px_-1px_rgba(43,38,39,0.03)] sm:p-6"
                @submit.prevent="submit"
            >
                <div class="flex flex-col gap-5">
                    <div>
                        <label for="username" class="mb-1.5 block text-sm font-semibold text-brand-ink">
                            Nama pengguna atau email
                        </label>
                        <input
                            id="username"
                            v-model="form.username"
                            type="text"
                            name="username"
                            autocomplete="username"
                            autocapitalize="none"
                            spellcheck="false"
                            placeholder="Masukkan nama pengguna atau email"
                            required
                            :aria-invalid="Boolean(form.errors.username)"
                            :aria-describedby="form.errors.username || form.errors.password ? 'login-error' : undefined"
                            class="min-h-12 w-full rounded-lg border border-brand-border bg-brand-buff px-4 text-base text-brand-ink outline-none transition placeholder:text-brand-muted/70 focus:border-brand-burgundy focus:bg-white focus:ring-2 focus:ring-brand-burgundy/15"
                        >
                    </div>

                    <div>
                        <label for="password" class="mb-1.5 block text-sm font-semibold text-brand-ink">
                            Kata sandi
                        </label>
                        <div class="relative">
                            <input
                                id="password"
                                v-model="form.password"
                                :type="passwordVisible ? 'text' : 'password'"
                                name="password"
                                autocomplete="current-password"
                                placeholder="Masukkan kata sandi"
                                required
                                :aria-invalid="Boolean(form.errors.username || form.errors.password)"
                                :aria-describedby="form.errors.username || form.errors.password ? 'login-error' : undefined"
                                class="min-h-12 w-full rounded-lg border border-brand-border bg-brand-buff px-4 pr-16 text-base text-brand-ink outline-none transition placeholder:text-brand-muted/70 focus:border-brand-burgundy focus:bg-white focus:ring-2 focus:ring-brand-burgundy/15"
                            >
                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 flex min-w-12 items-center justify-center rounded-r-lg px-3 text-brand-burgundy focus-visible:outline-2 focus-visible:outline-offset-[-4px] focus-visible:outline-brand-burgundy"
                                :aria-label="passwordVisible ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                                :aria-pressed="passwordVisible"
                                @click="passwordVisible = !passwordVisible"
                            >
                                <svg v-if="!passwordVisible" aria-hidden="true" viewBox="0 0 24 24" fill="none" class="size-5">
                                    <path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" />
                                    <circle cx="12" cy="12" r="2.75" stroke="currentColor" stroke-width="1.7" />
                                </svg>
                                <svg v-else aria-hidden="true" viewBox="0 0 24 24" fill="none" class="size-5">
                                    <path d="m3 3 18 18M10.6 6.2A10 10 0 0 1 12 6c6.1 0 9.5 6 9.5 6a16 16 0 0 1-3 3.6M6.2 6.3C3.8 7.8 2.5 12 2.5 12s3.4 6 9.5 6c1.2 0 2.3-.25 3.3-.65" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <p
                        v-if="form.errors.username || form.errors.password"
                        id="login-error"
                        role="alert"
                        aria-live="polite"
                        class="rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-sm font-medium leading-5 text-red-800"
                    >
                        {{ form.errors.username || form.errors.password }}
                    </p>

                    <label for="remember" class="-mt-1 flex min-h-11 items-center gap-3 text-sm text-brand-muted">
                        <input
                            id="remember"
                            v-model="form.remember"
                            type="checkbox"
                            name="remember"
                            class="size-5 rounded border-brand-border accent-brand-burgundy focus:ring-2 focus:ring-brand-burgundy/20"
                        >
                        <span>Ingat akun ini</span>
                    </label>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="flex min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-brand-burgundy px-4 text-sm font-semibold text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.25)] transition hover:bg-brand-burgundy-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-burgundy active:scale-[0.99] disabled:cursor-wait disabled:opacity-70"
                    >
                        <span>{{ form.processing ? 'Sedang masuk…' : 'Masuk ke dasbor' }}</span>
                        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" class="size-4">
                            <path d="M4.2 10h11.6m-4.8-4.8 4.8 4.8-4.8 4.8" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" />
                        </svg>
                    </button>
                </div>
            </form>

            <p class="mt-5 text-center text-xs font-medium tracking-wide text-brand-muted">Versi 0.1.0</p>
        </div>

        <footer class="mx-auto mt-8 w-full max-w-md text-center text-[0.7rem] leading-5 text-brand-muted">
            &copy; {{ new Date().getFullYear() }} NailsByMora. Hak cipta dilindungi.
        </footer>
    </main>
</template>
