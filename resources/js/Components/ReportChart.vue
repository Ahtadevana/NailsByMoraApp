<script setup>
import { computed } from 'vue';

const props = defineProps({
    chart: { type: Object, required: true },
});
const colors = ['#7c2838', '#d89b43', '#31826b', '#6586aa', '#bd4b4b', '#8b6fa6'];
const values = computed(() => props.chart.datasets.flatMap((dataset) => dataset.values.map(Number)));
const minimum = computed(() => Math.min(0, ...values.value));
const maximum = computed(() => Math.max(1, ...values.value));
const chartRange = computed(() => maximum.value - minimum.value || 1);
const donutTotal = computed(() => values.value.reduce((total, value) => total + Math.max(0, value), 0));
const donutStyle = computed(() => {
    let cursor = 0;
    const stops = props.chart.datasets[0]?.values.map((value, index) => {
        const part = donutTotal.value ? Math.max(0, Number(value)) / donutTotal.value * 100 : 0;
        const start = cursor;
        cursor += part;
        return `${props.chart.datasets[0].colors?.[index] ?? colors[index % colors.length]} ${start}% ${cursor}%`;
    }) ?? [];

    return { background: `conic-gradient(${stops.join(', ') || '#eee 0% 100%'})` };
});

function xPosition(index, count) {
    return count <= 1 ? 360 : 44 + (index * 632) / (count - 1);
}

function yPosition(value) {
    return 205 - ((Number(value) - minimum.value) / chartRange.value) * 170;
}

function linePath(data) {
    return data.map((value, index) => `${index === 0 ? 'M' : 'L'} ${xPosition(index, data.length)} ${yPosition(value)}`).join(' ');
}

function barHeight(value) {
    return Math.max(0, (Number(value) / maximum.value) * 166);
}

function formatValue(value) {
    return props.chart.currency
        ? new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value))
        : new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format(Number(value));
}

function colorFor(dataset, index) {
    return dataset.colors?.[index] ?? dataset.color ?? colors[index % colors.length];
}

function labelIndexes() {
    const count = props.chart.labels.length;
    const stride = Math.max(1, Math.ceil(count / 7));

    return props.chart.labels.map((label, index) => ({ label, index })).filter(({ index }) => (
        index % stride === 0 || index === count - 1
    ));
}
</script>

<template>
    <article class="min-w-0 rounded-2xl border border-brand-border bg-white p-4 shadow-[0_4px_12px_-8px_rgba(43,38,39,0.16)] sm:p-5">
        <header>
            <h3 class="font-bold">{{ chart.title }}</h3>
            <p v-if="chart.subtitle" class="mt-1 text-xs leading-5 text-brand-muted">{{ chart.subtitle }}</p>
        </header>

        <div v-if="chart.type === 'donut'" class="mt-5 flex flex-col items-center gap-5 sm:flex-row">
            <div class="grid size-44 shrink-0 place-items-center rounded-full" :style="donutStyle" role="img" :aria-label="chart.title">
                <div class="grid size-24 place-items-center rounded-full bg-white text-center">
                    <span class="text-xs font-semibold text-brand-muted">{{ formatValue(donutTotal) }}</span>
                </div>
            </div>
            <ul class="flex w-full flex-col gap-2">
                <li v-for="(label, index) in chart.labels" :key="label" class="flex items-center justify-between gap-3 text-xs">
                    <span class="flex min-w-0 items-center gap-2"><span class="size-2.5 shrink-0 rounded-full" :style="{ backgroundColor: colorFor(chart.datasets[0], index) }" /><span class="truncate">{{ label }}</span></span>
                    <span class="shrink-0 font-semibold tabular-nums">{{ formatValue(chart.datasets[0].values[index] ?? 0) }}</span>
                </li>
                <li v-if="!chart.labels.length" class="text-sm text-brand-muted">Tidak ada data untuk periode yang dipilih.</li>
            </ul>
        </div>

        <div v-else-if="chart.labels.length" class="mt-4 overflow-x-auto">
            <svg viewBox="0 0 720 250" role="img" :aria-label="chart.title" class="h-60 min-w-[520px] w-full overflow-visible">
                <g v-for="step in 5" :key="step">
                    <line x1="42" :y1="35 + (step - 1) * 42" x2="690" :y2="35 + (step - 1) * 42" stroke="#eee8e5" stroke-width="1" />
                    <text x="36" :y="39 + (step - 1) * 42" text-anchor="end" fill="#81797b" font-size="9">{{ formatValue(maximum - (step - 1) * chartRange / 4) }}</text>
                </g>
                <template v-if="chart.type === 'line'">
                    <g v-for="(dataset, datasetIndex) in chart.datasets" :key="dataset.label">
                        <path :d="linePath(dataset.values)" fill="none" :stroke="colorFor(dataset, datasetIndex)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                        <circle v-for="(value, index) in dataset.values" :key="`${dataset.label}-${index}`" :cx="xPosition(index, dataset.values.length)" :cy="yPosition(value)" r="4" :fill="colorFor(dataset, datasetIndex)">
                            <title>{{ chart.labels[index] }} · {{ dataset.label }}: {{ formatValue(value) }}</title>
                        </circle>
                    </g>
                </template>
                <template v-else>
                    <g v-for="(dataset, datasetIndex) in chart.datasets" :key="dataset.label">
                        <rect
                            v-for="(value, index) in dataset.values"
                            :key="`${dataset.label}-${index}`"
                            :x="48 + index * (632 / Math.max(chart.labels.length, 1)) + datasetIndex * (Math.min(28, 632 / Math.max(chart.labels.length, 1) / chart.datasets.length) + 2)"
                            :y="205 - barHeight(value)"
                            :width="Math.max(3, Math.min(28, 632 / Math.max(chart.labels.length, 1) / chart.datasets.length) - 2)"
                            :height="barHeight(value)"
                            :fill="colorFor(dataset, datasetIndex)"
                            rx="3"
                        >
                            <title>{{ chart.labels[index] }} · {{ dataset.label }}: {{ formatValue(value) }}</title>
                        </rect>
                    </g>
                </template>
                <g v-for="item in labelIndexes()" :key="`${item.index}-${item.label}`">
                    <text :x="chart.type === 'line' ? xPosition(item.index, chart.labels.length) : 48 + item.index * (632 / Math.max(chart.labels.length, 1))" y="229" text-anchor="middle" fill="#81797b" font-size="9">{{ item.label }}</text>
                </g>
            </svg>
            <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-2">
                <li v-for="(dataset, index) in chart.datasets" :key="dataset.label" class="flex items-center gap-2 text-xs text-brand-muted">
                    <span class="size-2.5 rounded-full" :style="{ backgroundColor: colorFor(dataset, index) }" />{{ dataset.label }}
                </li>
            </ul>
        </div>
        <p v-else class="mt-5 rounded-xl bg-brand-cream px-3 py-5 text-center text-sm text-brand-muted">Tidak ada data untuk periode yang dipilih.</p>
    </article>
</template>
