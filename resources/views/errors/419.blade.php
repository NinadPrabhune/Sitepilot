<x-error-layout 
    title="Page Expired"
    errorCode="419"
    errorTitle="Page Expired"
    errorMessage="Your session has timed out. Please refresh and try again."
    showBackButton="true"
>
    <style>
        @keyframes fadeInOut {
            0%, 100% { opacity: 0.7; }
            50% { opacity: 1; }
        }
        
        @keyframes clockTick {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .error-code {
            font-size: 4rem;
            font-weight: 700;
            animation: fadeInOut 3s ease-in-out infinite;
            color: #9333ea;
            margin-bottom: 10px;
        }
        
        .clock-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 1rem;
            position: relative;
            animation: fadeInOut 2s ease-in-out infinite;
            color: #9333ea;
        }
        
        .clock-hand {
            position: absolute;
            top: 50%;
            left: 50%;
            transform-origin: bottom center;
            background: currentColor;
        }
        
        .hour-hand {
            width: 2px;
            height: 20px;
            margin-left: -1px;
            margin-top: -20px;
            animation: clockTick 4s linear infinite;
        }
        
        .minute-hand {
            width: 1px;
            height: 25px;
            margin-left: -0.5px;
            margin-top: -25px;
            animation: clockTick 2s linear infinite;
        }
        
        .error-code:hover {
            animation-duration: 1.5s;
        }
    </style>
    
    <div class="error-code-wrapper">
        <div class="clock-icon">
            <svg fill="currentColor" viewBox="0 0 24 24" class="w-full h-full">
                <path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8z"/>
                <path d="M13 7h-2v6h6v-2h-4V7z"/>
            </svg>
            <div class="clock-hand hour-hand"></div>
            <div class="clock-hand minute-hand"></div>
        </div>
        <div class="error-code">419</div>
    </div>
</x-error-layout>
