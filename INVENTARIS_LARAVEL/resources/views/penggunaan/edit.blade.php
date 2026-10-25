@extends('layouts.app')

@section('title', 'Ubah Penggunaan - Sistem Inventaris')
@section('page-title', 'Ubah Penggunaan Barang')

@section('content')
<div class="bg-white rounded-xl border border-slate-200 p-6 max-w-2xl">
    <form method="POST" action="{{ route('penggunaan.update', $penggunaan) }}" enctype="multipart/form-data">
        @include('penggunaan._form')
    </form>
</div>
@endsection
