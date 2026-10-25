@extends('layouts.app')

@section('title', 'Tambah Barang Non Elektronik - Sistem Inventaris')
@section('page-title', 'Tambah Barang Non Elektronik')

@section('content')
<div class="bg-white rounded-xl border border-slate-200 p-6 max-w-3xl">
    <form method="POST" action="{{ route('non-elektronik.store') }}" enctype="multipart/form-data">
        @include('non-elektronik._form')
    </form>
</div>
@endsection
