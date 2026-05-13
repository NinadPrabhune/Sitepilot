<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Alert</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .alert-box { border-left: 4px solid #dc3545; background: #f8d7da; padding: 15px; margin-bottom: 20px; }
        .info-box { background: #f8f9fa; padding: 15px; margin-bottom: 15px; border-radius: 4px; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; font-size: 12px; color: #6c757d; }
    </style>
</head>
<body>
    <div class="container">
        <div class="alert-box">
            <h2 style="margin: 0 0 10px 0; color: #721c24;">🚨 CRITICAL: Financial System Alert</h2>
        </div>

        <div class="info-box">
            <h3 style="margin-top: 0;">Issue: {{ $data['issue_type'] }}</h3>
            
            @if(isset($data['details']))
                @foreach($data['details'] as $key => $value)
                    <p><strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong> {{ $value }}</p>
                @endforeach
            @endif
        </div>

        @if(isset($data['action_required']))
        <div class="info-box" style="border-left: 4px solid #ffc107; background: #fff3cd;">
            <h4 style="margin-top: 0; color: #856404;">⚠️ Action Required</h4>
            <p>{{ $data['action_required'] }}</p>
        </div>
        @endif

        @if(isset($data['link']))
        <p>
            <a href="{{ $data['link'] }}" class="btn">View Details</a>
        </p>
        @endif

        <div class="footer">
            <p>This is an automated alert from the SitePilot Financial System.</p>
            <p>Timestamp: {{ now()->format('Y-m-d H:i:s') }}</p>
        </div>
    </div>
</body>
</html>
