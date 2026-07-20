<?php

namespace App\Livewire;

use App\Models\OrganizationPackage;
use App\Models\PackageAddOn;
use App\Support\PackageDefinition;
use Livewire\Component;
use Filament\Notifications\Notification;

class UpgradePackageModal extends Component
{
    public bool   $open             = false;
    public string $mode             = 'upgrade'; // 'upgrade' or 'addon'
    public string $selectedType     = 'standard';
    public string $paymentMethod    = '';
    public string $paymentReference = '';
    public string $notes            = '';
    public bool   $submitted        = false;
    public array  $selectedAddOns   = [];

    protected $listeners = ['open-upgrade-modal' => 'openModal'];

    public function openModal(): void
    {
        $this->reset(['paymentMethod', 'paymentReference', 'notes', 'submitted', 'selectedAddOns']);

        $currentPackage = $this->getCurrentPackage();

        // Determine default mode and selected type based on current package
        if ($currentPackage?->package_type === 'standard') {
            // Standard users see add-ons first, can switch to upgrade
            $this->mode         = 'addon';
            $this->selectedType = 'professional'; // default upgrade target
        } elseif ($currentPackage?->package_type === 'starter' || $currentPackage?->is_free_trial) {
            // Starter/trial users can only upgrade
            $this->mode         = 'upgrade';
            $this->selectedType = 'standard';
        } else {
            $this->mode         = 'upgrade';
            $this->selectedType = 'standard';
        }

        $this->open = true;
    }

    public function setMode(string $mode): void
    {
        $this->mode = $mode;
        $this->reset(['paymentMethod', 'paymentReference', 'selectedAddOns']);
    }

    public function getCurrentPackage(): ?OrganizationPackage
    {
        $user = auth()->user();
        if (!$user?->organization_id) return null;

        return OrganizationPackage::where('organization_id', $user->organization_id)
            ->where('status', 'active')
            ->latest('purchased_at')
            ->first();
    }

    public function getCurrentPackageTypeProperty(): ?string
    {
        return $this->getCurrentPackage()?->package_type;
    }

    public function getIsOnStarterProperty(): bool
    {
        $type = $this->currentPackageType;
        return $type === 'starter' || $this->getCurrentPackage()?->is_free_trial;
    }

    public function getIsOnStandardProperty(): bool
    {
        return $this->currentPackageType === 'standard';
    }

    public function getSelectedDefinitionProperty(): array
    {
        return PackageDefinition::get($this->selectedType);
    }

    public function getUpgradePackagesProperty(): array
    {
        $current = $this->currentPackageType;

        // Only show packages that are actually an upgrade from current
        return collect(PackageDefinition::all())
            ->except(['free_trial', 'enterprise'])
            ->filter(function ($def, $type) use ($current) {
                $order = ['starter' => 1, 'standard' => 2, 'professional' => 3];
                $currentOrder = $order[$current] ?? 0;
                return ($order[$type] ?? 0) > $currentOrder;
            })
            ->toArray();
    }

    public function getAvailableAddOnsProperty(): array
    {
        $currentPackage = $this->getCurrentPackage();
        if (!$currentPackage) return [];

        // Get already purchased add-ons for this package
        $purchased = $currentPackage->addOns()->pluck('feature_key')->toArray();

        // Return only add-ons not yet purchased
        return collect(PackageDefinition::availableAddOns())
            ->filter(fn ($addon, $key) => !in_array($key, $purchased))
            ->toArray();
    }

    public function getAddOnTotalProperty(): float
    {
        return collect($this->selectedAddOns)
            ->sum(fn ($key) => PackageDefinition::availableAddOns()[$key]['price'] ?? 0);
    }

    public function getAllAddOnsTotalProperty(): float
    {
        return collect(PackageDefinition::availableAddOns())->sum('price');
    }

    public function getProfessionalPriceProperty(): float
    {
        return PackageDefinition::get('professional')['price'];
    }

    public function updatedSelectedType(): void {}

    public function submitUpgrade(): void
    {
        $this->validate([
            'selectedType'      => 'required|in:starter,standard,professional',
            'paymentMethod'     => 'required',
            'paymentReference'  => 'required|min:3',
        ], [
            'paymentMethod.required'    => 'Please select a payment method.',
            'paymentReference.required' => 'Please enter your transaction reference.',
        ]);

        $user = auth()->user();
        $def  = PackageDefinition::get($this->selectedType);

        OrganizationPackage::create([
            'organization_id'       => $user->organization_id,
            'package_type'          => $this->selectedType,
            'price_paid'            => $def['price'],
            'events_included'       => $def['events'],
            'tickets_included'      => $def['tickets'],
            'comp_tickets_included' => $def['comp_tickets'],
            'overage_ticket_rate'   => $def['overage_rate'],
            'status'                => 'pending',
            'purchased_at'          => now(),
            'payment_method'        => $this->paymentMethod,
            'payment_reference'     => $this->paymentReference,
            'notes'                 => $this->notes ?: null,
            'purchased_by'          => $user->id,
        ]);

        $this->submitted = true;

        Notification::make()
            ->title('Upgrade Request Submitted')
            ->body('Your package upgrade is pending approval. You will be notified once activated.')
            ->success()
            ->send();
    }

    public function submitAddOns(): void
    {
        $this->validate([
            'selectedAddOns'    => 'required|array|min:1',
            'paymentMethod'     => 'required',
            'paymentReference'  => 'required|min:3',
        ], [
            'selectedAddOns.required' => 'Please select at least one add-on.',
            'selectedAddOns.min'      => 'Please select at least one add-on.',
            'paymentMethod.required'  => 'Please select a payment method.',
            'paymentReference.required' => 'Please enter your transaction reference.',
        ]);

        $user           = auth()->user();
        $currentPackage = $this->getCurrentPackage();
        $addOns         = PackageDefinition::availableAddOns();
        $total          = 0;

        foreach ($this->selectedAddOns as $featureKey) {
            if (!isset($addOns[$featureKey])) continue;

            $price = $addOns[$featureKey]['price'];
            $total += $price;

            // Create add-on record as pending — admin activates on payment confirmation
            $currentPackage->addOns()->create([
                'feature_key'  => $featureKey,
                'price_paid'   => $price,
                'activated_at' => null, // activated by admin on approval
                'activated_by' => null,
            ]);
        }

        // Record the payment reference on the package notes for admin reference
        $currentPackage->update([
            'notes' => trim(($currentPackage->notes ?? '') . "\nAdd-on purchase [{$this->paymentReference}] — M{$total} — " . implode(', ', $this->selectedAddOns)),
        ]);

        $this->submitted = true;

        Notification::make()
            ->title('Add-on Request Submitted')
            ->body('Your add-on purchase is pending approval. Features will be activated once payment is confirmed.')
            ->success()
            ->send();
    }

    public function close(): void
    {
        $this->open      = false;
        $this->submitted = false;
    }

    public function render()
    {
        return view('livewire.upgrade-package-modal');
    }
}