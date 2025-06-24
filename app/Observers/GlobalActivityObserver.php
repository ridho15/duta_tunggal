<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class GlobalActivityObserver
{
    public function created(Model $model)
    {
        activity()
            ->causedBy(Auth::user())
            ->performedOn($model)
            ->withProperties([
                'attributes' => $model->getAttributes(),
            ])
            ->log(class_basename($model) . ' dibuat.');
    }

    public function updated(Model $model)
    {
        activity()
            ->causedBy(Auth::user())
            ->performedOn($model)
            ->withProperties([
                'old' => $model->getOriginal(),
                'attributes' => $model->getChanges(),
            ])
            ->log(class_basename($model) . ' diperbarui.');
    }

    public function deleted(Model $model)
    {
        activity()
            ->causedBy(Auth::user())
            ->performedOn($model)
            ->withProperties([
                'attributes' => $model->getOriginal(),
            ])
            ->log(class_basename($model) . ' dihapus.');
    }
}
