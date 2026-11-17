<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

const artServices = [
    { name: 'Simple', price: 'Rp3.000', detail: 'per jari' },
    { name: 'Cat Eye', price: 'Rp3.500', detail: 'per jari' },
    { name: '4D Art', price: 'Rp8.000–25.000', detail: 'per jari' },
    { name: '3D Chrome', price: 'Rp3.000–15.000', detail: 'per jari' },
    { name: 'Aksesori', price: 'Rp3.000–25.000', detail: 'per jari' },
    { name: 'Hard Design', price: 'Rp8.000–30.000', detail: 'per jari' },
];

const removalServices = [
    { name: 'Hapus gel', price: 'Rp3.500', detail: 'per jari' },
    { name: 'Hapus extension', price: 'Rp5.000', detail: 'per jari' },
    { name: 'Hapus gel dari salon lain', price: 'Rp4.000', detail: 'per jari' },
    { name: 'Hapus extension dari salon lain', price: 'Rp6.000', detail: 'per jari' },
];
const showScrollTop = ref(false);

function updateScrollTopVisibility() {
    showScrollTop.value = window.scrollY > 400;
}

function scrollBehavior() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';
}

function scrollToSection(id) {
    document.getElementById(id)?.scrollIntoView({ behavior: scrollBehavior() });
}

function scrollToTop() {
    window.scrollTo({ top: 0, behavior: scrollBehavior() });
}

onMounted(() => window.addEventListener('scroll', updateScrollTopVisibility, { passive: true }));
onBeforeUnmount(() => window.removeEventListener('scroll', updateScrollTopVisibility));

function bookingUrl(service) {
    const message = `Halo NailsByMora, saya ingin tanya tentang ${service}.`;

    return `https://wa.me/62895413977686?text=${encodeURIComponent(message)}`;
}
</script>

<template>
    <main class="min-h-svh overflow-hidden bg-[#fcf8f5] text-[#30272a]">
        <header class="relative z-10 mx-auto flex max-w-7xl items-center justify-between px-5 py-5 sm:px-8 lg:px-12">
            <a href="#beranda" class="font-serif text-xl font-semibold tracking-[-0.04em] sm:text-2xl" @click.prevent="scrollToSection('beranda')">
                NailsByMora<span class="text-[#a5415a]">.</span>
            </a>
            <nav aria-label="Navigasi katalog" class="flex items-center gap-5 text-sm font-medium text-[#71656a] sm:gap-8">
                <a href="#layanan" class="transition hover:text-[#8b263e]" @click.prevent="scrollToSection('layanan')">Layanan</a>
                <a href="#tentang" class="hidden transition hover:text-[#8b263e] sm:inline" @click.prevent="scrollToSection('tentang')">Tentang</a>
                <a
                    :href="bookingUrl('layanan kuku')"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="rounded-full bg-[#8b263e] px-4 py-2.5 text-xs font-semibold text-white transition hover:bg-[#731e32] sm:px-5 sm:text-sm"
                >
                    Buat janji
                </a>
            </nav>
        </header>

        <section id="beranda" class="mx-auto grid max-w-7xl items-center gap-10 px-5 pb-16 pt-5 sm:px-8 sm:pb-20 lg:grid-cols-[1fr_0.92fr] lg:gap-16 lg:px-12 lg:pb-28 lg:pt-12">
            <div class="relative z-10">
                <p class="mb-5 flex items-center gap-2 text-[0.68rem] font-bold uppercase tracking-[0.24em] text-[#a5415a] sm:text-xs">
                    <span class="size-1.5 rounded-full bg-[#a5415a]"></span>
                    Nail studio · Malang
                </p>
                <h1 class="max-w-2xl font-serif text-[3.3rem] font-medium leading-[0.99] tracking-[-0.065em] sm:text-6xl lg:text-[5.3rem]">
                    Detail kecil,<br>
                    <span class="text-[#a5415a] italic">rasa istimewa.</span>
                </h1>
                <p class="mt-6 max-w-md text-sm leading-7 text-[#71656a] sm:text-base sm:leading-8">
                    Rawat kuku, pilih warna, dan ceritakan desain yang kamu suka. Kami bantu mewujudkannya dengan teliti.
                </p>
                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <a
                        href="#layanan"
                        @click.prevent="scrollToSection('layanan')"
                        class="rounded-full bg-[#30272a] px-6 py-3.5 text-sm font-semibold text-white transition hover:bg-[#4b3b40]"
                    >
                        Lihat katalog
                    </a>
                    <a
                        :href="bookingUrl('konsultasi desain kuku')"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="rounded-full border border-[#e7dcd7] bg-white/70 px-6 py-3.5 text-sm font-semibold text-[#5a474d] transition hover:border-[#b86c7b] hover:text-[#8b263e]"
                    >
                        Konsultasi desain
                    </a>
                </div>
                <div class="mt-10 flex items-center gap-3 text-xs text-[#71656a]">
                    <div class="flex -space-x-2" aria-hidden="true">
                        <span class="size-7 rounded-full border-2 border-[#fcf8f5] bg-[#d7b1a8]"></span>
                        <span class="size-7 rounded-full border-2 border-[#fcf8f5] bg-[#b87578]"></span>
                        <span class="size-7 rounded-full border-2 border-[#fcf8f5] bg-[#e7d1bd]"></span>
                    </div>
                    <span>Manikur rapi, warna yang kamu banget.</span>
                </div>
            </div>

            <div class="relative mx-auto w-full max-w-xl lg:mr-0">
                <div class="absolute -right-7 -top-7 size-36 rounded-full bg-[#ead5d3]/70 blur-3xl sm:size-52"></div>
                <div class="relative grid grid-cols-[1fr_0.72fr] items-center gap-3 sm:gap-5">
                    <img
                        src="https://images.unsplash.com/photo-1604654894610-df63bc536371?auto=format&fit=crop&w=1000&q=85"
                        alt="Manikur dengan detail warna merah muda"
                        class="aspect-[0.79] w-full rounded-[45%_45%_1.5rem_1.5rem] object-cover shadow-[0_26px_60px_-30px_rgba(77,40,48,0.5)]"
                    >
                    <div class="flex flex-col gap-3 sm:gap-5">
                        <img
                            src="https://images.unsplash.com/photo-1632345031435-8727f6897d53?auto=format&fit=crop&w=700&q=85"
                            alt="Pilihan nail art dengan warna elegan"
                            class="aspect-[0.92] w-full rounded-tl-[5rem] rounded-br-[5rem] object-cover"
                        >
                        <div class="rounded-2xl bg-[#f0e5df] p-4 sm:rounded-3xl sm:p-6">
                            <span class="text-[0.62rem] font-bold uppercase tracking-[0.18em] text-[#966473] sm:text-xs">Favorit studio</span>
                            <p class="mt-1 font-serif text-lg leading-tight sm:mt-2 sm:text-2xl">Nail Gel Polos</p>
                            <p class="mt-2 text-sm font-semibold text-[#8b263e]">Rp85.000</p>
                        </div>
                    </div>
                </div>
                <div class="absolute -bottom-4 left-2 rounded-full border border-white/80 bg-white/90 px-4 py-2.5 text-[0.68rem] font-medium text-[#59484d] shadow-lg backdrop-blur sm:bottom-5 sm:left-[-1.5rem] sm:px-5 sm:py-3 sm:text-xs">
                    ✦ Russian manicure termasuk
                </div>
            </div>
        </section>

        <section class="border-y border-[#eee4df] bg-white/70">
            <div class="mx-auto grid max-w-7xl grid-cols-3 divide-x divide-[#eee4df] px-5 py-5 sm:px-8 sm:py-7 lg:px-12">
                <div class="px-2 text-center sm:px-5">
                    <p class="font-serif text-lg sm:text-2xl">Manikur</p>
                    <p class="mt-1 text-[0.62rem] text-[#82767a] sm:text-xs">Rapi & teliti</p>
                </div>
                <div class="px-2 text-center sm:px-5">
                    <p class="font-serif text-lg sm:text-2xl">Nail art</p>
                    <p class="mt-1 text-[0.62rem] text-[#82767a] sm:text-xs">Sesuai idemu</p>
                </div>
                <div class="px-2 text-center sm:px-5">
                    <p class="font-serif text-lg sm:text-2xl">Press on</p>
                    <p class="mt-1 text-[0.62rem] text-[#82767a] sm:text-xs">Cantik & praktis</p>
                </div>
            </div>
        </section>

        <section id="layanan" class="mx-auto max-w-7xl scroll-mt-8 px-5 py-16 sm:px-8 sm:py-20 lg:px-12 lg:py-28">
            <div class="mb-9 flex flex-col justify-between gap-3 sm:mb-12 sm:flex-row sm:items-end">
                <div>
                    <p class="text-[0.68rem] font-bold uppercase tracking-[0.22em] text-[#a5415a]">Pilihan untukmu</p>
                    <h2 class="mt-2 font-serif text-4xl tracking-[-0.05em] sm:text-5xl">Layanan & harga</h2>
                </div>
                <p class="max-w-xs text-sm leading-6 text-[#71656a]">Harga nail art dihitung per jari. Untuk desain khusus, konsultasikan dulu dengan kami.</p>
            </div>

            <div class="grid gap-5 lg:grid-cols-12">
                <article class="group relative min-h-[25rem] overflow-hidden rounded-[1.75rem] bg-[#543b42] lg:col-span-7">
                    <img
                        src="https://images.unsplash.com/photo-1610992015732-2449b76344bc?auto=format&fit=crop&w=1200&q=85"
                        alt="Manikur gel untuk tampilan kuku yang bersih"
                        class="absolute inset-0 size-full object-cover opacity-75 transition duration-700 group-hover:scale-[1.03]"
                    >
                    <div class="absolute inset-0 bg-gradient-to-t from-[#211619]/90 via-[#211619]/10 to-transparent"></div>
                    <div class="absolute inset-x-0 bottom-0 p-6 text-white sm:p-9">
                        <span class="rounded-full border border-white/35 bg-white/10 px-3 py-1.5 text-[0.65rem] font-semibold uppercase tracking-[0.14em] backdrop-blur">Paling disukai</span>
                        <div class="mt-5 flex flex-wrap items-end justify-between gap-4">
                            <div>
                                <h3 class="font-serif text-3xl sm:text-4xl">Nail Gel Polos</h3>
                                <p class="mt-2 max-w-sm text-xs leading-6 text-white/75 sm:text-sm">Sudah termasuk Russian manicure, vitamin kuku, dan lotion.</p>
                            </div>
                            <p class="font-serif text-2xl sm:text-3xl">Rp85.000</p>
                        </div>
                        <a
                            :href="bookingUrl('Nail Gel Polos')"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mt-5 inline-flex items-center gap-2 text-xs font-semibold text-white/90 transition hover:text-white"
                        >
                            Tanya layanan <span aria-hidden="true">↗</span>
                        </a>
                    </div>
                </article>

                <article class="flex flex-col justify-between rounded-[1.75rem] bg-[#f0e5df] p-6 sm:p-8 lg:col-span-5">
                    <div>
                        <span class="text-3xl" aria-hidden="true">✳</span>
                        <p class="mt-4 text-[0.65rem] font-bold uppercase tracking-[0.18em] text-[#966473]">Manikur klasik</p>
                        <h3 class="mt-2 font-serif text-3xl leading-tight sm:text-4xl">Russian<br class="hidden sm:block"> Manicure</h3>
                        <p class="mt-3 max-w-sm text-sm leading-6 text-[#71656a]">Perawatan detail untuk area kutikula dan kuku agar tampak bersih dan terawat.</p>
                    </div>
                    <div class="mt-8 flex items-end justify-between gap-3">
                        <p class="font-serif text-2xl text-[#8b263e]">Rp45.000</p>
                        <a
                            :href="bookingUrl('Russian Manicure')"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="rounded-full bg-white px-4 py-2.5 text-xs font-semibold text-[#634750] transition hover:bg-[#8b263e] hover:text-white"
                        >
                            Pilih layanan
                        </a>
                    </div>
                </article>

                <article class="rounded-[1.75rem] border border-[#eee4df] bg-white p-6 sm:p-8 lg:col-span-7">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-[0.65rem] font-bold uppercase tracking-[0.18em] text-[#966473]">Ekspresikan gayamu</p>
                            <h3 class="mt-2 font-serif text-3xl sm:text-4xl">Nail Art / Jari</h3>
                        </div>
                        <span class="rounded-full bg-[#f7efec] px-3 py-1.5 text-[0.65rem] font-semibold text-[#8b5262]">Harga per jari</span>
                    </div>
                    <div class="mt-6 grid gap-x-6 sm:grid-cols-2">
                        <div v-for="service in artServices" :key="service.name" class="flex items-center justify-between gap-2 border-b border-[#f0e9e6] py-3">
                            <span class="text-sm font-medium">{{ service.name }}</span>
                            <span class="shrink-0 text-right">
                                <span class="block text-xs font-semibold text-[#8b263e]">{{ service.price }}</span>
                                <span class="block text-[0.62rem] text-[#8a7e82]">{{ service.detail }}</span>
                            </span>
                        </div>
                    </div>
                    <p class="mt-4 text-xs leading-5 text-[#82767a]">Harga menyesuaikan detail dan tingkat kesulitan desain.</p>
                </article>

                <article class="relative overflow-hidden rounded-[1.75rem] bg-[#e8d8d0] p-6 sm:p-8 lg:col-span-5">
                    <div class="absolute -right-12 -top-12 size-48 rounded-full border border-white/40"></div>
                    <div class="absolute -right-6 -top-6 size-36 rounded-full border border-white/40"></div>
                    <div class="relative flex h-full flex-col justify-between">
                        <div>
                            <p class="text-[0.65rem] font-bold uppercase tracking-[0.18em] text-[#966473]">Panjang ekstra</p>
                            <h3 class="mt-2 font-serif text-3xl sm:text-4xl">Nail Tip<br>Extension</h3>
                            <p class="mt-3 max-w-xs text-sm leading-6 text-[#71656a]">Tambahkan panjang dan bentuk yang kamu inginkan.</p>
                        </div>
                        <div class="mt-8 flex items-end justify-between gap-3">
                            <p class="font-serif text-2xl text-[#8b263e]">Rp7.000<span class="font-sans text-xs text-[#71656a]"> / jari</span></p>
                            <a
                                :href="bookingUrl('Nail Tip Extension')"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="rounded-full bg-white/80 px-4 py-2.5 text-xs font-semibold text-[#634750] transition hover:bg-[#8b263e] hover:text-white"
                            >
                                Tanya dulu
                            </a>
                        </div>
                    </div>
                </article>

                <article class="rounded-[1.75rem] bg-[#30272a] p-6 text-white sm:p-8 lg:col-span-7">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <p class="text-[0.65rem] font-bold uppercase tracking-[0.18em] text-[#e1aab1]">Mulai dari Rp65.000</p>
                            <h3 class="mt-2 font-serif text-3xl sm:text-4xl">Press On Nails</h3>
                        </div>
                        <span class="text-2xl" aria-hidden="true">✧</span>
                    </div>
                    <p class="mt-3 text-sm leading-6 text-white/65">Set praktis yang bisa kamu pakai sendiri, sudah termasuk:</p>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <span v-for="item in ['Lem kuku', 'Nail buffer', 'Alcohol pads', 'Pendorong kutikula']" :key="item" class="rounded-full border border-white/15 bg-white/5 px-3 py-2 text-xs text-white/85">{{ item }}</span>
                    </div>
                    <a
                        :href="bookingUrl('Press On Nails')"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-6 inline-flex items-center gap-2 text-xs font-semibold text-[#f1c4c8] transition hover:text-white"
                    >
                        Pesan Press On <span aria-hidden="true">↗</span>
                    </a>
                </article>

                <article class="rounded-[1.75rem] border border-[#eee4df] bg-white p-6 sm:p-8 lg:col-span-5">
                    <p class="text-[0.65rem] font-bold uppercase tracking-[0.18em] text-[#966473]">Perawatan praktis</p>
                    <h3 class="mt-2 font-serif text-3xl sm:text-4xl">Removal</h3>
                    <div class="mt-5 divide-y divide-[#f0e9e6]">
                        <div v-for="service in removalServices" :key="service.name" class="flex items-center justify-between gap-3 py-3">
                            <span class="text-xs leading-5 text-[#594d51]">{{ service.name }}</span>
                            <span class="shrink-0 text-xs font-semibold text-[#8b263e]">{{ service.price }}<span class="font-normal text-[#8a7e82]"> / jari</span></span>
                        </div>
                    </div>
                </article>

                <article class="group relative min-h-[19rem] overflow-hidden rounded-[1.75rem] bg-[#d8c5bb] lg:col-span-5">
                    <img
                        src="https://images.unsplash.com/photo-1632345031435-8727f6897d53?auto=format&fit=crop&w=900&q=85"
                        alt="Perawatan kuku dan warna yang lembut"
                        class="absolute inset-0 size-full object-cover transition duration-700 group-hover:scale-[1.03]"
                        loading="lazy"
                    >
                    <div class="absolute inset-0 bg-gradient-to-t from-[#281d20]/85 via-transparent to-transparent"></div>
                    <div class="absolute inset-x-0 bottom-0 p-6 text-white sm:p-8">
                        <p class="text-[0.65rem] font-bold uppercase tracking-[0.18em] text-white/75">Perawatan kaki</p>
                        <h3 class="mt-2 font-serif text-3xl">Pedicure + Nail Gel Kaki</h3>
                        <p class="mt-2 text-xs leading-5 text-white/75">Russian manicure, aplikasi gel, lotion & pijat.</p>
                        <p class="mt-4 font-serif text-2xl">Rp65.000</p>
                    </div>
                </article>

                <article class="flex min-h-[19rem] flex-col justify-between rounded-[1.75rem] bg-[#f0e5df] p-6 sm:p-8 lg:col-span-7">
                    <div>
                        <p class="text-[0.65rem] font-bold uppercase tracking-[0.18em] text-[#966473]">Sentuhan akhir</p>
                        <h3 class="mt-2 font-serif text-3xl sm:text-4xl">Punya inspirasi desain?</h3>
                        <p class="mt-3 max-w-md text-sm leading-6 text-[#71656a]">Kirim referensi atau ceritakan konsep yang kamu inginkan. Kami bantu cek detail dan estimasi harganya.</p>
                    </div>
                    <a
                        :href="bookingUrl('konsultasi nail art')"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-8 inline-flex w-fit items-center gap-2 rounded-full bg-[#8b263e] px-5 py-3 text-xs font-semibold text-white transition hover:bg-[#731e32]"
                    >
                        Konsultasi via WhatsApp <span aria-hidden="true">↗</span>
                    </a>
                </article>
            </div>
        </section>

        <section id="tentang" class="bg-[#f0e5df]">
            <div class="mx-auto grid max-w-7xl gap-8 px-5 py-14 sm:px-8 sm:py-16 md:grid-cols-[1fr_auto] md:items-end lg:px-12">
                <div>
                    <p class="text-[0.68rem] font-bold uppercase tracking-[0.22em] text-[#a5415a]">Dari NailsByMora</p>
                    <h2 class="mt-2 max-w-xl font-serif text-3xl leading-tight tracking-[-0.04em] sm:text-4xl">Kuku cantik, waktumu jadi lebih spesial.</h2>
                    <p class="mt-3 max-w-lg text-sm leading-6 text-[#71656a]">Pilih layanan favoritmu, lalu hubungi kami untuk tanya jadwal dan detail desain.</p>
                </div>
                <div class="text-sm text-[#594d51]">
                    <p class="font-semibold">NailsByMora</p>
                    <a href="https://instagram.com/nails.by_mora" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex text-[#8b263e] underline decoration-[#c89ba2] underline-offset-4">@nails.by_mora</a>
                </div>
            </div>
        </section>

        <footer class="mx-auto flex max-w-7xl flex-col gap-2 px-5 py-6 text-xs text-[#82767a] sm:flex-row sm:items-center sm:justify-between sm:px-8 lg:px-12">
            <p>© {{ new Date().getFullYear() }} NailsByMora</p>
        </footer>

        <a
            :href="bookingUrl('layanan kuku')"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="Chat NailsByMora melalui WhatsApp"
            class="fixed bottom-4 right-4 z-40 grid size-14 place-items-center rounded-full border border-white/70 bg-[#138b62] text-white shadow-[0_10px_30px_-8px_rgba(19,139,98,0.65)] transition hover:-translate-y-0.5 hover:bg-[#0e7653] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#138b62] sm:bottom-6 sm:right-6"
        >
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="currentColor" class="size-6 shrink-0">
                <path d="M12.04 2a9.9 9.9 0 0 0-8.46 15.04L2.3 21.8l4.88-1.28A9.96 9.96 0 1 0 12.04 2Zm0 18.1a8.1 8.1 0 0 1-4.13-1.13l-.3-.18-2.9.76.78-2.82-.2-.3a8.13 8.13 0 1 1 6.75 3.67Zm4.46-6.09c-.24-.12-1.43-.7-1.65-.78-.22-.08-.38-.12-.54.12-.16.24-.62.78-.76.94-.14.16-.28.18-.52.06-.24-.12-1.01-.37-1.93-1.19-.72-.64-1.2-1.44-1.34-1.68-.14-.24-.02-.37.1-.49.11-.1.24-.28.36-.42.12-.14.16-.24.24-.4.08-.16.04-.3-.02-.42-.06-.12-.54-1.3-.74-1.78-.2-.46-.4-.4-.54-.4h-.46c-.16 0-.42.06-.64.3-.22.24-.84.82-.84 2s.86 2.32.98 2.48c.12.16 1.69 2.58 4.1 3.62.57.25 1.02.4 1.37.51.58.18 1.1.16 1.51.1.46-.07 1.43-.58 1.63-1.14.2-.56.2-1.04.14-1.14-.06-.1-.22-.16-.46-.28Z" />
            </svg>
        </a>
        <button
            v-if="showScrollTop"
            type="button"
            aria-label="Kembali ke atas"
            class="fixed bottom-20 right-5 z-40 grid size-11 place-items-center rounded-full border border-white bg-white text-[#59484d] shadow-lg transition hover:-translate-y-0.5 hover:text-[#8b263e] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8b263e] sm:bottom-24 sm:right-8"
            @click="scrollToTop"
        >
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" class="size-5">
                <path d="M12 19V5m-6 6 6-6 6 6" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" />
            </svg>
        </button>
    </main>
</template>
