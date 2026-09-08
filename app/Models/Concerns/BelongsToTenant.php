<?php

namespace App\Models\Concerns;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Restricts a model to the currently active Bitrix24 portal (member_id).
 *
 * When a tenant is bound to the context, reads are scoped to that member and
 * new records are assigned the member automatically. When no tenant is bound
 * (e.g. an unbound worker bootstrap), no filtering is applied so that direct
 * lookups used to resolve a tenant still work.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $memberId = TenantContext::memberId();

            if ($memberId !== null) {
                $builder->where($builder->getModel()->getTable().'.member_id', $memberId);
            }
        });

        static::creating(function ($model) {
            if ($model->member_id === null && TenantContext::memberId() !== null) {
                $model->member_id = TenantContext::memberId();
            }
        });
    }
}
