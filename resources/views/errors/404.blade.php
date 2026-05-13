<x-error-layout 
    title="Page Not Found"
    errorCode="404"
    errorTitle="Page Not Found"
    errorMessage="The page you're looking for doesn't exist."
    showBackButton="true"
>
    <style>
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        @keyframes searchPulse {
            0%, 100% { opacity: 0.7; }
            50% { opacity: 1; }
        }
        
        .error-code {
            font-size: 4rem;
            font-weight: 700;
            animation: float 3s ease-in-out infinite;
            color: #3b82f6;
            margin-bottom: 10px;
        }
        
        .search-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 1rem;
            animation: searchPulse 2s ease-in-out infinite;
            color: #3b82f6;
        }
        
        .error-code:hover {
            animation-duration: 1.5s;
        }
    </style>
    
    <div class="error-code-wrapper">
        <div class="search-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-full h-full">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
        </div>
        <div class="error-code">404</div>
    </div>
</x-error-layout>
