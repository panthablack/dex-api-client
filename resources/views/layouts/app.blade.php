<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'DSS Data Exchange SOAP Client')</title>

  <!-- Favicons -->
  <link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
  <link rel="shortcut icon" href="/favicon.ico">
  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
  <link rel="manifest" href="/site.webmanifest">
  <meta name="theme-color" content="#2563eb">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <script>
    // Global Alpine.js Toast Store
    document.addEventListener('alpine:init', () => {
      Alpine.store('toast', {
        toasts: [],

        add(message, type = 'info', timeout = 5000) {
          const id = 'toast-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
          const toast = {
            id,
            message,
            type,
            timeout
          };
          this.toasts.push(toast);

          // Auto-remove after timeout
          setTimeout(() => {
            this.remove(id);
          }, timeout);

          return id;
        },

        remove(id) {
          this.toasts = this.toasts.filter(toast => toast.id !== id);
        },

        clear() {
          this.toasts = [];
        },

        // Convenience methods
        success(message, timeout = 5000) {
          return this.add(message, 'success', timeout);
        },

        error(message, timeout = 8000) {
          return this.add(message, 'error', timeout);
        },

        warning(message, timeout = 6000) {
          return this.add(message, 'warning', timeout);
        },

        info(message, timeout = 5000) {
          return this.add(message, 'info', timeout);
        }
      });
    });

    // Global helper function for easy access
    window.showToast = function(message, type = 'info', timeout = 5000) {
      return Alpine.store('toast').add(message, type, timeout);
    };
  </script>
  <style>
    [x-cloak] {
      display: none !important;
    }

    .btn:disabled {
      opacity: 0.65;
      cursor: not-allowed;
    }

    .fa-spin {
      animation: fa-spin 1s infinite linear;
    }

    @keyframes fa-spin {
      0% {
        transform: rotate(0deg);
      }

      100% {
        transform: rotate(360deg);
      }
    }

    .xml-container {
      max-height: 400px;
      overflow-y: auto;
      background-color: #f8f9fa;
      border: 1px solid #dee2e6;
      padding: 15px;
      border-radius: 5px;
    }

    .status-badge {
      font-size: 0.875rem;
    }

    .nav-link.active {
      font-weight: bold;
    }

    /* Pastel badge colors for resource types */
    .bg-pastel-lavender {
      background-color: #c8b6ff !important;
      color: #3d2f5f !important;
    }

    .bg-pastel-mint {
      background-color: #b6f5d8 !important;
      color: #1f5e42 !important;
    }

    .bg-pastel-rose {
      background-color: #ffc8dd !important;
      color: #5f2940 !important;
    }

    .bg-pastel-peach {
      background-color: #ffd6a5 !important;
      color: #5f3d1f !important;
    }
  </style>
</head>

<body>
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
      <a class="navbar-brand" href="{{ route('home') }}">DSS Data Exchange</a>

      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav">
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}"
              href="{{ route('home') }}">Dashboard</a>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle {{ request()->routeIs('data-exchange.client-form', 'data-exchange.case-form', 'data-exchange.session-form', 'data-exchange.bulk-form') ? 'active' : '' }}"
              href="#" id="submitDropdown" role="button" data-bs-toggle="dropdown">
              Submit Data
            </a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="{{ route('data-exchange.client-form') }}">Submit Client
                  Data</a></li>
              <li><a class="dropdown-item" href="{{ route('data-exchange.case-form') }}">Submit Case
                  Data</a></li>
              <li><a class="dropdown-item" href="{{ route('data-exchange.session-form') }}">Submit Session
                  Data</a></li>
              <li><a class="dropdown-item" href="{{ route('data-exchange.bulk-form') }}">Bulk Upload</a>
              </li>
            </ul>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle {{ request()->routeIs('data-exchange.retrieve-form', 'data-exchange.clients.index', 'data-exchange.cases.index', 'data-exchange.cases.sessions.index') ? 'active' : '' }}"
              href="#" id="retrieveDropdown" role="button" data-bs-toggle="dropdown">
              Retrieve Data
            </a>
            <ul class="dropdown-menu">
              {{-- Hide Temporarily as view broken --}}
              {{-- <li><a class="dropdown-item" href="{{ route('data-exchange.retrieve-form') }}">
                                    <i class="fas fa-search"></i> Search & Retrieve</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li> --}}
              <li>
                <h6 class="dropdown-header">Browse Resources</h6>
              </li>
              <li><a class="dropdown-item" href="{{ route('data-exchange.clients.index') }}">
                  <i class="fas fa-users"></i> View All Clients</a></li>
              <li><a class="dropdown-item" href="{{ route('data-exchange.cases.index') }}">
                  <i class="fas fa-folder-open"></i> View All Cases</a></li>
              <li class="dropdown-divider"></li>
              <li><span class="dropdown-item-text text-muted small">
                  <i class="fas fa-info-circle"></i> Sessions are now accessed via Cases
                </span></li>
            </ul>
          </li>
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('data-migration.*') ? 'active' : '' }}"
              href="{{ route('data-migration.index') }}">
              <i class="fas fa-database"></i> Data Migration
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('data-exchange.reference-data') ? 'active' : '' }}"
              href="{{ route('data-exchange.reference-data') }}">Reference Data</a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <div class="container mt-4">
    <x-flash-messages />
    @yield('content')
  </div>

  <!-- Toast Container -->
  <div x-data class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1060;" x-cloak>
    <template x-for="toast in $store.toast.toasts" :key="toast.id">
      <div :id="toast.id" class="toast align-items-center border-0 mb-2"
        :class="{
            'bg-success text-white': toast.type === 'success',
            'bg-danger text-white': toast.type === 'error',
            'bg-warning text-dark': toast.type === 'warning',
            'bg-info text-white': toast.type === 'info',
            'bg-primary text-white': toast.type === 'primary',
            'bg-secondary text-white': toast.type === 'secondary'
        }"
        role="alert" aria-live="assertive" aria-atomic="true" style="min-width: 300px;" x-init="const toastEl = $el;
        const bsToast = new bootstrap.Toast(toastEl, { autohide: false });
        bsToast.show();">
        <div class="d-flex">
          <div class="toast-body" x-text="toast.message"></div>
          <button type="button" class="btn-close me-2 m-auto"
            :class="{ 'btn-close-white': toast.type !== 'warning' }" @click="$store.toast.remove(toast.id)"
            aria-label="Close"></button>
        </div>
      </div>
    </template>
  </div>

  <!-- Alert Component -->
  <x-alert></x-alert>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-core.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-xml.min.js"></script>
  @stack('scripts')
</body>

</html>
