<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Sanggar Tri Arga')</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    @stack('styles')
</head>
<body @hasSection('body_class')class="@yield('body_class')"@endif>
    @yield('content')

    @stack('scripts')
</body>
</html>
