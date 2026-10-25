@extends('layouts.app')

@section('title', 'Ubah Gedung - Sistem Inventaris')
@section('page-title', 'Ubah Gedung Perusahaan')

@section('content')
<div class="bg-white rounded-xl border border-slate-200 p-6 max-w-4xl">
    <form method="POST" action="{{ route('bangunan.update', $bangunan) }}" enctype="multipart/form-data">
        @include('bangunan._form')
    </form>
</div>
@endsection
