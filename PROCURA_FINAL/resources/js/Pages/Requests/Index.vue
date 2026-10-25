<script setup>
import { reactive, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import RouteTracker from '@/Components/RouteTracker.vue'
import Pagination from '@/Components/Pagination.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { formatIDR, formatDate } from '@/utils'

const props = defineProps({ requests: Object, filters: Object })
const f = reactive({ search: props.filters.search ?? '', status: props.filters.status ?? '', category: props.filters.category ?? '', priority: props.filters.priority ?? '' })

let t = null
watch(f, () => {
    clearTimeout(t)
    t = setTimeout(() => router.get(route('requests.index'), Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true, replace: true }), 300)
})
</script>

<template>
    <AppLayout>
        <template #title>Permintaan</template>
        <PageHeader title="Permintaan barang & jasa" subtitle="Semua kebutuhan dari Inventaris, dari yang baru masuk sampai diserahkan.">
            <Link :href="route('requests.create')" class="btn btn-primary">+ Permintaan baru</Link>
        </PageHeader>

        <div class="card">
            <div class="grid gap-3 border-b border-slate-100 p-4 sm:grid-cols-2 lg:grid-cols-4 dark:border-ink-700">
                <input v-model="f.search" type="search" class="input" placeholder="Cari judul, nomor, pemohon…" aria-label="Cari" />
                <select v-model="f.status" class="input" aria-label="Status">
                    <option value="">Semua status</option>
                    <option value="open">Masih berjalan</option>
                    <option v-for="(s, k) in $page.props.workflow.request" :key="k" :value="k">{{ s.label }}</option>
                </select>
                <select v-model="f.category" class="input" aria-label="Kategori">
                    <option value="">Semua kategori</option>
                    <option v-for="(l, k) in $page.props.workflow.categories" :key="k" :value="k">{{ l }}</option>
                </select>
                <select v-model="f.priority" class="input" aria-label="Prioritas">
                    <option value="">Semua prioritas</option>
                    <option v-for="(p, k) in $page.props.workflow.priority" :key="k" :value="k">{{ p.label }}</option>
                </select>
            </div>

            <div v-if="requests.data.length" class="overflow-x-auto">
                <table class="tbl">
                    <thead><tr><th>Permintaan</th><th>Jalur</th><th>Status</th><th class="text-right">Perkiraan</th><th>Dibutuhkan</th></tr></thead>
                    <tbody>
                        <tr v-for="r in requests.data" :key="r.id">
                            <td class="min-w-[16rem]">
                                <Link :href="route('requests.show', r.id)" class="font-medium text-slate-900 hover:text-harbor-700 dark:text-slate-100">{{ r.title }}</Link>
                                <p class="mt-0.5 text-xs"><span class="docno">{{ r.number }}</span> <span class="muted">· {{ r.requester_name }}<template v-if="r.requester_unit">, {{ r.requester_unit }}</template></span></p>
                            </td>
                            <td class="w-56"><RouteTracker :station="r.station" :status="r.status" compact /></td>
                            <td><div class="flex flex-col items-start gap-1"><StatusBadge :value="r.status" /><StatusBadge v-if="['high', 'urgent'].includes(r.priority)" kind="priority" :value="r.priority" /></div></td>
                            <td class="tabular text-right">{{ formatIDR(r.estimated_total) }}</td>
                            <td class="whitespace-nowrap text-xs" :class="r.late ? 'font-semibold text-rose-600' : 'muted'">{{ formatDate(r.needed_date) }}<span v-if="r.late"> · terlambat</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <EmptyState v-else title="Tidak ada permintaan yang cocok" text="Ubah filter, atau catat permintaan baru dari Inventaris."><Link :href="route('requests.create')" class="btn btn-primary">Buat permintaan</Link></EmptyState>
        </div>
        <Pagination :links="requests.links" />
    </AppLayout>
</template>
