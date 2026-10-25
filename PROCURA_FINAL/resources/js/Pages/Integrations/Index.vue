<script setup>
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Modal from '@/Components/Modal.vue'
import Field from '@/Components/Field.vue'
import ErrorSummary from '@/Components/ErrorSummary.vue'

const props = defineProps({ clients: Array, deliveries: Array, scopes: Object, events: Object, baseUrl: String, newToken: Object, newSecret: String })

const presets = {
    inventaris: { name: 'Sistem Inventaris', scopes: ['requests:write', 'requests:read', 'handover:confirm', 'catalog:read'], events: ['request.status_changed', 'purchase_order.sent', 'goods.received', 'handover.created'] },
    keuangan: { name: 'Sistem Keuangan', scopes: ['funding:read', 'funding:decide'], events: ['funding.submitted', 'funding.settled'] },
    sdm: { name: 'Sistem SDM', scopes: ['requests:read'], events: [] },
    lainnya: { name: '', scopes: ['requests:read'], events: [] },
}
const open = ref(false)
const editing = ref(null)
const form = useForm({ name: '', system: 'inventaris', scopes: [], webhook_url: '', webhook_events: [], is_active: true })
function openForm(c = null) {
    editing.value = c; form.clearErrors()
    if (c) form.defaults({ name: c.name, system: c.system, scopes: [...c.scopes], webhook_url: c.webhook_url ?? '', webhook_events: [...c.webhook_events], is_active: c.is_active }).reset()
    else form.defaults({ name: presets.inventaris.name, system: 'inventaris', scopes: [...presets.inventaris.scopes], webhook_url: '', webhook_events: [...presets.inventaris.events], is_active: true }).reset()
    open.value = true
}
function applyPreset() { const p = presets[form.system]; form.scopes = [...p.scopes]; form.webhook_events = [...p.events]; if (!editing.value) form.name = p.name }
function submit() {
    const opts = { preserveScroll: true, onSuccess: () => { open.value = false } }
    editing.value ? form.put(route('integrations.update', editing.value.id), opts) : form.post(route('integrations.store'), opts)
}
const regenerate = (c) => { if (confirm(`Buat token baru untuk ${c.name}? Token lama langsung tidak berlaku.`)) router.post(route('integrations.regenerate', c.id)) }
const ping = (c) => router.post(route('integrations.test', c.id), {}, { preserveScroll: true })
const remove = (c) => { if (confirm(`Hapus klien ${c.name}? Sistem tersebut tidak akan bisa mengakses API lagi.`)) router.delete(route('integrations.destroy', c.id), { preserveScroll: true }) }
const resend = (d) => router.post(route('integrations.resend', d.id), {}, { preserveScroll: true })

const tokenOpen = ref(!!props.newToken)
const copied = ref('')
async function copy(text, key) { try { await navigator.clipboard.writeText(text); copied.value = key; setTimeout(() => { copied.value = '' }, 1500) } catch (e) { /* abaikan */ } }
const curl = computed(() => `curl -X POST ${props.baseUrl}/requests \\\n  -H "Authorization: Bearer <TOKEN>" \\\n  -H "Idempotency-Key: INV-REQ-0231" \\\n  -H "Content-Type: application/json" \\\n  -d '{"title":"Laptop staf gudang","category":"elektronik","requester_name":"Rina",\n       "external_ref":"INV-REQ-0231","items":[{"name":"Laptop 14 inci","quantity":3,"unit":"unit","estimated_price":12500000}]}'`)
const systemLabel = { inventaris: 'Inventaris', keuangan: 'Keuangan', sdm: 'SDM', lainnya: 'Lainnya' }
</script>

<template>
    <AppLayout>
        <template #title>Integrasi API</template>
        <PageHeader title="Integrasi API" subtitle="Hubungkan sistem Inventaris, Keuangan, dan SDM ke PROCURA lewat REST API dan webhook bertanda tangan.">
            <button class="btn btn-primary" @click="openForm()">+ Klien API baru</button>
        </PageHeader>

        <section class="card mb-6">
            <div class="card-head"><h3 class="card-title">Klien terdaftar</h3><span class="muted text-xs">Base URL: <code class="font-mono">{{ baseUrl }}</code></span></div>
            <div v-if="clients.length" class="divide-y divide-slate-100 dark:divide-ink-700">
                <div v-for="c in clients" :key="c.id" class="flex flex-wrap items-start justify-between gap-3 p-5">
                    <div class="min-w-0">
                        <p class="flex flex-wrap items-center gap-2"><span class="font-medium">{{ c.name }}</span><span class="badge badge-blue">{{ systemLabel[c.system] }}</span><span class="badge" :class="c.is_active ? 'badge-green' : 'badge-slate'">{{ c.is_active ? 'Aktif' : 'Nonaktif' }}</span></p>
                        <p class="mt-1 text-xs"><span class="muted">Token</span> <code class="font-mono">{{ c.token_prefix }}…</code> <span class="muted">· terakhir dipakai {{ c.last_used_at ?? 'belum pernah' }}<template v-if="c.last_used_ip"> dari {{ c.last_used_ip }}</template></span></p>
                        <p class="mt-1 flex flex-wrap gap-1"><span v-for="s in c.scopes" :key="s" class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] dark:bg-ink-800">{{ s }}</span></p>
                        <p v-if="c.webhook_url" class="muted mt-1 truncate text-xs">Webhook → {{ c.webhook_url }} ({{ c.webhook_events.length }} event)</p>
                    </div>
                    <div class="flex flex-wrap gap-1">
                        <button v-if="c.webhook_url" class="btn btn-outline btn-sm" @click="ping(c)">Uji webhook</button>
                        <button class="btn btn-outline btn-sm" @click="openForm(c)">Ubah</button>
                        <button class="btn btn-outline btn-sm" @click="regenerate(c)">Token baru</button>
                        <button class="btn btn-ghost btn-sm text-rose-600" @click="remove(c)">Hapus</button>
                    </div>
                </div>
            </div>
            <p v-else class="muted px-5 py-10 text-center text-sm">Belum ada klien. Buat satu untuk Inventaris dan satu untuk Keuangan.</p>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="card">
                <div class="card-head"><h3 class="card-title">Pengiriman webhook terakhir</h3></div>
                <div v-if="deliveries.length" class="overflow-x-auto"><table class="tbl"><thead><tr><th>Waktu</th><th>Klien / event</th><th>Hasil</th><th></th></tr></thead>
                    <tbody><tr v-for="d in deliveries" :key="d.id"><td class="tabular whitespace-nowrap text-xs">{{ d.created_at }}</td><td class="text-xs">{{ d.client }}<br /><code class="font-mono">{{ d.event }}</code></td>
                        <td><span class="badge" :class="d.status === 'delivered' ? 'badge-green' : d.status === 'failed' ? 'badge-rose' : 'badge-slate'">{{ d.status === 'delivered' ? 'Terkirim' : d.status === 'failed' ? 'Gagal' : 'Menunggu' }}</span><p v-if="d.error" class="muted mt-0.5 max-w-[10rem] truncate text-[11px]" :title="d.error">{{ d.error }}</p></td>
                        <td class="text-right"><button v-if="d.status !== 'delivered'" class="btn btn-ghost btn-sm" @click="resend(d)">Kirim ulang</button></td></tr></tbody></table></div>
                <p v-else class="muted px-5 py-8 text-center text-sm">Belum ada pengiriman.</p>
            </section>

            <section class="card card-pad">
                <h3 class="card-title mb-3">Cara memakai</h3>
                <ol class="list-decimal space-y-1.5 pl-5 text-sm">
                    <li>Buat klien API, salin token (hanya tampil sekali).</li>
                    <li>Kirim header <code class="font-mono text-xs">Authorization: Bearer &lt;token&gt;</code> di setiap request.</li>
                    <li>Untuk operasi tulis, kirim <code class="font-mono text-xs">Idempotency-Key</code> agar request ulang tidak membuat data ganda.</li>
                    <li>Verifikasi webhook: <code class="font-mono text-xs">X-Procura-Signature = sha256=HMAC(body, secret)</code>.</li>
                </ol>
                <pre class="mt-4 overflow-x-auto rounded-lg bg-ink-900 p-3 text-xs leading-relaxed text-slate-200">{{ curl }}</pre>
                <p class="muted mt-3 text-xs">Endpoint: <code class="font-mono">GET /ping</code>, <code class="font-mono">GET /catalog</code>, <code class="font-mono">POST /requests</code>, <code class="font-mono">GET /requests/{no}</code>, <code class="font-mono">GET /funding</code>, <code class="font-mono">POST /funding/{no}/decision</code>, <code class="font-mono">POST /funding/{no}/disbursement</code>, <code class="font-mono">POST /handovers/{no}/confirm</code>. Dokumentasi lengkap ada di docs/API.md.</p>
            </section>
        </div>

        <Modal :show="open" :title="editing ? `Ubah ${editing.name}` : 'Klien API baru'" max-width="max-w-2xl" @close="open = false">
            <form class="space-y-4" @submit.prevent="submit">
                <ErrorSummary :errors="form.errors" />
                <div class="grid gap-3 sm:grid-cols-2">
                    <Field label="Sistem"><select v-model="form.system" class="input" @change="applyPreset"><option v-for="(l, k) in systemLabel" :key="k" :value="k">{{ l }}</option></select></Field>
                    <Field label="Nama klien" :error="form.errors.name"><input v-model="form.name" class="input" required /></Field>
                </div>
                <fieldset><legend class="label">Izin akses (scope)</legend>
                    <label v-for="(l, k) in scopes" :key="k" class="flex items-start gap-2 py-0.5 text-sm"><input v-model="form.scopes" type="checkbox" :value="k" class="mt-0.5 rounded border-slate-300" /><span><code class="font-mono text-xs">{{ k }}</code> <span class="muted">— {{ l }}</span></span></label>
                    <p v-if="form.errors.scopes" class="err">{{ form.errors.scopes }}</p></fieldset>
                <Field label="Alamat webhook (opsional)" :error="form.errors.webhook_url" hint="PROCURA akan POST ke alamat ini saat event terjadi."><input v-model="form.webhook_url" type="url" class="input" placeholder="https://inventaris.example/api/procura/webhook" /></Field>
                <fieldset v-if="form.webhook_url"><legend class="label">Event yang dikirim</legend>
                    <label v-for="(l, k) in events" :key="k" class="flex items-start gap-2 py-0.5 text-sm"><input v-model="form.webhook_events" type="checkbox" :value="k" class="mt-0.5 rounded border-slate-300" /><span><code class="font-mono text-xs">{{ k }}</code> <span class="muted">— {{ l }}</span></span></label></fieldset>
                <label v-if="editing" class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" class="rounded border-slate-300" /> Klien aktif</label>
                <div class="flex justify-end gap-2"><button type="button" class="btn btn-outline" @click="open = false">Batal</button><button class="btn btn-primary" :disabled="form.processing">Simpan</button></div>
            </form>
        </Modal>

        <Modal :show="tokenOpen && !!newToken" :title="`Token untuk ${newToken?.name}`" @close="tokenOpen = false">
            <div class="space-y-4">
                <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-200">Salin sekarang. Demi keamanan, token asli tidak disimpan dan tidak bisa ditampilkan lagi.</p>
                <div><p class="label">Token API</p><div class="flex gap-2"><code class="block min-w-0 flex-1 break-all rounded-lg bg-slate-100 p-2.5 font-mono text-xs dark:bg-ink-800">{{ newToken?.token }}</code><button class="btn btn-outline btn-sm" @click="copy(newToken.token, 't')">{{ copied === 't' ? 'Tersalin ✓' : 'Salin' }}</button></div></div>
                <div v-if="newSecret"><p class="label">Rahasia webhook (untuk verifikasi tanda tangan)</p><div class="flex gap-2"><code class="block min-w-0 flex-1 break-all rounded-lg bg-slate-100 p-2.5 font-mono text-xs dark:bg-ink-800">{{ newSecret }}</code><button class="btn btn-outline btn-sm" @click="copy(newSecret, 's')">{{ copied === 's' ? 'Tersalin ✓' : 'Salin' }}</button></div></div>
                <div class="flex justify-end"><button class="btn btn-primary" @click="tokenOpen = false">Sudah saya salin</button></div>
            </div>
        </Modal>
    </AppLayout>
</template>
