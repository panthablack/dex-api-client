@extends('layouts.app')

@section('title', 'Submit Client Data - DSS Data Exchange')

@section('content')
<x-form-container
    title="Submit Client Data"
    description="Submit individual client information to the DSS Data Exchange system"
    :form-action="route('data-exchange.submit-client')"
    :sample-data="$sampleData ?? null"
>
    <x-slot name="slot">Client Information Form</x-slot>
    
    <x-slot name="form">
        <div class="row">
            <x-form-field 
                name="client_id" 
                label="Client ID" 
                :required="true" 
            />
            <x-form-field 
                name="client_type" 
                label="Client Type" 
                type="select" 
                :options="['Individual' => 'Individual', 'Family' => 'Family', 'Group' => 'Group']"
            />
        </div>

        <div class="row">
            <x-form-field 
                name="first_name" 
                label="First Name" 
                :required="true" 
            />
            <x-form-field 
                name="last_name" 
                label="Last Name" 
                :required="true" 
            />
        </div>

        <div class="row">
            <x-form-field 
                name="date_of_birth" 
                label="Date of Birth" 
                type="date" 
                :required="true" 
            />
            <x-form-field 
                name="gender" 
                label="Gender" 
                type="select" 
                :required="true"
                :options="['M' => 'Male', 'F' => 'Female', 'X' => 'Non-binary', '9' => 'Not stated']"
            />
        </div>

        <div class="row">
            <x-form-field 
                name="is_birth_date_estimate" 
                label="Birth date is an estimate" 
                type="checkbox" 
                class="col-md-6"
            />
        </div>

        <div class="row">
            <x-form-field 
                name="indigenous_status" 
                label="Indigenous Status" 
                type="select" 
                :options="collect($atsiOptions ?? [])->pluck('Description', 'Code')->toArray()"
            />
            <x-form-field 
                name="country_of_birth" 
                label="Country of Birth" 
                type="select" 
                :options="collect($countries ?? [])->pluck('Description', 'Code')->toArray()"
            />
        </div>

        <div class="row">
            <x-form-field 
                name="suburb" 
                label="Suburb" 
                :required="true" 
                class="col-md-4"
            />
            <x-form-field 
                name="state" 
                label="State" 
                type="select" 
                :required="true"
                class="col-md-4"
                :options="['NSW' => 'NSW', 'VIC' => 'VIC', 'QLD' => 'QLD', 'WA' => 'WA', 'SA' => 'SA', 'TAS' => 'TAS', 'ACT' => 'ACT', 'NT' => 'NT']"
            />
            <x-form-field 
                name="postal_code" 
                label="Postal Code" 
                :required="true" 
                class="col-md-4"
            />
        </div>

        <div class="row">
            <x-form-field 
                name="primary_language" 
                label="Primary Language" 
                type="select" 
                :options="collect($languages ?? [])->pluck('Description', 'Code')->toArray()"
            />
        </div>

        <div class="row">
            <x-form-field 
                name="interpreter_required" 
                label="Interpreter Required" 
                type="checkbox" 
            />
            <x-form-field 
                name="disability_flag" 
                label="Has Disability" 
                type="checkbox" 
            />
        </div>

        <div class="row">
            <x-form-field 
                name="consent_to_provide_details" 
                label="Consent to Provide Details" 
                type="checkbox" 
                :required="true"
                :value="old('consent_to_provide_details', '1')"
            />
            <x-form-field 
                name="consent_to_be_contacted" 
                label="Consent to be Contacted" 
                type="checkbox" 
                :required="true"
                :value="old('consent_to_be_contacted', '1')"
            />
        </div>

        <div class="row">
            <x-form-field 
                name="is_using_pseudonym" 
                label="Using Pseudonym" 
                type="checkbox" 
            />
        </div>
    </x-slot>

    <x-slot name="buttons">
        <button type="submit" class="btn btn-primary">Submit Client Data</button>
    </x-slot>

    <x-slot name="helpContent">
        <small class="text-muted">
            <p><strong>Required fields</strong> are marked with <span class="text-danger">*</span></p>
            <p><strong>Gender Values:</strong></p>
            <ul>
                <li>M - Male</li>
                <li>F - Female</li>
                <li>X - Non-binary</li>
                <li>9 - Not stated</li>
            </ul>
            <p><strong>Indigenous Status:</strong></p>
            <ul>
                <li>Y - Yes</li>
                <li>N - No</li>
                <li>U - Unknown</li>
            </ul>
        </small>
    </x-slot>
</x-form-container>
@endsection