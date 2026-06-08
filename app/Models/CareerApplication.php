<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CareerApplication extends Model
{
    protected $fillable = ['full_name', 'email', 'phone', 'position', 'experience_level', 'about', 'status'];
}
