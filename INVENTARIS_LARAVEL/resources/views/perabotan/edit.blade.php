@extends('layouts.app')

@section('title', 'Ubah Perabotan - Sistem Inventaris')
@section('page-title', 'Ubah Perabotan')

@section('content')
<div class="bg-white rounded-xl border border-slate-200 p-6 max-w-3xl">
    <form method="POST" action="{{ route('perabotan.update', $perabotan) }}" enctype="multipart/form-data">
        @include('perabotan._form')
    </form>
</div>
@endsection
