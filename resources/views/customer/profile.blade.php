@extends('layouts.app')

@section('title', 'Profil Pengguna — Brread!')

@section('content')
<div class="max-w-[900px] mx-auto py-space-xl px-margin md:px-margin-tablet">
    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-space-md mb-space-lg">
        <div>
            <div class="inline-flex items-center gap-1.5 bg-primary-fixed text-primary px-3 py-1 rounded-full font-label-sm text-label-sm mb-2">
                <span class="material-symbols-outlined text-[16px]">account_circle</span>
                Pengaturan Akun Pelanggan
            </div>
            <h1 class="font-headline-md text-headline-md text-on-surface">Profil Saya</h1>
            <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">Kelola informasi pribadi, kontak, dan keamanan kata sandi Anda.</p>
        </div>
        <a
            href="{{ route('orders.index') }}"
            class="press-scale inline-flex items-center gap-2 bg-secondary-container text-on-secondary-container px-4 py-2 rounded-full font-label-md text-label-md hover:opacity-90 transition-opacity"
        >
            <span class="material-symbols-outlined text-[18px]">receipt_long</span>
            <span>Lihat Riwayat Transaksi</span>
        </a>
    </div>

    {{-- Feedback Messages --}}
    @if (session('profile_status'))
        <div class="mb-space-md p-space-sm rounded-xl bg-primary-container text-on-primary-container font-body-sm text-body-sm flex items-center gap-2">
            <span class="material-symbols-outlined text-[20px]">check_circle</span>
            <span>{{ session('profile_status') }}</span>
        </div>
    @endif

    @if (session('password_status'))
        <div class="mb-space-md p-space-sm rounded-xl bg-secondary-container text-on-secondary-container font-body-sm text-body-sm flex items-center gap-2">
            <span class="material-symbols-outlined text-[20px]">verified</span>
            <span>{{ session('password_status') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div role="alert" class="mb-space-md p-space-sm rounded-xl bg-error-container text-on-error-container font-body-sm text-body-sm flex items-start gap-2">
            <span class="material-symbols-outlined text-[20px] shrink-0 mt-0.5">error</span>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-space-lg">
        {{-- Profile Avatar & Overview Column --}}
        <div class="lg:col-span-1 flex flex-col gap-space-md">
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 shadow-[0_4px_20px_rgba(113,59,44,0.06)] p-space-lg flex flex-col items-center text-center">
                <div class="relative group">
                    <img
                        id="avatar-preview"
                        src="{{ $user->avatarUrl() }}"
                        alt="{{ $user->name }}"
                        class="w-28 h-28 rounded-full object-cover border-4 border-primary-container shadow-md"
                    >
                    <label
                        for="avatar-input"
                        class="absolute bottom-0 right-0 w-8 h-8 rounded-full bg-primary text-on-primary flex items-center justify-center cursor-pointer shadow hover:scale-105 transition-transform"
                        title="Ubah Foto Profil"
                    >
                        <span class="material-symbols-outlined text-[16px]">photo_camera</span>
                    </label>
                </div>

                <h2 class="font-title-md text-title-md text-on-surface mt-3">{{ $user->name }}</h2>
                <p class="font-body-sm text-body-sm text-on-surface-variant">{{ $user->email }}</p>
                <span class="mt-2 inline-flex items-center gap-1 bg-primary-fixed text-primary px-3 py-0.5 rounded-full text-xs font-semibold">
                    <span class="material-symbols-outlined text-[14px]">stars</span>
                    Brread! Member
                </span>

                <div class="w-full border-t border-outline-variant/20 mt-4 pt-3 text-left">
                    <p class="text-xs text-on-surface-variant font-medium">Terdaftar sejak:</p>
                    <p class="text-xs font-semibold text-on-surface mt-0.5">{{ $user->created_at->translatedFormat('d F Y') }}</p>
                </div>
            </div>

            {{-- Pickup Notice Card --}}
            <div class="bg-surface-container-low rounded-2xl border border-outline-variant/30 p-space-md flex items-start gap-3">
                <div class="w-9 h-9 rounded-full bg-tertiary-container text-on-tertiary-container flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[20px]">storefront</span>
                </div>
                <div>
                    <h3 class="font-title-sm text-title-sm text-on-surface">Metode Pemesanan</h3>
                    <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5">
                        Pesanan dilayani melalui <strong class="text-on-surface">Pickup di Booth Kampus</strong>. Tunjukkan nomor pesanan saat pengambilan.
                    </p>
                </div>
            </div>
        </div>

        {{-- Form Edit Data & Password Column --}}
        <div class="lg:col-span-2 flex flex-col gap-space-lg">
            {{-- Personal Data Form --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 shadow-[0_4px_20px_rgba(113,59,44,0.06)] p-space-lg sm:p-space-xl">
                <h2 class="font-title-lg text-title-lg text-on-surface flex items-center gap-2 mb-space-sm">
                    <span class="material-symbols-outlined text-primary">badge</span>
                    Data Diri & Kontak
                </h2>

                <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="space-y-space-md">
                    @csrf
                    @method('PUT')

                    <input
                        id="avatar-input"
                        type="file"
                        name="avatar"
                        accept="image/*"
                        class="hidden"
                        onchange="if (this.files && this.files[0]) { document.getElementById('avatar-preview').src = URL.createObjectURL(this.files[0]); }"
                    >

                    <div>
                        <label for="profile-name" class="block font-label-md text-label-md text-on-surface mb-1">Nama Lengkap</label>
                        <input
                            id="profile-name"
                            type="text"
                            name="name"
                            value="{{ old('name', $user->name) }}"
                            required
                            maxlength="255"
                            class="w-full rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-2.5 font-body-md text-body-md text-on-surface focus:outline-none focus:ring-2 focus:ring-primary"
                        >
                    </div>

                    <div>
                        <label for="profile-email" class="block font-label-md text-label-md text-on-surface mb-1">Alamat Email</label>
                        <input
                            id="profile-email"
                            type="email"
                            value="{{ $user->email }}"
                            disabled
                            class="w-full rounded-full border border-outline-variant/50 bg-surface-container px-space-md py-2.5 font-body-md text-body-md text-on-surface-variant cursor-not-allowed opacity-80"
                        >
                        <span class="text-xs text-on-surface-variant/70 mt-1 block">Alamat email digunakan sebagai ID akun permanen.</span>
                    </div>

                    <div>
                        <label for="profile-phone" class="block font-label-md text-label-md text-on-surface mb-1">Nomor WhatsApp / HP</label>
                        <input
                            id="profile-phone"
                            type="tel"
                            name="phone"
                            value="{{ old('phone', $user->phone) }}"
                            required
                            maxlength="30"
                            placeholder="Contoh: 081234567890"
                            class="w-full rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-2.5 font-body-md text-body-md text-on-surface focus:outline-none focus:ring-2 focus:ring-primary"
                        >
                    </div>

                    <div class="pt-space-xs">
                        <button
                            type="submit"
                            class="press-scale inline-flex items-center gap-2 rounded-full bg-primary-container px-6 py-2.5 font-label-lg text-label-lg text-on-primary-container shadow-[0_4px_12px_rgba(199,70,89,0.3)] hover:opacity-95 transition-opacity"
                        >
                            <span class="material-symbols-outlined text-[18px]">save</span>
                            <span>Simpan Perubahan</span>
                        </button>
                    </div>
                </form>
            </div>

            {{-- Delivery Address Section (Grayed Out / Disabled per requirement) --}}
            <div class="bg-surface-container-lowest/60 rounded-2xl border-2 border-dashed border-outline-variant/50 p-space-lg relative overflow-hidden select-none">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <h2 class="font-title-lg text-title-lg text-on-surface/60 flex items-center gap-2">
                        <span class="material-symbols-outlined text-on-surface-variant/50">local_shipping</span>
                        Alamat Pengiriman (Delivery)
                    </h2>
                    <span class="bg-surface-container-high text-on-surface-variant text-[11px] font-bold px-3 py-1 rounded-full uppercase tracking-wider">
                        Segera Hadir
                    </span>
                </div>

                <p class="font-body-sm text-body-sm text-on-surface-variant/70 mb-3">
                    Fitur pengantaran ke alamat rumah saat ini masih dalam tahap persiapan. Saat ini seluruh transaksi menggunakan opsi <strong>Pickup di Booth Kampus</strong>.
                </p>

                <div>
                    <label class="block font-label-md text-label-md text-on-surface-variant/50 mb-1">Detail Alamat</label>
                    <textarea
                        rows="2"
                        disabled
                        placeholder="Contoh: Jl. Diponegoro No. 45, Kecamatan Gambir, Jakarta Pusat (Segera Hadir)"
                        class="w-full rounded-2xl border border-outline-variant/30 bg-surface-container/40 p-space-sm font-body-md text-body-md text-on-surface-variant/40 cursor-not-allowed resize-none"
                    ></textarea>
                </div>
            </div>

            {{-- Change Password Form --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 shadow-[0_4px_20px_rgba(113,59,44,0.06)] p-space-lg sm:p-space-xl">
                <h2 class="font-title-lg text-title-lg text-on-surface flex items-center gap-2 mb-space-sm">
                    <span class="material-symbols-outlined text-secondary">lock</span>
                    Ubah Kata Sandi
                </h2>

                <form method="POST" action="{{ route('profile.password') }}" class="space-y-space-md">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="current_password" class="block font-label-md text-label-md text-on-surface mb-1">Kata Sandi Saat Ini</label>
                        <input
                            id="current_password"
                            type="password"
                            name="current_password"
                            required
                            autocomplete="current-password"
                            class="w-full rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-2.5 font-body-md text-body-md text-on-surface focus:outline-none focus:ring-2 focus:ring-primary"
                        >
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-space-sm">
                        <div>
                            <label for="new_password" class="block font-label-md text-label-md text-on-surface mb-1">Kata Sandi Baru</label>
                            <input
                                id="new_password"
                                type="password"
                                name="password"
                                required
                                autocomplete="new-password"
                                placeholder="Minimal 6 karakter"
                                class="w-full rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-2.5 font-body-md text-body-md text-on-surface focus:outline-none focus:ring-2 focus:ring-primary"
                            >
                        </div>
                        <div>
                            <label for="new_password_confirmation" class="block font-label-md text-label-md text-on-surface mb-1">Konfirmasi Kata Sandi Baru</label>
                            <input
                                id="new_password_confirmation"
                                type="password"
                                name="password_confirmation"
                                required
                                autocomplete="new-password"
                                placeholder="Ulangi kata sandi"
                                class="w-full rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-2.5 font-body-md text-body-md text-on-surface focus:outline-none focus:ring-2 focus:ring-primary"
                            >
                        </div>
                    </div>

                    <div class="pt-space-xs">
                        <button
                            type="submit"
                            class="press-scale inline-flex items-center gap-2 rounded-full bg-secondary-container px-6 py-2.5 font-label-lg text-label-lg text-on-secondary-container hover:opacity-95 transition-opacity"
                        >
                            <span class="material-symbols-outlined text-[18px]">key</span>
                            <span>Perbarui Kata Sandi</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

