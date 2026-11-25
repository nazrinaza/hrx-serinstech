<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    use BelongsToCompany;

    protected $fillable = ['name'];
}
