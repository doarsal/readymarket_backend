<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

trait ColumnsAndHeaders
{
    protected Collection $columnMapping;

    protected const COLUMN_UNDEFINED = 'Undefined';

    private function prepareHeaders(Collection $headers, array $allColumns): void
    {
        $headers->each(function(string $headerName) use ($allColumns) {
            if (in_array($headerName, $allColumns)) {
                $this->columnMapping->push($headerName);
            } else {
                $this->columnMapping->push(self::COLUMN_UNDEFINED);
            }
        });
    }

    private function validateHeaders(array $requiredColumns): void
    {
        $errors = Collection::make();

        foreach ($requiredColumns as $requiredColumn) {
            if ($this->columnMapping->search($requiredColumn) === false) {
                $errors->push("Falta la columna requerida: {$requiredColumn}");
            }
        }

        if ($errors->isNotEmpty()) {
            throw ValidationException::withMessages([
                'file' => $errors,
            ]);
        }
    }

    private function getColumnValue(Collection $row, string $columnName): mixed
    {
        $columnIndex = $this->columnMapping->search($columnName);

        if ($columnIndex === false) {
            return null;
        }

        return $row->get($columnIndex);
    }
}
