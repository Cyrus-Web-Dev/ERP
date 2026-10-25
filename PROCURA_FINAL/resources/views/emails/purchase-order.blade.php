<!DOCTYPE html>
<html lang="id"><body style="margin:0;background:#eef1f5;font-family:Arial,Helvetica,sans-serif;color:#16202c;">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:24px 0;"><tr><td align="center">
<table width="620" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:10px;overflow:hidden;">
  <tr><td style="background:#0f1b2d;padding:18px 28px;"><span style="color:#ffffff;font-size:18px;font-weight:bold;">PURCHASE ORDER</span>
    <span style="color:#f2a100;font-size:18px;font-weight:bold;float:right;">{{ $po->number }}</span></td></tr>
  <tr><td style="padding:24px 28px;">
    <p style="margin:0 0 12px;font-size:15px;">Yth. <strong>{{ $po->vendor->contact_person ?: $po->vendor->name }}</strong>,</p>
    <p style="margin:0 0 16px;font-size:14px;line-height:1.55;">Berikut pesanan pembelian dari kami. Mohon konfirmasi penerimaan PO ini dan jadwal pengiriman.</p>
    <table width="100%" cellpadding="6" cellspacing="0" style="font-size:13px;border-collapse:collapse;">
      <tr style="background:#f3f5f7;"><th align="left">Uraian</th><th align="right">Qty</th><th align="right">Harga</th><th align="right">Jumlah</th></tr>
      @foreach($po->items as $it)
      <tr style="border-bottom:1px solid #e5e9ee;"><td>{{ $it->description }}</td><td align="right">{{ rtrim(rtrim(number_format($it->quantity,2,',','.'),'0'),',') }} {{ $it->unit }}</td>
        <td align="right">{{ number_format($it->unit_price,0,',','.') }}</td><td align="right">{{ number_format($it->quantity*$it->unit_price,0,',','.') }}</td></tr>
      @endforeach
      <tr><td colspan="3" align="right">Subtotal</td><td align="right">{{ number_format($po->subtotal,0,',','.') }}</td></tr>
      <tr><td colspan="3" align="right">PPN {{ rtrim(rtrim(number_format($po->tax_percent,2,',','.'),'0'),',') }}%</td><td align="right">{{ number_format($po->tax_amount,0,',','.') }}</td></tr>
      <tr><td colspan="3" align="right"><strong>Total</strong></td><td align="right"><strong>Rp {{ number_format($po->total,0,',','.') }}</strong></td></tr>
    </table>
    <p style="margin:18px 0 4px;font-size:13px;"><strong>Tanggal pesan:</strong> {{ $po->order_date->format('d/m/Y') }}
      @if($po->expected_date) &nbsp; <strong>Dibutuhkan:</strong> {{ $po->expected_date->format('d/m/Y') }} @endif</p>
    @if($po->payment_terms)<p style="margin:0 0 4px;font-size:13px;"><strong>Pembayaran:</strong> {{ $po->payment_terms }}</p>@endif
    @if($po->delivery_address)<p style="margin:0 0 4px;font-size:13px;"><strong>Kirim ke:</strong> {{ $po->delivery_address }}</p>@endif
    @if($po->notes)<p style="margin:12px 0 0;font-size:13px;color:#475569;">{{ $po->notes }}</p>@endif
    <p style="font-size:12px;color:#94a3b8;margin:24px 0 0;">Email ini dikirim otomatis oleh sistem PROCURA.</p>
  </td></tr>
</table></td></tr></table></body></html>
