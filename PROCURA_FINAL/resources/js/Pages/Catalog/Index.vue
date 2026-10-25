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

const props = defineProps({ items: Object, filters: Object })
const can = useCan()
const f = reactive({ search: props.filters.search ?? '', category: props.filters.category ?? '' })
let t = null
watch(f, () => { clearTimeout(t); t = setTimeout(() => router.get(route('catalog.index'), Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true, replace: true }), 300) })

const editing = ref(null)
const open = ref(false)
const form = useForm({ sku: '', name: '', category: 'elektronik', unit: 'unit', reference_price: 0, description: '', is_active: true })
function openForm(i = null) {
    editing.value = i; form.reset(); form.clearErrors()
    if (i) Object.keys(form.data()).forEach((k) => { form[k] = i[k] ?? '' })
    open.value = true
}
function submit() {
    const opts = { preserveScroll: true, onSuccess: () => { open.value = false } }
    editing.value ? form.put(route('catalog.update', editing.value.id), opts) : form.post(route('catalog.store'), opts)
}
const remove = (i) => { if (confirm(`Hapus ${i.name} dari katalog?`)) router.delete(route('catalog.destroy', i.id), { preserveScroll: true }) }
</script>

<template>
    <AppLayout>
        <template #title>Katalog barang</template>
        <PageHeader title="Katalog barang & jasa" subtitle="Daftar barang standar beserta harga acuan. Dipakai untuk isi cepat saat membuat permintaan dan dibaca sistem Inventaris lewat API.">
            <button v-if="can('catalog.manage')" class="btn btn-primary" @click="openForm()">+ Tambah item</button>
        </PageHeader>
        <div class="card">
            <div class="grid gap-3 border-b border-slate-100 p-4 sm:grid-cols-3 dark:border-ink-700">
                <input v-model="f.search" type="search" class="input sm:col-span-2" placeholder="Cari nama atau SKU…" aria-label="Cari" />
                <select v-model="f.category" class="input" aria-label="Kategori"><option value="">Semua kategori</option><option v-for="(l, k) in $page.props.workflow.categories" :key="k" :value="k">{{ l }}</option></select>
            </div>
            <div v-if="items.data.length" class="overflow-x-auto"><table class="tbl">
                <thead><tr><th>SKU</th><th>Nama</th><th>Kategori</th><th>Satuan</th><th class="text-right">Harga acuan</th><th>Status</th><th></th></tr></thead>
                <tbody><tr v-for="i in items.data" :key="i.id"><td class="docno">{{ i.sku }}</td><td>{{ i.name }}</td><td class="text-xs">{{ $page.props.workflow.categories[i.category] }}</td><td>{{ i.unit }}</td>
                    <td class="tabular text-right">{{ formatIDR(i.reference_price) }}</td><td><span class="badge" :class="i.is_active ? 'badge-green' : 'badge-slate'">{{ i.is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td class="whitespace-nowrap text-right"><template v-if="can('catalog.manage')"><button class="btn btn-ghost btn-sm" @click="openForm(i)">Ubah</button><button class="btn btn-ghost btn-sm text-rose-600" @click="remove(i)">Hapus</button></template></td></tr></tbody></table></div>
            <EmptyState v-else title="Katalog kosong" />
        </div>
        <Pagination :links="items.links" />

        <Modal :show="open" :title="editing ? 'Ubah item' : 'Item baru'" @close="open = false">
            <form class="space-y-4" @submit.prevent="submit">
                <ErrorSummary :errors="form.errors" />
                <div class="grid gap-3 sm:grid-cols-2">
                    <Field label="SKU" :error="form.errors.sku"><input v-model="form.sku" class="input" required /></Field>
                    <Field label="Kategori"><select v-model="form.category" class="input"><option v-for="(l, k) in $page.props.workflow.categories" :key="k" :value="k">{{ l }}</option></select></Field>
                    <Field label="Nama" class="sm:col-span-2" :error="form.errors.name"><input v-model="form.name" class="input" required /></Field>
                    <Field label="Satuan"><input v-model="form.unit" class="input" required /></Field>
                    <Field label="Harga acuan (Rp)"><input v-model="form.reference_price" type="number" min="0" step="any" class="input" required /></Field>
                    <Field label="Deskripsi" class="sm:col-span-2"><textarea v-model="form.description" rows="2" class="input"></textarea></Field>
                    <label class="flex items-center gap-2 text-sm sm:col-span-2"><input v-model="form.is_active" type="checkbox" class="rounded border-slate-300" /> Aktif (muncul di pilihan permintaan)</label>
                </div>
                <div class="flex justify-end gap-2"><button type="button" class="btn btn-outline" @click="open = false">Batal</button><button class="btn btn-primary" :disabled="form.processing">Simpan</button></div>
            </form>
        </Modal>
    </AppLayout>
</template>
