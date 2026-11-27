<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Nondon') }} @yield('title')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-gray-100 dark:bg-gray-900">
    <div class="min-h-full flex flex-col">
        @if(auth()->check())
        {{-- Navigation bar --}}
        <nav class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <span class="text-xl font-bold text-gray-900 dark:text-white">{{ config('app.name', 'Nondon') }}</span>
                        </div>
                        <div class="hidden md:flex md:items-center md:ml-6 space-x-4">
                            <x-nav-link href="{{ route('dashboard') }}" :active="request()->routeIs('dashboard')">
                                {{ __('Dashboard') }}
                            </x-nav-link>
                            @can('viewAny', \App\Modules\Identity\Models\User::class)
                            <x-nav-link href="{{ route('users.index') }}" :active="request()->routeIs('users.*')">
                                {{ __('Users') }}
                            </x-nav-link>
                            @endcan
                            @can('viewAny', \App\Modules\Organization\Models\Branch::class)
                            <x-nav-link href="{{ route('branches.index') }}" :active="request()->routeIs('branches.*')">
                                {{ __('Branches') }}
                            </x-nav-link>
                            @endcan
                            @can('viewAny', \App\Modules\Organization\Models\FiscalYear::class)
                            <x-nav-link href="{{ route('fiscal-years.index') }}" :active="request()->routeIs('fiscal-years.*')">
                                {{ __('Fiscal Years') }}
                            </x-nav-link>
                            @endcan
                            @can('viewAny', \App\Modules\Catalog\Models\Product::class)
                            <x-nav-link href="{{ route('products.index') }}" :active="request()->routeIs('products.*')">
                                {{ __('Products') }}
                            </x-nav-link>
                            @endcan
                            @can('viewAny', \App\Modules\Purchasing\Models\Supplier::class)
                            <x-nav-link href="{{ route('suppliers.index') }}" :active="request()->routeIs('suppliers.*')">
                                {{ __('Suppliers') }}
                            </x-nav-link>
                            @endcan
                            @can('viewAny', \App\Modules\Inventory\Models\Garment::class)
                            <x-nav-link href="{{ route('garments.index') }}" :active="request()->routeIs('garments.*')">
                                {{ __('Garments') }}
                            </x-nav-link>
                            <x-nav-link href="{{ route('stock.availability') }}" :active="request()->routeIs('stock.*')">
                                {{ __('Stock') }}
                            </x-nav-link>
                            @endcan
                            @can('assignRoles', \App\Modules\Identity\Models\User::class)
                            <x-nav-link href="{{ route('roles.matrix') }}" :active="request()->routeIs('roles.*')">
                                {{ __('Permissions') }}
                            </x-nav-link>
                            @endcan
                            @if(auth()->user()?->hasRole(['main_branch_admin', 'branch_supervisor', 'online_supervisor']))
                            <x-nav-link href="{{ route('audit.index') }}" :active="request()->routeIs('audit.*')">
                                {{ __('Audit Log') }}
                            </x-nav-link>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center space-x-4">
                        {{-- Active role badge --}}
                        @if(auth()->user()?->activeRole)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">
                            {{ auth()->user()->activeRole->display_name ?? auth()->user()->activeRole->name }}
                        </span>
                        @endif

                        {{-- Role switcher (for multi-role users) --}}
                        @if(auth()->user()?->roles->count() > 1)
                        <a href="{{ route('role.select') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                            {{ __('Switch Role') }}
                        </a>
                        @endif

                        {{-- User menu --}}
                        <div class="relative">
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ auth()->user()?->display_name }}</span>
                        </div>

                        <button
                            wire:click="logout"
                            class="text-sm text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400"
                        >
                            {{ __('Logout') }}
                        </button>
                    </div>
                </div>
            </div>
        </nav>
        @endif

        {{-- Main content --}}
        <main class="flex-1 overflow-y-auto">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                {{-- Flash messages --}}
                @if(session('success'))
                <div class="mb-4 rounded-md bg-green-50 dark:bg-green-900 p-4">
                    <p class="text-sm text-green-800 dark:text-green-200">{{ session('success') }}</p>
                </div>
                @endif

                @if(session('error'))
                <div class="mb-4 rounded-md bg-red-50 dark:bg-red-900 p-4">
                    <p class="text-sm text-red-800 dark:text-red-200">{{ session('error') }}</p>
                </div>
                @endif

                {{ $slot }}
            </div>
        </main>

        {{-- Footer --}}
        <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 mt-auto">
            <div class="max-w-7xl mx-auto px-4 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                {{ config('app.name', 'Nondon') }} ERP &copy; {{ date('Y') }}
            </div>
        </footer>
    </div>

    @livewireScripts
</body>
</html>
