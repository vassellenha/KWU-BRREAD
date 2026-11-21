<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * A short fingerprint of a row as the editor saw it. The batch save pages send it back with every changed row,
 * so a row somebody else changed in the meantime is refused instead of being overwritten.
 *
 * It hashes the editable values plus updated_at, so two saves inside the same second still tell apart.
 */
final class RowVersion
{
    /**
     * @param  list<string>  $attributes
     */
    public static function of(Model $model, array $attributes): string
    {
        $values = array_map(
            fn (string $attribute): string => (string) json_encode($model->getAttribute($attribute)),
            $attributes,
        );

        return substr(sha1($model->getKey().'|'.$model->updated_at?->format('Y-m-d H:i:s.u').'|'.implode('|', $values)), 0, 16);
    }
}
