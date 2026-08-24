<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TramiteSecuencia extends Model
{
    protected $primaryKey = 'year';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = ['year', 'next_number'];
}
