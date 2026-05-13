<x-error-layout 
    title="Service Unavailable"
    errorCode="503"
    errorTitle="Service Unavailable"
    errorMessage="We are upgrading the system, please try again later."
    showBackButton="false"
>
    <style>
        @keyframes gearSpin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 0.7; }
            50% { opacity: 1; }
        }
        
        .error-code {
            font-size: 4rem;
            font-weight: 700;
            animation: pulse 2s ease-in-out infinite;
            color: #06b6d4;
            margin-bottom: 10px;
        }
        
        .maintenance-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 1rem;
            animation: gearSpin 4s linear infinite;
            color: #06b6d4;
        }
        
        .error-code:hover {
            animation-duration: 1s;
        }
    </style>
    
    <div class="error-code-wrapper">
        <div class="maintenance-icon">
            <svg fill="currentColor" viewBox="0 0 24 24" class="w-full h-full">
                <path d="M12 15.5A3.5 3.5 0 0 1 8.5 12a3.5 3.5 0 0 1 3.5 3.5 3.5 0 0 1-3.5m7.43-2.53c.04-.32.07-.64.07-.97c0-.33-.03-.66-.07-1l2.11-1.63c.19-.15.24-.42.12-.64l-2.49 1c-.52-.39-1.06-.73-1.69-.98l-.37-2.65A.506.506 0 0 1 14 2h-4c-.25 0-.46-.18-.5-.42l-2.37-2.65c-.63-.25-1.17-.59-1.69-.98l-2.49 1c-.22.08-.52.07-.64-.12l2.11-1.63c.04-.32.07-.64.07-.97M12 2C9.243 2 7 4.243 7 7v3H6c-1.103 0-2 .897-2 2v8c0 1.103.897 2 2 2h12c1.103 0 2-.897 2-2v-8c0-1.103-.897-2-2-2h-1V7c0-2.757-2.243-5-5-5z"/>
            </svg>
        </div>
        <div class="error-code">503</div>
    </div>
</x-error-layout>
