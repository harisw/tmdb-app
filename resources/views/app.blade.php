<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>Inertia Demo</title>
    @vite('resources/js/app.js')
</head>
<body class="bg-gray-100">
@inertia
</body>
</html>
