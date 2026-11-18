<?php

namespace App\Domains\Shared\Traits;

use Illuminate\Support\Str;

trait HasUuid
{
    /**
     * Boot the trait and automatically assign UUID if not set.
     */
    protected static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    /**
     * UUID is non-incrementing.
     */
    public function getIncrementing(): bool
    {
        return false;
    }

    /**
     * Key type is string.
     */
    public function getKeyType(): string
    {
        return 'string';
    }
}
