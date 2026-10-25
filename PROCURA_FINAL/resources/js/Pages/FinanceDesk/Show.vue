<script setup>
import { computed, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import Modal from '@/Components/Modal.vue'
import Field from '@/Components/Field.vue'
import ErrorSummary from '@/Components/ErrorSummary.vue'
import AttachmentPanel from '@/Components/AttachmentPanel.vue'
import { useCan } from '@/permissions'
import { formatIDR, formatDate, formatNumber } from '@/utils'

const props = defineProps({ funding: Object, pr: Object, items: Array, quotation: Object, payments: Array, threshold: Number, reports: Array })
const can = useCan()
const modal = ref(null)
const close = () => { modal.value = null }
const done = { preserveScroll: true, onSuccess: close }

const stamp = computed(() => ({
    approved: ['stamp-approve', 'DISETUJUI'], disbursed: ['stamp-approve', 'DANA CAIR'], settled: ['stamp-approve', 'LPJ SELESAI'],
    rejected: ['stamp-reject', 'DITOLAK'], revision: ['stamp-revision', 'REVISI'],
}[props.funding.status] ?? ['stamp-wait', 'MENUNGGU']))

const dForm = useForm({ decision: 'approve', approved_amount: props.funding.amount, note: '', finance_ref: '' })
const submitDecision = () => dForm.post(route('finance-desk.decide', props.funding.id), done)
const cash = useForm({ amount: props.funding.approved_amount ?? props.funding.amount, method: 'transfer', reference: '' })
const submitCash = () => cash.post(route('finance-desk.disburse', props.funding.id), done)
const lpj = useForm({ note: '' })
const submitLpj = () => lpj.post(route('finance-desk.settle', props.funding.id), done)
const approveInternal = () => router.post(route('finance-desk.approve-internal', props.funding.id), {}, { preserveScroll: true })

const f = computed(() => props.funding)
const timeline = computed(() => [
    { label: 'Diajukan Pengadaan', done: !!f.value.submitted_at, at: f.value.submitted_at },
    { label: 'Keputusan Keuangan', done: !!f.value.decided_at, at: f.value.decided_at },
    { label: 'Dana dicairkan', done: !!f.value.disbursed_at, at: f.value.disbursed_at },
    { label: 'LPJ diserahkan', done: !!f.value.settled_at, at: f.value.settled_at },
])
</script>

<template>
    <AppLayout>
        <template #title>Meja Keuangan</template>
        <Link :href="route('finance-desk.index')" class="muted text-xs hover:underline">← Meja Keuangan</Link>

        <div class="mb-6 mt-1 flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="flex items-center gap-2"><span class="docno text-sm">{{ f.number }}</span><StatusBadge kind="funding" :value="f.status" /></p>
                <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ pr.title }}</h2>
                <p class="mt-1 text-sm"><Link :href="route('requests.show', pr.id)" class="docno hover:underline">{{ pr.number }}</Link> <span class="muted">· {{ pr.requester_name }}<template v-if="pr.requester_unit">, {{ pr.requester_unit }}</template></span></p>
            </div>
            <span class="stamp" :class="stamp[0]">{{ stamp[1] }}</span>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <section class="card">
                    <div class="grid divide-y divide-slate-100 sm:grid-cols-3 sm:divide-x sm:divide-y-0 dark:divide-ink-700">
                        <div class="p-5"><p class="muted text-xs">Diajukan</p><p class="tabular mt-1 text-xl font-semibold">{{ formatIDR(f.amount) }}</p></div>
                        <div class="p-5"><p class="muted text-xs">Disetujui</p><p class="tabular mt-1 text-xl font-semibold">{{ f.approved_amount ? formatIDR(f.approved_amount) : '—' }}</p></div>
                        <div class="p-5"><p class="muted text-xs">Sisa dana di Pengadaan</p><p class="tabular mt-1 text-xl font-semibold">{{ f.disbursed_at ? formatIDR(f.remaining) : '—' }}</p></div>
                    </div>
                    <div class="border-t border-slate-100 p-5 text-sm dark:border-ink-700">
                        <p class="muted text-xs">Tujuan penggunaan dana</p><p class="mt-0.5">{{ f.purpose }}</p>
                        <p class="muted mt-2 text-xs">Diajukan oleh {{ f.submitter ?? '—' }}</p>
                    </div>
                </section>

                <section class="card">
                    <div class="card-head"><h3 class="card-title">Dasar pengajuan</h3></div>
                    <div class="overflow-x-auto"><table class="tbl"><thead><tr><th>Barang / jasa</th><th class="text-right">Jumlah</th><th class="text-right">Perkiraan Inventaris</th></tr></thead>
                        <tbody><tr v-for="i in items" :key="i.id"><td>{{ i.name }}</td><td class="tabular text-right">{{ formatNumber(i.quantity) }} {{ i.unit }}</td><td class="tabular text-right">{{ formatIDR(i.estimated_price * i.quantity) }}</td></tr></tbody></table></div>
                    <div v-if="quotation" class="border-t border-slate-100 p-5 text-sm dark:border-ink-700">
                        <p><span class="muted">Vendor terpilih:</span> <strong>{{ quotation.vendor }}</strong> <span class="docno">{{ quotation.number }}</span> — {{ formatIDR(quotation.total) }} (kirim {{ quotation.lead_time_days }} hari)</p>
                        <p v-if="quotation.selection_reason" class="muted mt-1 text-xs">Alasan: {{ quotation.selection_reason }}</p>
                    </div>
                </section>

                <section v-if="f.decided_at" class="card card-pad">
                    <h3 class="card-title mb-2">Keputusan Keuangan</h3>
                    <p class="text-sm"><strong>{{ f.decided_by_name }}</strong> <span class="muted">· {{ formatDate(f.decided_at) }}</span> <span v-if="f.finance_ref" class="docno">· {{ f.finance_ref }}</span></p>
                    <p v-if="f.decision_note" class="mt-2 rounded-lg bg-slate-50 p-3 text-sm dark:bg-ink-800">“{{ f.decision_note }}”</p>
                </section>

                <section v-if="payments.length || f.settled_at" class="card">
                    <div class="card-head"><h3 class="card-title">Pemakaian dana</h3></div>
                    <table class="tbl"><thead><tr><th>Tanggal</th><th>PO</th><th>Metode</th><th class="text-right">Nominal</th></tr></thead>
                        <tbody><tr v-for="p in payments" :key="p.id"><td>{{ formatDate(p.paid_at) }}</td><td class="docno">{{ p.po }}</td><td>{{ p.method }}<span v-if="p.reference" class="muted"> · {{ p.reference }}</span></td><td class="tabular text-right">{{ formatIDR(p.amount) }}</td></tr></tbody></table>
                    <dl v-if="f.settled_at" class="tabular grid grid-cols-3 gap-3 border-t border-slate-100 p-5 text-sm dark:border-ink-700">
                        <div><dt class="muted text-xs">Dana cair</dt><dd class="font-semibold">{{ formatIDR(f.disbursed_amount) }}</dd></div>
                        <div><dt class="muted text-xs">Terpakai</dt><dd class="font-semibold">{{ formatIDR(f.actual_spent) }}</dd></div>
                        <div><dt class="muted text-xs">Dikembalikan</dt><dd class="font-semibold">{{ formatIDR(f.returned_amount) }}</dd></div>
                    </dl>
                </section>

                <section class="card card-pad"><h3 class="card-title mb-3">Dokumen pendukung</h3>
                    <AttachmentPanel type="funding" :record-id="f.id" :attachments="f.attachments" :can-upload="can('funding.submit') || can('finance.decide')" /></section>
            </div>

            <aside class="space-y-6">
                <section class="card card-pad">
                    <h3 class="card-title mb-3">Tindakan</h3>
                    <div class="space-y-2">
                        <template v-if="f.status === 'pending_internal'">
                            <p class="muted text-sm">Nilai ≥ {{ formatIDR(threshold) }}: perlu persetujuan Manager sebelum sampai ke Keuangan.</p>
                            <button v-if="can('funding.approve')" class="btn btn-primary w-full" @click="approveInternal">Setujui internal & teruskan</button>
                        </template>
                        <template v-if="f.status === 'submitted'">
                            <button v-if="can('finance.decide')" class="btn btn-primary w-full" @click="modal = 'decide'">Beri keputusan</button>
                            <p v-else class="muted text-sm">Menunggu keputusan Keuangan. Keputusan masuk lewat halaman ini atau REST API dan otomatis mengabari Pengadaan.</p>
                        </template>
                        <template v-if="f.status === 'approved'">
                            <button v-if="can('finance.disburse')" class="btn btn-primary w-full" @click="modal = 'cash'">Cairkan dana</button>
                            <p v-else class="muted text-sm">Disetujui. Menunggu pencairan oleh Keuangan.</p>
                        </template>
                        <template v-if="f.status === 'disbursed'">
                            <button v-if="can('payment.manage')" class="btn btn-warn w-full" @click="modal = 'lpj'">Serahkan LPJ</button>
                            <p class="muted text-xs">LPJ dibuat setelah tagihan vendor lunas. Dana tersisa otomatis dicatat sebagai pengembalian.</p>
                        </template>
                        <p v-if="['rejected', 'settled', 'cancelled'].includes(f.status)" class="muted text-sm">Tidak ada tindakan lanjutan.</p>
                        <p v-if="f.status === 'revision'" class="muted text-sm">Menunggu Pengadaan merevisi dan mengajukan ulang dari halaman permintaan.</p>
                    </div>
                </section>

                <section class="card">
                    <div class="card-head"><h3 class="card-title">Jejak dokumen</h3></div>
                    <ol class="space-y-3 p-5">
                        <li v-for="s in timeline" :key="s.label" class="flex items-center gap-3 text-sm">
                            <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full border-2" :class="s.done ? 'border-harbor-500 bg-harbor-500 text-white' : 'border-slate-300 dark:border-ink-600'">
                                <svg v-if="s.done" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 111.4-1.4l3.8 3.8 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd" /></svg></span>
                            <span :class="s.done ? '' : 'muted'">{{ s.label }}</span><span v-if="s.at" class="tabular muted ml-auto text-xs">{{ formatDate(s.at) }}</span>
                        </li>
                    </ol>
                </section>

                <section v-if="reports.length" class="card">
                    <div class="card-head"><h3 class="card-title">Laporan terkait</h3></div>
                    <ul class="divide-y divide-slate-100 dark:divide-ink-700"><li v-for="r in reports" :key="r.id" class="px-5 py-3 text-sm"><p>{{ r.subject }}</p><p class="muted text-xs">{{ r.created_at }} · {{ r.direction === 'in' ? 'dari Keuangan' : 'ke Keuangan' }}</p></li></ul>
                </section>
            </aside>
        </div>

        <Modal :show="modal === 'decide'" title="Keputusan Keuangan" @close="close">
            <form class="space-y-4" @submit.prevent="submitDecision">
                <ErrorSummary :errors="dForm.errors" />
                <div class="grid grid-cols-3 gap-2" role="radiogroup" aria-label="Keputusan">
                    <label v-for="o in [['approve', 'Setujui'], ['revision', 'Minta revisi'], ['reject', 'Tolak']]" :key="o[0]" class="cursor-pointer rounded-lg border px-3 py-2 text-center text-sm font-medium"
                        :class="dForm.decision === o[0] ? 'border-harbor-500 bg-harbor-50 text-harbor-800 dark:bg-harbor-900/30 dark:text-harbor-200' : 'border-slate-300 dark:border-ink-600'">
                        <input v-model="dForm.decision" type="radio" :value="o[0]" class="sr-only" />{{ o[1] }}</label>
                </div>
                <Field v-if="dForm.decision === 'approve'" label="Nominal yang disetujui (Rp)" :error="dForm.errors.approved_amount" hint="Boleh lebih kecil dari yang diajukan."><input v-model="dForm.approved_amount" type="number" min="0" step="any" class="input" /></Field>
                <Field :label="dForm.decision === 'approve' ? 'Catatan (opsional)' : 'Alasan / catatan'" :error="dForm.errors.note"><textarea v-model="dForm.note" rows="3" class="input" :required="dForm.decision !== 'approve'"></textarea></Field>
                <Field label="No. dokumen di sistem Keuangan (opsional)"><input v-model="dForm.finance_ref" class="input" /></Field>
                <div class="flex justify-end gap-2"><button type="button" class="btn btn-outline" @click="close">Batal</button><button class="btn btn-primary" :disabled="dForm.processing">Catat keputusan</button></div>
            </form>
        </Modal>

        <Modal :show="modal === 'cash'" title="Cairkan dana" @close="close">
            <form class="space-y-4" @submit.prevent="submitCash">
                <ErrorSummary :errors="cash.errors" />
                <Field label="Nominal dicairkan (Rp)" :error="cash.errors.amount" :hint="`Maksimal ${formatIDR(f.approved_amount)}`"><input v-model="cash.amount" type="number" min="0" step="any" class="input" required /></Field>
                <div class="grid gap-3 sm:grid-cols-2">
                    <Field label="Metode"><select v-model="cash.method" class="input"><option value="transfer">Transfer</option><option value="tunai">Tunai</option><option value="cek">Cek</option></select></Field>
                    <Field label="No. referensi"><input v-model="cash.reference" class="input" /></Field>
                </div>
                <div class="flex justify-end gap-2"><button type="button" class="btn btn-outline" @click="close">Batal</button><button class="btn btn-primary" :disabled="cash.processing">Cairkan</button></div>
            </form>
        </Modal>

        <Modal :show="modal === 'lpj'" title="Pertanggungjawaban dana (LPJ)" @close="close">
            <form class="space-y-4" @submit.prevent="submitLpj">
                <p class="text-sm">Dana cair <strong class="tabular">{{ formatIDR(f.disbursed_amount) }}</strong>, terpakai <strong class="tabular">{{ formatIDR(f.paid) }}</strong>, sisa <strong class="tabular">{{ formatIDR(f.remaining) }}</strong> akan dicatat sebagai pengembalian ke Keuangan.</p>
                <Field label="Catatan" :error="lpj.errors.note"><textarea v-model="lpj.note" rows="3" class="input"></textarea></Field>
                <div class="flex justify-end gap-2"><button type="button" class="btn btn-outline" @click="close">Batal</button><button class="btn btn-warn" :disabled="lpj.processing">Serahkan LPJ</button></div>
            </form>
        </Modal>
    </AppLayout>
</template>
