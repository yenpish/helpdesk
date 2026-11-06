<?php

namespace App\Models\Concerns;

use App\Support\EncryptedRouteIdentifier;
use Illuminate\Database\Eloquent\Model;

trait HasEncryptedRouteKey
{
    public function getRouteKey(): string
    {
        return EncryptedRouteIdentifier::encode(static::class, $this->getAttribute($this->getRouteKeyName()));
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $decoded = EncryptedRouteIdentifier::decode((string) $value, static::class, $field);

        return $decoded === null ? null : parent::resolveRouteBinding($decoded, $field);
    }

    public function resolveSoftDeletableRouteBinding($value, $field = null): ?Model
    {
        $decoded = EncryptedRouteIdentifier::decode((string) $value, static::class, $field);

        return $decoded === null ? null : parent::resolveSoftDeletableRouteBinding($decoded, $field);
    }

    public function resolveChildRouteBinding($childType, $value, $field): ?Model
    {
        $related = $this->{$this->childRouteBindingRelationshipName($childType)}()->getRelated();
        $decoded = EncryptedRouteIdentifier::decode((string) $value, $related::class, $field);

        return $decoded === null ? null : parent::resolveChildRouteBinding($childType, $decoded, $field);
    }

    public function resolveSoftDeletableChildRouteBinding($childType, $value, $field): ?Model
    {
        $related = $this->{$this->childRouteBindingRelationshipName($childType)}()->getRelated();
        $decoded = EncryptedRouteIdentifier::decode((string) $value, $related::class, $field);

        return $decoded === null ? null : parent::resolveSoftDeletableChildRouteBinding($childType, $decoded, $field);
    }
}
