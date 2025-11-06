<?php

namespace App\Http\Controllers\Api\Products;

use App\Constants\RequestKeys;
use App\Http\Controllers\Controller;
use App\Http\Requests\Products\MassiveUpdate\InvokeRequest;
use App\Imports\ProductsImport;
use Exception;
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

            return response()->json([
                'success' => true,
                'data'    => [
                    'corrects'  => $import->correctProducts->toArray(),
                    'to_review' => $import->productsWithoutCategory->toArray(),
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
}
