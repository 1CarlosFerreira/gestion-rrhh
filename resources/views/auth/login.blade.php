<x-guest-layout variant="login">
    <x-auth-session-status class="mb-6" :status="session('status')" />
    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div>
            <x-input-label for="identifier" value="RUT o correo electrónico" />
            <x-text-input id="identifier" class="mt-1 block w-full" type="text" name="identifier" :value="old('identifier')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('identifier')" class="mt-2" />
        </div>
        <div class="mt-4">
            <x-input-label for="password" value="Contraseña" />
            <x-text-input id="password" class="mt-1 block w-full" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>
        <div class="mt-4 block"><label for="remember_me" class="inline-flex items-center"><input id="remember_me" type="checkbox" class="rounded border-gray-300 text-brand-primary shadow-sm focus:ring-brand-primary" name="remember"><span class="ms-2 text-sm text-gray-600">Recordarme</span></label></div>
        <div class="mt-6 flex flex-col-reverse gap-4 sm:flex-row sm:items-center sm:justify-end">
            @if (Route::has('password.request'))<a class="rounded-md text-sm text-gray-600 underline hover:text-gray-900" href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a>@endif
            <x-primary-button class="w-full justify-center sm:ms-3 sm:w-auto">Ingresar</x-primary-button>
        </div>
    </form>
</x-guest-layout>
