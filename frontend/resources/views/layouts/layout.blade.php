<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title')</title>
    @vite(['resources/css/app.css'])
    @yield('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body class="d-flex flex-column min-vh-100">
    @include('layouts._partials.menu')
    <main class="flex-fill container py-4">
        @yield('content')
    </main>
    
    @include('layouts._partials.footer')
    @vite(['resources/js/app.js'])
    @yield('js')
</body>
</html>