<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Iniciar sesión')" :description="__('Sistema de gestión SIGMA — acceso solo por invitación')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Correo electrónico o cédula')"
                type="text"
                required
                autofocus
                autocomplete="username"
                placeholder="correo@ejemplo.com ó 1234567890"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button" wire:loading.attr="disabled">
                    <span wire:loading.remove>{{ __('Ingresar') }}</span>
                    <span wire:loading>{{ __('Verificando...') }}</span>
                </flux:button>
            </div>
        </form>
    </div>
</x-layouts.auth>
