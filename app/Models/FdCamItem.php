<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Fdcam;
class FdCamItem extends Model
{
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [

    ];
    protected $table = 'fdcam_items';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'fdcam_id',
        'manufacture_barcode',
        'sku',
        'quality',
        'notes',
        'owner',
    ];

    /**
     * Relation
     */

    public function fdCam() {
        return $this->belongsTo(FdCam::class, foreignKey:'fdcam_id');
    }
}
