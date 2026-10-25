<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import { formatIDR, formatNumber } from '@/utils'

const props = defineProps({ year: Number, years: Array, months: Array, byCategory: Array, topVendors: Array, summary: Object, decisions: Object })
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']
const maxMonth = computed(() => Math.max(1, ...props.months))
const maxCat = computed(() => Math.max(1, ...props.byCategory.map((c) => c.total)))
const compact = (v) => (v >= 1e9 ? (v / 1e9).toFixed(1) + ' M' : v >= 1e6 ? (v / 1e6).toFixed(0) + ' jt' : v >= 1e3 ? (v / 1e3).toFixed(0) + ' rb' : String(Math.round(v)))
const changeYear = (e) => router.get(route('analytics.index'), { year: e.target.value }, { preserveState: true })
const variance = computed(() => props.summary.actual - props.summary.estimated)
const decisionRows = computed(() => [['approved', 'Disetujui'], ['disbursed', 'Dana cair'], ['settled', 'LPJ selesai'], ['revision', 'Diminta revisi'], ['rejected', 'Ditolak'], ['submitted', 'Menunggu']].map(([k, l]) => ({ k, l, n: Number(props.decisions[k] ?? 0) })).filter((r) => r.n))
</script>

<template>
    <AppLayout>
        <template #title>Analitik</template>
        <PageHeader title="Analitik pengadaan" subtitle="Belanja, kecepatan proses, dan seberapa akurat perkiraan Inventaris dibanding harga sebenarnya.">
            <select :value="year" class="input w-28" aria-label="Tahun" @change="changeYear"><option v-for="y in years" :key="y" :value="y">{{ y }}</option></select>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="card card-pad"><p class="muted text-xs">Total belanja (PO)</p><p class="tabular mt-1 text-2xl font-semibold">{{ formatIDR(summary.spend) }}</p><p class="muted mt-1 text-xs">{{ summary.orders }} PO</p></div>
            <div class="card card-pad"><p class="muted text-xs">Permintaan masuk</p><p class="tabular mt-1 text-2xl font-semibold">{{ summary.requests }}</p></div>
            <div class="card card-pad"><p class="muted text-xs">Rata-rata sampai selesai</p><p class="tabular mt-1 text-2xl font-semibold">{{ summary.cycle_days ? formatNumber(summary.cycle_days, 1) + ' hari' : '—' }}</p></div>
            <div class="card card-pad"><p class="muted text-xs">Sisa dana dikembalikan</p><p class="tabular mt-1 text-2xl font-semibold">{{ formatIDR(summary.returned) }}</p></div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <section class="card lg:col-span-2">
                <div class="card-head"><h3 class="card-title">Belanja per bulan ({{ year }})</h3></div>
                <div class="p-5">
                    <div class="flex h-48 items-end gap-2" role="img" :aria-label="`Grafik belanja bulanan ${year}`">
                        <div v-for="(v, i) in months" :key="i" class="group flex h-full flex-1 flex-col items-center justify-end">
                            <span class="tabular mb-1 text-[10px] text-slate-500 opacity-0 group-hover:opacity-100">{{ compact(v) }}</span>
                            <div class="w-full rounded-t-md bg-harbor-500 transition-all group-hover:bg-orange-600" :style="{ height: (v / maxMonth) * 100 + '%', minHeight: v ? '3px' : '0' }"></div>
                        </div>
                    </div>
                    <div class="mt-2 flex gap-2"><span v-for="m in MONTHS" :key="m" class="muted flex-1 text-center text-[11px]">{{ m }}</span></div>
                </div>
            </section>

            <section class="card">
                <div class="card-head"><h3 class="card-title">Perkiraan vs realisasi</h3></div>
                <div class="space-y-3 p-5">
                    <div><p class="muted text-xs">Perkiraan Inventaris (+PPN)</p><p class="tabular font-semibold">{{ formatIDR(summary.estimated) }}</p></div>
                    <div><p class="muted text-xs">Realisasi PO</p><p class="tabular font-semibold">{{ formatIDR(summary.actual) }}</p></div>
                    <div class="rounded-lg p-3 text-sm" :class="variance <= 0 ? 'bg-green-50 text-green-900 dark:bg-green-950 dark:text-green-200' : 'bg-amber-50 text-amber-900 dark:bg-amber-950 dark:text-amber-200'">
                        {{ summary.estimated ? (variance <= 0 ? 'Lebih hemat ' : 'Lebih mahal ') + formatIDR(Math.abs(variance)) + ' (' + Math.abs((variance / summary.estimated) * 100).toFixed(1) + '%)' : 'Belum ada data pembanding.' }}
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="card-head"><h3 class="card-title">Belanja per kategori</h3></div>
                <div v-if="byCategory.length" class="space-y-3 p-5">
                    <div v-for="c in byCategory" :key="c.key"><div class="mb-1 flex justify-between text-sm"><span>{{ c.label }}</span><span class="tabular font-medium">{{ formatIDR(c.total) }}</span></div>
                        <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-ink-800"><div class="h-full rounded-full bg-harbor-500" :style="{ width: (c.total / maxCat) * 100 + '%' }"></div></div></div>
                </div>
                <p v-else class="muted px-5 py-8 text-center text-sm">Belum ada data.</p>
            </section>

            <section class="card">
                <div class="card-head"><h3 class="card-title">Vendor teratas</h3></div>
                <table v-if="topVendors.length" class="tbl"><thead><tr><th>Vendor</th><th class="text-right">PO</th><th class="text-right">Nilai</th></tr></thead>
                    <tbody><tr v-for="v in topVendors" :key="v.id"><td>{{ v.name }}<p v-if="v.rating" class="muted text-xs">★ {{ v.rating }}</p></td><td class="tabular text-right">{{ v.orders }}</td><td class="tabular text-right">{{ formatIDR(v.spend) }}</td></tr></tbody></table>
                <p v-else class="muted px-5 py-8 text-center text-sm">Belum ada data.</p>
            </section>

            <section class="card">
                <div class="card-head"><h3 class="card-title">Nasib pengajuan dana</h3></div>
                <ul v-if="decisionRows.length" class="divide-y divide-slate-100 dark:divide-ink-700"><li v-for="r in decisionRows" :key="r.k" class="flex justify-between px-5 py-3 text-sm"><span>{{ r.l }}</span><span class="tabular font-semibold">{{ r.n }}</span></li></ul>
                <p v-else class="muted px-5 py-8 text-center text-sm">Belum ada pengajuan.</p>
            </section>
        </div>
    </AppLayout>
</template>
