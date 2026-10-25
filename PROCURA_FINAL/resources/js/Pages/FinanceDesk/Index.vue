<script setup>
import { ref, watch } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import Modal from '@/Components/Modal.vue'
import Field from '@/Components/Field.vue'
import Pagination from '@/Components/Pagination.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { useCan } from '@/permissions'
import { formatIDR, formatDate } from '@/utils'

const props = defineProps({ tab: String, filters: Object, funds: Object, reports: Object, counts: Object, totals: Object })
const can = useCan()
const search = ref(props.filters.search ?? '')
let t = null
watch(search, (v) => { clearTimeout(t); t = setTimeout(() => router.get(route('finance-desk.index'), { tab: props.tab, ...(v ? { search: v } : {}) }, { preserveState: true, replace: true }), 300) })

const tabs = [
    { key: 'waiting', label: 'Menunggu keputusan', n: 'waiting' },
    { key: 'decided', label: 'Sudah diputuskan', n: 'decided' },
    { key: 'cash', label: 'Pencairan', n: 'cash' },
    { key: 'settle', label: 'LPJ selesai' },
    { key: 'all', label: 'Semua' },
    { key: 'reports', label: 'Laporan & kabar', n: 'unread' },
]
const go = (k) => router.get(route('finance-desk.index'), { tab: k }, { preserveState: true })

const stampFor = (s) => ({ approved: ['stamp-approve', 'DISETUJUI'], disbursed: ['stamp-approve', 'DANA CAIR'], settled: ['stamp-approve', 'LPJ SELESAI'], rejected: ['stamp-reject', 'DITOLAK'], revision: ['stamp-revision', 'REVISI'] }[s] ?? ['stamp-wait', 'MENUNGGU'])

const open = ref(false)
const form = useForm({ direction: 'out', subject: '', body: '', funding_request_id: '' })
const submit = () => form.post(route('finance-desk.report.store'), { preserveScroll: true, onSuccess: () => { open.value = false; form.reset() } })
const markRead = (r) => router.post(route('finance-desk.report.read', r.id), {}, { preserveScroll: true })
</script>

<template>
    <AppLayout>
        <template #title>Meja Keuangan</template>
        <PageHeader title="Meja Keuangan" subtitle="Pengajuan dana dari Pengadaan, keputusan Keuangan (apa pun hasilnya), pencairan, dan pertanggungjawaban.">
            <button v-if="can('funding.submit') || can('finance.decide')" class="btn btn-outline" @click="open = true">Tulis / catat laporan</button>
        </PageHeader>

        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <div class="card card-pad"><p class="muted text-xs">Diajukan, menunggu</p><p class="tabular mt-1 text-xl font-semibold">{{ formatIDR(totals.requested) }}</p></div>
            <div class="card card-pad"><p class="muted text-xs">Disetujui, belum cair</p><p class="tabular mt-1 text-xl font-semibold">{{ formatIDR(totals.approved) }}</p></div>
            <div class="card card-pad"><p class="muted text-xs">Dana cair, belum dipakai</p><p class="tabular mt-1 text-xl font-semibold">{{ formatIDR(totals.in_hand) }}</p></div>
        </div>

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="tabs" role="tablist">
                <button v-for="x in tabs" :key="x.key" role="tab" :aria-selected="tab === x.key" class="tab" :class="{ 'tab-on': tab === x.key }" @click="go(x.key)">
                    {{ x.label }}<span v-if="x.n && counts[x.n]" class="rounded-full bg-orange-600 px-1.5 text-[10px] font-bold leading-4 text-white">{{ counts[x.n] }}</span>
                </button>
            </div>
            <input v-if="tab !== 'reports'" v-model="search" type="search" class="input max-w-xs" placeholder="Cari nomor / judul…" aria-label="Cari" />
        </div>

        <template v-if="tab !== 'reports'">
            <div v-if="funds.data.length" class="grid gap-4 md:grid-cols-2">
                <Link v-for="f in funds.data" :key="f.id" :href="route('finance-desk.show', f.id)" class="card group relative block overflow-hidden p-5 transition hover:border-harbor-300">
                    <div class="absolute right-4 top-4"><span class="stamp !py-0.5 !text-xs" :class="stampFor(f.status)[0]">{{ stampFor(f.status)[1] }}</span></div>
                    <p class="docno">{{ f.number }}<span v-if="f.finance_ref"> · Keu {{ f.finance_ref }}</span></p>
                    <h3 class="mt-1 max-w-[65%] font-medium leading-snug text-slate-900 group-hover:text-harbor-700 dark:text-slate-100">{{ f.request.title }}</h3>
                    <p class="tabular mt-3 text-2xl font-semibold">{{ formatIDR(f.approved_amount ?? f.amount) }}</p>
                    <p v-if="f.approved_amount && f.approved_amount < f.amount" class="muted tabular text-xs">diajukan {{ formatIDR(f.amount) }}</p>
                    <p class="mt-2 flex flex-wrap items-center gap-2 text-xs"><StatusBadge kind="funding" :value="f.status" />
                        <span v-if="f.decided_by_name" class="muted">oleh {{ f.decided_by_name }} · {{ formatDate(f.decided_at) }}</span>
                        <span v-else-if="f.submitted_at" class="muted">diajukan {{ formatDate(f.submitted_at) }}</span></p>
                    <p v-if="f.decision_note" class="mt-2 line-clamp-2 text-sm text-slate-600 dark:text-slate-300">“{{ f.decision_note }}”</p>
                </Link>
            </div>
            <div v-else class="card"><EmptyState title="Tidak ada pengajuan di tab ini" text="Pengajuan dana dibuat dari halaman detail permintaan setelah penawaran vendor dipilih." /></div>
            <Pagination :links="funds.links" />
        </template>

        <template v-else>
            <div v-if="reports.data.length" class="space-y-3">
                <article v-for="r in reports.data" :key="r.id" class="letter" :class="[r.direction === 'in' ? 'letter-in' : 'letter-out', r.direction === 'in' && !r.read ? 'ring-1 ring-orange-300' : '']">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="flex flex-wrap items-center gap-2 text-xs"><span class="docno">{{ r.number }}</span>
                                <span class="badge" :class="r.direction === 'in' ? 'badge-teal' : 'badge-slate'">{{ r.direction === 'in' ? 'Dari Keuangan' : 'Ke Keuangan' }}</span>
                                <span class="muted">{{ $page.props.workflow.reportKinds[r.kind] ?? r.kind }}</span><span v-if="r.direction === 'in' && !r.read" class="badge badge-orange">Baru</span></p>
                            <h3 class="mt-1 font-medium">{{ r.subject }}</h3>
                        </div>
                        <span class="tabular muted text-xs">{{ r.created_at }}</span>
                    </div>
                    <p v-if="r.body" class="mt-2 whitespace-pre-line text-sm text-slate-600 dark:text-slate-300">{{ r.body }}</p>
                    <div class="mt-3 flex items-center justify-between text-xs">
                        <span class="muted"><Link v-if="r.funding_id" :href="route('finance-desk.show', r.funding_id)" class="docno hover:underline">Lihat pengajuan dana</Link></span>
                        <button v-if="r.direction === 'in' && !r.read" class="btn btn-ghost btn-sm" @click="markRead(r)">Tandai dibaca</button>
                    </div>
                </article>
            </div>
            <div v-else class="card"><EmptyState title="Belum ada laporan" /></div>
            <Pagination :links="reports.links" />
        </template>

        <Modal :show="open" title="Laporan Pengadaan ⇄ Keuangan" @close="open = false">
            <form class="space-y-4" @submit.prevent="submit">
                <Field label="Arah laporan"><select v-model="form.direction" class="input"><option value="out">Dikirim Pengadaan ke Keuangan</option><option value="in">Diterima dari Keuangan</option></select></Field>
                <Field label="Perihal" :error="form.errors.subject"><input v-model="form.subject" class="input" required /></Field>
                <Field label="Isi"><textarea v-model="form.body" rows="4" class="input"></textarea></Field>
                <div class="flex justify-end gap-2"><button type="button" class="btn btn-outline" @click="open = false">Batal</button><button class="btn btn-primary" :disabled="form.processing">Simpan</button></div>
            </form>
        </Modal>
    </AppLayout>
</template>
