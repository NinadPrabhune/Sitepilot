<x-error-layout 
    title="Too Many Requests"
    errorCode="429"
    errorTitle="Too Many Requests"
    errorMessage="Please wait before trying again."
    showBackButton="true"
>
    <style>
        @keyframes countdown {
            0%, 100% { transform: scale(1); opacity: 0.8; }
            50% { transform: scale(1.05); opacity: 1; }
        }
        
        .error-code {
            font-size: 4rem;
            font-weight: 700;
            animation: countdown 2s ease-in-out infinite;
            color: #facc15;
            margin-bottom: 10px;
        }
        
        .countdown-timer {
            margin-top: 1rem;
            font-size: 1rem;
            font-weight: 600;
            color: #facc15;
        }
        
        .error-code:hover {
            animation-duration: 1s;
        }
    </style>
    
    <div class="error-code-wrapper">
        <div class="error-code">429</div>
        <div class="countdown-timer">
            <span id="countdown">30</span>s remaining
        </div>
    </div>
    
    <script>
        let countdown = 30;
        const countdownElement = document.getElementById('countdown');
        
        const timer = setInterval(() => {
            countdown--;
            if (countdownElement) {
                countdownElement.textContent = countdown;
            }
            
            if (countdown <= 0) {
                clearInterval(timer);
                if (countdownElement) {
                    countdownElement.textContent = '0';
                }
            }
        }, 1000);
    </script>
</x-error-layout>
