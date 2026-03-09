<header class="bg-white">
    <nav class="mx-auto flex max-w-7xl items-center justify-between p-6 lg:px-8" aria-label="Global">
        <div class="flex lg:flex-1">
        <a href={{ route('fdcr-receiving.index') }} class="-m-1.5 p-1.5">
            <span class="sr-only">LEX</span>
            <img class="h-8 w-auto" src="https://tailwindcss.com/plus-assets/img/logos/mark.svg?color=indigo&shade=600" alt="" />
        </a>
        </div>
        <div class="flex lg:hidden">
        <button type="button" class="-m-2.5 inline-flex items-center justify-center rounded-md p-2.5 text-gray-700">
            <span class="sr-only">Open main menu</span>
            <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
        </button>
        </div>
        <div class="hidden lg:flex lg:gap-x-12">
            <div class="relative">
                <!--
                'Product' flyout menu, show/hide based on flyout menu state.

                Entering: "transition ease-out duration-200"
                    From: "opacity-0 translate-y-1"
                    To: "opacity-100 translate-y-0"
                Leaving: "transition ease-in duration-150"
                    From: "opacity-100 translate-y-0"
                    To: "opacity-0 translate-y-1"
                -->
            </div>

            @foreach([
                'FD/CR Cam'       => route('fdcr-receiving.index'),
                // 'FD/CR Dashboard' => route('fdcr-dashboard.index'),
            ] as $linkName => $href)
            <a href={{ $href }} class="text-sm/6 font-semibold text-gray-900">{{ $linkName }}</a>
            @endforeach
        </div>
        <div class="hidden lg:flex lg:flex-1 lg:justify-end">
        {{-- <a href="https://auth.lazada.com/oauth/authorize?response_type=code&force_auth=true&redirect_uri=&client_id=115721" class="text-sm/6 font-semibold text-gray-900">Create Token <span aria-hidden="true">&rarr;</span></a> --}}
        </div>
    </nav>

    <!-- Mobile menu, show/hide based on menu open state. -->
    <div class="lg:hidden" role="dialog" aria-modal="true">
        <!-- Background backdrop, show/hide based on slide-over state. -->
        <div class="fixed inset-0 z-50"></div>
        <div class="fixed inset-y-0 right-0 z-50 w-full overflow-y-auto bg-white p-6 sm:max-w-sm sm:ring-1 sm:ring-gray-900/10">
        <div class="flex items-center justify-between">
            <a href="#" class="-m-1.5 p-1.5">
            <span class="sr-only">Your Company</span>
            <img class="h-8 w-auto" src="https://tailwindcss.com/plus-assets/img/logos/mark.svg?color=indigo&shade=600" alt="" />
            </a>
            <button type="button" class="-m-2.5 rounded-md p-2.5 text-gray-700">
            <span class="sr-only">Close menu</span>
            <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
            </button>
        </div>
        <div class="mt-6 flow-root">
            <div class="-my-6 divide-y divide-gray-500/10">
            <div class="space-y-2 py-6">
                <div class="-mx-3">
                <!-- 'Product' sub-menu, show/hide based on menu state. -->
                </div>
                <a href={{ route('fdcr-receiving.index') }} class="-mx-3 block rounded-lg px-3 py-2 text-base/7 font-semibold text-gray-900 hover:bg-gray-50">FD/CR CAM</div></a>
                {{-- <a href={{ route('fdcr-dashboard.index') }} class="-mx-3 block rounded-lg px-3 py-2 text-base/7 font-semibold text-gray-900 hover:bg-gray-50">FD/CR Dashboard</a> --}}
            </div>
            <div class="py-6">
                <a href="https://auth.lazada.com/oauth/authorize?response_type=code&force_auth=true&redirect_uri=&client_id=115721" class="-mx-3 block rounded-lg px-3 py-2.5 text-base/7 font-semibold text-gray-900 hover:bg-gray-50">Create Token</a>
            </div>
            </div>
        </div>
        </div>
    </div>
</header>
