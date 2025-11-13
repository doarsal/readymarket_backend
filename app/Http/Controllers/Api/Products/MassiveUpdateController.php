<?php

namespace App\Http\Controllers\Api\Products;

use App\Constants\RequestKeys;
use App\Http\Controllers\Controller;
use App\Http\Requests\Products\MassiveUpdate\InvokeRequest;
use App\Imports\ProductsImport;
use App\Models\Product;
use Config;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class MassiveUpdateController extends Controller
{
    public function __invoke(InvokeRequest $request)
    {
        $file = $request->file(RequestKeys::FILE);

        try {
            $import = new ProductsImport;
            Excel::import($import, $file);

            $correctProducts         = $import->correctProducts;
            $productsWithoutCategory = $import->productsWithoutCategory;
            $removedItems            = $this->checkRemovedItems($import->allProducts);

            return response()->json([
                'success' => true,
                'data'    => [
                    'corrects'  => $correctProducts->toArray(),
                    'to_review' => $productsWithoutCategory->toArray(),
                    'removed'   => $removedItems->toArray(),
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fallo al procesar el Excel',
                'errors'  => $e->errors(),
            ]);
        }
    }

    private function checkRemovedItems(Collection $allProducts): Collection
    {
        $storeId   = Config::get('app.store_id');
        $baseQuery = Product::whereNotIn('idproduct', $allProducts->toArray())->where('store_id', $storeId);
        $products  = (clone $baseQuery)->get();

        $baseQuery->delete();

        return $products;
    }
}
