<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageAddOn extends Model
{
    protected $fillable = [
        'organization_package_id',
        'feature_key',
        'price_paid',
        'activated_at',
        'activated_by',
    ];

    protected $casts = [
        'price_paid'   => 'decimal:2',
        'activated_at' => 'datetime',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(OrganizationPackage::class, 'organization_package_id');
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }
}