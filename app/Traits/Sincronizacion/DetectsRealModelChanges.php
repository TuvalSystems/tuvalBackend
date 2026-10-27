<?php 

namespace App\Traits\Sincronizacion;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

trait DetectsRealModelChanges
{
    protected function hasRealChanges(Model $model): bool
    {
        foreach ($model->getDirty() as $field => $_) {
            $original = $model->getOriginal($field);
            $current  = $model->getAttribute($field);

            if ($this->normalizeValue($original) !== $this->normalizeValue($current)) {
                return true;
            }
        }
        return false;
    }

    private function normalizeValue(mixed $value): string
    {
        if ($value === null) return '';
        if ($value instanceof Carbon) return $value->format('Y-m-d');
        if (is_numeric($value)) return rtrim(rtrim(number_format((float)$value, 4, '.', ''), '0'), '.');
        return trim((string) $value);
    }
}