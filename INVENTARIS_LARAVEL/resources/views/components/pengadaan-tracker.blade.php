{{-- Jalur pengadaan: pengajuan bergerak dari stasiun ke stasiun. Titik oranye = posisi sekarang. --}}
@props(['stasiun' => 'submitted', 'status' => '', 'ringkas' => false])
@php
    $daftar = config('procura.stasiun');
    $kunci = array_keys($daftar);
    $cari = array_search($stasiun, $kunci, true);
    $idx = $cari === false ? 0 : $cari;
    $berhenti = in_array($status, ['rejected', 'cancelled'], true);
    $tuntas = $status === 'completed';
    $keadaan = function ($i) use ($idx, $berhenti, $tuntas) {
        if ($berhenti) return $i < $idx ? 'lewat' : ($i === $idx ? 'berhenti' : 'depan');
        if ($tuntas) return 'lewat';
        return $i < $idx ? 'lewat' : ($i === $idx ? 'kini' : 'depan');
    };
@endphp
<ol class="flex w-full items-start" aria-label="Posisi di jalur pengadaan: {{ $daftar[$kunci[$idx]] }}">
    @foreach($kunci as $i => $k)
        @php $st = $keadaan($i); @endphp
        <li class="relative flex min-w-0 flex-1 flex-col items-center">
            @if($i > 0)
                <span class="absolute right-1/2 top-[9px] h-0.5 w-full {{ in_array($st, ['depan', 'berhenti']) ? 'border-t-2 border-dashed border-slate-300' : 'bg-brand-500' }}" aria-hidden="true"></span>
            @endif
            <span class="relative z-10 grid h-5 w-5 place-items-center rounded-full border-2 bg-white
                {{ $st === 'lewat' ? 'border-brand-500 bg-brand-500' : ($st === 'kini' ? 'border-orange-500 ring-4 ring-orange-100' : ($st === 'berhenti' ? 'border-rose-500' : 'border-slate-300')) }}">
                @if($st === 'lewat')
                    <i class="bi bi-check text-white text-xs leading-none"></i>
                @elseif($st === 'kini')
                    <span class="h-2 w-2 rounded-full bg-orange-500"></span>
                @elseif($st === 'berhenti')
                    <span class="text-[10px] font-bold leading-none text-rose-500">×</span>
                @endif
            </span>
            @unless($ringkas)
                <span class="mt-1.5 hidden px-0.5 text-center text-[11px] leading-tight sm:block
                    {{ $st === 'kini' ? 'font-semibold text-orange-600' : ($st === 'berhenti' ? 'font-semibold text-rose-600' : ($st === 'lewat' ? 'text-slate-600' : 'text-slate-400')) }}">{{ $daftar[$k] }}</span>
            @endunless
        </li>
    @endforeach
</ol>
