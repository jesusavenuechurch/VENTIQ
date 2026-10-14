<?php

namespace Tests\Feature\Payments;

use App\Models\KhoeboProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KhoeboProductsTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_are_made_once(): void
    {
        config(['services.khoebo.url' => 'https://khoebo.test/api/v1', 'services.khoebo.token' => 'itk_test']);
        $ids = ['VQ-PERSON' => 7, 'VQ-SALES' => 8];
        Http::fake(fn (Request $r) => Http::response(['data' => ['id' => $ids[$r['sku']]]], 201));

        $this->artisan('khoebo:products')->assertSuccessful();

        $this->assertSame(7, (int) KhoeboProduct::where('key', 'fee-person')->value('khoebo_id'));
        $this->assertSame(8, (int) KhoeboProduct::where('key', 'fee-sales')->value('khoebo_id'));
        Http::assertSent(fn (Request $r) => $r['sku'] === 'VQ-PERSON'
            && $r['sale_price'] === '7.50' && $r['type'] === 'service' && $r['tax_ids'] === [4]
            && $r['external_reference'] === 'ventiq-fee-person'
            && $r->hasHeader('Idempotency-Key', 'ventiq-product-fee-person'));

        // Run again: nothing new to make.
        $this->artisan('khoebo:products')->expectsOutputToContain('already in Khoebo')->assertSuccessful();
        Http::assertSentCount(2);
    }
}
