<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Site Locked') }} - {{ config('app.name', 'SitePilot') }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/plugins.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
    <style>
        body {
            background-color: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        .locked-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 40px;
            max-width: 500px;
            width: 90%;
            text-align: center;
        }
        .locked-icon {
            font-size: 64px;
            color: #f56565;
            margin-bottom: 20px;
        }
        .locked-title {
            font-size: 24px;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 16px;
        }
        .locked-message {
            font-size: 16px;
            color: #4a5568;
            margin-bottom: 24px;
            line-height: 1.6;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 20px;
        }
        .status-on-hold {
            background: #fef3c7;
            color: #92400e;
        }
        .status-finished {
            background: #e5e7eb;
            color: #374151;
        }
        .project-name {
            font-weight: 600;
            color: #1a202c;
        }
    </style>
</head>
<body>
    <div class="locked-card">
        <div class="locked-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
        </div>
        
        <h1 class="locked-title">{{ __('Site is Locked') }}</h1>
        
        <span class="status-badge @if($project->status == 'On Hold') status-on-hold @else status-finished @endif">
            {{ __($project->status) }}
        </span>
        
        <p class="locked-message">
            {!! sprintf(__("The site '%s' is currently marked as '%s'. No further updates or access are allowed until the status is changed to 'Ongoing'."), $project->name, $project->status) !!}
        </p>
        
        <p class="locked-message">
            {{ __('Please contact your administrator to unlock this site.') }}
        </p>
        
        <div style="margin-top: 20px;">
            <a href="{{ route('dashboard') }}" class="btn btn-secondary">
                {{ __('Go to Dashboard') }}
            </a>
        </div>
    </div>
</body>
</html>
