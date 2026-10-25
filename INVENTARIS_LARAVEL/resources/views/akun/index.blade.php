@extends('layouts.app')

@section('title', 'Manajemen Akun - Sistem Inventaris')
@section('page-title', 'Manajemen Akun')

@section('content')
<div class="space-y-5">

    <form method="GET" class="flex gap-2">
        <input type="text" name="search" value="{{ $search }}" placeholder="Cari nama, username, atau email..."
               class="px-4 py-2.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 flex-1 max-w-md">
        <button type="submit" class="px-4 py-2.5 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-medium"><i class="bi bi-search"></i> Cari</button>
        @if($search)
            <a href="{{ route('manage-accounts.index') }}" class="px-4 py-2.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-medium">Reset</a>
        @endif
    </form>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                    <tr>
                        <th class="text-left px-5 py-3">Nama</th>
                        <th class="text-left px-5 py-3">Username</th>
                        <th class="text-left px-5 py-3">Email</th>
                        <th class="text-left px-5 py-3">Role</th>
                        <th class="text-left px-5 py-3">Status</th>
                        <th class="text-right px-5 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($users as $u)
                        @php $isSelf = $u->id_user === auth()->id(); @endphp
                        <tr>
                            <td class="px-5 py-3 font-medium text-slate-800">
                                {{ $u->nama_lengkap }}
                                @if($isSelf)<span class="text-xs text-slate-400">(Anda)</span>@endif
                            </td>
                            <td class="px-5 py-3 text-slate-600">{{ $u->username }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $u->email }}</td>
                            <td class="px-5 py-3">
                                @if($isSelf)
                                    <span class="text-xs px-2 py-1 rounded-full {{ $u->level_akses === 'super_admin' ? 'bg-violet-100 text-violet-700' : 'bg-sky-100 text-sky-700' }}">
                                        {{ $u->level_akses === 'super_admin' ? 'Super Admin' : 'Admin' }}
                                    </span>
                                @else
                                    <form id="role-{{ $u->id_user }}" method="POST" action="{{ route('manage-accounts.update-role', $u->id_user) }}">
                                        @csrf @method('PUT')
                                        <select name="level_akses" onchange="if(confirm('Ubah role {{ addslashes($u->username) }}?')) this.form.submit(); else this.value='{{ $u->level_akses }}';"
                                                class="text-xs px-2 py-1.5 rounded-lg border border-slate-300">
                                            <option value="admin" {{ $u->level_akses === 'admin' ? 'selected' : '' }}>Admin</option>
                                            <option value="super_admin" {{ $u->level_akses === 'super_admin' ? 'selected' : '' }}>Super Admin</option>
                                        </select>
                                    </form>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                @if($isSelf)
                                    <span class="text-xs px-2 py-1 rounded-full bg-emerald-100 text-emerald-700">Aktif</span>
                                @else
                                    <form id="status-{{ $u->id_user }}" method="POST" action="{{ route('manage-accounts.update-status', $u->id_user) }}">
                                        @csrf @method('PUT')
                                        <select name="status" onchange="this.form.submit();"
                                                class="text-xs px-2 py-1.5 rounded-lg border {{ $u->status === 'aktif' ? 'border-emerald-300 text-emerald-700 bg-emerald-50' : 'border-red-300 text-red-700 bg-red-50' }}">
                                            <option value="aktif" {{ $u->status === 'aktif' ? 'selected' : '' }}>Aktif</option>
                                            <option value="nonaktif" {{ $u->status === 'nonaktif' ? 'selected' : '' }}>Nonaktif</option>
                                        </select>
                                    </form>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                @unless($isSelf)
                                    <div class="flex justify-end">
                                        <form id="hapus-akun-{{ $u->id_user }}" method="POST" action="{{ route('manage-accounts.destroy', $u->id_user) }}">@csrf @method('DELETE')</form>
                                        <button type="button" onclick="confirmHapus('hapus-akun-{{ $u->id_user }}', { title: 'Hapus akun {{ addslashes($u->username) }}?', text: 'Tindakan ini tidak bisa dibatalkan.' })"
                                                class="px-3 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-medium"><i class="bi bi-trash"></i></button>
                                    </div>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-12 text-center text-slate-400">Tidak ada akun ditemukan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($users->hasPages())
        <div>{{ $users->links() }}</div>
    @endif
</div>
@endsection
