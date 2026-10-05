<?php

namespace Tests\Feature;

use App\Models\Product;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StockStatusBadgeTest extends TestCase
{
    public static function matrix(): array
    {
        return [
            'in stock'                 => ['active', 6, 'in_stock', 'In Stock', 'stock-badge--in-stock'],
            'low stock at threshold'   => ['active', Product::LOW_STOCK_THRESHOLD, 'low_stock', 'Low Stock', 'stock-badge--low-stock'],
            'low stock at one'         => ['active', 1, 'low_stock', 'Low Stock', 'stock-badge--low-stock'],
            'out of stock'             => ['active', 0, 'out_of_stock', 'Out of Stock', 'stock-badge--out-of-stock'],
            'archived wins over stock' => ['archived', 0, 'archived', 'Archived', 'stock-badge--archived'],
            'archived with stock'      => ['archived', 50, 'archived', 'Archived', 'stock-badge--archived'],
        ];
    }

    #[DataProvider('matrix')]
    public function test_stock_status_matrix(string $status, int $stock, string $expected, string $label, string $class): void
    {
        $product = new Product(['status' => $status, 'stock' => $stock]);
        $product->setRelation('images', collect());

        $this->assertSame($expected, $product->stock_status);

        $html = (string) $this->blade('<x-stock-status-badge :product="$product" />', ['product' => $product]);
        $this->assertStringContainsString($class, $html);
        $this->assertStringContainsString($label, $html);
        $this->assertStringContainsString('<svg', $html);
    }
}
