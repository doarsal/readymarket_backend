<?php

namespace App\Http\Requests\Products\MassiveUpdate;

use App\Constants\RequestKeys;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="ProductsMassiveUpdateInvokeRequest",
 *     type="object",
 *     title="Products Massive Update - File Upload",
 *     description="Request body para actualización masiva de productos vía archivo",
 *     required={"file"},
 *     @OA\Property(
 *         property="file",
 *         type="string",
 *         format="binary",
 *         description="Archivo de carga masiva (CSV, XLS, XLSX)"
 *     )
 * )
 */
class InvokeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            RequestKeys::FILE          => 'required|file|mimes:csv',
            RequestKeys::SOFTWARE_FILE => 'required|file|mimes:csv',
        ];
    }

    public function messages(): array
    {
        return [
            RequestKeys::FILE . '.required'          => 'El archivo es requerido.',
            RequestKeys::FILE . '.mimes'             => 'El archivo debe ser CSV.',
            RequestKeys::FILE . '.file'              => 'El archivo debe ser CSV.',
            RequestKeys::SOFTWARE_FILE . '.required' => 'El archivo de software es requerido.',
            RequestKeys::SOFTWARE_FILE . '.mimes'    => 'El archivo de software debe ser CSV.',
            RequestKeys::SOFTWARE_FILE . '.file'     => 'El archivo de software debe ser CSV.',
        ];
    }
}
