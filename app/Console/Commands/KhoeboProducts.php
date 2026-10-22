<?php

namespace App\Console\Commands;

use App\Services\Khoebo\{KhoeboException, KhoeboProducts as Products};
use Illuminate\Console\Command;

/**
 * Make VENTIQ's products (config services.khoebo.products) in Khoebo.
 * Safe to run again: only products Khoebo doesn't have yet are made.
 */
class KhoeboProducts extends Command
{
    protected $signature = 'khoebo:products {--books=events : Which books: events or sessions}';
    protected $description = "Make VENTIQ's products in Khoebo (only the ones it doesn't have yet)";

    public function handle(): int
    {
        $books = (string) $this->option('books');
        if (!array_key_exists($books, \App\Services\Khoebo\KhoeboClient::BOOKS)) {
            $this->error('--books is events or sessions.');

            return self::FAILURE;
        }
        $products = new Products(\App\Services\Khoebo\KhoeboClient::for($books));
        $this->line("The {$books} books:");
        try {
            $synced = $products->sync();
        } catch (KhoeboException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        foreach ($synced as $key => $product) {
            $this->line(sprintf('%-12s %s, Khoebo product %d', $key, $product['made'] ? 'made' : 'already in Khoebo', $product['id']));
        }

        return self::SUCCESS;
    }
}
