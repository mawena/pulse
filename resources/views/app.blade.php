<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0B1020">
    <meta name="description" content="MawenaPulse — les signes vitaux de votre serveur, en direct.">

    <title>{{ config('app.name', 'MawenaPulse') }}</title>

    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="apple-touch-icon" href="/favicon.svg">

    @vite(['resources/js/app.js'])
</head>
<body>
    <div id="app"></div>
</body>
</html>
