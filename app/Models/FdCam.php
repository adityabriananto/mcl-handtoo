<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fdcam extends Model
{
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [

    ];
    protected $table = 'fdcams';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'tracking_number',
        'order_number',
        'parcel_type',
        'recording',
        'tpl'
    ];

    /**
     * Relation
     */
    public function items(): HasMany
    {
        return $this->hasMany(FdcamItem::class);
    }
}
