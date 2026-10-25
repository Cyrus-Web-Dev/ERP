@extends('layouts.app')

@section('title', 'Tambah Penggunaan - Sistem Inventaris')
@section('page-title', 'Tambah Penggunaan Barang')

@section('content')
<div class="bg-white rounded-xl border border-slate-200 p-6 max-w-2xl">
    <form method="POST" action="{{ route('penggunaan.store') }}" enctype="multipart/form-data">
        @include('penggunaan._form')
    </form>
</div>
@endsection
