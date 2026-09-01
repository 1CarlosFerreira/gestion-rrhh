@php
    $statusMessages = [
        'profile-updated' => 'Perfil actualizado correctamente.',
        'password-updated' => 'Contraseña actualizada correctamente.',
    ];
    $success = session('success') ?? ($statusMessages[session('status')] ?? session('status'));
    $error = session('error') ?? session('upload_error') ?? session('send_error');
@endphp

@if($success)
    <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 3500)" x-show="show" x-transition role="status" class="fixed right-4 top-4 z-50 flex max-w-sm items-start gap-3 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-900 shadow-lg">
        <span aria-hidden="true" class="font-semibold text-green-700">✓</span>
        <p class="flex-1">{{ $success }}</p>
        <button type="button" @click="show = false" class="-mt-1 text-lg leading-none text-green-700" aria-label="Cerrar notificación">×</button>
    </div>
@endif

@if($error)
    <section id="flash-error" x-data="{ show: true }" x-show="show" x-transition role="alert" class="fixed right-4 top-4 z-50 max-w-sm rounded-lg border border-red-200 bg-red-50 p-4 text-red-900 shadow-lg">
        <button type="button" @click="show = false" class="absolute right-3 top-3 text-lg leading-none text-red-700" aria-label="Cerrar notificación">×</button>
        <p class="pr-4 font-semibold">{{ $error }}</p>
        @if(session('send_error') && $errors->any())
            <ul class="mt-2 list-disc space-y-1 pl-5 pr-4 text-sm">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
        @endif
    </section>
@endif
