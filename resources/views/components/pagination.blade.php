@if ($pagination && $pagination['total'] > 0)
  <div class="d-flex justify-content-between align-items-center mt-4">
    <div class="text-muted">
      @if ($pagination['total'] > 0)
        Showing {{ $pagination['from'] }} to {{ $pagination['to'] }} of {{ $pagination['total'] }} results
      @else
        No results found
      @endif
    </div>

    @if ($pagination['last_page'] > 1)
      <nav aria-label="Table pagination">
        <ul class="pagination pagination-sm mb-0">
          {{-- Previous Page Link --}}
          @if ($pagination['current_page'] > 1)
            <li class="page-item">
              <a class="page-link"
                href="?page={{ $pagination['current_page'] - 1 }}&per_page={{ $pagination['per_page'] }}{{ isset($queryString) ? '&' . $queryString : '' }}"
                aria-label="Previous">
                <span aria-hidden="true">&laquo;</span>
              </a>
            </li>
          @else
            <li class="page-item disabled">
              <span class="page-link" aria-label="Previous">
                <span aria-hidden="true">&laquo;</span>
              </span>
            </li>
          @endif

          {{-- Page Numbers --}}
          @php
            $start = max(1, $pagination['current_page'] - 2);
            $end = min($pagination['last_page'], $pagination['current_page'] + 2);

            // Ensure we show at least 5 pages when possible
            if ($end - $start < 4) {
                if ($start == 1) {
                    $end = min($pagination['last_page'], $start + 4);
                } else {
                    $start = max(1, $end - 4);
                }
            }
          @endphp

          {{-- First page --}}
          @if ($start > 1)
            <li class="page-item">
              <a class="page-link"
                href="?page=1&per_page={{ $pagination['per_page'] }}{{ isset($queryString) ? '&' . $queryString : '' }}">1</a>
            </li>
            @if ($start > 2)
              <li class="page-item disabled">
                <span class="page-link">...</span>
              </li>
            @endif
          @endif

          {{-- Page range --}}
          @for ($i = $start; $i <= $end; $i++)
            @if ($i == $pagination['current_page'])
              <li class="page-item active">
                <span class="page-link">{{ $i }}</span>
              </li>
            @else
              <li class="page-item">
                <a class="page-link"
                  href="?page={{ $i }}&per_page={{ $pagination['per_page'] }}{{ isset($queryString) ? '&' . $queryString : '' }}">{{ $i }}</a>
              </li>
            @endif
          @endfor

          {{-- Last page --}}
          @if ($end < $pagination['last_page'])
            @if ($end < $pagination['last_page'] - 1)
              <li class="page-item disabled">
                <span class="page-link">...</span>
              </li>
            @endif
            <li class="page-item">
              <a class="page-link"
                href="?page={{ $pagination['last_page'] }}&per_page={{ $pagination['per_page'] }}{{ isset($queryString) ? '&' . $queryString : '' }}">{{ $pagination['last_page'] }}</a>
            </li>
          @endif

          {{-- Next Page Link --}}
          @if ($pagination['current_page'] < $pagination['last_page'])
            <li class="page-item">
              <a class="page-link"
                href="?page={{ $pagination['current_page'] + 1 }}&per_page={{ $pagination['per_page'] }}{{ isset($queryString) ? '&' . $queryString : '' }}"
                aria-label="Next">
                <span aria-hidden="true">&raquo;</span>
              </a>
            </li>
          @else
            <li class="page-item disabled">
              <span class="page-link" aria-label="Next">
                <span aria-hidden="true">&raquo;</span>
              </span>
            </li>
          @endif
        </ul>
      </nav>
    @endif
  </div>

  {{-- Per-page selector --}}
  <div class="d-flex justify-content-end align-items-center mt-2">
    <label for="perPageSelect" class="form-label me-2 mb-0 text-muted">Items per page:</label>
    <select id="perPageSelect" class="form-select form-select-sm" style="width: auto;"
      onchange="changePerPage(this.value)">
      <option value="10" {{ ($pagination['per_page'] ?? 10) == 10 ? 'selected' : '' }}>10</option>
      <option value="25" {{ ($pagination['per_page'] ?? 10) == 25 ? 'selected' : '' }}>25</option>
      <option value="50" {{ ($pagination['per_page'] ?? 10) == 50 ? 'selected' : '' }}>50</option>
      <option value="100" {{ ($pagination['per_page'] ?? 10) == 100 ? 'selected' : '' }}>100</option>
    </select>
  </div>

  <script>
    function changePerPage(perPage) {
      const url = new URL(window.location);
      url.searchParams.set('per_page', perPage);
      url.searchParams.set('page', '1'); // Reset to first page when changing per-page
      window.location.href = url.toString();
    }
  </script>
@endif
