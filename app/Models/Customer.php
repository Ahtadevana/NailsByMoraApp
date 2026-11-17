<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'phone', 'email', 'notes'])]
class Customer extends Model
{
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
