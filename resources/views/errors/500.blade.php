<x-error-layout 
    title="Server Error"
    errorCode="500"
    errorTitle="Server Error"
    errorMessage="Something went wrong on our end. We're working on it!"
    showBackButton="true"
>
    <style>
        @keyframes glitch {
            0%, 100% {
                text-shadow: 
                    0.05em 0 0 rgba(255, 0, 0, 0.75),
                    -0.05em -0.025em 0 rgba(0, 255, 0, 0.75),
                    0.025em 0.05em 0 rgba(0, 0, 255, 0.75);
                transform: translate(0);
            }
            14% {
                text-shadow: 
                    0.05em 0 0 rgba(255, 0, 0, 0.75),
                    -0.05em -0.025em 0 rgba(0, 255, 0, 0.75),
                    0.025em 0.05em 0 rgba(0, 0, 255, 0.75);
                transform: translate(-2px, 2px);
            }
            15% {
                text-shadow: 
                    -0.05em -0.025em 0 rgba(255, 0, 0, 0.75),
                    0.025em 0.025em 0 rgba(0, 255, 0, 0.75),
                    -0.05em -0.05em 0 rgba(0, 0, 255, 0.75);
                transform: translate(2px, -2px);
            }
            49% {
                text-shadow: 
                    -0.05em -0.025em 0 rgba(255, 0, 0, 0.75),
                    0.025em 0.025em 0 rgba(0, 255, 0, 0.75),
                    -0.05em -0.05em 0 rgba(0, 0, 255, 0.75);
                transform: translate(2px, -2px);
            }
            50% {
                text-shadow: 
                    0.025em 0.05em 0 rgba(255, 0, 0, 0.75),
                    0.05em 0 0 rgba(0, 255, 0, 0.75),
                    0 -0.05em 0 rgba(0, 0, 255, 0.75);
                transform: translate(-2px, 2px);
            }
            99% {
                text-shadow: 
                    0.025em 0.05em 0 rgba(255, 0, 0, 0.75),
                    0.05em 0 0 rgba(0, 255, 0, 0.75),
                    0 -0.05em 0 rgba(0, 0, 255, 0.75);
                transform: translate(-2px, 2px);
            }
        }
        
        .error-code {
            font-size: 4rem;
            font-weight: 700;
            animation: glitch 2s linear infinite;
            color: #ef4444;
            margin-bottom: 10px;
            position: relative;
        }
        
        .error-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 1rem;
            color: #ef4444;
        }
        
        .error-code:hover {
            animation-duration: 0.1s;
        }
    </style>
    
    <div class="error-code-wrapper">
        <div class="error-icon">
            <svg fill="currentColor" viewBox="0 0 24 24" class="w-full h-full">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2v-4h2v4z"/>
            </svg>
        </div>
        <div class="error-code">500</div>
    </div>
</x-error-layout>
