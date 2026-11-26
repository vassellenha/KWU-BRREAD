@extends('layouts.staff')

@section('title', 'Customer Orders')

@section('content')

    @php
        $rupiah = fn (float $amount): string => 'Rp ' . number_format($amount, 0, ',', '.');
        $fieldClasses = 'rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-sm text-body-sm text-on-surface placeholder:text-on-surface-variant focus:outline-none focus:ring-2 focus:ring-primary';
    @endphp

    {{-- PAGE HEADER --}}
    <x-staff.page-header icon="receipt_long" title="Customer Orders">
        Kelola pesanan masuk, konfirmasi via WhatsApp, buat tautan tracking pembeli, serta pantau ulasan pelanggan.
    </x-staff.page-header>

    {{-- FLASH NOTIFICATIONS --}}
    @if (session('success'))
        <div class="mb-space-md p-space-md rounded-xl bg-primary-container/20 border border-primary/30 flex items-center gap-space-sm text-on-surface shadow-sm">
            <span class="material-symbols-outlined text-primary text-[22px] shrink-0">check_circle</span>
            <p class="font-body-md text-body-md font-medium flex-1">{{ session('success') }}</p>
        </div>
    @endif
    @if (session('error'))
        <div class="mb-space-md p-space-md rounded-xl bg-error-container/20 border border-error/30 flex items-center gap-space-sm text-on-surface shadow-sm">
            <span class="material-symbols-outlined text-error text-[22px] shrink-0">error</span>
            <p class="font-body-md text-body-md font-medium flex-1">{{ session('error') }}</p>
        </div>
    @endif
    @if (session('info'))
        <div class="mb-space-md p-space-md rounded-xl bg-surface-container border border-outline-variant/40 flex items-center gap-space-sm text-on-surface shadow-sm">
            <span class="material-symbols-outlined text-on-surface-variant text-[22px] shrink-0">info</span>
            <p class="font-body-md text-body-md flex-1">{{ session('info') }}</p>
        </div>
    @endif

    {{-- STAT SUMMARY CARDS --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-space-md mb-space-lg">
        {{-- Total Orders --}}
        <div class="bg-surface-container-lowest rounded-xl border border-outline-variant/30 p-space-md shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider font-semibold">Total Orders</span>
                <span class="w-8 h-8 rounded-lg bg-surface-container flex items-center justify-center text-on-surface-variant shrink-0">
                    <span class="material-symbols-outlined text-[18px]">receipt_long</span>
                </span>
            </div>
            <p class="font-headline-sm text-headline-sm text-on-surface font-extrabold tabular-nums">
                {{ number_format($stats['total'], 0, ',', '.') }}
            </p>
            <span class="text-[11px] text-on-surface-variant mt-0.5 block">Semua riwayat pesanan</span>
        </div>

        {{-- Pending Confirmation --}}
        <div class="bg-surface-container-lowest rounded-xl border {{ $stats['pending'] > 0 ? 'border-amber-400 bg-amber-50/20' : 'border-outline-variant/30' }} p-space-md shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <span class="font-label-md text-label-md {{ $stats['pending'] > 0 ? 'text-amber-800' : 'text-on-surface-variant' }} uppercase tracking-wider font-semibold">Pending</span>
                <span class="w-8 h-8 rounded-lg {{ $stats['pending'] > 0 ? 'bg-amber-100 text-amber-700' : 'bg-surface-container text-on-surface-variant' }} flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[18px]">pending_actions</span>
                </span>
            </div>
            <p class="font-headline-sm text-headline-sm tabular-nums font-extrabold {{ $stats['pending'] > 0 ? 'text-amber-700' : 'text-on-surface' }}">
                {{ number_format($stats['pending'], 0, ',', '.') }}
            </p>
            <span class="text-[11px] {{ $stats['pending'] > 0 ? 'text-amber-700 font-medium' : 'text-on-surface-variant' }} mt-0.5 block">
                {{ $stats['pending'] > 0 ? 'Perlu konfirmasi staff' : 'Tidak ada antrean' }}
            </span>
        </div>

        {{-- Confirmed --}}
        <div class="bg-surface-container-lowest rounded-xl border border-outline-variant/30 p-space-md shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider font-semibold">Confirmed</span>
                <span class="w-8 h-8 rounded-lg bg-green-50 text-green-700 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[18px]">check_circle</span>
                </span>
            </div>
            <p class="font-headline-sm text-headline-sm text-on-surface font-extrabold tabular-nums">
                {{ number_format($stats['confirmed'], 0, ',', '.') }}
            </p>
            <span class="text-[11px] text-on-surface-variant mt-0.5 block">Menunggu pembayaran / tracking aktif</span>
        </div>

        {{-- Reviews --}}
        <div class="bg-surface-container-lowest rounded-xl border border-outline-variant/30 p-space-md shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider font-semibold">Reviews</span>
                <span class="w-8 h-8 rounded-lg bg-amber-50 text-amber-800 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1;">star</span>
                </span>
            </div>
            <div class="flex items-baseline gap-1.5">
                <p class="font-headline-sm text-headline-sm text-on-surface font-extrabold tabular-nums">
                    {{ $stats['avg_rating'] > 0 ? number_format($stats['avg_rating'], 1) : '-' }}
                </p>
                @if ($stats['avg_rating'] > 0)
                    <span class="text-[12px] font-bold text-amber-800">/ 5.0</span>
                @endif
            </div>
            <span class="text-[11px] text-on-surface-variant mt-0.5 block">
                {{ $stats['reviews_count'] > 0 ? "({$stats['reviews_count']} ulasan masuk)" : '(Belum ada ulasan)' }}
            </span>
        </div>
    </div>

    {{-- FILTER TABS & SEARCH BAR --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-space-sm mb-space-md">
        <nav aria-label="Status filters" class="flex flex-wrap items-center gap-space-xs">
            @php
                $statusTabs = [
                    'all' => 'Semua Pesanan',
                    'pending' => 'Masuk',
                    'unconfirmed' => 'Belum Dikonfirmasi',
                    'confirmed' => 'Sudah Dikonfirmasi',
                    'paid' => 'Pembayaran Berhasil',
                    'processing' => 'Disiapkan',
                    'ready' => 'Siap Diambil',
                    'cancelled' => 'Dibatalkan',
                ];
            @endphp
            @foreach ($statusTabs as $tabKey => $tabLabel)
                @php $isActive = $currentStatus === $tabKey; @endphp
                <a
                    href="{{ route('staff.orders.index', array_merge(request()->except('page'), ['status' => $tabKey])) }}"
                    class="press-scale inline-flex items-center gap-1.5 px-space-md py-1.5 rounded-full font-label-md text-label-md transition-[background-color,color] duration-200 {{ $isActive ? 'bg-primary-container text-on-primary-container font-bold shadow-sm' : 'bg-surface-container text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high' }}"
                >
                    {{ $tabLabel }}
                    @if ($tabKey === 'pending' && $stats['pending'] > 0)
                        <span class="px-1.5 py-0.2 rounded-full text-[11px] bg-primary text-on-primary font-bold">
                            {{ $stats['pending'] }}
                        </span>
                    @elseif ($tabKey === 'unconfirmed' && $stats['pending'] > 0)
                        <span class="px-1.5 py-0.2 rounded-full text-[11px] bg-amber-600 text-white font-bold">
                            {{ $stats['pending'] }}
                        </span>
                    @elseif ($tabKey === 'confirmed' && $stats['confirmed'] > 0)
                        <span class="px-1.5 py-0.2 rounded-full text-[11px] bg-green-700 text-white font-bold">
                            {{ $stats['confirmed'] }}
                        </span>
                    @endif
                </a>
            @endforeach
        </nav>

        <form method="GET" action="{{ route('staff.orders.index') }}" class="flex items-center gap-space-xs">
            @if ($currentStatus !== 'all')
                <input type="hidden" name="status" value="{{ $currentStatus }}">
            @endif
            @if ($pickup !== '')
                <input type="hidden" name="pickup" value="{{ $pickup }}">
            @endif
            <div class="relative w-full sm:w-auto">
                <input
                    type="text"
                    name="q"
                    value="{{ $search }}"
                    placeholder="Cari order, nama, no HP..."
                    class="{{ $fieldClasses }} pl-9 pr-space-md w-full sm:w-64"
                >
                <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-[18px] text-on-surface-variant/70 shrink-0">search</span>
            </div>
            @if ($search !== '')
                <a href="{{ route('staff.orders.index', array_filter(['status' => $currentStatus, 'pickup' => $pickup])) }}" class="press-scale p-1.5 rounded-full text-on-surface-variant hover:bg-surface-container" title="Reset pencarian">
                    <span class="material-symbols-outlined text-[18px] shrink-0">close</span>
                </a>
            @endif
            <button type="submit" class="press-scale px-space-md py-space-xs rounded-full bg-primary-container text-on-primary-container font-label-md text-label-md hover:opacity-95 font-semibold">
                Cari
            </button>
        </form>
    </div>

    {{-- PICKUP DAY FILTER: who collects on which day, and how many sandwiches that is --}}
    @if ($pickupDays->isNotEmpty() || $unscheduled > 0 || $pickup !== '')
        <section aria-label="Filter tanggal ambil" class="mb-space-md">
            <p class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider font-semibold mb-1.5">Tanggal ambil</p>
            <div class="flex flex-wrap items-center gap-space-xs">
                @php
                    $chip = fn (bool $active) => 'press-scale inline-flex items-center gap-1.5 px-space-md py-1.5 rounded-full font-label-md text-label-md transition-[background-color,color] duration-200 '.($active ? 'bg-primary-container text-on-primary-container font-bold shadow-sm' : 'bg-surface-container text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high');
                @endphp
                <a href="{{ route('staff.orders.index', request()->except('page', 'pickup')) }}" class="{{ $chip($pickup === '') }}">Semua tanggal</a>
                @foreach ($pickupDays as $day)
                    <a href="{{ route('staff.orders.index', array_merge(request()->except('page'), ['pickup' => $day['date']])) }}" class="{{ $chip($pickup === $day['date']) }}">
                        <span class="material-symbols-outlined text-[14px] shrink-0">event</span>
                        {{ \Illuminate\Support\Carbon::parse($day['date'])->locale('id')->translatedFormat('D, j M') }}
                        <span class="tabular-nums opacity-80">{{ $day['orders'] }} pesanan, {{ $day['sandwiches'] }} sandwich</span>
                    </a>
                @endforeach
                @if ($unscheduled > 0)
                    <a href="{{ route('staff.orders.index', array_merge(request()->except('page'), ['pickup' => 'none'])) }}" class="{{ $chip($pickup === 'none') }}">
                        Belum ada tanggal
                        <span class="tabular-nums opacity-80">{{ $unscheduled }}</span>
                    </a>
                @endif
            </div>
            @php $selectedDay = $pickupDays->firstWhere('date', $pickup); @endphp
            @if ($selectedDay)
                <p class="mt-1.5 text-[12px] text-on-surface-variant">Diambil {{ \Illuminate\Support\Carbon::parse($selectedDay['date'])->locale('id')->translatedFormat('l, j F Y') }}: {{ implode(', ', $selectedDay['people']) }}. Angka dalam kurung adalah jumlah sandwich.</p>
            @endif
        </section>
    @endif

    {{-- ORDERS TABLE CARD --}}
    <div class="bg-surface-container-lowest rounded-xl border border-outline-variant/30 overflow-hidden shadow-sm">
        <p id="orders-help" class="sr-only">Use Up and Down to move between orders. Press Enter to open the tracking page, or to reach the order's buttons. Confirm and Cancel stay explicit buttons.</p>
        <div x-data x-grid-nav.list class="overflow-x-auto">
            <table class="w-full min-w-[900px] text-left border-collapse" aria-describedby="orders-help">
                <thead>
                    <tr class="border-b border-outline-variant/30 bg-surface-container/40">
                        <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wider font-semibold w-40">No. Pesanan</th>
                        <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wider font-semibold w-44">Pembeli</th>
                        <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wider font-semibold">Rincian Item</th>
                        <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wider font-semibold w-28 text-right">Total</th>
                        <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wider font-semibold w-32 text-center">Status</th>
                        <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wider font-semibold w-56">Link Tracking</th>
                        <th class="orders-sticky px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wider font-semibold w-36 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/20 font-body-sm text-body-sm">
                    @forelse ($orders as $order)
                        @php
                            $creationsCount = $order->orderItems->pluck('creation_index')->unique()->count();
                            $creations = $order->orderItems->groupBy('creation_index')->map(function ($items) {
                                return [
                                    'qty' => $items->first()?->quantity ?? 1,
                                    'bread' => $items->firstWhere('component.category', 'bread')?->component?->name ?? 'Bread',
                                    'flavor' => $items->firstWhere('component.category', 'ice_cream')?->component?->name ?? 'Ice Cream',
                                    'toppings' => $items->filter(fn ($i) => ! in_array($i->component?->category, ['bread', 'ice_cream'], true))
                                        ->map(fn ($i) => $i->component?->name)->filter()->values()->all(),
                                ];
                            })->values();
                            $itemsSummary = $creations->map(function ($c) {
                                $line = "{$c['qty']}x {$c['bread']} · {$c['flavor']}";
                                return $c['toppings'] ? $line.' + '.implode(', ', $c['toppings']) : $line;
                            })->join(', ');
                        @endphp
                        <tr
                            data-grid-row
                            tabindex="-1"
                            aria-label="Order #{{ $order->order_number }}, {{ $order->customer_name }}"
                            @if ($order->public_status_url) data-open="{{ $order->public_status_url }}" @endif
                            class="hover:bg-surface-container/20 transition-colors"
                        >
                            {{-- ORDER NUMBER & DATE --}}
                            <td class="px-space-md py-space-sm align-top">
                                <span class="font-mono font-bold text-on-surface text-[13px] block">#{{ $order->order_number }}</span>
                                <span class="text-on-surface-variant text-[11px] block mt-0.5" title="{{ $order->created_at->format('d M Y H:i:s') }}">
                                    {{ $order->created_at->diffForHumans() }}
                                </span>
                            </td>

                            {{-- CUSTOMER INFO --}}
                            <td class="px-space-md py-space-sm align-top">
                                <p class="font-label-md text-label-md text-on-surface font-semibold">{{ $order->customer_name }}</p>
                                <a
                                    href="{{ $order->whatsapp_url }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 text-[12px] text-green-700 hover:text-green-800 font-medium mt-1 hover:underline"
                                    title="Hubungi pembeli via WhatsApp"
                                >
                                    <span class="material-symbols-outlined text-[15px] shrink-0 text-green-600">forum</span>
                                    <span>{{ $order->customer_phone }}</span>
                                </a>
                            </td>

                            {{-- ITEMS SUMMARY --}}
                            <td class="px-space-md py-space-sm align-top max-w-[280px]">
                                <div x-data="{ open: false }" data-order-items>
                                    <button
                                        type="button"
                                        @click.stop="open = !open"
                                        :aria-expanded="open"
                                        class="inline-flex items-center gap-1 text-[12px] font-semibold text-primary hover:underline"
                                    >
                                        <span class="material-symbols-outlined text-[16px] shrink-0 transition-transform" :class="open && 'rotate-180'">expand_more</span>
                                        <span x-text="open ? 'Tutup detail' : 'Lihat detail ({{ $creationsCount }} kreasi)'">Lihat detail ({{ $creationsCount }} kreasi)</span>
                                    </button>
                                    <p x-show="!open" class="text-on-surface line-clamp-2 leading-relaxed mt-1" title="{{ $itemsSummary }}">{{ $itemsSummary }}</p>
                                    <ol x-show="open" x-cloak class="mt-2 space-y-2" aria-label="Detail pesanan #{{ $order->order_number }}">
                                        @foreach ($creations as $c)
                                            <li class="rounded-lg bg-surface-container/40 px-2.5 py-2 text-[12px] leading-snug">
                                                <p class="font-semibold text-on-surface">{{ $loop->iteration }}. {{ $c['qty'] }}x sandwich</p>
                                                <p class="text-on-surface-variant">Roti: <span class="text-on-surface">{{ $c['bread'] }}</span></p>
                                                <p class="text-on-surface-variant">Es krim: <span class="text-on-surface">{{ $c['flavor'] }}</span></p>
                                                <p class="text-on-surface-variant">Topping: <span class="text-on-surface">{{ $c['toppings'] ? implode(', ', $c['toppings']) : 'tanpa topping' }}</span></p>
                                            </li>
                                        @endforeach
                                    </ol>
                                </div>
                            </td>

                            {{-- TOTAL PRICE --}}
                            <td class="px-space-md py-space-sm align-top text-right whitespace-nowrap">
                                <span class="font-label-md text-label-md font-bold text-primary tabular-nums">
                                    {{ $rupiah((float) $order->total_price) }}
                                </span>
                            </td>

                            {{-- STATUS BADGE --}}
                            <td class="px-space-md py-space-sm align-top text-center whitespace-nowrap">
                                @if ($order->isPending())
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-300">
                                        <span class="material-symbols-outlined text-[14px] shrink-0">hourglass_empty</span>
                                        Pending
                                    </span>
                                @elseif ($order->isConfirmed())
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-green-50 text-green-800 border border-green-300">
                                        <span class="material-symbols-outlined text-[14px] shrink-0">check_circle</span>
                                        Confirmed
                                    </span>
                                @elseif ($order->isPaid())
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-sky-50 text-sky-800 border border-sky-300">
                                        <span class="material-symbols-outlined text-[14px] shrink-0">payments</span>
                                        Paid
                                    </span>
                                @elseif ($order->isProcessing())
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-orange-50 text-orange-800 border border-orange-300" title="{{ $order->preparing_at ? 'Disiapkan '.$order->preparing_at->format('d M Y, H:i').' WIB' : '' }}">
                                        <span class="material-symbols-outlined text-[14px] shrink-0">bakery_dining</span>
                                        Disiapkan
                                    </span>
                                @elseif ($order->isReady())
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-300" title="{{ $order->ready_at ? 'Siap '.$order->ready_at->format('d M Y, H:i').' WIB' : '' }}">
                                        <span class="material-symbols-outlined text-[14px] shrink-0">inventory_2</span>
                                        Siap Diambil
                                    </span>
                                @elseif ($order->isCancelled())
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-700 border border-gray-300">
                                        <span class="material-symbols-outlined text-[14px] shrink-0">cancel</span>
                                        Cancelled
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-blue-50 text-blue-800 border border-blue-300">
                                        {{ ucfirst($order->status) }}
                                    </span>
                                @endif
                                @if ($order->pickup_on && ! $order->isCancelled())
                                    <span class="mt-1 block text-[11px] text-on-surface-variant">Ambil {{ $order->pickup_on->locale('id')->translatedFormat('D, j M') }}</span>
                                @endif
                            </td>

                            {{-- PUBLIC TRACKING LINK --}}
                            <td class="px-space-md py-space-sm align-top">
                                @if ($order->public_status_url)
                                    <div x-data="{ copied: false }" class="space-y-1">
                                        <div class="flex items-center gap-1">
                                            <input
                                                type="text"
                                                aria-label="Tautan tracking pesanan"
                                                readonly
                                                value="{{ $order->public_status_url }}"
                                                class="text-[11px] font-mono bg-surface-container px-2 py-1 rounded border border-outline-variant/40 w-36 truncate text-on-surface-variant select-all"
                                                @click="$el.select()"
                                            >
                                            <button
                                                type="button"
                                                @click="navigator.clipboard.writeText('{{ $order->public_status_url }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                                class="press-scale p-1 rounded bg-surface-container hover:bg-surface-container-high text-on-surface-variant transition-colors"
                                                :title="copied ? 'Tersalin!' : 'Salin tautan'"
                                            >
                                                <span x-show="!copied" class="material-symbols-outlined text-[16px] shrink-0">content_copy</span>
                                                <span x-show="copied" class="material-symbols-outlined text-[16px] text-green-600 shrink-0">check</span>
                                            </button>
                                            <a
                                                href="{{ $order->public_status_url }}"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="press-scale p-1 rounded bg-surface-container hover:bg-surface-container-high text-on-surface-variant transition-colors"
                                                title="Buka halaman tracking pembeli"
                                            >
                                                <span class="material-symbols-outlined text-[16px] shrink-0">open_in_new</span>
                                            </a>
                                        </div>
                                        <span x-show="copied" x-cloak class="text-[10px] text-green-700 font-bold block">
                                            Tautan berhasil disalin!
                                        </span>
                                        @if ($order->hasReview())
                                            <div class="flex items-center gap-1 text-[11px] text-amber-700 font-medium pt-0.5">
                                                <span class="material-symbols-outlined text-[14px] text-amber-500 shrink-0" style="font-variation-settings: 'FILL' 1;">star</span>
                                                <span>{{ $order->rating }}/5:</span>
                                                <span class="truncate max-w-[130px] italic text-on-surface-variant" title="{{ $order->review_text }}">“{{ $order->review_text }}”</span>
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-[11px] text-on-surface-variant italic flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[14px] shrink-0">schedule</span>
                                        Tersedia saat confirmed
                                    </span>
                                @endif
                            </td>

                            {{-- ACTIONS --}}
                            <td class="orders-sticky px-space-md py-space-sm align-top text-right whitespace-nowrap">
                                <div class="flex flex-col items-end gap-1.5">
                                    {{-- WA QUICK CHAT --}}
                                    <a
                                        href="{{ $order->whatsapp_url }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="press-scale inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-green-700 hover:bg-green-800 text-white font-label-md text-[11px] font-semibold shadow-sm transition-colors"
                                        title="Kirim pesan ke WhatsApp pembeli"
                                    >
                                        <span class="material-symbols-outlined text-[14px] shrink-0">forum</span>
                                        <span>Chat WA</span>
                                    </a>

                                    {{-- EDIT ORDER --}}
                                    @if ($editor::canEdit($order))
                                        <button
                                            type="button"
                                            data-edit="{{ json_encode($editor->editorPayload($order), JSON_UNESCAPED_UNICODE) }}"
                                            @click="$dispatch('order-edit', JSON.parse($el.dataset.edit))"
                                            class="press-scale inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-surface-container hover:bg-surface-container-high text-on-surface font-label-md text-[11px] font-semibold transition-colors"
                                            title="Ubah isi pesanan (roti, rasa, topping, jumlah)"
                                        >
                                            <span class="material-symbols-outlined text-[14px] shrink-0">edit</span>
                                            <span>Edit pesanan</span>
                                        </button>
                                    @endif
                                    @if ($order->revisions->isNotEmpty())
                                        <span class="font-label-md text-[11px] text-on-surface-variant">Diubah {{ $order->revisions->count() }}x</span>
                                    @endif

                                    {{-- CONFIRM BUTTON --}}
                                    @if ($order->isPending())
                                        <form method="POST" action="{{ route('staff.orders.confirm', $order) }}" data-confirm="Tautan tracking publik akan aktif untuk pesanan #{{ $order->order_number }}." data-confirm-title="Konfirmasi pesanan?" data-confirm-label="Ya, konfirmasi" data-cancel-label="Kembali">
                                            @csrf
                                            <button
                                                type="submit"
                                                class="press-scale inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-primary-container text-on-primary-container hover:opacity-95 font-label-md text-[11px] font-semibold shadow-sm transition-colors"
                                                title="Konfirmasi pesanan"
                                            >
                                                <span class="material-symbols-outlined text-[14px] shrink-0">check</span>
                                                <span>Confirm</span>
                                            </button>
                                        </form>
                                    @endif

                                    {{-- MARK PAID BUTTON --}}
                                    @if ($order->isConfirmed())
                                        <form method="POST" action="{{ route('staff.orders.paid', $order) }}" data-confirm="Pesanan #{{ $order->order_number }} ditandai sudah dibayar dan halaman tracking pembeli ikut berubah." data-confirm-title="Tandai sudah dibayar?" data-confirm-label="Ya, sudah dibayar" data-cancel-label="Kembali">
                                            @csrf
                                            <button
                                                type="submit"
                                                class="press-scale inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-sky-700 text-white hover:bg-sky-800 font-label-md text-[11px] font-semibold shadow-sm transition-colors"
                                                title="Tandai pesanan sudah dibayar"
                                            >
                                                <span class="material-symbols-outlined text-[14px] shrink-0">payments</span>
                                                <span>Sudah Dibayar</span>
                                            </button>
                                        </form>
                                    @endif

                                    {{-- MARK PREPARING BUTTON --}}
                                    @if ($order->isPaid())
                                        <form method="POST" action="{{ route('staff.orders.preparing', $order) }}" data-confirm="Pesanan #{{ $order->order_number }} ditandai sedang disiapkan dan tracking pembeli ikut berubah." data-confirm-title="Mulai siapkan pesanan?" data-confirm-label="Ya, disiapkan" data-cancel-label="Kembali">
                                            @csrf
                                            <button type="submit" class="press-scale inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-orange-600 text-white hover:bg-orange-700 font-label-md text-[11px] font-semibold shadow-sm transition-colors" title="Tandai pesanan sedang disiapkan">
                                                <span class="material-symbols-outlined text-[14px] shrink-0">bakery_dining</span>
                                                <span>Disiapkan</span>
                                            </button>
                                        </form>
                                    @endif

                                    {{-- MARK READY BUTTON --}}
                                    @if ($order->isProcessing())
                                        <form method="POST" action="{{ route('staff.orders.ready', $order) }}" data-confirm="Pesanan #{{ $order->order_number }} ditandai siap diambil di konter." data-confirm-title="Pesanan siap diambil?" data-confirm-label="Ya, siap diambil" data-cancel-label="Kembali">
                                            @csrf
                                            <button type="submit" class="press-scale inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-700 text-white hover:bg-emerald-800 font-label-md text-[11px] font-semibold shadow-sm transition-colors" title="Tandai pesanan siap diambil">
                                                <span class="material-symbols-outlined text-[14px] shrink-0">inventory_2</span>
                                                <span>Siap Diambil</span>
                                            </button>
                                        </form>
                                    @endif

                                    {{-- PICKUP DATE --}}
                                    @if (!$order->isCancelled())
                                        <form method="POST" action="{{ route('staff.orders.pickup-date', $order) }}" class="inline-flex items-center gap-1" title="Tanggal pengambilan, tampil di halaman tracking pembeli">
                                            @csrf
                                            <label for="pickup-{{ $order->id }}" class="sr-only">Tanggal pengambilan pesanan #{{ $order->order_number }}</label>
                                            <input
                                                id="pickup-{{ $order->id }}"
                                                type="date"
                                                name="pickup_on"
                                                value="{{ $order->pickup_on?->format('Y-m-d') }}"
                                                min="{{ now()->toDateString() }}"
                                                class="rounded-full border border-outline-variant bg-surface px-2 py-0.5 font-label-md text-[11px] text-on-surface"
                                            >
                                            <button type="submit" class="press-scale inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-surface-container hover:bg-surface-container-high text-on-surface font-label-md text-[11px] font-semibold transition-colors" title="Simpan tanggal pengambilan (kosongkan lalu simpan untuk menghapus)">
                                                <span class="material-symbols-outlined text-[14px] shrink-0">event</span>
                                                <span>Tgl Ambil</span>
                                            </button>
                                        </form>
                                    @endif

                                    {{-- CANCEL BUTTON --}}
                                    @if (!$order->isCancelled() && !$order->isReady())
                                        <form method="POST" action="{{ route('staff.orders.cancel', $order) }}" data-confirm="Pesanan #{{ $order->order_number }} akan dibatalkan dan stok bahan dipulihkan." data-confirm-title="Batalkan pesanan?" data-confirm-label="Ya, batalkan" data-cancel-label="Kembali" data-confirm-tone="danger">
                                            @csrf
                                            <button
                                                type="submit"
                                                class="press-scale inline-flex items-center gap-0.5 px-2 py-1 rounded-full bg-surface-container text-error hover:bg-error-container hover:text-on-error-container font-label-md text-[11px] font-medium transition-colors"
                                                title="Batalkan pesanan"
                                            >
                                                <span class="material-symbols-outlined text-[14px] shrink-0">close</span>
                                                <span>Batal</span>
                                            </button>
                                        </form>
                                    @endif

                                    {{-- DELETE BUTTON (admin only) --}}
                                    @if (auth()->user()->isAdmin())
                                        <form method="POST" action="{{ route('staff.orders.destroy', $order) }}" data-confirm="Pesanan #{{ $order->order_number }} akan dihapus permanen dari semua laporan. Stok bahan dipulihkan. Tidak bisa dibatalkan." data-confirm-title="Hapus pesanan?" data-confirm-label="Ya, hapus" data-cancel-label="Kembali" data-confirm-tone="danger">
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="press-scale inline-flex items-center gap-0.5 px-2 py-1 rounded-full bg-surface-container text-error hover:bg-error-container hover:text-on-error-container font-label-md text-[11px] font-medium transition-colors"
                                                title="Hapus pesanan permanen"
                                            >
                                                <span class="material-symbols-outlined text-[14px] shrink-0">delete</span>
                                                <span>Hapus</span>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-space-md py-space-xl text-center text-on-surface-variant">
                                <span class="material-symbols-outlined text-[40px] text-on-surface-variant/40 block mb-2 shrink-0">receipt_long</span>
                                <p class="font-body-md text-body-md font-semibold text-on-surface">Tidak ada pesanan ditemukan.</p>
                                <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5">
                                    @if ($search !== '')
                                        Tidak ada hasil yang cocok dengan pencarian "{{ $search }}".
                                    @else
                                        Belum ada pesanan dalam kategori status ini.
                                    @endif
                                </p>
                                @if ($search !== '' || $currentStatus !== 'all')
                                    <a href="{{ route('staff.orders.index') }}" class="mt-3 inline-flex items-center gap-1 px-3 py-1 rounded-full bg-surface-container text-primary font-label-md text-label-md hover:bg-surface-container-high transition-colors">
                                        <span class="material-symbols-outlined text-[16px] shrink-0">restart_alt</span>
                                        Reset Filter
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- PAGINATION --}}
    @if ($orders->hasPages())
        <div class="mt-space-md">
            {{ $orders->links() }}
        </div>
    @endif

@include('partials.ingredient-icons')
@include('staff.orders._edit-dialog')

@endsection
