<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiLog extends Model
{
    //
    protected $fillable = ['endpoint', 'method', 'payload', 'response', 'status_code', 'ip_address'];
    protected $casts = ['payload' => 'array', 'response' => 'array'];
}
