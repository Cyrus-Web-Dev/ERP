@props(['status'])
@php [$label, $kelas] = config("procura.status.{$status}", [$status, 'bg-slate-200 text-slate-700']); @endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium whitespace-nowrap {$kelas}"]) }}>{{ $label }}</span>
