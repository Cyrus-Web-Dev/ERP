@extends('layouts.app')

@section('title', 'Ubah Kendaraan - Sistem Inventaris')
@section('page-title', 'Ubah Kendaraan Operasional')

@section('content')
<div class="bg-white rounded-xl border border-slate-200 p-6 max-w-3xl">
    <form method="POST" action="{{ route('kendaraan.update', $kendaraan) }}" enctype="multipart/form-data">
        @include('kendaraan._form')
    </form>
</div>
@endsection
