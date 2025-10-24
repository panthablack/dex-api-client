@props(['prerequisiteType' => 'case', 'message' => '', 'actionLink' => '#'])

<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <div class="d-flex align-items-center">
        <div class="flex-shrink-0">
            <i class="fas fa-exclamation-triangle me-2"></i>
        </div>
        <div class="flex-grow-1">
            <h6 class="alert-heading mb-1">
                @if($prerequisiteType === 'case')
                    Case Data Required
                @elseif($prerequisiteType === 'shallow_session')
                    Shallow Sessions Required
                @else
                    Prerequisite Required
                @endif
            </h6>
            <p class="mb-2">
                @if($message)
                    {{ $message }}
                @else
                    @if($prerequisiteType === 'case')
                        You must complete a <strong>Case migration</strong> before you can enrich sessions.
                    @elseif($prerequisiteType === 'shallow_session')
                        You must generate <strong>shallow sessions</strong> from case data before you can enrich them.
                    @endif
                @endif
            </p>
            @if($actionLink !== '#')
                <a href="{{ $actionLink }}" class="btn btn-sm btn-warning">
                    <i class="fas fa-plus me-1"></i>
                    @if($prerequisiteType === 'case')
                        Create Case Migration
                    @elseif($prerequisiteType === 'shallow_session')
                        Generate Shallow Sessions
                    @else
                        Take Action
                    @endif
                </a>
            @endif
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
</div>
