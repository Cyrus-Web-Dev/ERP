@extends('layouts.app')

@section('title', 'Tambah Kendaraan - Sistem Inventaris')
@section('page-title', 'Tambah Kendaraan Operasional')

@section('content')
<div class="bg-white rounded-xl border border-slate-200 p-6 max-w-3xl">
    <form method="POST" action="{{ route('kendaraan.store') }}" enctype="multipart/form-data">
        @include('kendaraan._form')
    </form>
</div>
@endsection
