@props([
    'label' => '',
    'value' => 0,
    'icon' => 'fa-chart-bar',
    'color' => 'primary',
    'xBind' => null,
    'description' => null
])

<div class="card h-100">
    <div class="card-body d-flex align-items-center">
        <div class="flex-shrink-0">
            <div class="bg-{{ $color }}-opacity-10 p-3 rounded">
                <i class="fas {{ $icon }} text-{{ $color }} fa-lg"></i>
            </div>
        </div>
        <div class="ms-3 flex-grow-1">
            <h6 class="card-title text-muted mb-1">{{ $label }}</h6>
            <div class="d-flex align-items-baseline gap-2">
                <h4 class="mb-0" @if($xBind) x-text="{{ $xBind }}" @endif>
                    @if(!$xBind)
                        {{ $value }}
                    @endif
                </h4>
                @if($description)
                    <small class="text-muted">{{ $description }}</small>
                @endif
            </div>
        </div>
    </div>
</div>
