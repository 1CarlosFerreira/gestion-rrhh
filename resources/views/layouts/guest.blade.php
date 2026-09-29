@props(['variant' => 'default'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>
        <x-brand-favicons />

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-brand-text antialiased">
        @if ($variant === 'login')
            <main class="flex min-h-screen items-center justify-center bg-brand-primary-soft px-4 py-6 sm:px-6 sm:py-10">
                <div class="grid w-full max-w-5xl overflow-hidden rounded-2xl border border-brand-primary/20 bg-white shadow-xl shadow-brand-primary/10 lg:min-h-[34rem] lg:grid-cols-[1.05fr_0.95fr]">
                    <section class="flex flex-col justify-center bg-brand-primary px-6 py-7 text-white sm:px-10 sm:py-9 lg:px-12 lg:py-12" aria-labelledby="hospital-heading">
                        <a href="/" class="block rounded-lg bg-white/95 p-4 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-4 focus:ring-offset-brand-primary sm:-mx-2 lg:-mx-4">
                            <x-brand-logo variant="horizontal" class="mx-auto w-full max-w-lg" />
                        </a>
                        <div class="mt-6 max-w-md lg:mt-8">
                            <h2 id="hospital-heading" class="text-xl font-semibold tracking-tight sm:text-2xl">Gestión de trámites de Recursos Humanos</h2>
                            <p class="mt-2 text-[0.8125rem] font-normal leading-6 text-white/85">Una plataforma institucional para gestionar solicitudes y documentación de manera segura y ordenada.</p>
                        </div>
                    </section>

                    <section class="flex flex-col justify-center px-6 py-8 sm:px-10 sm:py-10 lg:px-12 lg:py-12" aria-labelledby="login-heading">
                        <div class="mb-7">
                            <h1 id="login-heading" class="text-3xl font-semibold tracking-tight text-brand-text">Gestión RRHH</h1>
                            <p class="mt-2 text-sm leading-6 text-gray-600">Ingresa con tus credenciales para acceder al sistema.</p>
                        </div>
                        {{ $slot }}
                    </section>
                </div>
            </main>
        @else
            <div class="flex min-h-screen flex-col items-center justify-center bg-brand-primary-soft px-4 py-8 sm:px-6">
                <a href="/" class="mb-4 block w-full max-w-md rounded-md focus:outline-none focus:ring-2 focus:ring-brand-primary focus:ring-offset-4 focus:ring-offset-brand-primary-soft">
                    <x-brand-logo variant="horizontal" class="mx-auto w-full" />
                </a>

                <div class="mb-6 text-center">
                    <h1 class="text-2xl font-semibold tracking-tight text-brand-text">Gestión RRHH</h1>
                    <p class="mt-1 text-sm text-gray-600">Sistema de gestión de trámites de Recursos Humanos</p>
                </div>

                <div class="w-full max-w-md overflow-hidden rounded-xl border border-gray-200 bg-white px-6 py-6 shadow-sm sm:px-8">
                    {{ $slot }}
                </div>
            </div>
        @endif
    </body>
</html>
