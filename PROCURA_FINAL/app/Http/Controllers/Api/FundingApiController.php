<?php

namespace App\Http\Controllers\Api;

use App\Models\FundingRequest;
use App\Models\Handover;
use App\Services\ProcurementService;
use App\Support\Presenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** Sisi Keuangan (pengajuan, keputusan, pencairan) & sisi Inventaris (konfirmasi serah terima). */
class FundingApiController extends ApiController
{
    public function __construct(private ProcurementService $svc)
    {
    }

    public function index(Request $request)
    {
        $list = FundingRequest::with('request')
            ->when($request->status, fn ($q, $s) => $q->whereIn('status', explode(',', $s)))
            ->when($request->updated_since, fn ($q, $s) => $q->where('updated_at', '>=', $s))
            ->orderByDesc('id')->paginate(min((int) $request->get('per_page', 25), 100));

        return $this->paginated($list, fn ($f) => Presenter::funding($f));
    }

    public function show(string $number)
    {
        return $this->ok(Presenter::funding(FundingRequest::with('request')->where('number', $number)->firstOrFail()));
    }

    public function decision(Request $request, string $number)
    {
        $f = FundingRequest::with('request')->where('number', $number)->firstOrFail();
        $v = Validator::make($request->all(), [
            'decision' => 'required|in:approve,reject,revision', 'approved_amount' => 'nullable|numeric|gt:0', 'note' => 'nullable|string|max:1000',
            'decided_by' => 'required|string|max:120', 'finance_ref' => 'nullable|string|max:60',
        ]);
        if ($v->fails()) {
            return $this->error(422, 'validation_failed', 'Data keputusan tidak valid.', $v->errors()->toArray());
        }
        $d = $v->validated();
        $client = $this->client($request);

        return $this->run(function () use ($f, $d, $client) {
            $this->svc->financeDecide($f, $d['decision'], isset($d['approved_amount']) ? (float) $d['approved_amount'] : null, $d['note'] ?? null, $d['decided_by'], null, $d['finance_ref'] ?? null, "API: {$client->name}");

            return $this->ok(Presenter::funding($f->fresh('request')));
        });
    }

    public function disbursement(Request $request, string $number)
    {
        $f = FundingRequest::with('request')->where('number', $number)->firstOrFail();
        $v = Validator::make($request->all(), [
            'amount' => 'required|numeric|gt:0', 'method' => 'required|in:transfer,tunai,cek', 'reference' => 'nullable|string|max:60', 'disbursed_by' => 'required|string|max:120',
        ]);
        if ($v->fails()) {
            return $this->error(422, 'validation_failed', 'Data pencairan tidak valid.', $v->errors()->toArray());
        }
        $d = $v->validated();
        $client = $this->client($request);

        return $this->run(function () use ($f, $d, $client) {
            $this->svc->disburse($f, (float) $d['amount'], $d['method'], $d['reference'] ?? null, $d['disbursed_by'], null, "API: {$client->name}");

            return $this->ok(Presenter::funding($f->fresh('request')));
        });
    }

    /** POST /funding/{number}/notes — catatan/laporan dari Keuangan ke Pengadaan (mis. LPJ diterima, dokumen kurang). */
    public function note(Request $request, string $number)
    {
        $f = FundingRequest::with('request')->where('number', $number)->firstOrFail();
        $v = Validator::make($request->all(), ['subject' => 'required|string|max:200', 'body' => 'nullable|string|max:3000', 'author' => 'required|string|max:120']);
        if ($v->fails()) {
            return $this->error(422, 'validation_failed', 'Data catatan tidak valid.', $v->errors()->toArray());
        }
        $d = $v->validated();
        $client = $this->client($request);

        $this->svc->report('keuangan', 'in', 'note', $d['subject'], trim(($d['body'] ?? '')."\n\n— {$d['author']}"), $f->request, $f, false, null);
        $this->svc->event($f->request, 'report', 'Catatan dari Keuangan: '.$d['subject'], null, "API: {$client->name}");

        return $this->ok(['funding' => $f->number, 'recorded' => true], 201);
    }

    public function confirmHandover(Request $request, string $number)
    {
        $h = Handover::with('request')->where('number', $number)->firstOrFail();
        $v = Validator::make($request->all(), ['confirmed_by' => 'required|string|max:120']);
        if ($v->fails()) {
            return $this->error(422, 'validation_failed', 'Isi confirmed_by.', $v->errors()->toArray());
        }
        $client = $this->client($request);
        $this->svc->confirmHandover($h, $v->validated()['confirmed_by'], null, "API: {$client->name}");

        return $this->ok(Presenter::handover($h->fresh()));
    }
}
