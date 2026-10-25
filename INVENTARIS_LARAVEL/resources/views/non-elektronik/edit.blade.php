@extends('layouts.app')

@section('title', 'Ubah Barang Non Elektronik - Sistem Inventaris')
@section('page-title', 'Ubah Barang Non Elektronik')

@section('content')
<div class="bg-white rounded-xl border border-slate-200 p-6 max-w-3xl">
    <form method="POST" action="{{ route('non-elektronik.update', $alat) }}" enctype="multipart/form-data">
        @include('non-elektronik._form')
    </form>
</div>
@endsection
