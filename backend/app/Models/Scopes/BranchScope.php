<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class BranchScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * super_admin은 모든 데이터 접근 가능
     * branch_admin, student, parent는 자신의 지점 데이터만 접근 가능
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (!$user) {
            return;
        }

        // super_admin은 모든 데이터 접근 가능
        if ($user->isSuperAdmin()) {
            return;
        }

        // 그 외는 자신의 지점 데이터만 접근 가능
        if ($user->branch_id) {
            $builder->where($model->getTable() . '.branch_id', $user->branch_id);
        }
    }
}
