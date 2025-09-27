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
