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
import { formatIDR, formatDate, formatNumber, todayLocal } from '@/utils'

const props = defineProps({ po: Object, vendor: Object, request: Object, funding: Object, items: Array, receipts: Array, payments: Array, attachments: Array })
const can = useCan()
const modal = ref(null)
const close = () => { modal.value = null }
const done = { preserveScroll: true, onSuccess: close }
const owed = computed(() => props.po.total - props.po.paid)
const conditionLabel = { good: 'Baik', partial_damage: 'Sebagian rusak', rejected: 'Ditolak' }

const send = () => { if (confirm(`Kirim PO ${props.po.number} ke ${props.vendor.name}?${props.vendor.email ? ' Email akan dikirim lewat Gmail.' : ''}`)) router.post(route('purchase-orders.send', props.po.id), {}, { preserveScroll: true }) }
const cancel = () => { if (confirm('Batalkan PO ini?')) router.post(route('purchase-orders.cancel', props.po.id), {}, { preserveScroll: true }) }

const rForm = useForm({ received_date: todayLocal(), delivery_note_no: '', condition: 'good', vendor_score: 5, notes: '', lines: [] })
function openReceive() {
    rForm.reset(); rForm.lines = props.items.filter((i) => i.quantity - i.received_quantity > 0.0001).map((i) => ({ po_item_id: i.id, description: i.description, unit: i.unit, outstanding: i.quantity - i.received_quantity, quantity_received: i.quantity - i.received_quantity, quantity_rejected: 0, note: '' }))
    modal.value = 'receive'
}
const submitReceive = () => rForm.transform((d) => ({ ...d, lines: d.lines.map(({ po_item_id, quantity_received, quantity_rejected, note }) => ({ po_item_id, quantity_received, quantity_rejected, note })) })).post(route('purchase-orders.receive', props.po.id), done)

const pForm = useForm({ amount: 0, paid_at: todayLocal(), method: 'transfer', reference: '', notes: '' })
function openPay() { pForm.amount = Math.min(owed.value, props.funding?.remaining ?? owed.value); modal.value = 'pay' }
const submitPay = () => pForm.post(route('purchase-orders.pay', props.po.id), done)
</script>

<template>
    <AppLayout>
        <template #title>Purchase Order</template>
        <Link :href="route('purchase-orders.index')" class="muted text-xs hover:underline">← Semua PO</Link>
        <div class="mb-6 mt-1 flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="flex items-center gap-2"><span class="docno text-sm">{{ po.number }}</span><StatusBadge kind="po" :value="po.status" /></p>
                <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ vendor.name }}</h2>
                <p class="mt-1 text-sm"><Link :href="route('requests.show', request.id)" class="hover:underline">{{ request.title }}</Link> <span class="docno">· {{ request.number }}</span></p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a :href="route('purchase-orders.pdf', po.id)" target="_blank" class="btn btn-outline">Cetak PDF</a>
                <button v-if="po.status === 'draft' && can('po.manage')" class="btn btn-primary" @click="send">Kirim ke vendor</button>
                <button v-if="['sent', 'partial'].includes(po.status) && can('receiving.manage')" class="btn btn-primary" @click="openReceive">Catat penerimaan</button>
                <button v-if="['sent', 'partial', 'received'].includes(po.status) && owed > 0 && can('payment.manage')" class="btn btn-warn" @click="openPay">Bayar vendor</button>
                <button v-if="['draft', 'sent'].includes(po.status) && !receipts.length && can('po.manage')" class="btn btn-outline" @click="cancel">Batalkan PO</button>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <section class="card">
                    <div class="overflow-x-auto"><table class="tbl">
                        <thead><tr><th>Uraian</th><th class="text-right">Dipesan</th><th class="text-right">Diterima</th><th class="text-right">Harga</th><th class="text-right">Jumlah</th></tr></thead>
                        <tbody>
                            <tr v-for="i in items" :key="i.id"><td>{{ i.description }}</td><td class="tabular text-right">{{ formatNumber(i.quantity) }} {{ i.unit }}</td>
                                <td class="tabular text-right" :class="i.received_quantity >= i.quantity ? 'text-green-700 dark:text-green-400' : ''">{{ formatNumber(i.received_quantity) }}</td>
                                <td class="tabular text-right">{{ formatIDR(i.unit_price) }}</td><td class="tabular text-right">{{ formatIDR(i.unit_price * i.quantity) }}</td></tr>
                            <tr><td colspan="4" class="text-right">Subtotal</td><td class="tabular text-right">{{ formatIDR(po.subtotal) }}</td></tr>
                            <tr><td colspan="4" class="text-right">PPN {{ po.tax_percent }}%</td><td class="tabular text-right">{{ formatIDR(po.tax_amount) }}</td></tr>
                            <tr class="font-semibold"><td colspan="4" class="text-right">Total</td><td class="tabular text-right">{{ formatIDR(po.total) }}</td></tr>
                        </tbody></table></div>
                </section>

                <section class="card">
                    <div class="card-head"><h3 class="card-title">Penerimaan barang / jasa</h3></div>
                    <div v-if="receipts.length" class="divide-y divide-slate-100 dark:divide-ink-700">
                        <div v-for="g in receipts" :key="g.id" class="p-5">
                            <p class="flex flex-wrap items-center gap-2 text-sm"><span class="docno font-semibold">{{ g.number }}</span><span>{{ formatDate(g.received_date) }}</span>
                                <span class="badge" :class="g.condition === 'good' ? 'badge-green' : 'badge-amber'">{{ conditionLabel[g.condition] }}</span>
                                <span v-if="g.delivery_note_no" class="muted text-xs">Surat jalan {{ g.delivery_note_no }}</span></p>
                            <p class="muted mt-1 text-xs">Diterima oleh {{ g.receiver }}<span v-if="g.vendor_score"> · nilai vendor {{ g.vendor_score }}/5</span></p>
                            <p v-if="g.notes" class="mt-1 text-sm">{{ g.notes }}</p>
                            <div class="mt-3"><AttachmentPanel type="receipt" :record-id="g.id" :attachments="g.attachments" :can-upload="can('receiving.manage')" /></div>
                        </div>
                    </div>
                    <p v-else class="muted px-5 py-8 text-center text-sm">Belum ada penerimaan.</p>
                </section>

                <section class="card">
                    <div class="card-head"><h3 class="card-title">Pembayaran ke vendor</h3></div>
                    <table v-if="payments.length" class="tbl"><thead><tr><th>Tanggal</th><th>Metode</th><th>Oleh</th><th class="text-right">Nominal</th></tr></thead>
                        <tbody><tr v-for="p in payments" :key="p.id"><td>{{ formatDate(p.paid_at) }}</td><td>{{ p.method }}<span v-if="p.reference" class="muted"> · {{ p.reference }}</span></td><td>{{ p.payer }}</td><td class="tabular text-right">{{ formatIDR(p.amount) }}</td></tr></tbody></table>
                    <p v-else class="muted px-5 py-8 text-center text-sm">Belum ada pembayaran.</p>
                </section>
            </div>

            <aside class="space-y-6">
                <section class="card card-pad">
                    <h3 class="card-title mb-3">Pembayaran</h3>
                    <div class="mb-3 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-ink-800"><div class="h-full rounded-full bg-harbor-500" :style="{ width: Math.min(100, (po.paid / po.total) * 100) + '%' }"></div></div>
                    <dl class="tabular space-y-1.5 text-sm"><div class="flex justify-between"><dt class="muted">Total PO</dt><dd>{{ formatIDR(po.total) }}</dd></div>
                        <div class="flex justify-between"><dt class="muted">Terbayar</dt><dd>{{ formatIDR(po.paid) }}</dd></div>
                        <div class="flex justify-between font-semibold"><dt>Sisa tagihan</dt><dd>{{ formatIDR(owed) }}</dd></div>
                        <div v-if="funding" class="flex justify-between border-t border-slate-100 pt-2 dark:border-ink-700"><dt class="muted">Sisa dana ({{ funding.number }})</dt><dd>{{ formatIDR(funding.remaining) }}</dd></div></dl>
                </section>
                <section class="card card-pad">
                    <h3 class="card-title mb-3">Vendor & pengiriman</h3>
                    <dl class="space-y-2 text-sm">
                        <div><dt class="muted text-xs">Kontak</dt><dd>{{ vendor.contact_person ?? '—' }}<br /><span class="muted text-xs">{{ vendor.email }} {{ vendor.phone }}</span></dd></div>
                        <div><dt class="muted text-xs">Tanggal pesan</dt><dd>{{ formatDate(po.order_date) }}</dd></div>
                        <div><dt class="muted text-xs">Diharapkan tiba</dt><dd>{{ formatDate(po.expected_date) }}</dd></div>
                        <div><dt class="muted text-xs">Pembayaran</dt><dd>{{ po.payment_terms || '—' }}</dd></div>
                        <div v-if="po.delivery_address"><dt class="muted text-xs">Alamat kirim</dt><dd class="whitespace-pre-line">{{ po.delivery_address }}</dd></div>
                        <div v-if="po.sent_at"><dt class="muted text-xs">Dikirim ke vendor</dt><dd>{{ po.sent_at }}</dd></div>
                    </dl>
                </section>
                <section class="card card-pad"><h3 class="card-title mb-3">Dokumen PO</h3><AttachmentPanel type="po" :record-id="po.id" :attachments="attachments" :can-upload="can('po.manage') || can('payment.manage')" /></section>
            </aside>
        </div>

        <Modal :show="modal === 'receive'" title="Catat penerimaan barang / jasa" max-width="max-w-2xl" @close="close">
            <form class="space-y-4" @submit.prevent="submitReceive">
                <ErrorSummary :errors="rForm.errors" />
                <div class="grid gap-3 sm:grid-cols-3">
                    <Field label="Tanggal diterima"><input v-model="rForm.received_date" type="date" class="input" required /></Field>
                    <Field label="No. surat jalan"><input v-model="rForm.delivery_note_no" class="input" /></Field>
                    <Field label="Kondisi"><select v-model="rForm.condition" class="input"><option value="good">Baik</option><option value="partial_damage">Sebagian rusak</option><option value="rejected">Ditolak</option></select></Field>
                </div>
                <div class="rounded-lg border border-slate-200 dark:border-ink-700"><table class="tbl"><thead><tr><th>Item</th><th class="w-28">Diterima baik</th><th class="w-28">Ditolak</th></tr></thead>
                    <tbody><tr v-for="l in rForm.lines" :key="l.po_item_id"><td>{{ l.description }}<p class="muted text-xs">sisa pesanan {{ formatNumber(l.outstanding) }} {{ l.unit }}</p></td>
                        <td><input v-model="l.quantity_received" type="number" min="0" step="any" class="input" :aria-label="`Diterima ${l.description}`" /></td>
                        <td><input v-model="l.quantity_rejected" type="number" min="0" step="any" class="input" :aria-label="`Ditolak ${l.description}`" /></td></tr></tbody></table></div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <Field label="Nilai kinerja vendor (1–5)" class="sm:col-span-1"><select v-model="rForm.vendor_score" class="input"><option v-for="n in [5, 4, 3, 2, 1]" :key="n" :value="n">{{ n }}</option></select></Field>
                    <Field label="Catatan" class="sm:col-span-2"><input v-model="rForm.notes" class="input" /></Field>
                </div>
                <div class="flex justify-end gap-2"><button type="button" class="btn btn-outline" @click="close">Batal</button><button class="btn btn-primary" :disabled="rForm.processing">Simpan penerimaan</button></div>
            </form>
        </Modal>

        <Modal :show="modal === 'pay'" title="Bayar vendor" @close="close">
            <form class="space-y-4" @submit.prevent="submitPay">
                <ErrorSummary :errors="pForm.errors" />
                <Field label="Nominal (Rp)" :error="pForm.errors.amount" :hint="`Sisa tagihan ${formatIDR(owed)} · sisa dana ${formatIDR(funding?.remaining ?? 0)}`"><input v-model="pForm.amount" type="number" min="0" step="any" class="input" required /></Field>
                <div class="grid gap-3 sm:grid-cols-3">
                    <Field label="Tanggal"><input v-model="pForm.paid_at" type="date" class="input" required /></Field>
                    <Field label="Metode"><select v-model="pForm.method" class="input"><option value="transfer">Transfer</option><option value="tunai">Tunai</option><option value="cek">Cek</option></select></Field>
                    <Field label="No. referensi"><input v-model="pForm.reference" class="input" /></Field>
                </div>
                <Field label="Catatan"><input v-model="pForm.notes" class="input" /></Field>
                <div class="flex justify-end gap-2"><button type="button" class="btn btn-outline" @click="close">Batal</button><button class="btn btn-warn" :disabled="pForm.processing">Catat pembayaran</button></div>
            </form>
        </Modal>
    </AppLayout>
</template>
