<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import UserAvatar from '@/Components/UserAvatar.vue'

const page = usePage()
const sidebarOpen = ref(false)
const isDark = ref(false)
const notifOpen = ref(false)
const notifCount = ref(0)
const notifItems = ref([])
const notifWrap = ref(null)
const dismissedError = ref(false)
let notifTimer = null

const user = computed(() => page.props.auth?.user ?? null)
const isAdmin = computed(() => (user.value?.roles ?? []).includes('admin'))

// Pesan error NON-field (aturan alur pengadaan dilanggar, dsb.) ditampilkan sebagai banner.
const GLOBAL_ERROR_KEYS = ['status', 'approval', 'delete', 'gmail', 'general']
const bannerMessages = computed(() => {
  const msgs = []
  const flashError = page.props.flash?.error
  if (flashError) msgs.push(flashError)
  for (const key of GLOBAL_ERROR_KEYS) {
    const m = page.props.errors?.[key]
    if (m && !msgs.includes(m)) msgs.push(m)
  }
  return msgs
})

async function fetchNotifications() {
  if (document.hidden) return
  try {
    const { data } = await axios.get(route('notifications.summary'))
    notifCount.value = data.count
    notifItems.value = data.items
  } catch (e) { /* notifikasi bukan fitur kritikal */ }
}
function closeNotifOnClickOutside(e) { if (notifWrap.value && !notifWrap.value.contains(e.target)) notifOpen.value = false }
function closeOnEscape(e) { if (e.key === 'Escape') { notifOpen.value = false; sidebarOpen.value = false } }

onMounted(() => {
  fetchNotifications()
  notifTimer = setInterval(fetchNotifications, 120000)
  document.addEventListener('click', closeNotifOnClickOutside)
  document.addEventListener('keydown', closeOnEscape)
  try { isDark.value = localStorage.getItem('procura-theme') === 'dark' } catch (e) { /* abaikan */ }
  applyTheme()
})
onUnmounted(() => {
  clearInterval(notifTimer)
  document.removeEventListener('click', closeNotifOnClickOutside)
  document.removeEventListener('keydown', closeOnEscape)
})

function applyTheme() { document.documentElement.classList.toggle('dark', isDark.value) }
function toggleTheme() {
  isDark.value = !isDark.value
  try { localStorage.setItem('procura-theme', isDark.value ? 'dark' : 'light') } catch (e) { /* abaikan */ }
  applyTheme()
}

const I = {
  home: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
  inbox: 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4',
  doc: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
  cart: 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z',
  cash: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-9c-1.11 0-2.08.402-2.599 1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
  box: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
  tag: 'M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-5 5a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z',
  chart: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2',
  users: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
  plug: 'M13 10V3L4 14h7v7l9-11h-7z',
  db: 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4',
  log: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
}

const groups = computed(() => {
  const b = page.props.badges ?? {}
  return [
    { items: [{ name: 'Ringkasan', href: route('dashboard'), icon: I.home }] },
    { title: 'Alur pengadaan', items: [
      { name: 'Permintaan', href: route('requests.index'), icon: I.doc, badge: b.requests },
      { name: 'Purchase Order', href: route('purchase-orders.index'), icon: I.cart },
    ] },
    { title: 'Meja antar-divisi', items: [
      { name: 'Meja Inventaris', href: route('inventory-desk.index'), icon: I.inbox, badge: b.inventory },
      { name: 'Meja Keuangan', href: route('finance-desk.index'), icon: I.cash, badge: b.finance },
    ] },
    { title: 'Data master', items: [
      { name: 'Vendor', href: route('vendors.index'), icon: I.box },
      { name: 'Katalog barang', href: route('catalog.index'), icon: I.tag },
    ] },
    { title: 'Wawasan', items: [{ name: 'Analitik', href: route('analytics.index'), icon: I.chart }] },
    ...(isAdmin.value ? [{ title: 'Administrasi', items: [
      { name: 'Kelola Pengguna', href: route('admin.users.index'), icon: I.users, badge: page.props.pending_users, amber: true },
      { name: 'Integrasi API', href: route('integrations.index'), icon: I.plug },
      { name: 'Backup Data', href: route('admin.backup.index'), icon: I.db },
      { name: 'Log Aktivitas', href: route('settings.activity-log.index'), icon: I.log },
    ] }] : []),
  ]
})

function isActive(href) {
  const path = new URL(href).pathname
  const url = page.url.split('?')[0]
  return url === path || url.startsWith(path + '/')
}
</script>

<template>
  <div class="min-h-screen bg-paper dark:bg-ink-950">
    <!-- Sidebar desktop -->
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col bg-ink-900 lg:flex">
      <div class="flex h-16 shrink-0 items-center gap-3 px-5">
        <img src="/images/logo.svg" alt="Logo PROCURA" class="h-9 w-9 rounded-lg" />
        <div class="text-white">
          <p class="text-sm font-semibold leading-tight tracking-wide">PROCURA</p>
          <p class="text-xs leading-tight text-slate-400">Pengadaan &amp; Procurement</p>
        </div>
      </div>
      <nav class="flex-1 overflow-y-auto px-3 pb-4" aria-label="Menu utama">
        <div v-for="(g, gi) in groups" :key="gi" class="mt-4 first:mt-2">
          <p v-if="g.title" class="mb-1 px-3 text-xs text-slate-500">{{ g.title }}</p>
          <Link v-for="item in g.items" :key="item.name" :href="item.href"
            class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition"
            :class="isActive(item.href) ? 'bg-harbor-500/15 text-harbor-300' : 'text-slate-300 hover:bg-ink-800 hover:text-white'">
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" /></svg>
            {{ item.name }}
            <span v-if="item.badge > 0" class="ml-auto rounded-full px-2 py-0.5 text-[10px] font-bold text-white" :class="item.amber ? 'bg-amber-500' : 'bg-orange-600'">{{ item.badge }}</span>
          </Link>
        </div>
      </nav>
      <div class="shrink-0 border-t border-ink-700 p-4">
        <div class="flex items-center gap-3">
          <Link :href="route('profile.edit')" class="-m-1 flex min-w-0 flex-1 items-center gap-3 rounded-lg p-1 hover:bg-ink-800" title="Buka profil saya">
            <UserAvatar :src="user?.avatar_url" :name="user?.name ?? '?'" :size="36" />
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium text-white">{{ user?.name }}</p>
              <p class="truncate text-xs text-slate-400">{{ [user?.position || user?.job_title, user?.role_label].filter(Boolean).join(' · ') }}</p>
            </div>
          </Link>
          <Link :href="route('logout')" method="post" as="button" class="shrink-0 text-slate-400 hover:text-white" title="Keluar">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
          </Link>
        </div>
      </div>
    </aside>

    <!-- Sidebar mobile -->
    <div v-if="sidebarOpen" class="fixed inset-0 z-40 lg:hidden">
      <div class="absolute inset-0 bg-black/50" @click="sidebarOpen = false"></div>
      <aside class="absolute inset-y-0 left-0 flex w-72 flex-col bg-ink-900 p-4">
        <nav class="flex-1 overflow-y-auto" aria-label="Menu utama">
          <div v-for="(g, gi) in groups" :key="gi" class="mt-3 first:mt-0">
            <p v-if="g.title" class="mb-1 px-3 text-xs text-slate-500">{{ g.title }}</p>
            <Link v-for="item in g.items" :key="item.name" :href="item.href" @click="sidebarOpen = false"
              class="flex items-center rounded-lg px-3 py-2.5 text-sm font-medium"
              :class="isActive(item.href) ? 'bg-harbor-500/15 text-harbor-300' : 'text-slate-300 hover:bg-ink-800 hover:text-white'">
              {{ item.name }}
              <span v-if="item.badge > 0" class="ml-auto rounded-full bg-orange-600 px-2 py-0.5 text-[10px] font-bold text-white">{{ item.badge }}</span>
            </Link>
          </div>
        </nav>
        <div class="shrink-0 border-t border-ink-700 pt-3">
          <Link :href="route('profile.edit')" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-300 hover:bg-ink-800">
            <UserAvatar :src="user?.avatar_url" :name="user?.name ?? '?'" :size="24" /> Profil saya
          </Link>
          <Link :href="route('logout')" method="post" as="button" class="w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-300 hover:bg-ink-800">Keluar</Link>
        </div>
      </aside>
    </div>

    <div class="lg:pl-64">
      <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-slate-200 bg-white/85 px-4 backdrop-blur lg:px-8 dark:border-ink-700 dark:bg-ink-900/85">
        <div class="flex items-center gap-3">
          <button class="text-slate-500 lg:hidden" title="Buka menu" @click="sidebarOpen = true">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
          </button>
          <p class="text-sm font-medium text-slate-600 dark:text-slate-300"><slot name="title">PROCURA</slot></p>
        </div>
        <div class="flex items-center gap-2">
          <button class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-ink-800" title="Ganti tema" @click="toggleTheme">
            <svg v-if="!isDark" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
            <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
          </button>
          <div ref="notifWrap" class="relative">
            <button class="relative rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-ink-800" title="Notifikasi" @click="notifOpen = !notifOpen">
              <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
              <span v-if="notifCount > 0" class="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-orange-600 text-[10px] font-bold text-white">{{ notifCount > 9 ? '9+' : notifCount }}</span>
            </button>
            <div v-if="notifOpen" class="absolute right-0 z-30 mt-2 w-80 rounded-xl bg-white p-2 shadow-lg ring-1 ring-slate-100 dark:bg-ink-800 dark:ring-ink-700">
              <p class="px-3 py-2 text-xs font-semibold text-slate-400">Perlu perhatianmu</p>
              <Link v-for="(item, i) in notifItems" :key="i" :href="item.url" class="block rounded-lg px-3 py-2.5 text-sm hover:bg-slate-50 dark:hover:bg-ink-700" @click="notifOpen = false"
                :class="{ 'text-rose-600': item.type === 'danger', 'text-amber-700': item.type === 'warning', 'text-green-700': item.type === 'success', 'text-slate-600 dark:text-slate-300': item.type === 'info' }">{{ item.text }}</Link>
              <p v-if="notifItems.length === 0" class="px-3 py-4 text-center text-sm text-slate-400">Semua beres. Tidak ada yang menunggu.</p>
            </div>
          </div>
          <slot name="actions" />
        </div>
      </header>

      <div v-if="$page.props.flash?.success" role="status" class="mx-4 mt-4 rounded-lg bg-green-50 px-4 py-3 text-sm font-medium text-green-800 lg:mx-8 dark:bg-green-950 dark:text-green-300">{{ $page.props.flash.success }}</div>
      <div v-if="bannerMessages.length && !dismissedError" role="alert" class="mx-4 mt-4 flex items-start justify-between gap-3 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 lg:mx-8 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300">
        <ul class="space-y-1"><li v-for="m in bannerMessages" :key="m">{{ m }}</li></ul>
        <button type="button" class="shrink-0 font-bold opacity-60 hover:opacity-100" title="Tutup" @click="dismissedError = true">&times;</button>
      </div>

      <main class="p-4 lg:p-8"><slot /></main>
    </div>
  </div>
</template>
