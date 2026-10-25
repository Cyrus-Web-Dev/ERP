<script setup>
import { reactive, ref, watch } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import Modal from '@/Components/Modal.vue'
import Field from '@/Components/Field.vue'
import Pagination from '@/Components/Pagination.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { useCan } from '@/permissions'
import { formatDate } from '@/utils'

const props = defineProps({ tab: String, reports: Object, filters: Object, inbox: Array, awaitingConfirm: Array, unread: Number, activeRequests: Array, kinds: Object })
const can = useCan()
const search = ref(props.filters.search ?? '')
let t = null
watch(search, (v) => { clearTimeout(t); t = setTimeout(() => router.get(route('inventory-desk.index'), { tab: props.tab, ...(v ? { search: v } : {}) }, { preserveState: true, replace: true }), 300) })

const tabs = [{ key: 'in', label: 'Masuk dari Inventaris' }, { key: 'out', label: 'Terkirim ke Inventaris' }, { key: 'handover', label: 'Semua laporan' }]
const go = (k) => router.get(route('inventory-desk.index'), { tab: k }, { preserveState: true })

const open = ref(false)
const form = useForm({ direction: 'out', subject: '', body: '', purchase_request_id: '' })
function newReport(direction) { form.reset(); form.direction = direction; open.value = true }
const submit = () => form.post(route('inventory-desk.store'), { preserveScroll: true, onSuccess: () => { open.value = false } })

const markRead = (r) => router.post(route('inventory-desk.read', r.id), {}, { preserveScroll: true })
const readAll = () => router.post(route('inventory-desk.read-all'), {}, { preserveScroll: true })
const confirmHandover = (h) => router.post(route('inventory-desk.confirm', h.id), {}, { preserveScroll: true })
</script>

<template>
    <AppLayout>
        <template #title>Meja Inventaris</template>
        <PageHeader title="Meja Inventaris" subtitle="Tempat menerima permintaan dan laporan dari Inventaris, serta melaporkan perkembangan dan serah terima kembali kepada mereka.">
            <button v-if="unread" class="btn btn-outline" @click="readAll">Tandai semua dibaca ({{ unread }})</button>
            <button v-if="can('request.manage')" class="btn btn-outline" @click="newReport('in')">Catat laporan masuk</button>
            <button v-if="can('request.manage')" class="btn btn-primary" @click="newReport('out')">Tulis laporan ke Inventaris</button>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="tabs" role="tablist">
                        <button v-for="x in tabs" :key="x.key" role="tab" :aria-selected="tab === x.key" class="tab" :class="{ 'tab-on': tab === x.key }" @click="go(x.key)">{{ x.label }}</button>
                    </div>
                    <input v-model="search" type="search" class="input max-w-xs" placeholder="Cari perihal / nomor…" aria-label="Cari laporan" />
                </div>

                <div v-if="reports.data.length" class="space-y-3">
                    <article v-for="r in reports.data" :key="r.id" class="letter" :class="[r.direction === 'in' ? 'letter-in' : 'letter-out', r.direction === 'in' && !r.read ? 'ring-1 ring-orange-300' : '']">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="flex flex-wrap items-center gap-2 text-xs"><span class="docno">{{ r.number }}</span>
                                    <span class="badge" :class="r.direction === 'in' ? 'badge-teal' : 'badge-slate'">{{ r.direction === 'in' ? 'Dari Inventaris' : 'Ke Inventaris' }}</span>
                                    <span class="muted">{{ kinds[r.kind] ?? r.kind }}</span><span v-if="r.direction === 'in' && !r.read" class="badge badge-orange">Baru</span></p>
                                <h3 class="mt-1 font-medium text-slate-900 dark:text-slate-100">{{ r.subject }}</h3>
                            </div>
                            <span class="tabular muted shrink-0 text-xs">{{ r.created_at }}</span>
                        </div>
                        <p v-if="r.body" class="mt-2 whitespace-pre-line text-sm text-slate-600 dark:text-slate-300">{{ r.body }}</p>
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs">
                            <span class="muted">
                                <Link v-if="r.request_id" :href="route('requests.show', r.request_id)" class="docno hover:underline">{{ r.request_number }}</Link>
                                <template v-if="r.author"> · {{ r.author }}</template><template v-else-if="r.is_auto"> · otomatis oleh sistem</template>
                            </span>
                            <button v-if="r.direction === 'in' && !r.read" class="btn btn-ghost btn-sm" @click="markRead(r)">Tandai dibaca</button>
                        </div>
                    </article>
                </div>
                <div v-else class="card"><EmptyState title="Belum ada laporan" text="Laporan muncul otomatis saat ada permintaan masuk atau saat Pengadaan mengabari perkembangan." /></div>
                <Pagination :links="reports.links" />
            </div>

            <aside class="space-y-6">
                <section class="card">
                    <div class="card-head"><h3 class="card-title">Permintaan menunggu ditinjau</h3><span class="badge" :class="inbox.length ? 'badge-orange' : 'badge-slate'">{{ inbox.length }}</span></div>
                    <ul v-if="inbox.length" class="divide-y divide-slate-100 dark:divide-ink-700">
                        <li v-for="r in inbox" :key="r.id"><Link :href="route('requests.show', r.id)" class="block px-5 py-3 hover:bg-slate-50 dark:hover:bg-ink-800">
                            <p class="truncate text-sm font-medium">{{ r.title }}</p>
                            <p class="mt-0.5 flex items-center gap-2 text-xs"><span class="muted">{{ r.requester_name }}</span><StatusBadge kind="priority" :value="r.priority" /><span v-if="r.needed_date" class="muted">butuh {{ formatDate(r.needed_date) }}</span></p>
                        </Link></li>
                    </ul>
                    <p v-else class="muted px-5 py-6 text-center text-sm">Semua permintaan sudah ditinjau.</p>
                    <div class="border-t border-slate-100 p-3 dark:border-ink-700"><Link :href="route('requests.create')" class="btn btn-outline w-full btn-sm">+ Catat permintaan dari Inventaris</Link></div>
                </section>

                <section class="card">
                    <div class="card-head"><h3 class="card-title">Serah terima menunggu konfirmasi</h3></div>
                    <ul v-if="awaitingConfirm.length" class="divide-y divide-slate-100 dark:divide-ink-700">
                        <li v-for="h in awaitingConfirm" :key="h.id" class="px-5 py-3">
                            <p class="text-sm font-medium">{{ h.title }}</p>
                            <p class="muted text-xs"><span class="docno">{{ h.number }}</span> · {{ formatDate(h.handover_date) }} · {{ h.receiver_name }}</p>
                            <button v-if="can('handover.confirm')" class="btn btn-primary btn-sm mt-2" @click="confirmHandover(h)">Konfirmasi sudah diterima</button>
                        </li>
                    </ul>
                    <p v-else class="muted px-5 py-6 text-center text-sm">Tidak ada.</p>
                    <p class="muted border-t border-slate-100 px-5 py-3 text-xs dark:border-ink-700">Sistem Inventaris juga bisa mengonfirmasi otomatis lewat REST API.</p>
                </section>
            </aside>
        </div>

        <Modal :show="open" :title="form.direction === 'in' ? 'Catat laporan dari Inventaris' : 'Tulis laporan ke Inventaris'" @close="open = false">
            <form class="space-y-4" @submit.prevent="submit">
                <Field label="Perihal" :error="form.errors.subject"><input v-model="form.subject" class="input" required /></Field>
                <Field label="Isi laporan" :error="form.errors.body"><textarea v-model="form.body" rows="4" class="input"></textarea></Field>
                <Field label="Terkait permintaan (opsional)">
                    <select v-model="form.purchase_request_id" class="input"><option value="">— tidak terkait —</option><option v-for="r in activeRequests" :key="r.id" :value="r.id">{{ r.number }} · {{ r.title }}</option></select>
                </Field>
                <div class="flex justify-end gap-2"><button type="button" class="btn btn-outline" @click="open = false">Batal</button><button class="btn btn-primary" :disabled="form.processing">Simpan</button></div>
            </form>
        </Modal>
    </AppLayout>
</template>
