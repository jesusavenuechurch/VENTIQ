<?php

namespace Tests\Feature\Payments;

use App\Models\Organization;
use App\Services\Khoebo\{KhoeboCustomers, KhoeboException};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KhoeboCustomersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.khoebo.url' => 'https://khoebo.test/api/v1', 'services.khoebo.token' => 'itk_test']);
    }

    public function test_an_organization_is_made_a_customer_once(): void
    {
        Http::fake(['khoebo.test/*' => Http::response(['data' => ['id' => 41, 'name' => 'Maseru Events']], 201)]);
        $org = Organization::factory()->create(['name' => 'Maseru Events', 'email' => 'hello@maseru.test']);

        $this->assertSame(41, app(KhoeboCustomers::class)->ensure($org));
        $this->assertSame(41, (int) $org->fresh()->khoebo_customer_id);

        Http::assertSent(fn (Request $r) => $r->url() === 'https://khoebo.test/api/v1/customers'
            && $r->hasHeader('Authorization', 'Bearer itk_test')
            && $r->hasHeader('Idempotency-Key', "ventiq-customer-org-{$org->id}")
            && $r['name'] === 'Maseru Events'
            && $r['email'] === 'hello@maseru.test'
            && $r['is_company'] === true
            && $r['external_reference'] === "ventiq-org-{$org->id}");

        // Already a customer: Khoebo isn't asked again.
        app(KhoeboCustomers::class)->ensure($org->fresh());
        Http::assertSentCount(1);
    }

    public function test_a_refusal_keeps_the_organization_unlinked(): void
    {
        Http::fake(['khoebo.test/*' => Http::response(['message' => 'The name field is required.'], 422)]);
        $org = Organization::factory()->create();

        try {
            app(KhoeboCustomers::class)->ensure($org);
            $this->fail('Expected a KhoeboException');
        } catch (KhoeboException $e) {
            $this->assertSame(422, $e->status);
            $this->assertStringContainsString('name field is required', $e->getMessage());
        }
        $this->assertNull($org->fresh()->khoebo_customer_id);
    }

    public function test_nothing_is_sent_until_khoebo_is_set_up(): void
    {
        config(['services.khoebo.token' => null]);
        Http::fake();

        $this->expectException(KhoeboException::class);
        app(KhoeboCustomers::class)->ensure(Organization::factory()->create());
    }
}
