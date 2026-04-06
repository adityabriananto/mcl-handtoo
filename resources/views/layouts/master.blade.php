<!DOCTYPE html>
<html>
    <head>
        @include('_includes.meta')
        @include('_includes.head-script')
        @include('_includes.head-style')
        @yield('page-style')
    </head>
    <body class="nav-md w-full">
        <div class="top_nav w-full shadow my-4">
            @include('_includes.header')
        </div>

        <!-- page content -->
        <div class="flex flex-wrap flex-row w-full" role="main">
            <div class="notification-container">
                @include('_includes.notification')
            </div>
            <div class="flex flex-row flex-wrap w-9/10 mx-auto">
                <div class="w-full justify-center text-center my-4" style="font-size: 20px">
                    <h1 class="text-xl md:text-4xl text-blue-500 font-bold">
                        @yield('page-title')
                    </h1>
                </div>
                <div class="w-full">
                    @yield('body')
                    @yield('table')
                </div>
            </div>
        </div>

        <!-- /page content -->
        <div style="width: 100%; height: 100%">
            @include('_includes.footer')
        </div>
        @include('_includes.foot-script')
        @yield('page-script')
    </body>
</html>
