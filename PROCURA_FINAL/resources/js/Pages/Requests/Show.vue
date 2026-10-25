<script setup>
import { computed, reactive, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import RouteTracker from '@/Components/RouteTracker.vue'
import Modal from '@/Components/Modal.vue'
import Field from '@/Components/Field.vue'
import ErrorSummary from '@/Components/ErrorSummary.vue'
import AttachmentPanel from '@/Components/AttachmentPanel.vue'
import { useCan } from '@/permissions'
import { formatIDR, formatDate, formatNumber, todayLocal } from '@/utils'

const props = defineProps({
    pr: Object, items: Array, quotations: Array, fundings: Array, orders: Array, handovers: Array, events: Array,
    attachments: Array, vendors: Array, suggestedAmount: Number, threshold: Number,
})
const can = useCan()
const modal = ref(null)
const open = (m) => { modal.value = m }
const close = () => { modal.value = null }
const done = { preserveScroll: true, onSuccess: close }

const selected = computed(() => props.quotations.find((q) => q.is_selected))
const lowest = computed(() => props.quotations.length > 1 ? Math.min(...props.quotations.map((q) => q.total)) : null)
const activeFunding = computed(() => props.fundings.find((f) => !['rejected', 'cancelled'].includes(f.status)))
const activePo = computed(() => props.orders.find((o) => o.status !== 'cancelled'))
const canQuote = computed(() => can('quotation.manage') && ['submitted', 'reviewing', 'sourcing'].includes(props.pr.status))
const canAskFunds = computed(() => can('funding.submit') && props.pr.status === 'sourcing' && selected.value && !activeFunding.value)
const canCancel = computed(() => can('request.manage') && !props.pr.closed && !['received', 'handed_over'].includes(props.pr.status))

// --- form penolakan / pembatalan ---
const reasonForm = useForm({ reason: '' })
const reasonMode = ref('reject')
function openReason(mode) { reasonMode.value = mode; reasonForm.reset(); open('reason') }
const submitReason = () => reasonForm.post(route(reasonMode.value === 'reject' ? 'requests.reject' : 'requests.cancel', props.pr.id), done)

// --- penawaran ---
const qForm = useForm({ vendor_id: '', vendor_ref: '', quoted_date: todayLocal(), valid_until: '', lead_time_days: 7, payment_terms: '', notes: '', prices: {} })
function openQuote() { qForm.reset(); qForm.prices = Object.fromEntries(props.items.map((i) => [i.id, ''])); open('quote') }
function onVendor() {
    const v = props.vendors.find((x) => x.id === Number(qForm.vendor_id))
    if (v && !qForm.payment_terms) qForm.payment_terms = `Net ${v.payment_terms_days} hari`
}
const qTotal = computed(() => props.items.reduce((a, i) => a + (Number(qForm.prices[i.id]) || 0) * i.quantity, 0))
const submitQuote = () => qForm.post(route('requests.quotations.store', props.pr.id), done)

const pickTarget = ref(null)
const pickForm = useForm({ reason: '' })
function openPick(q) {
    pickTarget.value = q
    pickForm.reason = q.total === lowest.value ? 'Harga terendah dan memenuhi spesifikasi' : ''
    open('pick')
}
const submitPick = () => pickForm.post(route('requests.quotations.select', [props.pr.id, pickTarget.value.id]), done)
const removeQuote = (q) => { if (confirm(`Hapus penawaran ${q.vendor.name}?`)) router.delete(route('requests.quotations.destroy', [props.pr.id, q.id]), { preserveScroll: true }) }

// --- dana ---
const fForm = useForm({ amount: 0, purpose: '' })
function openFunding() { fForm.amount = props.suggestedAmount; fForm.purpose = `Pembelian: ${props.pr.title}`; open('funding') }
const needsInternal = computed(() => Number(fForm.amount) >= props.threshold && !can('funding.approve'))
const submitFunding = () => fForm.post(route('requests.funding.store', props.pr.id), done)
const rForm = useForm({ amount: 0, purpose: '' })
const resubTarget = ref(null)
function openResubmit(f) { resubTarget.value = f; rForm.amount = f.amount; rForm.purpose = f.purpose; open('resubmit') }
const submitResubmit = () => rForm.post(route('finance-desk.resubmit', resubTarget.value.id), done)
const approveInternal = (f) => router.post(route('finance-desk.approve-internal', f.id), {}, { preserveScroll: true })

// --- PO ---
const poForm = useForm({ order_date: todayLocal(), expected_date: '', tax_percent: 11, payment_terms: '', delivery_address: '', notes: '' })
function openPo() { poForm.expected_date = ''; poForm.payment_terms = selected.value?.payment_terms ?? ''; open('po') }
const submitPo = () => poForm.post(route('requests.po.store', props.pr.id), done)

// --- serah terima ---
const hForm = useForm({ handover_date: todayLocal(), receiver_name: '', notes: '' })
const submitHandover = () => hForm.post(route('requests.handover', props.pr.id), done)

const review = () => router.post(route('requests.review', props.pr.id), {}, { preserveScroll: true })
</script>

<template>
    <AppLayout>
        <template #title>Permintaan</template>

        <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <Link :href="route('requests.index')" class="muted text-xs hover:underline">← Semua permintaan</Link>
                <h2 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ pr.title }}</h2>
                <p class="mt-1 flex flex-wrap items-center gap-2 text-sm">
                    <span class="docno">{{ pr.number }}</span><StatusBadge :value="pr.status" /><StatusBadge kind="priority" :value="pr.priority" />
                    <span v-if="pr.external_ref" class="muted">· Ref Inventaris {{ pr.external_ref }}</span>
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button v-if="pr.status === 'submitted' && can('request.manage')" class="btn btn-primary" @click="review">Mulai tinjau</button>
                <button v-if="canQuote" class="btn btn-primary" @click="openQuote">+ Catat penawaran</button>
                <button v-if="canAskFunds" class="btn btn-warn" @click="openFunding">Ajukan dana ke Keuangan</button>
                <button v-if="pr.status === 'funded' && can('po.manage')" class="btn btn-primary" @click="openPo">Buat Purchase Order</button>
                <button v-if="pr.status === 'received' && can('handover.manage')" class="btn btn-primary" @click="open('handover')">Serahkan ke Inventaris</button>
                <button v-if="['submitted', 'reviewing', 'sourcing'].includes(pr.status) && can('request.manage')" class="btn btn-outline" @click="openReason('reject')">Tolak</button>
                <button v-if="canCancel" class="btn btn-outline" @click="openReason('cancel')">Batalkan</button>
            </div>
        </div>

        <div class="card card-pad mb-6"><RouteTracker :station="pr.station" :status="pr.status" /></div>

        <div v-if="pr.rejection_reason" class="mb-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-200">
            <strong>{{ pr.status === 'cancelled' ? 'Dibatalkan' : 'Ditolak' }}:</strong> {{ pr.rejection_reason }}
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <!-- Barang & jasa -->
                <section class="card">
                    <div class="card-head"><h3 class="card-title">Barang / jasa diminta</h3><span class="muted text-xs">Perkiraan {{ formatIDR(pr.estimated_total) }}</span></div>
                    <div class="overflow-x-auto"><table class="tbl">
                        <thead><tr><th>Nama</th><th class="text-right">Jumlah</th><th class="text-right">Harga perkiraan</th><th class="text-right">Subtotal</th></tr></thead>
                        <tbody><tr v-for="i in items" :key="i.id">
                            <td><p class="font-medium">{{ i.name }}</p><p v-if="i.specification" class="muted text-xs">{{ i.specification }}</p></td>
                            <td class="tabular text-right">{{ formatNumber(i.quantity) }} {{ i.unit }}</td>
                            <td class="tabular text-right">{{ formatIDR(i.estimated_price) }}</td>
                            <td class="tabular text-right">{{ formatIDR(i.estimated_price * i.quantity) }}</td>
                        </tr></tbody>
                    </table></div>
                </section>

                <!-- Perbandingan penawaran -->
                <section class="card">
                    <div class="card-head">
                        <h3 class="card-title">Perbandingan penawaran vendor</h3>
                        <button v-if="canQuote" class="btn btn-outline btn-sm" @click="openQuote">+ Penawaran</button>
                    </div>
                    <div v-if="quotations.length" class="overflow-x-auto">
                        <table class="tbl">
                            <thead><tr>
                                <th>Harga satuan</th>
                                <th v-for="q in quotations" :key="q.id" class="min-w-[10rem]" :class="q.is_selected ? '!bg-harbor-50 dark:!bg-harbor-900/30' : ''">
                                    <p class="text-slate-800 dark:text-slate-100">{{ q.vendor.name }}</p>
                                    <p class="docno font-normal">{{ q.number }}<span v-if="q.vendor.rating"> · ★ {{ q.vendor.rating }}</span></p>
                                </th>
                            </tr></thead>
                            <tbody>
                                <tr v-for="i in items" :key="i.id">
                                    <td class="text-xs">{{ i.name }} <span class="muted">× {{ formatNumber(i.quantity) }}</span></td>
                                    <td v-for="q in quotations" :key="q.id" class="tabular text-right" :class="q.is_selected ? 'bg-harbor-50/60 dark:bg-harbor-900/20' : ''">{{ formatIDR(q.prices[i.id]) }}</td>
                                </tr>
                                <tr class="font-semibold">
                                    <td>Total (sebelum PPN)</td>
                                    <td v-for="q in quotations" :key="q.id" class="tabular text-right" :class="q.is_selected ? 'bg-harbor-50/60 dark:bg-harbor-900/20' : ''">
                                        {{ formatIDR(q.total) }}
                                        <p v-if="q.total === lowest" class="text-[11px] font-medium text-green-700 dark:text-green-400">Terendah</p>
                                    </td>
                                </tr>
                                <tr class="text-xs"><td class="muted">Waktu kirim</td><td v-for="q in quotations" :key="q.id" class="text-right">{{ q.lead_time_days }} hari</td></tr>
                                <tr class="text-xs"><td class="muted">Pembayaran</td><td v-for="q in quotations" :key="q.id" class="text-right">{{ q.payment_terms || '—' }}</td></tr>
                                <tr class="text-xs"><td class="muted">Berlaku s/d</td><td v-for="q in quotations" :key="q.id" class="text-right">{{ formatDate(q.valid_until) }}</td></tr>
                                <tr v-if="canQuote || selected"><td></td>
                                    <td v-for="q in quotations" :key="q.id" class="text-right">
                                        <span v-if="q.is_selected" class="badge badge-green">✓ Terpilih</span>
                                        <div v-else-if="canQuote" class="flex justify-end gap-1">
                                            <button class="btn btn-primary btn-sm" @click="openPick(q)">Pilih</button>
                                            <button class="btn btn-ghost btn-sm text-rose-600" @click="removeQuote(q)">Hapus</button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-if="selected" class="border-t border-slate-100 px-5 py-3 text-xs dark:border-ink-700"><span class="muted">Alasan memilih {{ selected.vendor.name }}:</span> {{ selected.selection_reason }}</p>
                    </div>
                    <p v-else class="muted px-5 py-8 text-center text-sm">Belum ada penawaran. Minta penawaran ke minimal dua vendor agar perbandingannya berarti.</p>
                    <div v-for="q in quotations" :key="'att' + q.id" v-show="q.attachments.length || can('quotation.manage')" class="border-t border-slate-100 px-5 py-3 dark:border-ink-700">
                        <p class="mb-1 text-xs font-medium">Dokumen penawaran {{ q.vendor.name }}</p>
                        <AttachmentPanel type="quotation" :record-id="q.id" :attachments="q.attachments" :can-upload="can('quotation.manage')" />
                    </div>
                </section>

                <!-- Pendanaan -->
                <section class="card">
                    <div class="card-head"><h3 class="card-title">Pendanaan dari Keuangan</h3>
                        <button v-if="canAskFunds" class="btn btn-warn btn-sm" @click="openFunding">Ajukan dana</button></div>
                    <div v-if="fundings.length" class="divide-y divide-slate-100 dark:divide-ink-700">
                        <div v-for="f in fundings" :key="f.id" class="p-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="flex items-center gap-2"><Link :href="route('finance-desk.show', f.id)" class="docno text-sm font-semibold hover:underline">{{ f.number }}</Link><StatusBadge kind="funding" :value="f.status" /></p>
                                    <p class="tabular mt-1 text-lg font-semibold">{{ formatIDR(f.amount) }}</p>
                                    <p class="muted mt-0.5 text-xs">{{ f.purpose }}</p>
                                </div>
                                <div class="flex gap-2">
                                    <button v-if="f.status === 'pending_internal' && can('funding.approve')" class="btn btn-primary btn-sm" @click="approveInternal(f)">Setujui internal</button>
                                    <button v-if="f.status === 'revision' && can('funding.submit')" class="btn btn-warn btn-sm" @click="openResubmit(f)">Revisi & ajukan ulang</button>
                                </div>
                            </div>
                            <p v-if="f.status === 'pending_internal'" class="mt-2 text-xs text-amber-700 dark:text-amber-300">Nilai ≥ {{ formatIDR(threshold) }} — perlu persetujuan Manager sebelum diteruskan ke Keuangan.</p>
                            <div v-if="f.decided_at" class="mt-3 rounded-lg bg-slate-50 p-3 text-sm dark:bg-ink-800">
                                <p class="text-xs"><span class="muted">Keputusan Keuangan oleh</span> <strong>{{ f.decided_by_name }}</strong> <span v-if="f.finance_ref" class="docno">· {{ f.finance_ref }}</span></p>
                                <p v-if="f.approved_amount" class="tabular mt-0.5">Disetujui {{ formatIDR(f.approved_amount) }}</p>
                                <p v-if="f.decision_note" class="mt-1">“{{ f.decision_note }}”</p>
                            </div>
                            <dl v-if="f.disbursed_at" class="tabular mt-3 grid grid-cols-3 gap-3 text-xs">
                                <div><dt class="muted">Dana cair</dt><dd class="font-medium">{{ formatIDR(f.disbursed_amount) }}</dd></div>
                                <div><dt class="muted">Sisa belum dipakai</dt><dd class="font-medium">{{ formatIDR(f.remaining) }}</dd></div>
                                <div v-if="f.settled_at"><dt class="muted">Dikembalikan (LPJ)</dt><dd class="font-medium">{{ formatIDR(f.returned_amount) }}</dd></div>
                            </dl>
                            <div class="mt-3" v-if="f.attachments.length || can('funding.submit')"><AttachmentPanel type="funding" :record-id="f.id" :attachments="f.attachments" :can-upload="can('funding.submit')" /></div>
                        </div>
                    </div>
                    <p v-else class="muted px-5 py-8 text-center text-sm">{{ selected ? 'Penawaran sudah dipilih. Ajukan dana ke Keuangan untuk melanjutkan.' : 'Pilih satu penawaran vendor dulu, lalu ajukan dana ke Keuangan.' }}</p>
                </section>

                <!-- PO & serah terima -->
                <section v-if="orders.length || handovers.length" class="card">
                    <div class="card-head"><h3 class="card-title">Pemesanan & serah terima</h3></div>
                    <div class="divide-y divide-slate-100 dark:divide-ink-700">
                        <div v-for="o in orders" :key="o.id" class="flex flex-wrap items-center justify-between gap-2 px-5 py-3.5">
                            <div><Link :href="route('purchase-orders.show', o.id)" class="docno text-sm font-semibold hover:underline">{{ o.number }}</Link> <span class="text-sm">· {{ o.vendor }}</span>
                                <p class="muted text-xs">Perkiraan tiba {{ formatDate(o.expected_date) }}</p></div>
                            <div class="flex items-center gap-3"><span class="tabular text-sm font-medium">{{ formatIDR(o.total) }}</span><StatusBadge kind="po" :value="o.status" /></div>
                        </div>
                        <div v-for="h in handovers" :key="'h' + h.id" class="px-5 py-3.5 text-sm">
                            <p><span class="docno font-semibold">{{ h.number }}</span> · diserahkan {{ formatDate(h.handover_date) }} kepada <strong>{{ h.receiver_name }}</strong>
                                <span class="badge ml-2" :class="h.status === 'confirmed' ? 'badge-green' : 'badge-amber'">{{ h.status === 'confirmed' ? 'Dikonfirmasi Inventaris' : 'Menunggu konfirmasi Inventaris' }}</span></p>
                            <p v-if="h.notes" class="muted mt-0.5 text-xs">{{ h.notes }}</p>
                        </div>
                    </div>
                </section>

                <section class="card card-pad"><h3 class="card-title mb-3">Lampiran permintaan</h3>
                    <AttachmentPanel type="request" :record-id="pr.id" :attachments="attachments" :can-upload="can('request.manage') || can('request.create')" /></section>
            </div>

            <!-- Kolom kanan -->
            <aside class="space-y-6">
                <section class="card card-pad">
                    <h3 class="card-title mb-3">Pemohon</h3>
                    <dl class="space-y-2 text-sm">
                        <div><dt class="muted text-xs">Nama</dt><dd>{{ pr.requester_name }}</dd></div>
                        <div v-if="pr.requester_unit"><dt class="muted text-xs">Unit</dt><dd>{{ pr.requester_unit }}</dd></div>
                        <div><dt class="muted text-xs">Kategori</dt><dd>{{ $page.props.workflow.categories[pr.category] ?? pr.category }}</dd></div>
                        <div><dt class="muted text-xs">Dibutuhkan</dt><dd>{{ formatDate(pr.needed_date) }}</dd></div>
                        <div><dt class="muted text-xs">Ditangani</dt><dd>{{ pr.handler ?? 'Belum ada' }}</dd></div>
                        <div v-if="pr.justification"><dt class="muted text-xs">Alasan</dt><dd class="whitespace-pre-line">{{ pr.justification }}</dd></div>
                    </dl>
                </section>
                <section class="card">
                    <div class="card-head"><h3 class="card-title">Lini masa</h3></div>
                    <ol class="relative space-y-4 p-5">
                        <span class="absolute bottom-5 left-[26px] top-6 w-px bg-slate-200 dark:bg-ink-700" aria-hidden="true"></span>
                        <li v-for="e in events" :key="e.id" class="relative flex gap-3">
                            <span class="z-10 mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full bg-harbor-500 ring-4 ring-white dark:ring-ink-900"></span>
                            <div class="min-w-0 text-sm"><p>{{ e.description }}</p><p class="muted text-xs">{{ e.created_at }} · {{ e.user?.name ?? e.actor_label ?? 'Sistem' }}</p></div>
                        </li>
                    </ol>
                </section>
            </aside>
        </div>

        <!-- ===== Modal ===== -->
        <Modal :show="modal === 'reason'" :title="reasonMode === 'reject' ? 'Tolak permintaan' : 'Batalkan permintaan'" @close="close">
            <form class="space-y-4" @submit.prevent="submitReason">
                <Field label="Alasan (akan diteruskan ke Inventaris)" :error="reasonForm.errors.reason"><textarea v-model="reasonForm.reason" rows="3" class="input" required></textarea></Field>
                <div class="flex justify-end gap-2"><button type="button" class="btn btn-outline" @click="close">Kembali</button><button class="btn btn-danger" :disabled="reasonForm.processing">{{ reasonMode === 'reject' ? 'Tolak' : 'Batalkan' }}</button></div>
            </form>
        </Modal>

        <Modal :show="modal === 'quote'" title="Catat penawaran vendor" max-width="max-w-2xl" @close="close">
            <form class="space-y-4" @submit.prevent="submitQuote">
                <ErrorSummary :errors="qForm.errors" />
                <div class="grid gap-3 sm:grid-cols-2">
                    <Field label="Vendor" :error="qForm.errors.vendor_id"><select v-model="qForm.vendor_id" class="input" required @change="onVendor"><option value="" disabled>Pilih vendor…</option><option v-for="v in vendors" :key="v.id" :value="v.id">{{ v.name }}</option></select></Field>
                    <Field label="No. surat penawaran vendor"><input v-model="qForm.vendor_ref" class="input" /></Field>
                    <Field label="Tanggal penawaran"><input v-model="qForm.quoted_date" type="date" class="input" required /></Field>
                    <Field label="Berlaku sampai" :error="qForm.errors.valid_until"><input v-model="qForm.valid_until" type="date" class="input" /></Field>
                    <Field label="Waktu kirim (hari)"><input v-model="qForm.lead_time_days" type="number" min="0" class="input" required /></Field>
                    <Field label="Syarat pembayaran"><input v-model="qForm.payment_terms" class="input" placeholder="mis. Net 14 hari" /></Field>
                </div>
                <div class="rounded-lg border border-slate-200 dark:border-ink-700">
                    <table class="tbl"><thead><tr><th>Item</th><th class="text-right">Qty</th><th class="w-44">Harga satuan (Rp)</th></tr></thead>
                        <tbody><tr v-for="i in items" :key="i.id"><td>{{ i.name }}<p class="muted text-xs">perkiraan {{ formatIDR(i.estimated_price) }}</p></td><td class="tabular text-right">{{ formatNumber(i.quantity) }}</td>
                            <td><input v-model="qForm.prices[i.id]" type="number" min="0" step="any" class="input" required :aria-label="`Harga ${i.name}`" /></td></tr></tbody></table>
                    <p class="tabular border-t border-slate-100 px-4 py-2 text-right text-sm font-semibold dark:border-ink-700">Total {{ formatIDR(qTotal) }}</p>
                </div>
                <Field label="Catatan"><textarea v-model="qForm.notes" rows="2" class="input"></textarea></Field>
                <div class="flex justify-end gap-2"><button type="button" class="btn btn-outline" @click="close">Batal</button><button class="btn btn-primary" :disabled="qForm.processing">Simpan penawaran</button></div>
            </form>
        </Modal>

        <Modal :show="modal === 'pick'" :title="`Pilih ${pickTarget?.vendor.name ?? 'vendor'}`" @close="close">
            <form class="space-y-4" @submit.prevent="submitPick">
                <Field label="Alasan memilih vendor ini" :error="pickForm.errors.reason" hint="Tercatat sebagai dasar keputusan (berguna saat diaudit)."><textarea v-model="pickForm.reason" rows="3" class="input" required></textarea></Field>
                <div class="flex justify-end gap-2"><button type="button" class="btn btn-outline" @click="close">Batal</button><button class="btn btn-primary" :disabled="pickForm.processing">Pilih penawaran</button></div>
            </form>
        </Modal>

        <Modal :show="modal === 'funding'" title="Ajukan dana ke Keuangan" @close="close">
            <form class="space-y-4" @submit.prevent="submitFunding">
                <ErrorSummary :errors="fForm.errors" />
                <Field label="Nominal yang diajukan (Rp)" :error="fForm.errors.amount" :hint="`Saran: penawaran terpilih + PPN = ${formatIDR(suggestedAmount)}`"><input v-model="fForm.amount" type="number" min="0" step="any" class="input" required /></Field>
                <Field label="Tujuan penggunaan dana" :error="fForm.errors.purpose"><textarea v-model="fForm.purpose" rows="3" class="input" required></textarea></Field>
                <p v-if="needsInternal" class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-200">Nominal ≥ {{ formatIDR(threshold) }}: pengajuan ditahan dulu untuk persetujuan Manager, baru diteruskan ke Keuangan.</p>
                <div class="flex justify-end gap-2"><button type="button" class="btn btn-outline" @click="close">Batal</button><button class="btn btn-warn" :disabled="fForm.processing">Ajukan</button></div>
            </form>
        </Modal>

        <Modal :show="modal === 'resubmit'" title="Revisi & ajukan ulang" @close="close">
            <form class="space-y-4" @submit.prevent="submitResubmit">
                <p v-if="resubTarget?.decision_note" class="rounded-lg bg-violet-50 px-3 py-2 text-sm text-violet-900 dark:bg-violet-950 dark:text-violet-200">Catatan Keuangan: “{{ resubTarget.decision_note }}”</p>
                <Field label="Nominal (Rp)" :error="rForm.errors.amount"><input v-model="rForm.amount" type="number" min="0" step="any" class="input" required /></Field>
                <Field label="Tujuan / penjelasan" :error="rForm.errors.purpose"><textarea v-model="rForm.purpose" rows="3" class="input" required></textarea></Field>
                <div class="flex justify-end gap-2"><button type="button" class="btn btn-outline" @click="close">Batal</button><button class="btn btn-warn" :disabled="rForm.processing">Ajukan ulang</button></div>
            </form>
        </Modal>

        <Modal :show="modal === 'po'" title="Buat Purchase Order" @close="close">
            <form class="space-y-4" @submit.prevent="submitPo">
                <ErrorSummary :errors="poForm.errors" />
                <p class="muted text-sm">Vendor & item mengikuti penawaran terpilih ({{ selected?.vendor.name }}).</p>
                <div class="grid gap-3 sm:grid-cols-3">
                    <Field label="Tanggal pesan"><input v-model="poForm.order_date" type="date" class="input" required /></Field>
                    <Field label="Diharapkan tiba" :error="poForm.errors.expected_date"><input v-model="poForm.expected_date" type="date" class="input" /></Field>
                    <Field label="PPN (%)"><input v-model="poForm.tax_percent" type="number" min="0" max="100" step="any" class="input" required /></Field>
                </div>
                <Field label="Syarat pembayaran"><input v-model="poForm.payment_terms" class="input" /></Field>
                <Field label="Alamat pengiriman"><textarea v-model="poForm.delivery_address" rows="2" class="input"></textarea></Field>
                <Field label="Catatan untuk vendor"><textarea v-model="poForm.notes" rows="2" class="input"></textarea></Field>
                <div class="flex justify-end gap-2"><button type="button" class="btn btn-outline" @click="close">Batal</button><button class="btn btn-primary" :disabled="poForm.processing">Buat PO (draf)</button></div>
            </form>
        </Modal>

        <Modal :show="modal === 'handover'" title="Serah terima ke Inventaris" @close="close">
            <form class="space-y-4" @submit.prevent="submitHandover">
                <Field label="Tanggal serah terima"><input v-model="hForm.handover_date" type="date" class="input" required /></Field>
                <Field label="Diterima oleh (di Inventaris)" :error="hForm.errors.receiver_name"><input v-model="hForm.receiver_name" class="input" required /></Field>
                <Field label="Catatan"><textarea v-model="hForm.notes" rows="2" class="input"></textarea></Field>
                <div class="flex justify-end gap-2"><button type="button" class="btn btn-outline" @click="close">Batal</button><button class="btn btn-primary" :disabled="hForm.processing">Catat serah terima</button></div>
            </form>
        </Modal>
    </AppLayout>
</template>
