<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Hệ Thống Quản Trị Admin - SportHub')</title>
    <!-- Theme Init Script to avoid FOUC -->
    <script>
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style> body { font-family: 'Plus Jakarta Sans', sans-serif; } </style>
</head>
<body class="bg-slate-100 dark:bg-slate-900 text-slate-900 dark:text-slate-100 flex h-screen overflow-hidden transition-colors duration-200">

    <!-- Admin Sidebar -->
    <aside class="w-64 bg-slate-900 dark:bg-slate-950 text-white flex flex-col border-r border-slate-800 flex-shrink-0">
        <div class="h-16 flex items-center px-6 border-b border-slate-800 gap-3">
            <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center font-bold text-white">👑</div>
            <div>
                <span class="font-bold text-lg text-indigo-400">SportHub Admin</span>
                <span class="block text-[10px] text-slate-400 uppercase tracking-wider">Hệ thống quản trị</span>
            </div>
        </div>

        <nav class="flex-grow p-4 space-y-1 overflow-y-auto text-sm font-medium">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-800 transition text-slate-300 hover:text-white">
                📈 Thống kê toàn hệ thống
            </a>
            <a href="{{ route('admin.users.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-800 transition text-slate-300 hover:text-white">
                👥 Quản lý Người dùng
            </a>
            <a href="{{ route('admin.field-owners.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-800 transition text-slate-300 hover:text-white">
                🏢 Kiểm duyệt Chủ sân
            </a>
            <a href="{{ route('admin.sport-categories.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-800 transition text-slate-300 hover:text-white">
                ⚽ Danh mục Thể thao & Loại sân
            </a>
            <a href="{{ route('admin.fields.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-800 transition text-slate-300 hover:text-white">
                🏟️ Kiểm duyệt & QL Sân
            </a>
            <a href="{{ route('admin.bookings.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-800 transition text-slate-300 hover:text-white">
                📋 Quản lý Đơn đặt toàn hệ thống
            </a>
            <a href="{{ route('admin.reviews.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-800 transition text-slate-300 hover:text-white">
                💬 Quản lý Đánh giá & Khiếu nại
            </a>
        </nav>

        <div class="p-4 border-t border-slate-800 text-xs">
            <div class="flex items-center justify-between mb-3 text-slate-400">
                <span>{{ Auth::user()->name }}</span>
                <span class="bg-indigo-500/20 text-indigo-400 px-2 py-0.5 rounded-full text-[10px]">Admin</span>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full text-center py-2 bg-slate-800 hover:bg-slate-700 rounded-lg text-red-400 transition font-semibold">Đăng xuất</button>
            </form>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-grow flex flex-col overflow-y-auto bg-slate-50 dark:bg-slate-900">
        <header class="bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 h-16 flex items-center justify-between px-8 sticky top-0 z-10 shadow-sm">
            <h1 class="text-lg font-bold text-slate-900 dark:text-white">@yield('page_title', 'Bảng Điều Khiển Quản Trị')</h1>
            <div class="flex items-center gap-4">
                <x-theme-toggle />
                <a href="{{ route('home') }}" class="text-xs text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">← Quay về Trang chủ</a>
            </div>
        </header>

        <main class="p-8 flex-grow">
            @if(session('success'))
                <div class="mb-6 bg-emerald-500/20 border border-emerald-500/30 text-emerald-300 px-4 py-3 rounded-xl">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="mb-6 bg-rose-500/20 border border-rose-500/30 text-rose-300 px-4 py-3 rounded-xl">
                    {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    @yield('scripts')
</body>
</html>
