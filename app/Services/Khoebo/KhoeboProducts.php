<?php

namespace App\Services\Khoebo;

use App\Models\KhoeboProduct;

/**
 * VENTIQ's products in Khoebo: the list lives in config
 * (services.khoebo.products); each is made in Khoebo once and its id kept
 * in khoebo_products.
 */
class KhoeboProducts
{
    public function __construct(private KhoeboClient $khoebo) {}

    /**
     * Make in Khoebo every product it doesn't have yet.
     *
     * @return array<string, array{id: int, made: bool}> per product key
     */
    public function sync(): array
    {
        $result = [];
        foreach (array_keys(config('services.khoebo.products', [])) as $key) {
            $had = KhoeboProduct::where('key', $key)->exists();
            $result[$key] = ['id' => $this->ensure($key), 'made' => ! $had];
        }

        return $result;
    }

    /** @return int Khoebo's id for the product */
    public function ensure(string $key): int
    {
        if ($product = KhoeboProduct::where('key', $key)->first()) {
            return (int) $product->khoebo_id;
        }

        $made = $this->khoebo->create('products', $this->body($key), "ventiq-product-{$key}");

        KhoeboProduct::create(['key' => $key, 'khoebo_id' => $made['id']]);

        return (int) $made['id'];
    }

    /** Khoebo's id for a product already made, for order lines. */
    public function id(string $key): int
    {
        return $this->ensure($key);
    }

    public function body(string $key): array
    {
        $product = config("services.khoebo.products.{$key}")
            ?? throw new KhoeboException("No VENTIQ product called {$key} in config services.khoebo.products.");

        return [
            'name'               => $product['name'],
            'sku'                => $product['sku'],
            'type'               => $product['type'] ?? 'service',
            'sale_price'         => number_format((float) $product['sale_price'], 2, '.', ''),
            'tax_ids'            => config('services.khoebo.tax_ids', []),
            'external_reference' => "ventiq-{$key}",
        ];
    }
}
