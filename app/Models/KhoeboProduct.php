<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Khoebo's id for one of VENTIQ's products (config services.khoebo.products). */
class KhoeboProduct extends Model
{
    protected $fillable = ['key', 'khoebo_id'];
}
