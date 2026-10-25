@extends('layouts.app')

@section('title', 'Tambah Gedung - Sistem Inventaris')
@section('page-title', 'Tambah Gedung Perusahaan')

@section('content')
<div class="bg-white rounded-xl border border-slate-200 p-6 max-w-4xl">
    <form method="POST" action="{{ route('bangunan.store') }}" enctype="multipart/form-data">
        @include('bangunan._form')
    </form>
</div>
@endsection
