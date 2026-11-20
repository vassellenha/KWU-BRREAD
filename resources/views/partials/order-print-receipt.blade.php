{{-- Print-only receipt. Hidden on screen; the @media print rules in order-status.css swap it in for the page. --}}
@php
    $stampClass = $order->isConfirmed() ? 'confirmed' : ($order->isCancelled() ? 'cancelled' : 'pending');
    $stampText = $order->isConfirmed() ? 'Terkonfirmasi' : ($order->isCancelled() ? 'Dibatalkan' : 'Menunggu');
@endphp
<section class="os-print" aria-hidden="true">
    <div class="rcpt">
        <div class="rcpt__body">
            <div class="rcpt__brand">
                <img class="rcpt__logo" src="{{ asset('logo.png') }}" alt="">
                <p class="rcpt__tag">Singapore Ice Cream Sandwich</p>
                <p class="rcpt__addr">{{ \App\Support\ShopSettings::get()['address_lines'][0] ?? \App\Support\ShopSettings::get()['address_short'] }}</p>
            </div>

            <span class="rcpt__stamp rcpt__stamp--{{ $stampClass }}">{{ $stampText }}</span>

            <dl class="rcpt__meta">
                <dt>No. Pesanan</dt>
                <dd>#{{ $order->order_number }}</dd>
                <dt>Dipesan</dt>
                <dd>{{ $order->created_at->format('d M Y, H:i') }} WIB</dd>
                @if ($order->confirmed_at)
                    <dt>Dikonfirmasi</dt>
                    <dd>{{ $order->confirmed_at->format('d M Y, H:i') }} WIB</dd>
                @endif
                <dt>Pembeli</dt>
                <dd>{{ $order->customer_name }}</dd>
                <dt>WhatsApp</dt>
                <dd>{{ $order->customer_phone }}</dd>
            </dl>

            <hr class="rcpt__rule">

            <p class="rcpt__sec">Pesanan Kamu</p>

            @foreach ($creations as $idx => $creation)
                <div class="rcpt__item">
                    <span class="rcpt__item-head">Kreasi #{{ $idx + 1 }}</span>
                    @foreach ($creation['lines'] as $line)
                        <div class="rcpt__row">
                            <span class="rcpt__row-label">{{ $line['label'] }}</span>
                            <span class="rcpt__row-name">{{ $line['name'] }}</span>
                            <span class="rcpt__lead"></span>
                            <span class="rcpt__row-price">{{ $rupiah($line['price']) }}</span>
                        </div>
                    @endforeach
                    <div class="rcpt__row rcpt__row--sum">
                        <span class="rcpt__row-label">{{ $creation['quantity'] }} pcs &times; {{ $rupiah($creation['unit_price']) }}</span>
                        <span class="rcpt__lead"></span>
                        <span class="rcpt__row-price">{{ $rupiah($creation['subtotal']) }}</span>
                    </div>
                </div>
            @endforeach

            <hr class="rcpt__rule">

            <div class="rcpt__calc">
                <div class="rcpt__row">
                    <span class="rcpt__row-name">Subtotal Produk</span>
                    <span class="rcpt__lead"></span>
                    <span class="rcpt__row-price">{{ $rupiah((float) $order->total_price) }}</span>
                </div>
                <div class="rcpt__row">
                    <span class="rcpt__row-name">Pajak &amp; Layanan</span>
                    <span class="rcpt__lead"></span>
                    <span class="rcpt__row-price">Rp 0</span>
                </div>
            </div>

            <div class="rcpt__total">
                <span>Total</span>
                <span>{{ $rupiah((float) $order->total_price) }}</span>
            </div>

            <div class="rcpt__code">
                {!! \App\Support\Code39::svg($order->order_number, 56) !!}
                <p class="os-code">{{ \App\Support\Code39::clean($order->order_number) }}</p>
            </div>

            @if ($order->hasReview())
                <p class="rcpt__stars">
                    Rating kamu: {{ str_repeat('★', (int) $order->rating) }}{{ str_repeat('☆', 5 - (int) $order->rating) }}
                </p>
            @endif

            <p class="rcpt__thanks">Makasih udah jajan di Brread!</p>
            <p class="rcpt__small">
                Simpan struk ini sebagai bukti pesanan.<br>
                Dicetak {{ now()->format('d M Y, H:i') }} WIB
            </p>
            <p class="rcpt__ig">&#64;{{ \App\Support\ContactInfo::get()['instagram'] }}</p>
            <p class="rcpt__cut" aria-hidden="true">- - - potong di sini - - -</p>
        </div>
    </div>
</section>
