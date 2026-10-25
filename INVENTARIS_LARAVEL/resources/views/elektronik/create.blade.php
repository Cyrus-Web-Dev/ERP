@extends('layouts.app')

@section('title', 'Tambah Barang Elektronik - Sistem Inventaris')
@section('page-title', 'Tambah Barang Elektronik')

@section('content')
<div class="bg-white rounded-xl border border-slate-200 p-6 max-w-3xl">
    <form method="POST" action="{{ route('elektronik.store') }}" enctype="multipart/form-data">
        @include('elektronik._form')
    </form>
</div>
@endsection
