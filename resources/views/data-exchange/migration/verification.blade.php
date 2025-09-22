@extends('layouts.app')

@section('title', 'Full Verification - ' . $migration->name)

@section('content')
    <div x-data="verificationApp()" x-init="init()" x-cloak class="appRoot">
        <x-verification.breadcrumbs :migration="$migration" />

        <x-verification.header :migration="$migration" />

        <x-verification.loading-card />

        <x-verification.status-card />

        <x-verification.resource-results />

        <x-verification.details-panel />

        @if (!($migration->status === 'COMPLETED' || $migration->batches->where('status', 'COMPLETED')->count() > 0))
            <x-verification.not-available-card />
        @else
            <x-verification.about-verification-card />
        @endif

        <x-verification.error-modal />
    </div>

    <x-js.verification :migration="$migration" />
@endsection
