@extends('layouts.app')

@section('title', 'Daftar Akun Baru — Brread!')

@section('content')
<div class="min-h-[75vh] flex items-center justify-center py-space-xl px-margin md:px-margin-tablet">
    <div class="w-full max-w-md">
        <div class="text-center mb-space-md">
            <span class="inline-flex items-center gap-1.5 bg-secondary-fixed text-primary px-space-md py-1 rounded-full font-label-md text-label-md mb-2">
                <span class="material-symbols-outlined text-[16px]">bakery_dining</span>
                Bergabung dengan #BrreadSquad
            </span>
            <h1 class="font-headline-md text-headline-md text-on-surface">Buat Akun Brread!</h1>
            <p class="font-body-md text-body-md text-on-surface-variant mt-1">
                Daftar untuk simpan kreasi sandwich favorit & lacak transaksi.
            </p>
        </div>

        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 shadow-[0_8px_30px_rgba(113,59,44,0.08)] p-space-lg sm:p-space-xl">
            @if ($errors->any())
                <div role="alert" class="mb-space-md p-space-sm rounded-xl bg-error-container text-on-error-container font-body-sm text-body-sm flex items-start gap-2">
                    <span class="material-symbols-outlined text-[18px] shrink-0 mt-0.5">error</span>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('register.attempt') }}" class="space-y-space-sm">
                @csrf
                <div>
                    <label for="name" class="block font-label-md text-label-md text-on-surface mb-1">Nama Lengkap</label>
                    <div class="relative">
                        <input
                            id="name"
                            type="text"
                            name="name"
                            value="{{ old('name') }}"
                            required
                            autofocus
                            autocomplete="name"
                            placeholder="Contoh: Rian Anggara"
                            class="w-full rounded-full border border-outline-variant bg-surface-container-lowest pl-11 pr-space-md py-2.5 font-body-md text-body-md text-on-surface placeholder:text-on-surface-variant/50 focus:outline-none focus:ring-2 focus:ring-primary"
                        >
                        <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-[20px] text-on-surface-variant/60">person</span>
                    </div>
                </div>

                <div>
                    <label for="email" class="block font-label-md text-label-md text-on-surface mb-1">Alamat Email</label>
                    <div class="relative">
                        <input
                            id="email"
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            required
                            autocomplete="email"
                            placeholder="nama@email.com"
                            class="w-full rounded-full border border-outline-variant bg-surface-container-lowest pl-11 pr-space-md py-2.5 font-body-md text-body-md text-on-surface placeholder:text-on-surface-variant/50 focus:outline-none focus:ring-2 focus:ring-primary"
                        >
                        <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-[20px] text-on-surface-variant/60">mail</span>
                    </div>
                </div>

                <div>
                    <label for="phone" class="block font-label-md text-label-md text-on-surface mb-1">Nomor WhatsApp / HP</label>
                    <div class="relative">
                        <input
                            id="phone"
                            type="tel"
                            name="phone"
                            value="{{ old('phone') }}"
                            required
                            autocomplete="tel"
                            placeholder="081234567890"
                            class="w-full rounded-full border border-outline-variant bg-surface-container-lowest pl-11 pr-space-md py-2.5 font-body-md text-body-md text-on-surface placeholder:text-on-surface-variant/50 focus:outline-none focus:ring-2 focus:ring-primary"
                        >
                        <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-[20px] text-on-surface-variant/60">call</span>
                    </div>
                </div>

                <div x-data="{ showPass: false }">
                    <label for="password" class="block font-label-md text-label-md text-on-surface mb-1">Kata Sandi</label>
                    <div class="relative">
                        <input
                            id="password"
                            :type="showPass ? 'text' : 'password'"
                            name="password"
                            required
                            autocomplete="new-password"
                            placeholder="Minimal 6 karakter"
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

                <div>
                    <label for="password_confirmation" class="block font-label-md text-label-md text-on-surface mb-1">Konfirmasi Kata Sandi</label>
                    <div class="relative">
                        <input
                            id="password_confirmation"
                            type="password"
                            name="password_confirmation"
                            required
                            autocomplete="new-password"
                            placeholder="Ketik ulang kata sandi"
                            class="w-full rounded-full border border-outline-variant bg-surface-container-lowest pl-11 pr-space-md py-2.5 font-body-md text-body-md text-on-surface placeholder:text-on-surface-variant/50 focus:outline-none focus:ring-2 focus:ring-primary"
                        >
                        <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-[20px] text-on-surface-variant/60">lock_reset</span>
                    </div>
                </div>

                <button
                    type="submit"
                    class="press-scale mt-space-sm w-full flex items-center justify-center gap-space-xs rounded-full bg-primary-container px-space-lg py-3 font-label-lg text-label-lg text-on-primary-container shadow-[0_4px_16px_rgba(199,70,89,0.35)] hover:opacity-95 active:translate-y-[2px] transition-[opacity,transform,box-shadow] duration-200"
                >
                    <span class="material-symbols-outlined text-[20px]">person_add</span>
                    <span>Daftar Akun Brread!</span>
                </button>
            </form>

            <div class="mt-space-lg pt-space-md border-t border-outline-variant/30 text-center">
                <p class="font-body-sm text-body-sm text-on-surface-variant">
                    Sudah memiliki akun?
                    <a href="{{ route('login') }}" class="font-bold text-primary hover:underline ml-1">
                        Masuk di sini
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

