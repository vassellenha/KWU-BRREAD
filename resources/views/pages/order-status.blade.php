@extends('layouts.app')

@section('title', 'Status Pesanan #' . $order->order_number)

@section('description', 'Track your Brread! order status and delivery updates.')

@section('content')

    @php
        $errors = $errors ?? new \Illuminate\Support\ViewErrorBag();
        $rupiah = fn (float $amount): string => 'Rp ' . number_format($amount, 0, ',', '.');

        $state = $order->isConfirmed() ? 'confirmed' : ($order->isPending() ? 'pending' : ($order->isCancelled() ? 'cancelled' : 'other'));
        $art = fn (string $name): string => asset('img/cutout/' . $name . '.webp');
        $first = $creations->first();

        $statusText = $order->isConfirmed() ? 'Pesanan Terkonfirmasi'
            : ($order->isPending() ? 'Menunggu Konfirmasi'
            : ($order->isCancelled() ? 'Pesanan Dibatalkan' : ucfirst($order->status)));

        $tracker = [
            'state' => $state,
            'total' => $creations->count(),
            'since' => $order->created_at->toIso8601String(),
        ];
    @endphp

    <div class="os-page">

        {{-- Icon sprite: drawn inline so the page needs no icon font. --}}
        <svg class="os-sprite" aria-hidden="true" focusable="false">
            <defs>
                <symbol id="osi-check" viewBox="0 0 24 24"><path d="M4.5 12.8l4.8 4.7L19.5 6.8"/></symbol>
                <symbol id="osi-x" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></symbol>
                <symbol id="osi-hourglass" viewBox="0 0 24 24"><path d="M6 3h12M6 21h12M8 3v4l4 5-4 5v4M16 3v4l-4 5 4 5v4"/></symbol>
                <symbol id="osi-cone" viewBox="0 0 24 24"><path d="M3.5 9.5V8a3 3 0 013-3h11a3 3 0 013 3v1.5zM3.5 15v1.5a3 3 0 003 3h11a3 3 0 003-3V15zM3.5 12.2h2l1.5 1.4 2-1.4 2 1.4 2-1.4 2 1.4 2-1.4h2.5"/></symbol>
                <symbol id="osi-star" viewBox="0 0 24 24"><path d="M12 2.8l2.9 6.2 6.8.8-5 4.6 1.4 6.7-6.1-3.4-6.1 3.4 1.4-6.7-5-4.6 6.8-.8z"/></symbol>
                <symbol id="osi-send" viewBox="0 0 24 24"><path d="M3 11.6L21 3.4l-8.2 17.6-2.4-7.2zM10.4 13.8L21 3.4"/></symbol>
                <symbol id="osi-print" viewBox="0 0 24 24"><path d="M7 8V3h10v5M7 17H4.5v-8h15v8H17M7 14h10v7H7z"/></symbol>
                <symbol id="osi-down" viewBox="0 0 24 24"><path d="M12 4v15M6 13l6 6 6-6"/></symbol>
                <symbol id="osi-info" viewBox="0 0 24 24"><path d="M12 11v6M12 7.2v.1"/><circle cx="12" cy="12" r="9"/></symbol>
                <symbol id="osi-bag" viewBox="0 0 24 24"><path d="M4 9l1.2-4.5h13.6L20 9M4 9h16v11H4zM9 13v1a3 3 0 006 0v-1"/></symbol>
                <symbol id="osi-plus" viewBox="0 0 24 24"><path d="M12 4v16M4 12h16"/></symbol>
                <symbol id="osi-camera" viewBox="0 0 24 24"><path d="M3.5 8.5a2 2 0 012-2h2.2L9.3 4h5.4l1.6 2.5h2.2a2 2 0 012 2V18a2 2 0 01-2 2h-13a2 2 0 01-2-2z"/><circle cx="12" cy="13" r="3.6"/></symbol>
                <symbol id="osi-eye" viewBox="0 0 24 24"><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/></symbol>
            </defs>
        </svg>

        <div class="os-screen">

            {{-- FLASH MESSAGES --}}
            @if (session('success'))
                <div class="os-flash">
                    <svg class="os-ico" aria-hidden="true"><use href="#osi-check"/></svg>
                    <p class="flex-1 m-0">{{ session('success') }}</p>
                </div>
            @endif
            @if (session('error'))
                <div class="os-flash os-flash--error">
                    <svg class="os-ico" aria-hidden="true"><use href="#osi-x"/></svg>
                    <p class="flex-1 m-0">{{ session('error') }}</p>
                </div>
            @endif

            {{-- Ticker tape: the cart's sign, scrolling slowly. --}}
            <div class="os-tape" aria-hidden="true">
                <div class="os-tape__track">
                    @for ($loop2 = 0; $loop2 < 2; $loop2++)
                        <span class="os-tape__run">
                            @foreach (['Dibikin saat dipesan', 'Singapore style ice cream sandwich', 'Dingin, manis, anti galau', $order->order_number] as $phrase)
                                <b>{{ $phrase }}</b><i></i>
                            @endforeach
                        </span>
                    @endfor
                </div>
            </div>

            {{-- THE TICKET: queue number, stamp card, and the sandwich on the stub --}}
            <section
                class="os-hero os-hero--{{ $state }}"
                x-data="orderTracker({{ \Illuminate\Support\Js::from($tracker) }})"
            >
                <div class="tk">
                    <i class="tk__notch tk__notch--t" aria-hidden="true"></i>
                    <i class="tk__notch tk__notch--b" aria-hidden="true"></i>

                    <div class="tk__main">
                        <div class="tk__kicker" aria-hidden="true">
                            <span>Konter Brread!</span>
                            <span>Tiket Pesanan</span>
                        </div>

                        <span class="os-stamp os-stamp--{{ $state }}">{{ $statusText }}</span>

                        <h1 class="tk__no"><span class="tk__no-label">Pesanan</span> #{{ $order->order_number }}</h1>

                        <dl class="tk__meta">
                            <div>
                                <dt>Dipesan</dt><i class="tk__lead" aria-hidden="true"></i>
                                <dd>{{ $order->created_at->format('d M Y, H:i') }} WIB</dd>
                            </div>
                            @if ($order->confirmed_at)
                                <div>
                                    <dt>Dikonfirmasi</dt><i class="tk__lead" aria-hidden="true"></i>
                                    <dd>{{ $order->confirmed_at->format('d M Y, H:i') }} WIB</dd>
                                </div>
                            @endif
                        </dl>

                        <div class="tk__actions">
                            <button type="button" onclick="window.print()" class="os-btn os-btn--ghost os-btn--sm" title="Cetak struk belanja">
                                <svg class="os-ico" aria-hidden="true"><use href="#osi-print"/></svg>
                                <span>Cetak Struk</span>
                            </button>
                        </div>

                        {{-- STAMP CARD --}}
                        <div class="os-track">
                            <p class="os-track__title">Kartu stempel</p>
                            <ol class="os-track__steps" aria-label="Progres pesanan">
                                <li class="os-step is-done" style="--i: 0">
                                    <span class="os-node"><svg class="os-ico" aria-hidden="true"><use href="#osi-check"/></svg></span>
                                    <span class="os-step__label">1. Masuk</span>
                                    <span class="os-step__time">{{ $order->created_at->format('H:i') }} WIB</span>
                                </li>

                                <li class="os-step {{ $order->isConfirmed() ? 'is-done' : ($order->isCancelled() ? 'is-stopped' : 'is-current') }}" style="--i: 1">
                                    <span class="os-node">
                                        <svg class="os-ico" aria-hidden="true">
                                            <use href="#{{ $order->isConfirmed() ? 'osi-check' : ($order->isCancelled() ? 'osi-x' : 'osi-hourglass') }}"/>
                                        </svg>
                                    </span>
                                    <span class="os-step__label">2. Dikonfirmasi</span>
                                    <span class="os-step__time">
                                        {{ $order->confirmed_at ? $order->confirmed_at->format('H:i') . ' WIB' : ($order->isCancelled() ? 'Dibatalkan' : 'Menunggu') }}
                                    </span>
                                </li>

                                <li class="os-step {{ $order->isConfirmed() ? 'is-ready' : 'is-todo' }}" style="--i: 2">
                                    <span class="os-node"><svg class="os-ico" aria-hidden="true"><use href="#osi-cone"/></svg></span>
                                    <span class="os-step__label">3. Siap Nikmati</span>
                                    <span class="os-step__time">{{ $order->isConfirmed() ? 'Di konter toko' : '-' }}</span>
                                </li>
                            </ol>
                        </div>

                        {{-- LIVE STATUS LINE --}}
                        <div class="os-note" role="status">
                            <span class="os-note__icon" aria-hidden="true">
                                <svg class="os-ico"><use href="#{{ $order->isConfirmed() ? 'osi-star' : ($order->isCancelled() ? 'osi-info' : 'osi-hourglass') }}"/></svg>
                            </span>
                            <p>
                                @if ($order->isConfirmed())
                                    <b>Pesananmu udah dikonfirmasi!</b>
                                    Sandwich-nya disiapkan dan bisa diambil di konter toko.
                                @elseif ($order->isCancelled())
                                    <b>Pesanan ini dibatalkan.</b>
                                    Stok bahannya sudah dikembalikan ke gudang.
                                @else
                                    <b>Admin lagi ngecek pesananmu</b>
                                    <span class="os-dots" aria-hidden="true"><span></span><span></span><span></span></span>
                                    <span class="block">Sudah menunggu <b x-text="waited">beberapa saat</b>. Halaman ini bisa kamu muat ulang kapan saja.</span>
                                @endif
                            </p>
                        </div>

                        {{-- CUSTOMER --}}
                        <dl class="os-who">
                            <div>
                                <dt>Nama Pembeli</dt>
                                <dd>{{ $order->customer_name }}</dd>
                            </div>
                            <div>
                                <dt>Nomor WhatsApp</dt>
                                <dd>{{ $order->customer_phone }}</dd>
                            </div>
                        </dl>
                    </div>

                    {{-- THE STUB: the sandwich builds itself on a sticker disc --}}
                    <div class="tk__stub">
                        <span class="os-ribbon" aria-hidden="true">Fresh, bukan stok lama</span>
                        <div
                            class="os-stage is-intro"
                            x-ref="stage"
                            tabindex="0"
                            @pointermove="tilt($event)"
                            @pointerleave="untilt()"
                            @click="poke($event)"
                            @keydown.enter.prevent="poke($event)"
                            @keydown.space.prevent="poke($event)"
                            role="img"
                            aria-label="Animasi sandwich es krim pesananmu"
                        >
                            <div class="os-disc"></div>
                            <i class="os-spark" aria-hidden="true"></i>
                            <i class="os-spark" aria-hidden="true"></i>
                            <i class="os-spark" aria-hidden="true"></i>

                            @foreach ($creations as $idx => $creation)
                                <div class="os-sandwich" x-show="active === {{ $idx }}" @if ($idx > 0) style="display: none" @endif>
                                    <span class="os-shadow" aria-hidden="true"></span>
                                    <div class="os-bob">
                                        <div class="os-stack">
                                            @include('partials.order-folded-sandwich', ['creation' => $creation, 'uid' => 'fs'.$idx, 'art' => $art])
                                        </div>
                                    </div>
                                    @if ($state !== 'cancelled')
                                        @for ($ray = 0; $ray < 10; $ray++)
                                            <i class="os-burst" aria-hidden="true" style="--a: {{ $ray * 36 }}deg"></i>
                                        @endfor
                                    @endif
                                </div>
                            @endforeach

                            @if ($state !== 'cancelled')
                                <span class="os-bubble" x-ref="bubble" aria-hidden="true"></span>
                            @endif

                            @if ($state === 'cancelled')
                                <span class="os-puddle" aria-hidden="true"></span>
                                <i class="os-melt-drop" style="--l: 38%; --d: 2.6s" aria-hidden="true"></i>
                                <i class="os-melt-drop" style="--l: 52%; --d: 3.4s" aria-hidden="true"></i>
                                <i class="os-melt-drop" style="--l: 63%; --d: 4.1s" aria-hidden="true"></i>
                            @endif

                            @if ($state === 'pending' || $state === 'other')
                                <span class="os-wait" aria-hidden="true"><svg class="os-ico"><use href="#osi-hourglass"/></svg></span>
                            @endif
                        </div>

                        @if ($creations->count() > 1)
                            <div class="os-chips" role="tablist" aria-label="Pilih kreasi">
                                @foreach ($creations as $idx => $creation)
                                    <button
                                        type="button"
                                        class="os-chip"
                                        :class="{ 'is-active': active === {{ $idx }} }"
                                        @click.stop="pick({{ $idx }})"
                                    >Kreasi #{{ $idx + 1 }}</button>
                                @endforeach
                            </div>
                        @endif

                        @if ($state === 'pending' || $state === 'other')
                            <div class="os-clock-wrap" aria-hidden="true">
                                <span>Sudah antri</span>
                                <div class="os-clock">
                                    <template x-for="(ch, i) in clock" :key="i + ch">
                                        <b :class="{ 'is-colon': ch === ':' }" x-text="ch"></b>
                                    </template>
                                </div>
                            </div>
                        @elseif ($state === 'confirmed')
                            <p class="os-hint" aria-hidden="true">
                                <svg class="os-ico"><use href="#osi-down"/></svg>
                                <span>Colek sandwich-nya!</span>
                            </p>
                        @endif
                    </div>
                </div>
            </section>

            <div class="os-grid">

                {{-- THERMAL RECEIPT: prints itself out of the slot when it scrolls into view --}}
                <div class="os-printer" x-data="printOnView">
                    <div class="os-slot" aria-hidden="true"></div>
                    <div class="os-feed">
                        <article class="os-rcpt">
                            <header class="os-rcpt__head">
                                <img class="os-rcpt__logo" src="{{ asset('logo.png') }}" alt="Brread!" width="208" height="120">
                                <p class="os-rcpt__tag">Singapore Ice Cream Sandwich</p>
                            </header>

                            <h2 class="os-rcpt__title">Rincian Belanja</h2>
                            <p class="os-rcpt__count">{{ $creations->count() }} Kreasi Sandwich</p>

                            <hr>

                            @foreach ($creations as $idx => $creation)
                                <div class="os-rcpt__item">
                                    <span class="os-rcpt__item-head">Kreasi #{{ $idx + 1 }}</span>
                                    @foreach ($creation['lines'] as $line)
                                        <div class="os-rcpt__row">
                                            <span class="os-rcpt__row-label">{{ $line['label'] }}</span>
                                            <span class="os-rcpt__row-name">{{ $line['name'] }}</span>
                                            <i class="os-rcpt__lead" aria-hidden="true"></i>
                                            <span class="os-rcpt__row-price">{{ $rupiah($line['price']) }}</span>
                                        </div>
                                    @endforeach
                                    @if ($creation['toppings']->isEmpty())
                                        <p class="os-rcpt__none">Tanpa topping tambahan</p>
                                    @endif
                                    <div class="os-rcpt__row os-rcpt__row--sum">
                                        <span class="os-rcpt__row-label">{{ $creation['quantity'] }} pcs &times; {{ $rupiah($creation['unit_price']) }}</span>
                                        <i class="os-rcpt__lead" aria-hidden="true"></i>
                                        <span class="os-rcpt__row-price">{{ $rupiah($creation['subtotal']) }}</span>
                                    </div>
                                </div>
                            @endforeach

                            <hr>

                            <div class="os-rcpt__row">
                                <span class="os-rcpt__row-name">Subtotal Produk</span>
                                <i class="os-rcpt__lead" aria-hidden="true"></i>
                                <span class="os-rcpt__row-price">{{ $rupiah((float) $order->total_price) }}</span>
                            </div>
                            <div class="os-rcpt__row">
                                <span class="os-rcpt__row-name">Pajak &amp; Layanan</span>
                                <i class="os-rcpt__lead" aria-hidden="true"></i>
                                <span class="os-rcpt__row-price">Rp 0 <span class="os-rcpt__free">(Gratis)</span></span>
                            </div>

                            <div class="os-rcpt__total">
                                <span>Total Pembayaran</span>
                                <span
                                    class="os-rcpt__amount"
                                    x-data="countUp({{ (float) $order->total_price }})"
                                    x-text="shown"
                                >{{ $rupiah((float) $order->total_price) }}</span>
                            </div>

                            <div class="os-rcpt__code" aria-hidden="true">
                                {!! \App\Support\Code39::svg($order->order_number, 56, '#2a1a14') !!}
                                <p class="os-code">{{ \App\Support\Code39::clean($order->order_number) }}</p>
                            </div>

                            <p class="os-rcpt__thanks">Makasih udah jajan!</p>
                            <p class="os-rcpt__small">Simpan struk ini sebagai bukti pesanan.</p>
                        </article>
                    </div>
                </div>

                <div class="os-side">

                    {{-- REVIEW SLIP --}}
                    <div class="os-slip">
                        <span class="os-slip__tab" aria-hidden="true">Slip ulasan</span>
                        <h2>Ulasan &amp; Rating Pesanan</h2>
                        <p class="os-slip__sub">Bagikan pengalaman manis Anda menikmati sandwich es krim Brread!</p>

                        @if ($order->hasReview())
                            {{-- SUBMITTED REVIEW --}}
                            <div class="os-done">
                                <div class="os-done__top">
                                    <div class="os-done__stars" role="img" aria-label="{{ $order->rating }} dari 5 bintang">
                                        @for ($star = 1; $star <= 5; $star++)
                                            <svg class="os-ico os-done__star {{ $star <= $order->rating ? 'is-on' : '' }}" style="--i: {{ $star }}" aria-hidden="true"><use href="#osi-star"/></svg>
                                        @endfor
                                        <span class="os-done__score">{{ $order->rating }} / 5 Bintang</span>
                                    </div>
                                    <span class="os-done__when">Diulas {{ $order->reviewed_at?->diffForHumans() }}</span>
                                </div>
                                @if ($order->review_text)
                                    <p class="os-done__quote">“{{ $order->review_text }}”</p>
                                @endif
                                @if ($order->reviewPhoto)
                                    <figure class="os-done__photo">
                                        <img src="{{ $order->reviewPhoto->url() }}" width="{{ $order->reviewPhoto->width }}" height="{{ $order->reviewPhoto->height }}" alt="Foto dari pelanggan untuk pesanan ini" loading="lazy">
                                    </figure>
                                @endif
                                <p class="os-done__ok">
                                    <svg class="os-ico" aria-hidden="true"><use href="#osi-check"/></svg>
                                    <span>Ulasan Anda telah tersimpan dan membantu kami terus berkembang!</span>
                                </p>
                            </div>
                        @elseif ($viewOnly)
                            {{-- STAFF: look, never rate --}}
                            <div class="os-viewonly" role="note">
                                <svg class="os-ico" aria-hidden="true"><use href="#osi-eye"/></svg>
                                <div>
                                    <p class="os-viewonly__title">Mode lihat saja</p>
                                    <p class="os-viewonly__text">Anda masuk sebagai {{ auth()->user()->name ?? 'staf' }}. Rating dan ulasan hanya bisa diisi oleh pelanggan lewat link ini, supaya tidak ada yang bisa merekayasa ulasan.</p>
                                </div>
                            </div>
                        @else
                            {{-- REVIEW FORM --}}
                            <form
                                method="POST"
                                enctype="multipart/form-data"
                                action="{{ route('order.review', ['token' => $order->confirmation_token]) }}"
                                x-data="reviewForm({{ \Illuminate\Support\Js::from(['rating' => (int) old('rating', 5), 'text' => old('review_text', '')]) }})"
                                @submit.prevent="submit($event)"
                            >
                                @csrf

                                <div>
                                    <span class="os-label">Berapa rating kepuasan Anda?</span>
                                    <input type="hidden" name="rating" :value="rating">
                                    <div class="os-stars" @mouseleave="hover = 0">
                                        <div class="os-stars__row" role="group" aria-label="Rating bintang">
                                            <template x-for="i in 5" :key="i">
                                                <button
                                                    type="button"
                                                    class="os-star"
                                                    :class="{ 'is-on': i <= shown, 'is-pop': popped === i }"
                                                    @click="setRating(i)"
                                                    @mouseenter="hover = i"
                                                    :aria-label="i + ' Bintang'"
                                                    :aria-pressed="i === rating"
                                                >
                                                    <svg class="os-ico" aria-hidden="true"><use href="#osi-star"/></svg>
                                                    <template x-for="n in 8" :key="n">
                                                        <i class="os-star__spark" :style="spark(n)"></i>
                                                    </template>
                                                </button>
                                            </template>
                                        </div>
                                        <span class="os-mood" :class="{ 'is-bump': bump }" x-text="labels[shown]">Luar Biasa Sempurna!</span>
                                    </div>
                                    @error('rating')
                                        <p class="os-error">{{ $message }}</p>
                                    @enderror
                                    <p x-show="errors.rating" x-text="errors.rating" x-cloak class="os-error"></p>
                                </div>

                                <div class="os-field">
                                    <label for="review_text" class="os-label">
                                        <span>Ulasan / Komentar <small x-text="photo ? '(wajib untuk foto)' : '(Opsional)'">(Opsional)</small></span>
                                        <small><span x-text="text.length">0</span>/1000</small>
                                    </label>
                                    <textarea
                                        id="review_text"
                                        name="review_text"
                                        rows="4"
                                        maxlength="1000"
                                        x-model="text"
                                        placeholder="Bagaimana perpaduan rasa es krim dan rotinya? Apakah manisnya pas?"
                                        class="os-textarea"
                                    >{{ old('review_text') }}</textarea>
                                    @error('review_text')
                                        <p class="os-error">{{ $message }}</p>
                                    @enderror
                                    <p x-show="errors.review_text" x-text="errors.review_text" x-cloak class="os-error"></p>
                                </div>

                                {{-- The photo: optional, but it never goes out without a few words (see needsWords). --}}
                                <div class="os-field">
                                    <span class="os-label">
                                        <span>Foto Sandwich <small>(Opsional)</small></span>
                                        <small>JPG, PNG, WebP</small>
                                    </span>
                                    <input x-ref="photo" id="review_photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="pickPhoto($event)">
                                    <label for="review_photo" class="os-photo" :class="{ 'has-photo': photo }" x-show="!photo">
                                        <svg class="os-ico" aria-hidden="true"><use href="#osi-camera"/></svg>
                                        <span class="os-photo__title">Tambah foto sandwich-mu</span>
                                        <span class="os-photo__hint">Foto terbaik masuk ke "Made by our customers" di halaman depan. Wajib ditemani ulasan.</span>
                                    </label>
                                    <figure class="os-photo-preview" x-show="photo" x-cloak>
                                        <img :src="photo" alt="Pratinjau foto ulasan">
                                        <button type="button" class="os-photo-preview__x" @click="clearPhoto()" aria-label="Hapus foto">
                                            <svg class="os-ico" aria-hidden="true"><use href="#osi-x"/></svg>
                                        </button>
                                    </figure>
                                    @error('photo')
                                        <p class="os-error">{{ $message }}</p>
                                    @enderror
                                    <p x-show="errors.photo" x-text="errors.photo" x-cloak class="os-error"></p>
                                    <p x-show="needsWords" x-cloak class="os-need" role="status">Foto harus disertai ulasan minimal 10 karakter. Tulis sedikit tentang sandwich-nya dulu ya.</p>
                                </div>

                                <div class="os-slip__actions">
                                    <button type="submit" :disabled="sending || needsWords" :aria-disabled="needsWords" class="os-btn os-send">
                                        <svg class="os-ico" aria-hidden="true"><use href="#osi-send"/></svg>
                                        <span>Kirim Ulasan</span>
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>

                    {{-- ACTIONS --}}
                    <div class="os-cta">
                        <a href="{{ route('build') }}" class="os-btn os-btn--butter">
                            <svg class="os-ico" aria-hidden="true"><use href="#osi-plus"/></svg>
                            <span>Buat Sandwich Baru di Studio</span>
                        </a>
                        <a href="{{ url('/') }}" class="os-btn os-btn--ghost">
                            <svg class="os-ico" aria-hidden="true"><use href="#osi-bag"/></svg>
                            <span>Kembali ke Beranda</span>
                        </a>
                    </div>
                </div>
            </div>

        </div>

        {{-- PRINT-ONLY RECEIPT --}}
        @include('partials.order-print-receipt')
    </div>

    {{-- THANK-YOU POPUP: sits outside the animated cards so position: fixed covers the viewport --}}
    <div
        class="os-screen"
        style="padding: 0; max-width: none"
        x-data="reviewPopup({{ \Illuminate\Support\Js::from([
            'open' => (bool) (session('success') && $order->hasReview()),
            'rating' => (int) ($order->rating ?? 5),
            'text' => $order->review_text ?? '',
        ]) }})"
        x-on:review:sending.window="onSending()"
        x-on:review:done.window="onDone($event)"
        x-on:review:failed.window="onFailed($event)"
        x-on:review:close.window="dismiss()"
        x-on:keydown.escape.window="open && close()"
    >
        <div class="os-pop os-page-scope" x-show="open" x-cloak role="dialog" aria-modal="true" aria-labelledby="os-pop-title">
            <div class="os-pop__backdrop" @click="close()"></div>

            <div class="os-pop__card" x-show="open">
                <div class="os-pop__body">

                {{-- SENDING --}}
                <template x-if="phase === 'sending'">
                    <div class="os-sendbox">
                        <div class="os-send-anim" aria-hidden="true">
                            <div class="os-mini">
                                @include('partials.order-folded-sandwich', ['creation' => $first, 'uid' => 'fsmini', 'art' => $art, 'still' => true])
                            </div>
                            <i class="os-fly" style="--c: #f48fab; --x: -50px; --r: -60deg; --d: 0s"></i>
                            <i class="os-fly" style="--c: #ffd166; --x: 10px; --r: 40deg; --d: 0.35s"></i>
                            <i class="os-fly" style="--c: #d94f73; --x: 60px; --r: 80deg; --d: 0.7s"></i>
                        </div>
                        <h2 id="os-pop-title" class="os-pop__title" style="animation-delay: 0s">Mengirim ulasan manismu</h2>
                        <p class="os-pop__sub os-dots" style="animation-delay: 0.1s">Sebentar ya<span></span><span></span><span></span></p>
                        <div class="os-bar" aria-hidden="true"></div>
                    </div>
                </template>

                {{-- DONE --}}
                <template x-if="phase === 'done'">
                    <div>
                        <button type="button" class="os-pop__close" @click="close()" aria-label="Tutup">
                            <svg class="os-ico" aria-hidden="true"><use href="#osi-x"/></svg>
                        </button>

                        <div class="os-pop__stamp">
                            <i class="os-pop__ring" aria-hidden="true"></i>
                            <i class="os-pop__ring" aria-hidden="true"></i>
                            <p class="os-pop__word" x-text="stampWord"></p>
                        </div>

                        <h2 id="os-pop-title" class="os-pop__title" x-text="headline"></h2>
                        <p class="os-pop__sub" x-text="subline"></p>

                        <div class="os-pop__stars" role="img" :aria-label="rating + ' dari 5 bintang'">
                            <template x-for="i in 5" :key="i">
                                <svg class="os-ico os-pop__star" :class="{ 'is-on': i <= rating }" :style="'--i: ' + i" aria-hidden="true"><use href="#osi-star"/></svg>
                            </template>
                        </div>

                        <p class="os-pop__quote" x-show="text" x-text="'“' + text + '”'"></p>

                        <div class="os-pop__actions">
                            <button type="button" x-ref="primary" @click="close()" class="os-btn">
                                <svg class="os-ico" aria-hidden="true"><use href="#osi-check"/></svg>
                                <span>Lihat Ulasanku</span>
                            </button>
                            <a href="{{ route('build') }}" class="os-btn os-btn--ghost">
                                <svg class="os-ico" aria-hidden="true"><use href="#osi-plus"/></svg>
                                <span>Buat Sandwich Baru</span>
                            </a>
                        </div>
                    </div>
                </template>

                {{-- ERROR --}}
                <template x-if="phase === 'error'">
                    <div class="py-2">
                        <div class="os-pop__fail" aria-hidden="true"><svg class="os-ico"><use href="#osi-x"/></svg></div>
                        <h2 id="os-pop-title" class="os-pop__title" style="animation-delay: 0.9s">Ulasan belum terkirim</h2>
                        <p class="os-pop__sub" style="animation-delay: 1s" x-text="message"></p>
                        <div class="os-pop__actions">
                            <button type="button" @click="close()" class="os-btn">
                                <span>Oke, muat ulang</span>
                            </button>
                        </div>
                    </div>
                </template>

                </div>
            </div>
        </div>
    </div>

@endsection
