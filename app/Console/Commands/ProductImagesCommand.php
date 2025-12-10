<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ProductImagesCommand extends Command
{
    protected $signature   = 'product:images {product_id?}';
    protected $description = 'Command description';

    public function handle(): void
    {
        $productId = $this->argument('product_id');
        $products  = Product::whereNull('prod_icon')->whereNull('deleted_at');

        if ($productId) {
            $products = Product::where('idproduct', $productId);
        }

        $updatedProductsId = Collection::make();
        $corrects          = Collection::make();
        $notFound          = Collection::make();

        $this->withProgressBar($products->cursor(),
            function(Product $product) use (&$updatedProductsId, &$corrects, &$notFound) {
                if ($product->prod_icon || $updatedProductsId->contains($product->ProductId)) {
                    return;
                }

                $updatedProductsId->push($product->ProductId);
                $findImagesByProductId = Product::where('ProductId', $product->ProductId)
                    ->whereNotNull('prod_icon')
                    ->withTrashed()
                    ->first();

                if (!$findImagesByProductId) {
                    $notFound->push($product->ProductId);

                    return;
                }

                $corrects->push($product->ProductId);

                Product::where('ProductId', $product->ProductId)->whereNull('prod_icon')->update([
                    'prod_icon'        => $findImagesByProductId->prod_icon,
                    'prod_slideimage'  => $findImagesByProductId->prod_slideimage,
                    'prod_screenshot1' => $findImagesByProductId->prod_screenshot1,
                    'prod_screenshot2' => $findImagesByProductId->prod_screenshot2,
                    'prod_screenshot3' => $findImagesByProductId->prod_screenshot3,
                    'prod_screenshot4' => $findImagesByProductId->prod_screenshot4,
                ]);
            });

        $this->newLine();
        $this->info("✅ Actualizados correctamente: {$corrects->count()}");
        $this->table(['Product ID'], $corrects->map(fn($id) => [$id])->toArray());

        $this->newLine();
        $this->info("🚫 Sin poder actualizar (por falta de imágenes): {$notFound->count()}");
        $this->table(['Product ID'], $notFound->map(fn($id) => [$id])->toArray());
    }
}
