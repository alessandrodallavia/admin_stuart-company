<footer class="w-full p-12">
    <div class="border-t dark:border-t-[0.5px] border-gray-200 dark:opacity-50 mb-12"></div>

    <div class="flex items-center justify-center gap-x-24">
        <div class="max-w-50">
            <img src="{{ asset('assets/logos/logo-stuart.png') }}" alt="Logo Stuart">
        </div>
        
        <div class="flex flex-col lg:flex-row gap-y-4 lg:gap-y-0 lg:gap-x-24">
            <span class="font-medium text-14 text-gray-500 dark:text-gray-300">© Stuart Tutti i diritti riservati</span>
            <div class="flex gap-x-12">
                <a href="{{ route('cookie-policy') }}" target="_blank" class="font-medium text-14 text-gray-500 dark:text-gray-300">Cookie Policy</a>
                <a href="{{ route('privacy-policy') }}" target="_blank" class="font-medium text-14 text-gray-500 dark:text-gray-300">Privacy Policy</a>
            </div>
        </div>
    </div>
</footer>

<script id="Cookiebot" src="https://consent.cookiebot.com/uc.js" data-cbid="ab667725-a4fa-40a8-a528-07b2381e39ac" data-blockingmode="auto" type="text/javascript"></script>