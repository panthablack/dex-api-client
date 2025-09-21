@extends('layouts.app')

@section('title', 'Full Verification - ' . $migration->name)

@section('content')
    <div x-data="verificationApp()" x-init="init()" x-cloak>
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('data-migration.index') }}" class="text-decoration-none">
                        Data Migration
                    </a>
                </li>
                <li class="breadcrumb-item">
                    <a href="{{ route('data-migration.show', $migration) }}" class="text-decoration-none">
                        {{ $migration->name }}
                    </a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">
                    Full Verification
                </li>
            </ol>
        </nav>

        <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
                <h1 class="h2 text-primary">Full Verification</h1>
                <h4 class="text-muted">{{ $migration->name }}</h4>
                <small class="text-muted">Comprehensive data integrity verification</small>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('data-migration.show', $migration) }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Migration
                </a>
                @if ($migration->status === 'COMPLETED' || $migration->batches->where('status', 'COMPLETED')->count() > 0)
                    <!-- Loading state - hide buttons -->
                    <template x-if="verification.status === 'loading'">
                        <div class="d-flex align-items-center">
                            <x-spinners.small />
                            <span class="text-muted">Loading...</span>
                        </div>
                    </template>

                    <!-- Show stop button during active verification -->
                    <template
                        x-if="verification.status === 'starting' || verification.status === 'in_progress' || verification.status === 'stopping'">
                        <button @click="stopVerification()" class="btn btn-outline-danger"
                            :disabled="verification.status === 'stopping'">
                            <i
                                :class="verification.status === 'stopping' ? 'fas fa-spinner fa-spin me-1' : 'fas fa-stop me-1'"></i>
                            <span x-text="verification.status === 'stopping' ? 'Stopping...' : 'Stop Verification'"></span>
                        </button>
                    </template>

                    <!-- Show action buttons when not loading or running -->
                    <template x-if="!['loading', 'starting', 'in_progress', 'stopping'].includes(verification.status)">
                        <div class="btn-group">
                            <!-- First time verification button -->
                            <button x-show="hasNeverBeenVerified()" @click="startVerification()" class="btn btn-primary"
                                title="Start data verification for the first time">
                                <i class="fas fa-play me-1"></i> Start Verification
                            </button>

                            <!-- Verification has been run before - always show these buttons -->
                            <template x-if="!hasNeverBeenVerified()">
                                <button @click="startVerification()" class="btn btn-primary"
                                    title="Reset all verification states and start fresh verification">
                                    <i class="fas fa-redo me-1"></i> Run Verification Again
                                </button>
                            </template>

                            <!-- Continue Verification - show when verification has been attempted -->
                            <button x-show="!hasNeverBeenVerified()" @click="continueVerification()"
                                class="btn btn-outline-primary" :disabled="!hasUnverifiedRecords()"
                                :title="hasUnverifiedRecords() ? 'Continue verification of failed and pending records only' :
                                    'No failed or pending records to continue with'">
                                <i class="fas fa-play me-1"></i> Continue Verification
                            </button>
                        </div>
                    </template>
                @else
                    <button class="btn btn-secondary" disabled title="Migration must be completed first">
                        <i class="fas fa-lock me-1"></i> Verification Unavailable
                    </button>
                @endif
            </div>
        </div>

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

    <x-js.verification />
@endsection
