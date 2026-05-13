@props([
    'title' => 'Error',
    'errorCode' => '500',
    'errorTitle' => 'Something went wrong',
    'errorMessage' => 'An unexpected error occurred.',
    'showBackButton' => true
])

@php
    $admin_settings = getAdminAllSetting();
    $color = !empty($admin_settings['color'])?$admin_settings['color']:'theme-3';
    $themeColor = isset($admin_settings['color_flag']) && $admin_settings['color_flag'] == 'true' ? 'custom-color' : $color;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ $errorTitle }} - {{ $errorMessage }}">
    
    <title>{{ $errorCode }} - {{ $errorTitle }} | {{ config('app.name', 'Laravel') }}</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    
    <!-- Site CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('assets/fonts/fontawesome.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/fonts/tabler-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/fonts/feather.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/fonts/material.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/customizer.css') }}">
    <link rel="stylesheet" href="{{ asset('css/custome.css') }}">
    <link rel="stylesheet" href="{{ asset('css/custom-color.css') }}">
    
    <!-- Dark Theme CSS -->
    <link rel="stylesheet" href="{{ asset('assets/css/style-dark.css') }}">
    <link rel="stylesheet" href="{{ asset('css/custom-auth-dark.css') }}">
    
    <style>
        :root {
            --color-customColor: <?= $color ?>;
        }
        
        body {
            background: #1a1d23;
            color: #fff;
        }
        
        .error-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: #1a1d23;
        }
        
        .error-card {
            background: #2d3748;
            border: 1px solid #4a5568;
            border-radius: 8px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }
        
        .error-logo {
            margin-bottom: 30px;
        }
        
        .error-logo img {
            height: 60px;
            width: auto;
            opacity: 0.9;
        }
        
        .error-code {
            font-size: 4rem;
            font-weight: 700;
            margin-bottom: 20px;
            line-height: 1;
        }
        
        .error-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: #e2e8f0;
        }
        
        .error-message {
            font-size: 1rem;
            color: #a0aec0;
            margin-bottom: 30px;
            line-height: 1.5;
        }
        
        .error-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn-primary {
            background: var(--color-customColor);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .btn-secondary {
            background: transparent;
            color: #e2e8f0;
            border: 1px solid #4a5568;
        }
        
        .btn-secondary:hover {
            background: #4a5568;
            transform: translateY(-2px);
        }
        
        .error-footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #4a5568;
            font-size: 0.875rem;
            color: #718096;
        }
        
        /* Custom animations will be defined in individual error pages */
    </style>
</head>
<body class="{{ $themeColor }}">
    <div class="error-container">
        <div class="error-card">
            <!-- Logo -->
            <div class="error-logo">
                <img src="{{ get_file(sidebar_logo()) }}" 
                     alt="{{ config('app.name', 'Laravel') }}"
                     onerror="this.src='{{ asset('uploads/logo/logo_dark.png') }}'">
            </div>
            
            <!-- Error Code -->
            <div class="error-code-container mb-4">
                {{ $slot }}
            </div>
            
            <!-- Error Title -->
            <h1 class="error-title">{{ $errorTitle }}</h1>
            
            <!-- Error Message -->
            <p class="error-message">{{ $errorMessage }}</p>
            
            <!-- Action Buttons -->
            <div class="error-actions">
                <a href="{{ url('/') }}" class="btn btn-primary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                    </svg>
                    Go Home
                </a>
                
                @if($showBackButton)
                <button onclick="history.back()" class="btn btn-secondary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Go Back
                </button>
                @endif
            </div>
            
            <!-- Footer -->
            <div class="error-footer">
                Error Code: {{ $errorCode }} | © {{ date('Y') }} {{ config('app.name', 'Laravel') }}
            </div>
        </div>
    </div>
</body>
</html>
