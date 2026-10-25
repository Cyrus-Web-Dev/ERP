<script setup>
import { computed } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Field from '@/Components/Field.vue'
import ErrorSummary from '@/Components/ErrorSummary.vue'
import { formatIDR } from '@/utils'

const props = defineProps({ catalog: Array })
const blank = () => ({ name: '', specification: '', quantity: 1, unit: 'unit', estimated_price: 0, catalog_item_id: null })
const form = useForm({
    source: 'inventaris', title: '', category: 'elektronik', requester_name: '', requester_unit: '', external_ref: '', justification: '',
    priority: 'normal', needed_date: '', items: [blank()],
})
const total = computed(() => form.items.reduce((a, i) => a + (Number(i.quantity) || 0) * (Number(i.estimated_price) || 0), 0))
const suggestions = computed(() => props.catalog.filter((c) => c.category === form.category))

function pick(row, id) {
    const c = props.catalog.find((x) => x.id === Number(id))
    if (!c) { row.catalog_item_id = null; return }
    Object.assign(row, { catalog_item_id: c.id, name: c.name, unit: c.unit, estimated_price: c.reference_price })
}
const submit = () => form.post(route('requests.store'))
</script>

<template>
    <AppLayout>
        <template #title>Permintaan baru</template>
        <PageHeader title="Catat permintaan dari Inventaris" subtitle="Isi kebutuhan barang atau jasa. Permintaan otomatis tercatat di Meja Inventaris." />
        <form class="mx-auto max-w-4xl space-y-6" @submit.prevent="submit">
            <ErrorSummary :errors="form.errors" />

            <section class="card card-pad grid gap-4 sm:grid-cols-2">
                <Field label="Judul permintaan" :error="form.errors.title" class="sm:col-span-2"><input v-model="form.title" class="input" placeholder="mis. Penggantian laptop staf gudang" required /></Field>
                <Field label="Nama pemohon" :error="form.errors.requester_name"><input v-model="form.requester_name" class="input" required /></Field>
                <Field label="Unit / divisi"><input v-model="form.requester_unit" class="input" placeholder="mis. Divisi Inventaris" /></Field>
                <Field label="Kategori" :error="form.errors.category">
                    <select v-model="form.category" class="input"><option v-for="(l, k) in $page.props.workflow.categories" :key="k" :value="k">{{ l }}</option></select>
                </Field>
                <Field label="Prioritas">
                    <select v-model="form.priority" class="input"><option v-for="(p, k) in $page.props.workflow.priority" :key="k" :value="k">{{ p.label }}</option></select>
                </Field>
                <Field label="Dibutuhkan paling lambat" :error="form.errors.needed_date"><input v-model="form.needed_date" type="date" class="input" /></Field>
                <Field label="No. permintaan di sistem Inventaris" hint="Opsional, untuk pelacakan silang."><input v-model="form.external_ref" class="input" placeholder="mis. INV-REQ-0231" /></Field>
                <Field label="Alasan / latar belakang" class="sm:col-span-2"><textarea v-model="form.justification" rows="2" class="input"></textarea></Field>
            </section>

            <section class="card">
                <div class="card-head"><h3 class="card-title">Barang / jasa yang dibutuhkan</h3><button type="button" class="btn btn-outline btn-sm" @click="form.items.push(blank())">+ Tambah baris</button></div>
                <div class="divide-y divide-slate-100 dark:divide-ink-700">
                    <div v-for="(row, i) in form.items" :key="i" class="grid gap-3 p-4 sm:grid-cols-12">
                        <div v-if="suggestions.length" class="sm:col-span-12">
                            <select class="input text-xs" :value="row.catalog_item_id ?? ''" aria-label="Pilih dari katalog" @change="pick(row, $event.target.value)">
                                <option value="">Isi cepat dari katalog… (atau ketik manual di bawah)</option>
                                <option v-for="c in suggestions" :key="c.id" :value="c.id">{{ c.name }} — {{ formatIDR(c.reference_price) }}</option>
                            </select>
                        </div>
                        <Field label="Nama" class="sm:col-span-5" :error="form.errors[`items.${i}.name`]"><input v-model="row.name" class="input" required /></Field>
                        <Field label="Jumlah" class="sm:col-span-2" :error="form.errors[`items.${i}.quantity`]"><input v-model="row.quantity" type="number" min="0" step="any" class="input" required /></Field>
                        <Field label="Satuan" class="sm:col-span-2"><input v-model="row.unit" class="input" required /></Field>
                        <Field label="Harga perkiraan / satuan" class="sm:col-span-3"><input v-model="row.estimated_price" type="number" min="0" step="any" class="input" /></Field>
                        <Field label="Spesifikasi" class="sm:col-span-11"><input v-model="row.specification" class="input" placeholder="Merek, ukuran, kebutuhan khusus…" /></Field>
                        <div class="flex items-end sm:col-span-1"><button type="button" class="btn btn-ghost btn-sm text-rose-600" :disabled="form.items.length === 1" title="Hapus baris" @click="form.items.splice(i, 1)">Hapus</button></div>
                    </div>
                </div>
                <div class="flex items-center justify-between border-t border-slate-100 px-5 py-3 dark:border-ink-700">
                    <span class="muted text-sm">Perkiraan total (sebelum PPN)</span><span class="tabular text-lg font-semibold">{{ formatIDR(total) }}</span>
                </div>
            </section>

            <div class="flex justify-end gap-2">
                <Link :href="route('requests.index')" class="btn btn-outline">Batal</Link>
                <button type="submit" class="btn btn-primary" :disabled="form.processing">Kirim permintaan</button>
            </div>
        </form>
    </AppLayout>
</template>
