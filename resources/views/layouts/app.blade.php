<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Đặt Sân Thể Thao Trực Tuyến - SportHub')</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Theme Init Script to avoid FOUC -->
    <script>
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    
    <!-- Tailwind v4 CDN for styling -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            500: '#22c55e',
                            600: '#16a34a',
                            700: '#15803d',
                            900: '#14532d',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 flex flex-col min-h-screen transition-colors duration-200">

    <!-- Header Navigation -->
    <header class="bg-slate-900 dark:bg-slate-900/90 dark:backdrop-blur border-b border-slate-800 text-white sticky top-0 z-50 shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Logo -->
                <a href="{{ route('home') }}" class="flex items-center gap-3 group">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-500 to-teal-400 flex items-center justify-center text-white font-extrabold text-xl shadow-lg shadow-emerald-500/20 group-hover:scale-105 transition-transform">
                        ⚽
                    </div>
                    <div>
                        <span class="text-xl font-black bg-gradient-to-r from-emerald-400 to-teal-300 bg-clip-text text-transparent">SportHub</span>
                        <span class="block text-[10px] text-slate-400 font-medium tracking-widest -mt-1 uppercase">Đặt sân trực tuyến</span>
                    </div>
                </a>

                <!-- Navigation Links -->
                <nav class="hidden md:flex items-center space-x-6 text-sm font-medium">
                    <a href="{{ route('home') }}" class="hover:text-emerald-400 transition-colors py-2">Trang chủ</a>
                    <a href="{{ route('customer.fields.index') }}" class="hover:text-emerald-400 transition-colors py-2">Tìm sân thể thao</a>
                </nav>

                <!-- Right Controls: Theme Toggle & Auth Navigation -->
                <div class="flex items-center gap-3">
                    <x-theme-toggle />

                    @auth
                        <div class="relative group">
                            <button class="flex items-center gap-2 p-1.5 rounded-full hover:bg-slate-800 transition">
                                <div class="w-8 h-8 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold text-sm">
                                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                                </div>
                                <span class="text-sm font-semibold hidden sm:inline">{{ Auth::user()->name }}</span>
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </button>
                            <div class="absolute right-0 mt-1 w-56 bg-slate-800 rounded-xl shadow-2xl border border-slate-700 py-2 hidden group-hover:block z-50">
                                <div class="px-4 py-2 border-b border-slate-700">
                                    <p class="text-xs text-slate-400">Đăng nhập với tư cách</p>
                                    <p class="text-sm font-semibold text-emerald-400 capitalize">{{ Auth::user()->role }}</p>
                                </div>
                                @if(Auth::user()->isAdmin())
                                    <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-700">Trang Quản Trị Admin</a>
                                @elseif(Auth::user()->isFieldOwner())
                                    <a href="{{ route('field-owner.dashboard') }}" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-700">Trang Chủ Sân</a>
                                @else
                                    <a href="{{ route('customer.bookings.index') }}" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-700">Lịch sử đặt sân</a>
                                    <a href="{{ route('customer.profile.edit') }}" class="block px-4 py-2 text-sm text-slate-200 hover:bg-slate-700">Thông tin cá nhân</a>
                                @endif
                                <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-700 mt-1 pt-1">
                                    @csrf
                                    <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-400 hover:bg-slate-700">Đăng xuất</button>
                                </form>
                            </div>
                        </div>
                    @else
                        <a href="{{ route('login') }}" class="text-sm font-semibold text-slate-300 hover:text-white px-3 py-2">Đăng nhập</a>
                        <a href="{{ route('register') }}" class="text-sm font-semibold bg-emerald-500 hover:bg-emerald-600 text-slate-950 px-4 py-2 rounded-xl shadow-md shadow-emerald-500/20 transition-all hover:scale-105">Đăng ký ngay</a>
                    @endauth
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow">
        @if(session('success'))
            <div class="max-w-7xl mx-auto px-4 mt-4">
                <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 px-4 py-3 rounded-xl flex items-center justify-between">
                    <span>{{ session('success') }}</span>
                </div>
            </div>
        @endif
        @if(session('error'))
            <div class="max-w-7xl mx-auto px-4 mt-4">
                <div class="bg-rose-500/10 border border-rose-500/30 text-rose-700 px-4 py-3 rounded-xl flex items-center justify-between">
                    <span>{{ session('error') }}</span>
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-slate-950 border-t border-slate-900 text-slate-400 py-12 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 md:grid-cols-4 gap-8">
            <div>
                <span class="text-xl font-black text-white">SportHub</span>
                <p class="text-xs text-slate-500 mt-2 leading-relaxed">Hệ thống đặt sân thể thao trực tuyến nhanh chóng, tiện lợi hàng đầu Việt Nam.</p>
            </div>
            <div>
                <h4 class="text-white text-sm font-bold mb-3">Loại sân thể thao</h4>
                <ul class="space-y-2 text-xs">
                    <li><a href="#" class="hover:text-emerald-400">Sân bóng đá</a></li>
                    <li><a href="#" class="hover:text-emerald-400">Sân cầu lông</a></li>
                    <li><a href="#" class="hover:text-emerald-400">Sân tennis</a></li>
                    <li><a href="#" class="hover:text-emerald-400">Sân bóng rổ</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-white text-sm font-bold mb-3">Hỗ trợ</h4>
                <ul class="space-y-2 text-xs">
                    <li><a href="#" class="hover:text-emerald-400">Hướng dẫn đặt sân</a></li>
                    <li><a href="#" class="hover:text-emerald-400">Chính sách hủy sân</a></li>
                    <li><a href="#" class="hover:text-emerald-400">Dành cho chủ sân</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-white text-sm font-bold mb-3">Liên hệ</h4>
                <p class="text-xs">Hotline: 1900 8888</p>
                <p class="text-xs mt-1">Email: hotro@sporthub.vn</p>
            </div>
        </div>
        <div class="max-w-7xl mx-auto px-4 mt-8 pt-6 border-t border-slate-900 text-center text-xs text-slate-600">
            &copy; 2026 SportHub. All rights reserved. Website Quản lý và Đặt sân Thể thao Trực tuyến.
        </div>
    </footer>

    @yield('scripts')
</body>
</html>
