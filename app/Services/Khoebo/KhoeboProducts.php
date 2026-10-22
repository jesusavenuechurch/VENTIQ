<?php

namespace App\Services\Khoebo;

use App\Models\KhoeboProduct;

/**
 * VENTIQ's products in Khoebo: each book's list lives in its config
 * (services.khoebo.products, services.khoebo.sessions.products); each is
 * made in its Khoebo company once and its id kept in khoebo_products.
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
        foreach (array_keys($this->khoebo->setting('products', [])) as $key) {
            $had = KhoeboProduct::where('key', $this->stored($key))->exists();
            $result[$key] = ['id' => $this->ensure($key), 'made' => ! $had];
        }

        return $result;
    }

    /** @return int Khoebo's id for the product */
    public function ensure(string $key): int
    {
        if ($product = KhoeboProduct::where('key', $this->stored($key))->first()) {
            return (int) $product->khoebo_id;
        }

        $made = $this->khoebo->create('products', $this->body($key), "ventiq-product-{$key}");

        KhoeboProduct::create(['key' => $this->stored($key), 'khoebo_id' => $made['id']]);

        return (int) $made['id'];
    }

    /** Khoebo's id for a product already made, for order lines. */
    public function id(string $key): int
    {
        return $this->ensure($key);
    }

    /** Each book's products are its own company's: Sessions' are kept as "sessions:plan-team". */
    private function stored(string $key): string
    {
        return $this->khoebo->book === 'events' ? $key : "{$this->khoebo->book}:{$key}";
    }

    public function body(string $key): array
    {
        $product = $this->khoebo->setting("products.{$key}")
            ?? throw new KhoeboException("No VENTIQ product called {$key} in the {$this->khoebo->book} books (config).");

        return [
            'name'               => $product['name'],
            'sku'                => $product['sku'],
            'type'               => $product['type'] ?? 'service',
            'sale_price'         => number_format((float) $product['sale_price'], 2, '.', ''),
            'tax_ids'            => $this->khoebo->setting('tax_ids', []),
            'external_reference' => "ventiq-{$key}",
        ];
    }
}
