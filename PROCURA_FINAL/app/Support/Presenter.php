<?php

namespace App\Support;

use App\Models\FundingRequest;
use App\Models\Handover;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;

/** Bentuk data untuk REST API & webhook (stabil, tidak bocor kolom internal). */
class Presenter
{
    public static function request(PurchaseRequest $pr, bool $detail = true): array
    {
        $out = [
            'id' => $pr->id,
            'number' => $pr->number,
            'external_ref' => $pr->external_ref,
            'title' => $pr->title,
            'category' => $pr->category,
            'priority' => $pr->priority,
            'status' => $pr->status,
            'status_label' => Workflow::label(Workflow::REQUEST_STATUS, $pr->status),
            'station' => $pr->stationKey(),
            'requester' => ['name' => $pr->requester_name, 'unit' => $pr->requester_unit],
            'needed_date' => $pr->needed_date?->toDateString(),
            'estimated_total' => $pr->estimated_total,
            'rejection_reason' => $pr->rejection_reason,
            'created_at' => $pr->created_at?->toIso8601String(),
            'completed_at' => $pr->completed_at?->toIso8601String(),
        ];

        if ($detail) {
            $pr->loadMissing(['items', 'fundingRequests', 'purchaseOrders.vendor', 'handovers']);
            $out['items'] = $pr->items->map(fn ($i) => ['name' => $i->name, 'specification' => $i->specification, 'quantity' => $i->quantity, 'unit' => $i->unit, 'estimated_price' => $i->estimated_price])->all();
            $out['funding'] = $pr->fundingRequests->map(fn ($f) => self::funding($f))->all();
            $out['purchase_orders'] = $pr->purchaseOrders->map(fn ($p) => self::order($p, true))->all();
            $out['handovers'] = $pr->handovers->map(fn ($h) => self::handover($h))->all();
        }

        return $out;
    }

    public static function funding(FundingRequest $f): array
    {
        return [
            'id' => $f->id,
            'number' => $f->number,
            'request_number' => $f->request?->number,
            'request_title' => $f->request?->title,
            'category' => $f->request?->category,
            'status' => $f->status,
            'status_label' => Workflow::label(Workflow::FUNDING_STATUS, $f->status),
            'amount' => $f->amount,
            'purpose' => $f->purpose,
            'submitted_at' => $f->submitted_at?->toIso8601String(),
            'approved_amount' => $f->approved_amount,
            'decided_by' => $f->decided_by_name,
            'decided_at' => $f->decided_at?->toIso8601String(),
            'decision_note' => $f->decision_note,
            'finance_ref' => $f->finance_ref,
            'disbursed_amount' => $f->disbursed_amount,
            'disbursed_at' => $f->disbursed_at?->toIso8601String(),
            'actual_spent' => $f->actual_spent,
            'returned_amount' => $f->returned_amount,
            'settled_at' => $f->settled_at?->toIso8601String(),
        ];
    }

    public static function order(PurchaseOrder $po, bool $withItems = false): array
    {
        $out = [
            'id' => $po->id,
            'number' => $po->number,
            'request_number' => $po->request?->number,
            'status' => $po->status,
            'vendor' => $po->vendor?->name,
            'order_date' => $po->order_date?->toDateString(),
            'expected_date' => $po->expected_date?->toDateString(),
            'tax_percent' => $po->tax_percent,
            'total' => $po->total,
        ];

        if ($withItems) {
            $po->loadMissing('items');
            $out['items'] = $po->items->map(fn ($i) => [
                'description' => $i->description, 'quantity' => $i->quantity, 'received_quantity' => $i->received_quantity,
                'unit' => $i->unit, 'unit_price' => $i->unit_price,
            ])->all();
        }

        return $out;
    }

    public static function handover(Handover $h): array
    {
        return [
            'id' => $h->id,
            'number' => $h->number,
            'status' => $h->status,
            'handover_date' => $h->handover_date?->toDateString(),
            'receiver_name' => $h->receiver_name,
            'notes' => $h->notes,
            'confirmed_at' => $h->confirmed_at?->toIso8601String(),
        ];
    }
}
