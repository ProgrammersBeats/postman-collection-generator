<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} - API Documentation</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --sidebar-width: 300px;
            --header-height: 64px;
            --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            --font-mono: 'JetBrains Mono', 'Fira Code', monospace;

            --color-bg: #f8fafc;
            --color-surface: #ffffff;
            --color-sidebar: #0f172a;
            --color-sidebar-hover: #1e293b;
            --color-sidebar-active: #334155;
            --color-sidebar-text: #94a3b8;
            --color-sidebar-text-bright: #e2e8f0;

            --color-text: #1e293b;
            --color-text-secondary: #64748b;
            --color-text-muted: #94a3b8;
            --color-border: #e2e8f0;
            --color-border-light: #f1f5f9;

            --color-primary: #6366f1;
            --color-primary-light: #eef2ff;

            --color-get: #10b981;
            --color-get-bg: #ecfdf5;
            --color-get-text: #065f46;

            --color-post: #3b82f6;
            --color-post-bg: #eff6ff;
            --color-post-text: #1e40af;

            --color-put: #f59e0b;
            --color-put-bg: #fffbeb;
            --color-put-text: #92400e;

            --color-patch: #8b5cf6;
            --color-patch-bg: #f5f3ff;
            --color-patch-text: #5b21b6;

            --color-delete: #ef4444;
            --color-delete-bg: #fef2f2;
            --color-delete-text: #991b1b;
        }

        body {
            font-family: var(--font-sans);
            background: var(--color-bg);
            color: var(--color-text);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Header ── */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--header-height);
            background: var(--color-sidebar);
            border-bottom: 1px solid rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            padding: 0 24px;
            z-index: 100;
        }

        .header-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            width: var(--sidebar-width);
            flex-shrink: 0;
        }

        .header-logo-icon {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--color-primary), #a855f7);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .header-logo-icon svg {
            width: 20px;
            height: 20px;
            color: white;
        }

        .header-title {
            color: white;
            font-weight: 700;
            font-size: 16px;
            letter-spacing: -0.01em;
        }

        .header-subtitle {
            color: var(--color-sidebar-text);
            font-size: 12px;
            font-weight: 500;
        }

        .header-actions {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 16px;
        }

        .header-search {
            position: relative;
            width: 320px;
        }

        .header-search input {
            width: 100%;
            height: 38px;
            padding: 0 16px 0 40px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            color: white;
            font-size: 14px;
            font-family: var(--font-sans);
            outline: none;
            transition: all 0.2s;
        }

        .header-search input::placeholder { color: var(--color-sidebar-text); }
        .header-search input:focus {
            background: rgba(255,255,255,0.12);
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.15);
        }

        .header-search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--color-sidebar-text);
        }

        .header-stats {
            display: flex;
            gap: 12px;
        }

        .stat-badge {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: rgba(255,255,255,0.06);
            border-radius: 8px;
            font-size: 13px;
            color: var(--color-sidebar-text-bright);
            font-weight: 500;
        }

        .stat-badge .stat-num {
            font-weight: 700;
            color: white;
        }

        /* ── Sidebar ── */
        .sidebar {
            position: fixed;
            top: var(--header-height);
            left: 0;
            width: var(--sidebar-width);
            height: calc(100vh - var(--header-height));
            background: var(--color-sidebar);
            overflow-y: auto;
            z-index: 50;
            padding: 16px 0;
        }

        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-track { background: transparent; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }

        .sidebar-section-title {
            padding: 8px 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--color-sidebar-text);
        }

        .sidebar-folder {
            cursor: pointer;
            user-select: none;
        }

        .sidebar-folder-header {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 7px 20px;
            font-size: 13px;
            font-weight: 600;
            color: var(--color-sidebar-text-bright);
            transition: background 0.15s;
        }

        .sidebar-folder-header:hover { background: var(--color-sidebar-hover); }

        .sidebar-folder-arrow {
            width: 16px;
            height: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s;
            flex-shrink: 0;
        }

        .sidebar-folder-arrow.open { transform: rotate(90deg); }

        .sidebar-folder-arrow svg {
            width: 10px;
            height: 10px;
            color: var(--color-sidebar-text);
        }

        .sidebar-folder-count {
            margin-left: auto;
            font-size: 11px;
            font-weight: 600;
            color: var(--color-sidebar-text);
            background: rgba(255,255,255,0.06);
            padding: 1px 7px;
            border-radius: 10px;
        }

        .sidebar-folder-children {
            overflow: hidden;
            transition: max-height 0.25s ease;
        }

        .sidebar-folder-children.collapsed { max-height: 0 !important; }

        .sidebar-route {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 5px 20px 5px 44px;
            font-size: 13px;
            color: var(--color-sidebar-text);
            text-decoration: none;
            transition: all 0.15s;
            cursor: pointer;
        }

        .sidebar-route:hover {
            background: var(--color-sidebar-hover);
            color: var(--color-sidebar-text-bright);
        }

        .sidebar-route.active {
            background: var(--color-sidebar-active);
            color: white;
        }

        .sidebar-route .method-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .sidebar-route .route-label {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Nested folder indentation */
        .sidebar-folder .sidebar-folder .sidebar-folder-header { padding-left: 40px; }
        .sidebar-folder .sidebar-folder .sidebar-route { padding-left: 64px; }
        .sidebar-folder .sidebar-folder .sidebar-folder .sidebar-folder-header { padding-left: 60px; }
        .sidebar-folder .sidebar-folder .sidebar-folder .sidebar-route { padding-left: 84px; }

        /* ── Main Content ── */
        .main {
            margin-left: var(--sidebar-width);
            margin-top: var(--header-height);
            min-height: calc(100vh - var(--header-height));
            padding: 32px 40px;
            max-width: 900px;
        }

        .section {
            margin-bottom: 48px;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--color-border);
        }

        .section-breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: var(--color-text-muted);
            font-weight: 500;
            margin-bottom: 8px;
        }

        .section-breadcrumb-separator {
            color: var(--color-border);
        }

        .section-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--color-text);
            letter-spacing: -0.02em;
        }

        .section-meta {
            font-size: 13px;
            color: var(--color-text-secondary);
        }

        /* ── Route Card ── */
        .route-card {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: 12px;
            margin-bottom: 16px;
            overflow: hidden;
            transition: box-shadow 0.2s, border-color 0.2s;
        }

        .route-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            border-color: #cbd5e1;
        }

        .route-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            cursor: pointer;
            user-select: none;
        }

        .method-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 64px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            font-family: var(--font-mono);
            letter-spacing: 0.04em;
            text-transform: uppercase;
            flex-shrink: 0;
        }

        .method-badge.get    { background: var(--color-get-bg);    color: var(--color-get-text); }
        .method-badge.post   { background: var(--color-post-bg);   color: var(--color-post-text); }
        .method-badge.put    { background: var(--color-put-bg);    color: var(--color-put-text); }
        .method-badge.patch  { background: var(--color-patch-bg);  color: var(--color-patch-text); }
        .method-badge.delete { background: var(--color-delete-bg); color: var(--color-delete-text); }

        .route-path {
            font-family: var(--font-mono);
            font-size: 14px;
            font-weight: 500;
            color: var(--color-text);
            flex: 1;
        }

        .route-path .route-param {
            color: var(--color-primary);
            font-weight: 600;
        }

        .route-auth-badge {
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            flex-shrink: 0;
        }

        .route-auth-badge.auth {
            background: #fef3c7;
            color: #92400e;
        }

        .route-auth-badge.public {
            background: var(--color-get-bg);
            color: var(--color-get-text);
        }

        .route-card-expand {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--color-text-muted);
            transition: transform 0.2s;
            flex-shrink: 0;
        }

        .route-card-expand.open { transform: rotate(180deg); }

        .route-card-body {
            padding: 0 20px 20px;
            display: none;
            border-top: 1px solid var(--color-border-light);
        }

        .route-card-body.open {
            display: block;
        }

        .route-detail-grid {
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 8px 16px;
            padding-top: 16px;
            font-size: 13px;
        }

        .route-detail-label {
            color: var(--color-text-secondary);
            font-weight: 600;
        }

        .route-detail-value {
            color: var(--color-text);
        }

        .route-detail-value code {
            background: var(--color-border-light);
            padding: 2px 6px;
            border-radius: 4px;
            font-family: var(--font-mono);
            font-size: 12px;
        }

        .middleware-tag {
            display: inline-flex;
            padding: 2px 8px;
            background: var(--color-primary-light);
            color: var(--color-primary);
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            font-family: var(--font-mono);
            margin-right: 4px;
            margin-bottom: 4px;
        }

        .validation-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            font-size: 13px;
        }

        .validation-table th {
            text-align: left;
            padding: 8px 12px;
            background: var(--color-border-light);
            font-weight: 600;
            color: var(--color-text-secondary);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-bottom: 1px solid var(--color-border);
        }

        .validation-table td {
            padding: 8px 12px;
            border-bottom: 1px solid var(--color-border-light);
        }

        .validation-table td:first-child {
            font-family: var(--font-mono);
            font-weight: 500;
            color: var(--color-primary);
            font-size: 12px;
        }

        /* ── Empty State ── */
        .empty-state {
            text-align: center;
            padding: 80px 40px;
            color: var(--color-text-muted);
        }

        .empty-state svg { color: var(--color-border); margin-bottom: 16px; }
        .empty-state h3 { font-size: 18px; color: var(--color-text-secondary); margin-bottom: 8px; }
        .empty-state p { font-size: 14px; }

        /* ── No Results ── */
        .no-results {
            display: none;
            text-align: center;
            padding: 60px 40px;
            color: var(--color-text-muted);
        }

        .no-results h3 { font-size: 16px; color: var(--color-text-secondary); margin-bottom: 8px; }

        /* ── Footer ── */
        .footer {
            margin-left: var(--sidebar-width);
            padding: 24px 40px;
            border-top: 1px solid var(--color-border);
            text-align: center;
            font-size: 13px;
            color: var(--color-text-muted);
        }

        /* ── Method dot colors ── */
        .dot-get    { background: var(--color-get); }
        .dot-post   { background: var(--color-post); }
        .dot-put    { background: var(--color-put); }
        .dot-patch  { background: var(--color-patch); }
        .dot-delete { background: var(--color-delete); }

        /* ── Responsive ── */
        @media (max-width: 900px) {
            .sidebar { display: none; }
            .main, .footer { margin-left: 0; }
            .header-logo { width: auto; }
            .header-search { width: 200px; }
            .header-stats { display: none; }
        }

        /* ── Print ── */
        @media print {
            .sidebar, .header { display: none; }
            .main, .footer { margin-left: 0; margin-top: 0; }
            .route-card-body { display: block !important; }
        }
    </style>
</head>
<body>

<!-- Header -->
<header class="header">
    <div class="header-logo">
        <div class="header-logo-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
            </svg>
        </div>
        <div>
            <div class="header-title">{{ $title }}</div>
            <div class="header-subtitle">API Documentation</div>
        </div>
    </div>

    <div class="header-actions">
        <div class="header-search">
            <span class="header-search-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </span>
            <input type="text" id="searchInput" placeholder="Search endpoints..." autocomplete="off">
        </div>

        <div class="header-stats">
            <div class="stat-badge">
                <span class="stat-num">{{ $routeCount }}</span> endpoints
            </div>
            <div class="stat-badge">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <span class="stat-num">{{ $authRouteCount }}</span> protected
            </div>
            <div class="stat-badge">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>
                <span class="stat-num">{{ $publicRouteCount }}</span> public
            </div>
        </div>
    </div>
</header>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-section-title">Endpoints ({{ $routeCount }})</div>
    <div id="sidebarTree"></div>
</aside>

<!-- Main Content -->
<main class="main" id="mainContent">
    @if(count($sections) === 0)
        <div class="empty-state">
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            <h3>No API routes found</h3>
            <p>Register API routes in your route files and they will appear here automatically.</p>
        </div>
    @endif

    @foreach($sections as $section)
    <div class="section" id="section-{{ $section['slug'] }}" data-section>
        <!-- Breadcrumb -->
        @if(str_contains($section['breadcrumb'], ' / '))
        <div class="section-breadcrumb">
            @foreach(explode(' / ', $section['breadcrumb']) as $i => $crumb)
                @if($i > 0)
                    <span class="section-breadcrumb-separator">/</span>
                @endif
                <span>{{ $crumb }}</span>
            @endforeach
        </div>
        @endif

        <!-- Section Header -->
        <div class="section-header">
            <h2 class="section-title">{{ $section['name'] }}</h2>
            <span class="section-meta">
                {{ $section['routeCount'] }} endpoint{{ $section['routeCount'] !== 1 ? 's' : '' }}
                @if($section['authCount'] > 0)
                    &middot; {{ $section['authCount'] }} protected
                @endif
            </span>
        </div>

        <!-- Route Cards -->
        @foreach($section['routes'] as $route)
        @php
            $method = strtolower($route->getPrimaryMethod());
            $uri = $route->uri;
            $uriDisplay = preg_replace('/\{([^}]+)\}/', '<span class="route-param">{$1}</span>', e($uri));
        @endphp
        <div class="route-card" data-route data-method="{{ $method }}" data-uri="{{ $uri }}" data-name="{{ $route->name ?? '' }}">
            <div class="route-card-header" onclick="toggleRouteCard(this)">
                <span class="method-badge {{ $method }}">{{ strtoupper($method) }}</span>
                <span class="route-path">{!! $uriDisplay !!}</span>

                @if($route->requiresAuth)
                    <span class="route-auth-badge auth">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        Auth
                    </span>
                @else
                    <span class="route-auth-badge public">Public</span>
                @endif

                <span class="route-card-expand">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </span>
            </div>

            <div class="route-card-body">
                <div class="route-detail-grid">
                    @if($route->name)
                    <span class="route-detail-label">Route Name</span>
                    <span class="route-detail-value"><code>{{ $route->name }}</code></span>
                    @endif

                    <span class="route-detail-label">Full URI</span>
                    <span class="route-detail-value"><code>{{ $uri }}</code></span>

                    <span class="route-detail-label">Method</span>
                    <span class="route-detail-value">{{ implode(', ', array_filter($route->methods, fn($m) => $m !== 'HEAD')) }}</span>

                    @if($route->controller)
                    <span class="route-detail-label">Controller</span>
                    <span class="route-detail-value"><code>{{ class_basename($route->controller) }}@{{ $route->action }}</code></span>
                    @endif

                    @if($route->description)
                    <span class="route-detail-label">Description</span>
                    <span class="route-detail-value">{{ $route->description }}</span>
                    @endif

                    @if($route->requiresAuth)
                    <span class="route-detail-label">Auth</span>
                    <span class="route-detail-value">Required (Bearer Token)</span>
                    @endif

                    @if(!empty($route->middleware))
                    <span class="route-detail-label">Middleware</span>
                    <span class="route-detail-value">
                        @foreach($route->middleware as $mw)
                            <span class="middleware-tag">{{ $mw }}</span>
                        @endforeach
                    </span>
                    @endif
                </div>

                @if(!empty($route->parameters))
                <h4 style="margin-top: 20px; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: var(--color-text-secondary);">URL Parameters</h4>
                <table class="validation-table">
                    <thead>
                        <tr>
                            <th>Parameter</th>
                            <th>Required</th>
                            <th>Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($route->parameters as $paramName => $param)
                        <tr>
                            <td>{{ $paramName }}</td>
                            <td>{{ ($param['required'] ?? true) ? 'Yes' : 'No' }}</td>
                            <td>{{ $param['type'] ?? 'string' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif

                @if(!empty($route->validationRules))
                <h4 style="margin-top: 20px; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: var(--color-text-secondary);">Request Body Parameters</h4>
                <table class="validation-table">
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th>Rules</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($route->validationRules as $field => $rules)
                        <tr>
                            <td>{{ $field }}</td>
                            <td>{{ is_array($rules) ? implode(', ', array_map(fn($r) => is_string($r) ? $r : get_class($r), $rules)) : $rules }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @endforeach

    <div class="no-results" id="noResults">
        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <h3>No matching endpoints</h3>
        <p>Try a different search term.</p>
    </div>
</main>

<!-- Footer -->
<footer class="footer">
    Generated by <strong>Laravel Postman Generator</strong> &middot; {{ now()->format('M d, Y \a\t h:i A') }}
</footer>

<script>
(function() {
    // ── Sidebar Tree Rendering ──
    const treeData = {!! $sidebarTree !!};

    function renderTree(nodes, container) {
        nodes.forEach(function(node) {
            const folder = document.createElement('div');
            folder.className = 'sidebar-folder';

            const header = document.createElement('div');
            header.className = 'sidebar-folder-header';

            const hasChildren = (node.children && node.children.length > 0);
            const hasRoutes = (node.routes && node.routes.length > 0);

            header.innerHTML =
                '<span class="sidebar-folder-arrow open"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>' +
                '<span>' + escapeHtml(node.name) + '</span>' +
                '<span class="sidebar-folder-count">' + node.count + '</span>';

            header.addEventListener('click', function() {
                const arrow = header.querySelector('.sidebar-folder-arrow');
                const children = folder.querySelector('.sidebar-folder-children');
                if (children) {
                    children.classList.toggle('collapsed');
                    arrow.classList.toggle('open');
                }
            });

            folder.appendChild(header);

            const childrenContainer = document.createElement('div');
            childrenContainer.className = 'sidebar-folder-children';

            // Render routes
            if (hasRoutes) {
                node.routes.forEach(function(route) {
                    const routeEl = document.createElement('a');
                    routeEl.className = 'sidebar-route';
                    routeEl.href = '#section-' + node.slug;
                    routeEl.innerHTML =
                        '<span class="method-dot dot-' + route.method.toLowerCase() + '"></span>' +
                        '<span class="route-label">' + escapeHtml(route.name) + '</span>';
                    routeEl.addEventListener('click', function(e) {
                        e.preventDefault();
                        const target = document.getElementById('section-' + node.slug);
                        if (target) {
                            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            window.scrollBy(0, -80);
                        }
                    });
                    childrenContainer.appendChild(routeEl);
                });
            }

            // Render sub-folders
            if (hasChildren) {
                renderTree(node.children, childrenContainer);
            }

            folder.appendChild(childrenContainer);
            container.appendChild(folder);
        });
    }

    const sidebarTree = document.getElementById('sidebarTree');
    renderTree(treeData, sidebarTree);

    // ── Toggle Route Card ──
    window.toggleRouteCard = function(header) {
        const body = header.nextElementSibling;
        const expand = header.querySelector('.route-card-expand');
        body.classList.toggle('open');
        expand.classList.toggle('open');
    };

    // ── Search ──
    const searchInput = document.getElementById('searchInput');
    const noResults = document.getElementById('noResults');

    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        const sections = document.querySelectorAll('[data-section]');
        const routes = document.querySelectorAll('[data-route]');
        let visibleCount = 0;

        routes.forEach(function(card) {
            const method = card.getAttribute('data-method') || '';
            const uri = card.getAttribute('data-uri') || '';
            const name = card.getAttribute('data-name') || '';

            const matches = !query ||
                method.includes(query) ||
                uri.toLowerCase().includes(query) ||
                name.toLowerCase().includes(query);

            card.style.display = matches ? '' : 'none';
            if (matches) visibleCount++;
        });

        // Hide empty sections
        sections.forEach(function(section) {
            const visibleRoutes = section.querySelectorAll('[data-route]:not([style*="display: none"])');
            section.style.display = visibleRoutes.length > 0 ? '' : 'none';
        });

        noResults.style.display = visibleCount === 0 && query ? 'block' : 'none';
    });

    // Keyboard shortcut: / to focus search
    document.addEventListener('keydown', function(e) {
        if (e.key === '/' && document.activeElement !== searchInput) {
            e.preventDefault();
            searchInput.focus();
        }
        if (e.key === 'Escape' && document.activeElement === searchInput) {
            searchInput.value = '';
            searchInput.dispatchEvent(new Event('input'));
            searchInput.blur();
        }
    });

    // ── Helper ──
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }
})();
</script>

</body>
</html>
