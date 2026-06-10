<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait TrainingScoped
{
    protected static function bootTrainingScoped(): void
    {
        static::addGlobalScope('training', function (Builder $builder) {
            $user = Auth::guard('admin')->user();

            if (! $user) {
                return;
            }

            if ($user->training_mode_active) {
                $builder
                    ->where($builder->qualifyColumn('is_training'), true)
                    ->where($builder->qualifyColumn('training_owner_id'), $user->id);

                return;
            }

            $builder->where($builder->qualifyColumn('is_training'), false);
        });
    }
}
