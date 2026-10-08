<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** An organization's own details: the first-time setup and later edits. */
class OrganizationDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // routes check who may edit
    }

    protected function prepareForValidation(): void
    {
        $digits = preg_replace('/\D/', '', (string) $this->input('phone'));
        $this->merge(['phone' => $digits === '' ? null : '+266' . substr($digits, -8)]);
    }

    public function rules(): array
    {
        $organization = $this->attributes->get('organization');

        return [
            'name'          => ['required', 'string', 'max:255', Rule::unique('organizations', 'name')->ignore($organization?->id)],
            'phone'         => ['required', 'regex:/^\+266[0-9]{8}$/'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'website'       => ['nullable', 'url', 'max:255'],
            'tagline'       => ['nullable', 'string', 'max:120'],
            'description'   => ['nullable', 'string', 'max:1000'],
            'logo'          => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'remove_logo'   => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Another organization already uses that name on VENTIQ. Add something to tell yours apart, e.g. your town.',
            'phone.regex' => 'Enter an 8-digit Lesotho number.',
            'website.url' => 'Enter the full address, starting with https://',
        ];
    }

    /** The validated details, with the logo stored (or removed) and ready to save. */
    public function details(): array
    {
        $data = collect($this->validated())->except(['logo', 'remove_logo'])->all();

        if ($this->hasFile('logo')) {
            $data['logo_path'] = $this->file('logo')->store('organization-logos', 'public');
        } elseif ($this->boolean('remove_logo')) {
            $data['logo_path'] = null;
        }

        return $data;
    }
}
