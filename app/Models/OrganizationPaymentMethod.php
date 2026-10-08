<?php

namespace App\Models;

use App\Services\Payments\PaymentAccountService;
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
        'is_default',
        'display_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
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

            // Attendees' payments point at this row, so changing what it
            // is would rewrite where they paid. A new number is a new
            // account; the old one gets archived (is_active = false).
            if ($model->exists
                && $model->isDirty(['payment_method', 'account_number'])
                && app(PaymentAccountService::class)->hasPayments($model)) {
                throw new \Exception(
                    'This account has received payments, so its number can\'t be changed. Add a new account and deactivate this one instead.'
                );
            }
        });

        static::deleting(function ($model) {
            if (app(PaymentAccountService::class)->hasPayments($model)) {
                throw new \Exception(
                    'This account has received payments, so it can\'t be deleted. Deactivate it instead.'
                );
            }
        });

        // One default per method within an organization.
        static::saved(function ($model) {
            if ($model->is_default) {
                static::where('organization_id', $model->organization_id)
                    ->where('payment_method', $model->payment_method)
                    ->where('id', '!=', $model->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
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

    /** Method plus the organizer's own label, e.g. "EcoCash — Events Account". */
    public function getDisplayLabelAttribute(): string
    {
        return $this->account_name ? "{$this->label} — {$this->account_name}" : $this->label;
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