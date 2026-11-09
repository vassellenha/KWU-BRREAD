@extends('layouts.app')

@section('title', 'Masuk Akun — Brread!')

@section('content')
<div class="min-h-[75vh] flex items-center justify-center py-space-xl px-margin md:px-margin-tablet">
    <div class="w-full max-w-md">
        <div class="text-center mb-space-md">
            <span class="inline-flex items-center gap-1.5 bg-primary-fixed text-primary px-space-md py-1 rounded-full font-label-md text-label-md mb-2">
                <span class="material-symbols-outlined text-[16px]">waving_hand</span>
                Selamat Datang di Brread!
            </span>
            <h1 class="font-headline-md text-headline-md text-on-surface">Masuk ke Akun Anda</h1>
            <p class="font-body-md text-body-md text-on-surface-variant mt-1">
                Lacak pesanan roti es krim dan nikmati kemudahan checkout.
            </p>
        </div>

        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 shadow-[0_8px_30px_rgba(113,59,44,0.08)] p-space-lg sm:p-space-xl">
            @if (session('status'))
                <div class="mb-space-md p-space-sm rounded-xl bg-primary-container text-on-primary-container font-body-sm text-body-sm flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">check_circle</span>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if ($errors->any())
                <div role="alert" class="mb-space-md p-space-sm rounded-xl bg-error-container text-on-error-container font-body-sm text-body-sm flex items-start gap-2">
                    <span class="material-symbols-outlined text-[18px] shrink-0 mt-0.5">error</span>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('login.attempt') }}" class="space-y-space-md">
                @csrf
                <div>
                    <label for="email" class="block font-label-md text-label-md text-on-surface mb-1.5">Email</label>
                    <div class="relative">
                        <input
                            id="email"
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            autocomplete="email"
                            placeholder="nama@email.com"
                            class="w-full rounded-full border border-outline-variant bg-surface-container-lowest pl-11 pr-space-md py-2.5 font-body-md text-body-md text-on-surface placeholder:text-on-surface-variant/50 focus:outline-none focus:ring-2 focus:ring-primary"
                        >
                        <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-[20px] text-on-surface-variant/60">mail</span>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block font-label-md text-label-md text-on-surface">Kata Sandi</label>
                    </div>
                    <div class="relative" x-data="{ showPass: false }">
                        <input
                            id="password"
                            :type="showPass ? 'text' : 'password'"
                            name="password"
                            required
                            autocomplete="current-password"
                            placeholder="Masukkan kata sandi"
                            class="w-full rounded-full border border-outline-variant bg-surface-container-lowest pl-11 pr-11 py-2.5 font-body-md text-body-md text-on-surface placeholder:text-on-surface-variant/50 focus:outline-none focus:ring-2 focus:ring-primary"
                        >
                        <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-[20px] text-on-surface-variant/60">lock</span>
                        <button
                            type="button"
                            @click="showPass = !showPass"
                            class="absolute right-3.5 top-1/2 -translate-y-1/2 text-on-surface-variant/60 hover:text-on-surface transition-colors"
                        >
                            <span class="material-symbols-outlined text-[20px]" x-text="showPass ? 'visibility_off' : 'visibility'">visibility</span>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between text-label-md">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="remember" class="rounded border-outline-variant text-primary focus:ring-primary w-4 h-4">
                        <span class="text-on-surface-variant">Ingat saya</span>
                    </label>
                </div>

                <button
                    type="submit"
                    class="press-scale w-full flex items-center justify-center gap-space-xs rounded-full bg-primary-container px-space-lg py-3 font-label-lg text-label-lg text-on-primary-container shadow-[0_4px_16px_rgba(199,70,89,0.35)] hover:opacity-95 active:translate-y-[2px] transition-[opacity,transform,box-shadow] duration-200"
                >
                    <span class="material-symbols-outlined text-[20px]">login</span>
                    <span>Masuk ke Akun</span>
                </button>
            </form>

            <div class="mt-space-lg pt-space-md border-t border-outline-variant/30 text-center">
                <p class="font-body-sm text-body-sm text-on-surface-variant">
                    Belum punya akun?
                    <a href="{{ route('register') }}" class="font-bold text-primary hover:underline ml-1">
                        Daftar sekarang
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

