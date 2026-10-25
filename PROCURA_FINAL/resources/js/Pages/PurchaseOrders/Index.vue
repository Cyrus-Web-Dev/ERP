<script setup>
import { reactive, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import Pagination from '@/Components/Pagination.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { formatIDR, formatDate } from '@/utils'

const props = defineProps({ orders: Object, filters: Object })
const f = reactive({ search: props.filters.search ?? '', status: props.filters.status ?? '' })
let t = null
watch(f, () => { clearTimeout(t); t = setTimeout(() => router.get(route('purchase-orders.index'), Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true, replace: true }), 300) })
</script>

<template>
    <AppLayout>
        <template #title>Purchase Order</template>
        <PageHeader title="Purchase Order" subtitle="Pesanan ke vendor, penerimaan barang, dan status pembayarannya. PO dibuat dari halaman permintaan setelah dana cair." />
        <div class="card">
            <div class="grid gap-3 border-b border-slate-100 p-4 sm:grid-cols-3 dark:border-ink-700">
                <input v-model="f.search" type="search" class="input sm:col-span-2" placeholder="Cari nomor PO, vendor, permintaan…" aria-label="Cari" />
                <select v-model="f.status" class="input" aria-label="Status"><option value="">Semua status</option><option v-for="(s, k) in $page.props.workflow.po" :key="k" :value="k">{{ s.label }}</option></select>
            </div>
            <div v-if="orders.data.length" class="overflow-x-auto"><table class="tbl">
                <thead><tr><th>PO</th><th>Vendor</th><th>Permintaan</th><th class="text-right">Total</th><th class="text-right">Terbayar</th><th>Tiba</th><th>Status</th></tr></thead>
                <tbody><tr v-for="o in orders.data" :key="o.id">
                    <td><Link :href="route('purchase-orders.show', o.id)" class="docno text-sm font-semibold text-harbor-700 hover:underline dark:text-harbor-300">{{ o.number }}</Link><p class="muted text-xs">{{ formatDate(o.order_date) }}</p></td>
                    <td>{{ o.vendor }}</td>
                    <td class="max-w-[14rem]"><Link :href="route('requests.show', o.request.id)" class="line-clamp-1 hover:underline">{{ o.request.title }}</Link></td>
                    <td class="tabular text-right">{{ formatIDR(o.total) }}</td>
                    <td class="tabular text-right" :class="o.paid >= o.total ? 'text-green-700 dark:text-green-400' : ''">{{ formatIDR(o.paid) }}</td>
                    <td class="whitespace-nowrap text-xs" :class="o.late ? 'font-semibold text-rose-600' : 'muted'">{{ formatDate(o.expected_date) }}<span v-if="o.late"> · lewat</span></td>
                    <td><StatusBadge kind="po" :value="o.status" /></td>
                </tr></tbody></table></div>
            <EmptyState v-else title="Belum ada Purchase Order" text="PO muncul setelah Keuangan mencairkan dana untuk sebuah permintaan." />
        </div>
        <Pagination :links="orders.links" />
    </AppLayout>
</template>
