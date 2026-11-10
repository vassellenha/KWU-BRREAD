@extends('layouts.app')

@section('title', 'Status Pesanan #' . $order->order_number)

@section('content')

    @php
        $errors = $errors ?? new \Illuminate\Support\ViewErrorBag();
        $rupiah = fn (float $amount): string => 'Rp ' . number_format($amount, 0, ',', '.');
    @endphp

    <div class="min-h-screen py-space-xl bg-surface-container-low font-plus-jakarta print:bg-white print:py-0">
        <div class="max-w-3xl mx-auto px-margin md:px-margin-tablet">

            {{-- FLASH MESSAGE --}}
            @if (session('success'))
                <div class="mb-space-lg p-space-md rounded-xl bg-green-50 border border-green-200 flex items-center gap-space-sm text-green-900 shadow-sm print:hidden">
                    <span class="material-symbols-outlined text-green-600 text-[24px] shrink-0">celebration</span>
                    <p class="font-body-md text-body-md font-semibold flex-1">{{ session('success') }}</p>
                </div>
            @endif
            @if (session('error'))
                <div class="mb-space-lg p-space-md rounded-xl bg-red-50 border border-red-200 flex items-center gap-space-sm text-red-900 shadow-sm print:hidden">
                    <span class="material-symbols-outlined text-red-600 text-[24px] shrink-0">error</span>
                    <p class="font-body-md text-body-md font-semibold flex-1">{{ session('error') }}</p>
                </div>
            @endif

            {{-- STATUS BANNER & TRACKER CARD --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 p-space-lg sm:p-space-xl shadow-sm mb-space-lg print:border-none print:shadow-none print:p-0">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-space-md pb-space-lg border-b border-outline-variant/20">
                    <div>
                        {{-- STATUS BADGE --}}
                        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-label-md font-bold mb-2.5
                            @if ($order->isConfirmed()) bg-green-50 text-green-800 border border-green-300
                            @elseif ($order->isPending()) bg-amber-50 text-amber-800 border border-amber-300
                            @elseif ($order->isCancelled()) bg-gray-100 text-gray-700 border border-gray-300
                            @else bg-blue-50 text-blue-800 border border-blue-300 @endif
                        ">
                            <span class="material-symbols-outlined text-[16px] shrink-0">
                                @if ($order->isConfirmed()) check_circle
                                @elseif ($order->isPending()) hourglass_empty
                                @elseif ($order->isCancelled()) cancel
                                @else info @endif
                            </span>
                            <span>
                                @if ($order->isConfirmed())
                                    Pesanan Terkonfirmasi
                                @elseif ($order->isPending())
                                    Menunggu Konfirmasi
                                @elseif ($order->isCancelled())
                                    Pesanan Dibatalkan
                                @else
                                    {{ ucfirst($order->status) }}
                                @endif
                            </span>
                        </div>

                        <h1 class="font-headline-md text-headline-md text-on-surface font-extrabold tracking-tight">
                            Pesanan #{{ $order->order_number }}
                        </h1>
                        <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
                            Dipesan pada {{ $order->created_at->format('d M Y, H:i') }} WIB
                            @if ($order->confirmed_at)
                                · Dikonfirmasi pada {{ $order->confirmed_at->format('d M Y, H:i') }} WIB
                            @endif
                        </p>
                    </div>

                    {{-- PRINT / DOWNLOAD RECEIPT BUTTON --}}
                    <div class="flex items-center gap-space-xs shrink-0 print:hidden">
                        <button
                            type="button"
                            onclick="window.print()"
                            class="press-scale inline-flex items-center gap-2 px-4 py-2 rounded-full bg-surface-container border border-outline-variant/40 font-label-md text-label-md text-on-surface hover:bg-surface-container-high transition-colors shadow-sm"
                            title="Cetak struk belanja"
                        >
                            <span class="material-symbols-outlined text-[18px] text-primary shrink-0">print</span>
                            <span class="font-semibold">Cetak Struk</span>
                        </button>
                    </div>
                </div>

                {{-- VISUAL ORDER TRACKER TIMELINE (Hidden on print) --}}
                <div class="py-space-md border-b border-outline-variant/20 print:hidden">
                    <div class="grid grid-cols-3 gap-2 text-center relative">
                        {{-- Step 1: Pesanan Masuk --}}
                        <div class="flex flex-col items-center">
                            <div class="w-8 h-8 rounded-full bg-green-500 text-white flex items-center justify-center font-bold text-xs mb-1.5 shadow-sm">
                                <span class="material-symbols-outlined text-[18px] shrink-0">check</span>
                            </div>
                            <span class="font-label-md text-label-md text-on-surface font-bold">1. Masuk</span>
                            <span class="text-[11px] text-on-surface-variant">{{ $order->created_at->format('H:i') }} WIB</span>
                        </div>

                        {{-- Step 2: Konfirmasi --}}
                        <div class="flex flex-col items-center">
                            <div class="w-8 h-8 rounded-full {{ $order->isConfirmed() ? 'bg-green-500 text-white' : ($order->isPending() ? 'bg-amber-500 text-white animate-pulse' : 'bg-gray-300 text-gray-600') }} flex items-center justify-center font-bold text-xs mb-1.5 shadow-sm">
                                @if ($order->isConfirmed())
                                    <span class="material-symbols-outlined text-[18px] shrink-0">check</span>
                                @elseif ($order->isPending())
                                    <span class="material-symbols-outlined text-[18px] shrink-0">hourglass_empty</span>
                                @else
                                    <span class="material-symbols-outlined text-[18px] shrink-0">close</span>
                                @endif
                            </div>
                            <span class="font-label-md text-label-md {{ $order->isConfirmed() ? 'text-on-surface font-bold' : ($order->isPending() ? 'text-amber-800 font-bold' : 'text-on-surface-variant') }}">
                                2. Dikonfirmasi
                            </span>
                            <span class="text-[11px] text-on-surface-variant">
                                {{ $order->confirmed_at ? $order->confirmed_at->format('H:i') . ' WIB' : ($order->isCancelled() ? 'Dibatalkan' : 'Menunggu') }}
                            </span>
                        </div>

                        {{-- Step 3: Siap / Selesai --}}
                        <div class="flex flex-col items-center">
                            <div class="w-8 h-8 rounded-full {{ $order->isConfirmed() ? 'bg-primary-container text-on-primary-container' : 'bg-surface-container text-on-surface-variant/40' }} flex items-center justify-center font-bold text-xs mb-1.5">
                                <span class="material-symbols-outlined text-[18px] shrink-0">icecream</span>
                            </div>
                            <span class="font-label-md text-label-md {{ $order->isConfirmed() ? 'text-primary font-bold' : 'text-on-surface-variant/60' }}">
                                3. Siap Nikmati
                            </span>
                            <span class="text-[11px] text-on-surface-variant">
                                {{ $order->isConfirmed() ? 'Di konter toko' : '-' }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- CUSTOMER DETAILS GRID --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-space-md pt-space-md">
                    <div class="p-space-sm rounded-xl bg-surface-container/30 border border-outline-variant/20">
                        <span class="text-[11px] font-bold text-on-surface-variant uppercase tracking-wider block">Nama Pembeli</span>
                        <p class="font-label-lg text-label-lg text-on-surface font-semibold mt-0.5">{{ $order->customer_name }}</p>
                    </div>
                    <div class="p-space-sm rounded-xl bg-surface-container/30 border border-outline-variant/20">
                        <span class="text-[11px] font-bold text-on-surface-variant uppercase tracking-wider block">Nomor WhatsApp</span>
                        <p class="font-label-lg text-label-lg text-on-surface font-semibold mt-0.5">{{ $order->customer_phone }}</p>
                    </div>
                </div>
            </div>

            {{-- DIGITAL RECEIPT CARD --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 p-space-lg sm:p-space-xl shadow-sm mb-space-lg print:border-none print:shadow-none print:p-0">
                <div class="flex items-center justify-between pb-space-md border-b border-outline-variant/20 mb-space-md">
                    <div class="flex items-center gap-space-xs">
                        <span class="material-symbols-outlined text-primary text-[24px] shrink-0">receipt</span>
                        <h2 class="font-title-lg text-title-lg text-on-surface font-bold">Rincian Belanja</h2>
                    </div>
                    <span class="text-body-sm text-on-surface-variant font-medium">{{ $creations->count() }} Kreasi Sandwich</span>
                </div>

                {{-- CREATIONS LIST --}}
                <div class="space-y-space-md mb-space-lg">
                    @foreach ($creations as $idx => $creation)
                        <div class="p-space-md rounded-xl bg-surface-container-low/60 border border-outline-variant/20 flex flex-col sm:flex-row sm:items-center justify-between gap-space-sm">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-space-xs mb-1.5 flex-wrap">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-primary-container text-on-primary-container">
                                        Kreasi #{{ $idx + 1 }}
                                    </span>
                                    <span class="font-label-lg text-label-lg text-on-surface font-bold">
                                        {{ $creation['bread']?->name ?? 'Bread' }} · {{ $creation['flavor']?->name ?? 'Flavor' }}
                                    </span>
                                </div>
                                <div class="text-body-sm text-on-surface-variant space-y-1 mt-1">
                                    <p class="flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-[16px] text-amber-700 shrink-0">bakery_dining</span>
                                        <span>Roti: <strong class="text-on-surface font-medium">{{ $creation['bread']?->name ?? '-' }}</strong></span>
                                    </p>
                                    <p class="flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-[16px] text-primary shrink-0">icecream</span>
                                        <span>Es Krim: <strong class="text-on-surface font-medium">{{ $creation['flavor']?->name ?? '-' }}</strong></span>
                                    </p>
                                    <p class="flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-[16px] text-tertiary shrink-0">nutrition</span>
                                        <span>Topping:
                                            @if ($creation['toppings']->isNotEmpty())
                                                <strong class="text-on-surface font-medium">{{ $creation['toppings']->pluck('name')->join(', ') }}</strong>
                                            @else
                                                <span class="italic text-on-surface-variant/60">Tanpa topping tambahan</span>
                                            @endif
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <div class="text-right sm:self-center border-t sm:border-t-0 pt-2 sm:pt-0 border-outline-variant/20 shrink-0">
                                <span class="text-body-sm text-on-surface-variant block">{{ $creation['quantity'] }} pcs &times; {{ $rupiah($creation['unit_price']) }}</span>
                                <span class="font-title-md text-title-md font-bold text-primary tabular-nums block mt-0.5">
                                    {{ $rupiah($creation['subtotal']) }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- BILL TOTAL BREAKDOWN --}}
                <div class="border-t border-outline-variant/30 pt-space-md space-y-2">
                    <div class="flex justify-between text-body-md text-on-surface-variant">
                        <span>Subtotal Produk</span>
                        <span class="tabular-nums font-medium text-on-surface">{{ $rupiah((float) $order->total_price) }}</span>
                    </div>
                    <div class="flex justify-between text-body-md text-on-surface-variant">
                        <span>Pajak &amp; Layanan</span>
                        <span class="tabular-nums font-medium text-green-700">Rp 0 (Gratis)</span>
                    </div>
                    <div class="flex justify-between items-baseline pt-2.5 border-t border-outline-variant/20">
                        <span class="font-title-lg text-title-lg text-on-surface font-bold">Total Pembayaran</span>
                        <span class="font-headline-sm text-headline-sm text-primary font-extrabold tabular-nums">
                            {{ $rupiah((float) $order->total_price) }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- REVIEW & RATING SECTION --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 p-space-lg sm:p-space-xl shadow-sm mb-space-lg print:hidden">
                <div class="flex items-center gap-space-xs pb-space-sm border-b border-outline-variant/20 mb-space-md">
                    <span class="material-symbols-outlined text-amber-500 text-[24px] shrink-0">grade</span>
                    <div>
                        <h2 class="font-title-lg text-title-lg text-on-surface font-bold">Ulasan &amp; Rating Pesanan</h2>
                        <p class="font-body-sm text-body-sm text-on-surface-variant">
                            Bagikan pengalaman manis Anda menikmati sandwich es krim Brread!
                        </p>
                    </div>
                </div>

                @if ($order->hasReview())
                    {{-- DISPLAY SUBMITTED REVIEW --}}
                    <div class="rounded-xl bg-amber-50/80 border border-amber-200 p-space-md text-on-surface">
                        <div class="flex items-center justify-between mb-2 flex-wrap gap-1">
                            <div class="flex items-center gap-1">
                                @for ($star = 1; $star <= 5; $star++)
                                    <span
                                        class="material-symbols-outlined text-[24px] shrink-0 {{ $star <= $order->rating ? 'text-amber-400 fill' : 'text-gray-300' }}"
                                        style="{{ $star <= $order->rating ? "font-variation-settings: 'FILL' 1;" : "font-variation-settings: 'FILL' 0;" }}"
                                    >
                                        star
                                    </span>
                                @endfor
                                <span class="font-bold text-amber-900 ml-2 text-title-sm">{{ $order->rating }} / 5 Bintang</span>
                            </div>
                            <span class="text-[12px] text-amber-800/80 font-medium">
                                Diulas {{ $order->reviewed_at?->diffForHumans() }}
                            </span>
                        </div>
                        @if ($order->review_text)
                            <p class="font-body-md text-body-md text-amber-950 italic mt-2 leading-relaxed">
                                “{{ $order->review_text }}”
                            </p>
                        @endif
                        <p class="mt-space-sm text-[12px] text-amber-800 font-semibold flex items-center gap-1">
                            <span class="material-symbols-outlined text-[16px] text-green-700 shrink-0">verified</span>
                            <span>Ulasan Anda telah tersimpan dan membantu kami terus berkembang!</span>
                        </p>
                    </div>
                @else
                    {{-- REVIEW FORM --}}
                    <form
                        method="POST"
                        action="{{ route('order.review', ['token' => $order->confirmation_token]) }}"
                        x-data="{
                            rating: {{ old('rating', 5) }},
                            hoverRating: 0,
                            labels: ['', 'Kurang Memuaskan', 'Cukup Enak', 'Enak & Manis', 'Sangat Enak!', 'Luar Biasa Sempurna! ⭐']
                        }"
                        class="space-y-space-md"
                    >
                        @csrf

                        {{-- STAR RATING INTERACTIVE SELECTOR --}}
                        <div>
                            <label class="block font-label-lg text-label-lg text-on-surface mb-2 font-semibold">
                                Berapa rating kepuasan Anda?
                            </label>
                            <input type="hidden" name="rating" :value="rating">
                            <div class="flex items-center gap-2 flex-wrap" @mouseleave="hoverRating = 0">
                                <div class="inline-flex items-center gap-0.5">
                                    <template x-for="i in 5" :key="i">
                                        <button
                                            type="button"
                                            @click="rating = i"
                                            @mouseenter="hoverRating = i"
                                            class="p-1 transition-transform duration-150 hover:scale-125 focus:outline-none focus-visible:outline-none cursor-pointer select-none"
                                            :aria-label="i + ' Bintang'"
                                        >
                                            <span
                                                class="material-symbols-outlined text-[36px] sm:text-[40px] transition-all duration-150 shrink-0 block leading-none"
                                                :class="(hoverRating ? i <= hoverRating : i <= rating) ? 'text-amber-400 drop-shadow-sm scale-105 fill' : 'text-stone-300 hover:text-amber-300 scale-100 outline'"
                                                :style="(hoverRating ? i <= hoverRating : i <= rating) ? 'font-variation-settings: \'FILL\' 1;' : 'font-variation-settings: \'FILL\' 0;'"
                                            >
                                                star
                                            </span>
                                        </button>
                                    </template>
                                </div>
                                <span
                                    class="ml-1 font-label-md text-label-md text-amber-900 font-bold px-2.5 py-1 rounded-full bg-amber-50 border border-amber-200/70 inline-flex items-center transition-all duration-150 shadow-xs"
                                    x-text="labels[hoverRating || rating]"
                                ></span>
                            </div>
                            @error('rating')
                                <p class="text-error text-body-sm mt-1 font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- REVIEW COMMENT --}}
                        <div>
                            <label for="review_text" class="block font-label-lg text-label-lg text-on-surface mb-1 font-semibold">
                                Ulasan / Komentar <span class="text-on-surface-variant font-normal text-body-sm">(Opsional)</span>
                            </label>
                            <textarea
                                id="review_text"
                                name="review_text"
                                rows="3"
                                maxlength="1000"
                                placeholder="Bagaimana perpaduan rasa es krim dan rotinya? Apakah manisnya pas?"
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest p-space-sm font-body-md text-body-md text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:ring-2 focus:ring-primary resize-y"
                            >{{ old('review_text') }}</textarea>
                            @error('review_text')
                                <p class="text-error text-body-sm mt-1 font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- SUBMIT BUTTON --}}
                        <div class="flex justify-end">
                            <button
                                type="submit"
                                class="press-scale inline-flex items-center gap-2 rounded-full bg-primary-container px-space-xl py-space-sm font-label-lg text-label-lg text-on-primary-container shadow-[0_4px_12px_rgba(199,70,89,0.3)] hover:opacity-95 transition-[opacity,transform] duration-200 font-bold"
                            >
                                <span class="material-symbols-outlined text-[20px] shrink-0">send</span>
                                <span>Kirim Ulasan</span>
                            </button>
                        </div>
                    </form>
                @endif
            </div>

            {{-- ACTION FOOTER --}}
            <div class="flex flex-wrap items-center justify-center gap-space-sm text-center print:hidden pb-space-xl">
                <a
                    href="{{ route('build') }}"
                    class="press-scale inline-flex items-center gap-2 rounded-full bg-primary-container text-on-primary-container px-space-lg py-space-sm font-label-lg text-label-lg font-bold shadow-sm hover:opacity-95 transition-opacity"
                >
                    <span class="material-symbols-outlined text-[18px] shrink-0">auto_awesome</span>
                    <span>Buat Sandwich Baru di Studio</span>
                </a>
                <a
                    href="{{ url('/') }}"
                    class="press-scale inline-flex items-center gap-2 rounded-full bg-surface-container border border-outline-variant/40 text-on-surface px-space-lg py-space-sm font-label-lg text-label-lg hover:bg-surface-container-high transition-colors font-medium"
                >
                    <span class="material-symbols-outlined text-[18px] shrink-0">storefront</span>
                    <span>Kembali ke Beranda</span>
                </a>
            </div>

        </div>
    </div>

    {{-- PRINT STYLES --}}
    <style>
        @media print {
            body {
                background: #fff !important;
                color: #000 !important;
            }
            header, footer, nav, aside, .print\:hidden {
                display: none !important;
            }
            .pt-20 {
                padding-top: 0 !important;
            }
        }
    </style>

@endsection
