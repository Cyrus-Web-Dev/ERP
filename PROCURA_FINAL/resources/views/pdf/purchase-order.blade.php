@extends('pdf.layout')
@section('content')
<table style="margin:0 0 6px;"><tr>
  <td style="border:0;"><h1>PURCHASE ORDER</h1><div class="subtitle">{{ config('app.name') }}</div></td>
  <td style="border:0;text-align:right;"><div style="font-size:14px;font-weight:bold;">{{ $po->number }}</div>
    <div>Tanggal: {{ $po->order_date->format('d/m/Y') }}</div>@if($po->expected_date)<div>Dibutuhkan: {{ $po->expected_date->format('d/m/Y') }}</div>@endif</td>
</tr></table>
<table style="margin-top:0;"><tr>
  <td style="border:0;width:50%;vertical-align:top;"><div class="section-title">Kepada Vendor</div><strong>{{ $po->vendor->name }}</strong><br>{{ $po->vendor->contact_person }}<br>{{ $po->vendor->address }}<br>{{ $po->vendor->phone }} {{ $po->vendor->email }}</td>
  <td style="border:0;vertical-align:top;"><div class="section-title">Pengiriman</div>{{ $po->delivery_address ?: '—' }}<br>Pembayaran: {{ $po->payment_terms ?: '—' }}<br>Ref. permintaan: {{ $po->request->number }}</td>
</tr></table>
<table>
  <tr><th>#</th><th>Uraian</th><th class="text-right">Qty</th><th>Satuan</th><th class="text-right">Harga</th><th class="text-right">Jumlah</th></tr>
  @foreach($po->items as $i => $it)
  <tr><td>{{ $i + 1 }}</td><td>{{ $it->description }}</td><td class="text-right">{{ rtrim(rtrim(number_format($it->quantity, 2, ',', '.'), '0'), ',') }}</td><td>{{ $it->unit }}</td>
    <td class="text-right">{{ number_format($it->unit_price, 0, ',', '.') }}</td><td class="text-right">{{ number_format($it->quantity * $it->unit_price, 0, ',', '.') }}</td></tr>
  @endforeach
  <tr><td colspan="5" class="text-right">Subtotal</td><td class="text-right">{{ number_format($po->subtotal, 0, ',', '.') }}</td></tr>
  <tr><td colspan="5" class="text-right">PPN {{ rtrim(rtrim(number_format($po->tax_percent, 2, ',', '.'), '0'), ',') }}%</td><td class="text-right">{{ number_format($po->tax_amount, 0, ',', '.') }}</td></tr>
  <tr class="total-row"><td colspan="5" class="text-right">TOTAL</td><td class="text-right">Rp {{ number_format($po->total, 0, ',', '.') }}</td></tr>
</table>
@if($po->notes)<div class="section-title">Catatan</div><div>{{ $po->notes }}</div>@endif
<table style="margin-top:36px;"><tr><td style="border:0;width:60%;"></td><td style="border:0;text-align:center;">Hormat kami,<br><br><br><br>______________________<br>Bagian Pengadaan</td></tr></table>
@endsection
