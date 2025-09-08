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
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
                        <a class="nav-link dropdown-toggle {{ request()->routeIs('data-exchange.retrieve-form', 'data-exchange.clients.index', 'data-exchange.cases.index', 'data-exchange.sessions.index') ? 'active' : '' }}"
                            href="#" id="retrieveDropdown" role="button" data-bs-toggle="dropdown">
                            Retrieve Data
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('data-exchange.retrieve-form') }}">
                                <i class="fas fa-search"></i> Search & Retrieve</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header">Browse Resources</h6></li>
                            <li><a class="dropdown-item" href="{{ route('data-exchange.clients.index') }}">
                                <i class="fas fa-users"></i> View All Clients</a></li>
                            <li><a class="dropdown-item" href="{{ route('data-exchange.cases.index') }}">
                                <i class="fas fa-folder-open"></i> View All Cases</a></li>
                            <li><a class="dropdown-item" href="{{ route('data-exchange.sessions.index') }}">
                                <i class="fas fa-calendar-alt"></i> View All Sessions</a></li>
                        </ul>
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
        @yield('content')
    </div>

    <!-- Toast Container -->
    <x-toast-container>
        <!-- Toasts will be dynamically added here -->
    </x-toast-container>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-core.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-xml.min.js"></script>
    @stack('scripts')
</body>

</html>
