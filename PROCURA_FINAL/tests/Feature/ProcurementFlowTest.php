<?php

namespace Tests\Feature;

use App\Exceptions\WorkflowException;
use App\Models\ApiClient;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ProcurementService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Menguji aturan alur: Inventaris -> Pengadaan -> Keuangan -> dana -> PO -> terima -> LPJ -> serah terima. */
class ProcurementFlowTest extends TestCase
{
    use RefreshDatabase;

    private ProcurementService $svc;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->user = User::factory()->create();
        $this->user->assignRole('admin');
        $this->svc = app(ProcurementService::class);
    }

    private function newRequest(): PurchaseRequest
    {
        return $this->svc->createRequest([
            'title' => 'Laptop staf', 'category' => 'elektronik', 'requester_name' => 'Rina', 'external_ref' => 'INV-1',
            'items' => [['name' => 'Laptop', 'quantity' => 2, 'unit' => 'unit', 'estimated_price' => 10000000]],
        ], 'inventaris', $this->user);
    }

    private function withQuotation(PurchaseRequest $pr, float $price = 9000000): void
    {
        $vendor = Vendor::create(['code' => 'V1', 'name' => 'Vendor Satu', 'email' => 'v@example.test', 'status' => 'active']);
        $q = $this->svc->addQuotation($pr, ['vendor_id' => $vendor->id, 'quoted_date' => now()->toDateString(), 'lead_time_days' => 5, 'prices' => [$pr->items->first()->id => $price]], $this->user);
        $this->svc->selectQuotation($pr->fresh(), $q, 'Harga terendah', $this->user);
    }

    public function test_password_is_hashed_with_argon2id(): void
    {
        $this->assertStringStartsWith('$argon2id$', Hash::make('rahasia-123'));
    }

    public function test_full_procurement_lifecycle(): void
    {
        $pr = $this->newRequest();
        $this->assertSame('submitted', $pr->status);
        $this->assertDatabaseHas('reports', ['purchase_request_id' => $pr->id, 'counterpart' => 'inventaris', 'direction' => 'in']);

        $this->svc->startReview($pr, $this->user);
        $this->withQuotation($pr->fresh());
        $pr = $pr->fresh();
        $this->assertSame('sourcing', $pr->status);

        $f = $this->svc->submitFunding($pr, 20000000, 'Beli laptop', $this->user);   // < ambang => langsung ke Keuangan
        $this->assertSame('submitted', $f->status);
        $this->assertSame('funding', $pr->fresh()->status);

        $this->svc->financeDecide($f, 'approve', 20000000, 'OK', 'Bu Sari', null, 'KEU-77');
        $this->svc->disburse($f->fresh(), 20000000, 'transfer', 'TRF-1', 'Bu Sari');
        $this->assertSame('funded', $pr->fresh()->status);

        $po = $this->svc->createPurchaseOrder($pr->fresh(), ['order_date' => now()->toDateString(), 'tax_percent' => 11], $this->user);
        $this->assertEquals(19980000.0, $po->total);   // 2 x 9.000.000 + PPN 11%
        [$mailed] = $this->svc->sendPurchaseOrder($po, $this->user);
        $this->assertSame('ordered', $pr->fresh()->status);

        $this->svc->receiveGoods($po->fresh(), ['received_date' => now()->toDateString(), 'vendor_score' => 5, 'lines' => [['po_item_id' => $po->items->first()->id, 'quantity_received' => 2]]], $this->user);
        $this->assertSame('received', $pr->fresh()->status);
        $this->assertEquals(5.0, Vendor::first()->rating);

        $this->svc->payVendor($po->fresh(), 19980000, now()->toDateString(), 'transfer', 'PAY-1', null, $this->user);
        $this->svc->settleFunding($f->fresh(), null, $this->user);
        $this->assertEquals(20000.0, $f->fresh()->returned_amount);

        $h = $this->svc->handOver($pr->fresh(), now()->toDateString(), 'Rina', null, $this->user);
        $this->assertSame('completed', $pr->fresh()->status);   // serah terima + LPJ selesai
        $this->svc->confirmHandover($h, 'Rina');
        $this->assertSame('confirmed', $h->fresh()->status);
    }

    public function test_purchase_order_cannot_exceed_disbursed_funds(): void
    {
        $pr = $this->newRequest();
        $this->withQuotation($pr, 9000000);
        $pr = $pr->fresh();
        $f = $this->svc->submitFunding($pr, 18000000, 'Beli laptop', $this->user);
        $this->svc->financeDecide($f, 'approve', null, null, 'Bu Sari');
        $this->svc->disburse($f->fresh(), 18000000, 'transfer', null, 'Bu Sari');

        $this->expectException(WorkflowException::class);
        $this->svc->createPurchaseOrder($pr->fresh(), ['order_date' => now()->toDateString(), 'tax_percent' => 11], $this->user);   // 19,98 jt > 18 jt
    }

    public function test_cannot_create_po_before_funds_are_disbursed(): void
    {
        $pr = $this->newRequest();
        $this->withQuotation($pr);
        $this->expectException(WorkflowException::class);
        $this->svc->createPurchaseOrder($pr->fresh(), ['order_date' => now()->toDateString()], $this->user);
    }

    public function test_finance_rejection_returns_request_to_sourcing_and_can_resubmit(): void
    {
        $pr = $this->newRequest();
        $this->withQuotation($pr);
        $f = $this->svc->submitFunding($pr->fresh(), 19980000, 'Beli', $this->user);
        $this->svc->financeDecide($f, 'reject', null, 'Anggaran habis', 'Bu Sari');

        $this->assertSame('rejected', $f->fresh()->status);
        $this->assertSame('sourcing', $pr->fresh()->status);
        $this->assertNotNull($this->svc->submitFunding($pr->fresh(), 19980000, 'Ajukan lagi', $this->user));
    }

    public function test_large_funding_requires_internal_approval_for_non_approvers(): void
    {
        config(['procura.internal_approval_threshold' => 1000]);
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        $staff->givePermissionTo('funding.submit');

        $pr = $this->newRequest();
        $this->withQuotation($pr);
        $f = $this->svc->submitFunding($pr->fresh(), 5000000, 'Beli', $staff);
        $this->assertSame('pending_internal', $f->status);

        $this->svc->approveInternal($f, $this->user);
        $this->assertSame('submitted', $f->fresh()->status);
    }

    public function test_api_client_can_create_request_idempotently(): void
    {
        [$plain, $prefix, $hash] = ApiClient::makeToken();
        ApiClient::create(['name' => 'Inventaris', 'system' => 'inventaris', 'token_prefix' => $prefix, 'token_hash' => $hash, 'scopes' => ['requests:write', 'requests:read']]);
        $payload = ['title' => 'Kursi', 'category' => 'perabotan', 'requester_name' => 'Andi', 'external_ref' => 'INV-9', 'items' => [['name' => 'Kursi', 'quantity' => 4, 'unit' => 'unit']]];
        $h = ['Authorization' => "Bearer {$plain}", 'Idempotency-Key' => 'abc-1'];

        $first = $this->postJson('/api/v1/requests', $payload, $h)->assertCreated();
        $second = $this->postJson('/api/v1/requests', $payload, $h)->assertOk();
        $this->assertSame($first->json('data.number'), $second->json('data.number'));
        $this->assertSame(1, PurchaseRequest::count());
    }

    public function test_api_rejects_missing_or_wrong_scope(): void
    {
        $this->getJson('/api/v1/requests')->assertUnauthorized();

        [$plain, $prefix, $hash] = ApiClient::makeToken();
        ApiClient::create(['name' => 'Keuangan', 'system' => 'keuangan', 'token_prefix' => $prefix, 'token_hash' => $hash, 'scopes' => ['funding:read']]);
        $this->postJson('/api/v1/requests', [], ['Authorization' => "Bearer {$plain}"])->assertForbidden();
    }

    public function test_api_notes_endpoint_records_note_from_inventory(): void
    {
        [$plain, $prefix, $hash] = ApiClient::makeToken();
        ApiClient::create(['name' => 'Inventaris', 'system' => 'inventaris', 'token_prefix' => $prefix, 'token_hash' => $hash, 'scopes' => ['requests:write']]);
        $pr = $this->newRequest();

        $this->postJson("/api/v1/requests/{$pr->external_ref}/notes", ['subject' => 'Mohon dipercepat', 'body' => 'Dibutuhkan minggu depan', 'author' => 'Rina'], ['Authorization' => "Bearer {$plain}"])
            ->assertCreated();

        $this->assertDatabaseHas('reports', ['purchase_request_id' => $pr->id, 'kind' => 'note', 'subject' => 'Mohon dipercepat', 'direction' => 'in']);
    }

    public function test_api_funding_note_from_finance_is_recorded(): void
    {
        [$plain, $prefix, $hash] = ApiClient::makeToken();
        ApiClient::create(['name' => 'Keuangan', 'system' => 'keuangan', 'token_prefix' => $prefix, 'token_hash' => $hash, 'scopes' => ['funding:decide']]);
        $pr = $this->newRequest();
        $this->withQuotation($pr);
        $f = $this->svc->submitFunding($pr->fresh(), 19980000, 'Beli', $this->user);

        $this->postJson("/api/v1/funding/{$f->number}/notes", ['subject' => 'LPJ diterima', 'author' => 'Bu Sari'], ['Authorization' => "Bearer {$plain}"])->assertCreated();
        $this->assertDatabaseHas('reports', ['funding_request_id' => $f->id, 'counterpart' => 'keuangan', 'direction' => 'in', 'subject' => 'LPJ diterima']);
    }
}
