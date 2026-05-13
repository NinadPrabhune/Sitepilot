<x-error-layout 
    title="Access Denied"
    errorCode="403"
    errorTitle="Access Denied"
    errorMessage="You don't have permission to view this page."
    showBackButton="true"
>
    <style>
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        @keyframes lockPulse {
            0%, 100% { opacity: 0.8; }
            50% { opacity: 1; }
        }
        
        .error-code {
            font-size: 4rem;
            font-weight: 700;
            animation: shake 2s ease-in-out infinite;
            color: #ef4444;
            margin-bottom: 10px;
        }
        
        .lock-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 1rem;
            animation: lockPulse 2s ease-in-out infinite;
            color: #ef4444;
        }
        
        .error-code:hover {
            animation-duration: 0.5s;
        }
    </style>
    
    <div class="error-code-wrapper">
        <div class="lock-icon">
            <svg fill="currentColor" viewBox="0 0 24 24" class="w-full h-full">
                <path d="M12 2C9.243 2 7 4.243 7 7v3H6c-1.103 0-2 .897-2 2v8c0 1.103.897 2 2 2h12c1.103 0 2-.897 2-2v-8c0-1.103-.897-2-2-2h-1V7c0-2.757-2.243-5-5-5zM9 7c0-1.654 1.346-3 3-3s3 1.346 3 3v3H9V7zm4 10.723V20h-2v-2.277c-.595-.347-1-.985-1-1.723 0-1.103.897-2 2-2s2 .897 2 2c0 .738-.405 1.376-1 1.723z"/>
            </svg>
        </div>
        <div class="error-code">403</div>
    </div>
</x-error-layout>
