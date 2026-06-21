<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrganizationPaymentMethod extends Model
{
    use HasFactory;

    protected $table = 'organization_payment_methods';

    protected $fillable = [
        'organization_id',
        'payment_method',
        'account_name',
        'account_number',
        'instructions',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    /**
     * Boot method to add model events
     */
    protected static function boot()
    {
        parent::boot();

        // Before saving, validate that non-cash methods have account numbers
       static::saving(function ($model) {
            $requiresAccount = config(
                "constants.payment_methods.{$model->payment_method}.requires_account",
                true
            );

            if ($requiresAccount && empty($model->account_number)) {
                throw new \Exception(
                    'Account number is required for ' . $model->payment_method . ' payment method.'
                );
            }
        });
    }

    /**
     * Relationships
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('payment_method');
    }

    /**
     * Accessors
     */
    public function getLabelAttribute(): string
    {
        // Get the config array for this payment method
        $config = config('constants.payment_methods.' . $this->payment_method);
        
        // Return the label from config, or fallback to uppercase method name
        return $config['label'] ?? strtoupper(str_replace('_', ' ', $this->payment_method));
    }

    public function getIconAttribute(): string
    {
        $config = config('constants.payment_methods.' . $this->payment_method);
        return $config['icon'] ?? 'fa-wallet';
    }

    public function getColorAttribute(): string
    {
        $config = config('constants.payment_methods.' . $this->payment_method);
        return $config['color'] ?? 'text-gray-600';
    }

    public function getDisplayDetailsAttribute(): string
    {
        // For cash, just return the label
        if ($this->payment_method === 'cash') {
            return $this->label;
        }

        // For others, include account details
        return collect([
            $this->account_name,
            $this->account_number,
        ])->filter()->implode(' • ');
    }

    /**
     * Check if this payment method requires account number
     */
    public function requiresAccountNumber(): bool
    {
        $config = config('constants.payment_methods.' . $this->payment_method);
        return $config['requires_account'] ?? true;
    }

    /**
     * Get a friendly name for the account field
     */
    public function getAccountFieldLabel(): string
    {
        $config = config('constants.payment_methods.' . $this->payment_method);
        return $config['account_label'] ?? 'Account Number';
    }
}