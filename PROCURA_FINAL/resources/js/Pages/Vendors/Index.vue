<script setup>
import { reactive, ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Modal from '@/Components/Modal.vue'
import Field from '@/Components/Field.vue'
import ErrorSummary from '@/Components/ErrorSummary.vue'
import Pagination from '@/Components/Pagination.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { useCan } from '@/permissions'
import { formatIDR } from '@/utils'

const props = defineProps({ vendors: Object, filters: Object })
const can = useCan()
const f = reactive({ search: props.filters.search ?? '', status: props.filters.status ?? '' })
let t = null
watch(f, () => { clearTimeout(t); t = setTimeout(() => router.get(route('vendors.index'), Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true, replace: true }), 300) })

const statusMeta = { active: ['Aktif', 'badge-green'], inactive: ['Nonaktif', 'badge-slate'], blacklisted: ['Daftar hitam', 'badge-rose'] }
const editing = ref(null)
const open = ref(false)
const form = useForm({ name: '', category: '', contact_person: '', email: '', phone: '', address: '', npwp: '', bank_name: '', bank_account: '', bank_holder: '', payment_terms_days: 14, status: 'active', notes: '' })
function openForm(v = null) {
    editing.value = v; form.reset(); form.clearErrors()
    if (v) Object.keys(form.data()).forEach((k) => { form[k] = v[k] ?? (k === 'payment_terms_days' ? 14 : '') })
    open.value = true
}
function submit() {
    const opts = { preserveScroll: true, onSuccess: () => { open.value = false } }
    editing.value ? form.put(route('vendors.update', editing.value.id), opts) : form.post(route('vendors.store'), opts)
}
const remove = (v) => { if (confirm(`Hapus vendor ${v.name}?`)) router.delete(route('vendors.destroy', v.id), { preserveScroll: true }) }
</script>

<template>
    <AppLayout>
        <template #title>Vendor</template>
        <PageHeader title="Vendor" subtitle="Pemasok barang dan penyedia jasa. Nilai kinerja terisi otomatis dari catatan penerimaan barang.">
            <button v-if="can('vendor.manage')" class="btn btn-primary" @click="openForm()">+ Vendor baru</button>
        </PageHeader>
        <div class="card">
            <div class="grid gap-3 border-b border-slate-100 p-4 sm:grid-cols-3 dark:border-ink-700">
                <input v-model="f.search" type="search" class="input sm:col-span-2" placeholder="Cari nama, kode, kontak…" aria-label="Cari" />
                <select v-model="f.status" class="input" aria-label="Status"><option value="">Semua status</option><option v-for="(m, k) in statusMeta" :key="k" :value="k">{{ m[0] }}</option></select>
            </div>
            <div v-if="vendors.data.length" class="overflow-x-auto"><table class="tbl">
                <thead><tr><th>Vendor</th><th>Kontak</th><th>Kategori</th><th class="text-right">PO</th><th class="text-right">Nilai belanja</th><th>Kinerja</th><th>Status</th><th></th></tr></thead>
                <tbody><tr v-for="v in vendors.data" :key="v.id">
                    <td><p class="font-medium">{{ v.name }}</p><p class="docno">{{ v.code }}</p></td>
                    <td class="text-xs">{{ v.contact_person }}<br /><span class="muted">{{ v.email }}</span></td>
                    <td class="text-xs">{{ $page.props.workflow.categories[v.category] ?? '—' }}</td>
                    <td class="tabular text-right">{{ v.orders_count }}</td><td class="tabular text-right">{{ formatIDR(v.spend) }}</td>
                    <td class="text-xs">{{ v.rating_count ? '★ ' + Number(v.rating).toFixed(1) + ` (${v.rating_count})` : '—' }}</td>
                    <td><span class="badge" :class="statusMeta[v.status][1]">{{ statusMeta[v.status][0] }}</span></td>
                    <td class="whitespace-nowrap text-right"><template v-if="can('vendor.manage')"><button class="btn btn-ghost btn-sm" @click="openForm(v)">Ubah</button><button class="btn btn-ghost btn-sm text-rose-600" @click="remove(v)">Hapus</button></template></td>
                </tr></tbody></table></div>
            <EmptyState v-else title="Belum ada vendor" text="Tambahkan vendor agar bisa dipilih saat mencatat penawaran." />
        </div>
        <Pagination :links="vendors.links" />

        <Modal :show="open" :title="editing ? `Ubah ${editing.name}` : 'Vendor baru'" max-width="max-w-2xl" @close="open = false">
            <form class="space-y-4" @submit.prevent="submit">
                <ErrorSummary :errors="form.errors" />
                <div class="grid gap-3 sm:grid-cols-2">
                    <Field label="Nama vendor" :error="form.errors.name" class="sm:col-span-2"><input v-model="form.name" class="input" required /></Field>
                    <Field label="Kategori"><select v-model="form.category" class="input"><option value="">—</option><option v-for="(l, k) in $page.props.workflow.categories" :key="k" :value="k">{{ l }}</option></select></Field>
                    <Field label="Status"><select v-model="form.status" class="input"><option v-for="(m, k) in statusMeta" :key="k" :value="k">{{ m[0] }}</option></select></Field>
                    <Field label="Nama kontak"><input v-model="form.contact_person" class="input" /></Field>
                    <Field label="Telepon"><input v-model="form.phone" class="input" /></Field>
                    <Field label="Email (untuk kirim PO)" :error="form.errors.email"><input v-model="form.email" type="email" class="input" /></Field>
                    <Field label="NPWP"><input v-model="form.npwp" class="input" /></Field>
                    <Field label="Alamat" class="sm:col-span-2"><textarea v-model="form.address" rows="2" class="input"></textarea></Field>
                    <Field label="Bank"><input v-model="form.bank_name" class="input" /></Field>
                    <Field label="No. rekening"><input v-model="form.bank_account" class="input" /></Field>
                    <Field label="Atas nama"><input v-model="form.bank_holder" class="input" /></Field>
                    <Field label="Tempo bayar (hari)"><input v-model="form.payment_terms_days" type="number" min="0" class="input" required /></Field>
                </div>
                <div class="flex justify-end gap-2"><button type="button" class="btn btn-outline" @click="open = false">Batal</button><button class="btn btn-primary" :disabled="form.processing">Simpan</button></div>
            </form>
        </Modal>
    </AppLayout>
</template>
