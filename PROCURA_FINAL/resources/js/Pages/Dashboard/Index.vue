<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { formatIDR, formatDate } from '@/utils'

const props = defineProps({ stats: Object, pipeline: Object, attention: Array, events: Array })

// Corong alur: berapa permintaan berada di tiap tahap sekarang.
const lanes = computed(() => [
    { label: 'Baru masuk', keys: ['submitted'], to: 'submitted' },
    { label: 'Ditinjau / penawaran', keys: ['reviewing', 'sourcing'], to: 'sourcing' },
    { label: 'Menunggu dana', keys: ['funding'], to: 'funding' },
    { label: 'Dana cair', keys: ['funded'], to: 'funded' },
    { label: 'Dipesan', keys: ['ordered'], to: 'ordered' },
    { label: 'Diterima', keys: ['received'], to: 'received' },
].map((l) => ({ ...l, n: l.keys.reduce((a, k) => a + Number(props.pipeline[k] ?? 0), 0) })))
const maxLane = computed(() => Math.max(1, ...lanes.value.map((l) => l.n)))
</script>

<template>
    <AppLayout>
        <template #title>Ringkasan</template>
        <PageHeader title="Ringkasan pengadaan" subtitle="Posisi semua permintaan yang sedang berjalan dan hal yang menunggu tindakan.">
            <Link :href="route('requests.create')" class="btn btn-primary">+ Permintaan baru</Link>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Link :href="route('requests.index', { status: 'open' })" class="card card-pad block transition hover:border-harbor-300">
                <p class="muted text-xs">Permintaan berjalan</p>
                <p class="tabular mt-1 text-3xl font-semibold">{{ stats.open }}</p>
                <p class="muted mt-1 text-xs">{{ stats.inbox }} baru masuk belum ditinjau</p>
            </Link>
            <Link :href="route('finance-desk.index')" class="card card-pad block transition hover:border-harbor-300">
                <p class="muted text-xs">Dana menunggu Keuangan</p>
                <p class="tabular mt-1 text-3xl font-semibold">{{ stats.funding_waiting }}</p>
                <p class="muted mt-1 text-xs">{{ stats.funding_awaiting_cash }} disetujui, belum cair</p>
            </Link>
            <Link :href="route('purchase-orders.index')" class="card card-pad block transition hover:border-harbor-300">
                <p class="muted text-xs">PO aktif di vendor</p>
                <p class="tabular mt-1 text-3xl font-semibold">{{ stats.po_active }}</p>
                <p class="muted mt-1 text-xs">menunggu barang tiba</p>
            </Link>
            <div class="card card-pad">
                <p class="muted text-xs">Dana di tangan Pengadaan</p>
                <p class="tabular mt-1 text-2xl font-semibold">{{ formatIDR(stats.committed) }}</p>
                <p class="muted mt-1 text-xs">cair, belum dipertanggungjawabkan</p>
            </div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <div class="card lg:col-span-2">
                <div class="card-head"><h3 class="card-title">Di mana permintaan sekarang?</h3></div>
                <div class="space-y-2.5 p-5">
                    <Link v-for="l in lanes" :key="l.label" :href="route('requests.index', { status: l.to })" class="group flex items-center gap-3">
                        <span class="w-36 shrink-0 text-sm text-slate-600 group-hover:text-harbor-700 dark:text-slate-300">{{ l.label }}</span>
                        <span class="h-6 flex-1 overflow-hidden rounded-md bg-slate-100 dark:bg-ink-800">
                            <span class="block h-full rounded-md bg-harbor-500 transition-all" :style="{ width: (l.n / maxLane) * 100 + '%', minWidth: l.n ? '1.5rem' : '0' }"></span>
                        </span>
                        <span class="tabular w-6 text-right text-sm font-semibold">{{ l.n }}</span>
                    </Link>
                </div>
                <div class="grid grid-cols-3 divide-x divide-slate-100 border-t border-slate-100 text-center dark:divide-ink-700 dark:border-ink-700">
                    <div class="p-4"><p class="muted text-xs">Realisasi tahun ini</p><p class="tabular mt-0.5 text-sm font-semibold">{{ formatIDR(stats.spent_year) }}</p></div>
                    <div class="p-4"><p class="muted text-xs">Sisa dana dikembalikan</p><p class="tabular mt-0.5 text-sm font-semibold">{{ formatIDR(stats.saved_year) }}</p></div>
                    <div class="p-4"><p class="muted text-xs">Rata-rata waktu selesai</p><p class="tabular mt-0.5 text-sm font-semibold">{{ stats.avg_days ? stats.avg_days + ' hari' : '—' }}</p></div>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h3 class="card-title">Kabar dari divisi lain</h3></div>
                <div class="divide-y divide-slate-100 dark:divide-ink-700">
                    <Link :href="route('inventory-desk.index')" class="flex items-center justify-between px-5 py-3.5 hover:bg-slate-50 dark:hover:bg-ink-800">
                        <span class="text-sm">Laporan baru dari Inventaris</span>
                        <span class="badge" :class="stats.unread_inventaris ? 'badge-orange' : 'badge-slate'">{{ stats.unread_inventaris }}</span>
                    </Link>
                    <Link :href="route('finance-desk.index', { tab: 'reports' })" class="flex items-center justify-between px-5 py-3.5 hover:bg-slate-50 dark:hover:bg-ink-800">
                        <span class="text-sm">Keputusan / kabar baru dari Keuangan</span>
                        <span class="badge" :class="stats.unread_keuangan ? 'badge-orange' : 'badge-slate'">{{ stats.unread_keuangan }}</span>
                    </Link>
                </div>
                <div class="card-head border-t"><h3 class="card-title">Perlu perhatian</h3></div>
                <ul v-if="attention.length" class="divide-y divide-slate-100 dark:divide-ink-700">
                    <li v-for="r in attention" :key="r.id">
                        <Link :href="route('requests.show', r.id)" class="block px-5 py-3 hover:bg-slate-50 dark:hover:bg-ink-800">
                            <p class="truncate text-sm font-medium">{{ r.title }}</p>
                            <p class="mt-0.5 flex flex-wrap items-center gap-2 text-xs">
                                <StatusBadge kind="priority" :value="r.priority" />
                                <span v-if="r.needed_date" :class="r.late ? 'font-semibold text-rose-600' : 'muted'">{{ r.late ? 'Terlambat · ' : 'Butuh ' }}{{ formatDate(r.needed_date) }}</span>
                            </p>
                        </Link>
                    </li>
                </ul>
                <p v-else class="muted px-5 py-6 text-center text-sm">Tidak ada yang mendesak.</p>
            </div>
        </div>

        <div class="card mt-6">
            <div class="card-head"><h3 class="card-title">Aktivitas terbaru</h3></div>
            <ul v-if="events.length" class="divide-y divide-slate-100 dark:divide-ink-700">
                <li v-for="e in events" :key="e.id" class="flex gap-3 px-5 py-3 text-sm">
                    <span class="tabular muted w-32 shrink-0 text-xs">{{ e.created_at }}</span>
                    <span class="min-w-0"><Link :href="route('requests.show', e.request_id)" class="docno hover:underline">{{ e.number }}</Link> {{ e.description }}</span>
                </li>
            </ul>
            <p v-else class="muted px-5 py-8 text-center text-sm">Belum ada aktivitas.</p>
        </div>
    </AppLayout>
</template>
