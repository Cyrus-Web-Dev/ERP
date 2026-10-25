@extends('layouts.app')

@section('title', 'Tambah Perabotan - Sistem Inventaris')
@section('page-title', 'Tambah Perabotan')

@section('content')
<div class="bg-white rounded-xl border border-slate-200 p-6 max-w-3xl">
    <form method="POST" action="{{ route('perabotan.store') }}" enctype="multipart/form-data">
        @include('perabotan._form')
    </form>
</div>
@endsection
