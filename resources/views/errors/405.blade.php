<x-error-layout 
    title="Method Not Allowed"
    errorCode="405"
    errorTitle="Method Not Allowed"
    errorMessage="This request method is not supported."
    showBackButton="true"
>
    <style>
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        @keyframes warningRotate {
            0%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(-5deg); }
            75% { transform: rotate(5deg); }
        }
        
        .error-code {
            font-size: 4rem;
            font-weight: 700;
            animation: shake 2s ease-in-out infinite;
            color: #fb923c;
            margin-bottom: 10px;
        }
        
        .warning-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 1rem;
            animation: warningRotate 2s ease-in-out infinite;
            color: #fb923c;
        }
        
        .error-code:hover {
            animation-duration: 0.5s;
        }
    </style>
    
    <div class="error-code-wrapper">
        <div class="warning-icon">
            <svg fill="currentColor" viewBox="0 0 24 24" class="w-full h-full">
                <path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/>
            </svg>
        </div>
        <div class="error-code">405</div>
    </div>
</x-error-layout>
