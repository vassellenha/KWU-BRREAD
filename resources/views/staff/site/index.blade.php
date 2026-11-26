@extends('layouts.staff')

@section('title', 'Website')

@section('content')

    @php
        $field = 'w-full rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-md text-body-md text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:ring-2 focus:ring-primary';
        $label = 'block font-label-md text-label-md text-on-surface-variant uppercase tracking-wide mb-1';
    @endphp

    <x-staff.page-header icon="storefront" title="Website">
        Everything customers read about the shop: taking orders, contact, address, hours, promises and their reviews.
    </x-staff.page-header>

    {{-- CONTACT PERSON --}}
    <section class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 overflow-hidden mb-space-lg">
        <div class="p-space-md border-b border-outline-variant/30">
            <h2 class="font-title-md text-title-md text-on-surface">Contact person</h2>
            <p class="font-body-sm text-body-sm text-on-surface-variant">Shown on the Contact page, under the call button and on the printed receipt. Saved values go live at once.</p>
        </div>
        <form method="POST" action="{{ route('staff.site.contact') }}" class="p-space-md grid grid-cols-1 md:grid-cols-2 gap-space-md">
            @csrf
            @method('PUT')
            <div>
                <label for="contact_name" class="{{ $label }}">Contact person (optional)</label>
                <input id="contact_name" name="contact_name" type="text" maxlength="60" autocomplete="off" value="{{ old('contact_name', $stored['contact_name'] ?? '') }}" placeholder="Leave empty to show the counter only" class="{{ $field }}">
            </div>
            <div>
                <label for="contact_phone" class="{{ $label }}">Phone (call)</label>
                <input id="contact_phone" name="contact_phone" type="tel" inputmode="tel" required value="{{ old('contact_phone', $contact['phone']) }}" placeholder="+62 21 7250 1234" class="{{ $field }}">
            </div>
            <div>
                <label for="contact_whatsapp" class="{{ $label }}">WhatsApp (optional)</label>
                <input id="contact_whatsapp" name="contact_whatsapp" type="text" inputmode="tel" value="{{ old('contact_whatsapp', $contact['whatsapp']) }}" placeholder="Atmint Amba: 0821 3601 9367 (ceria), Atmint Sandi: 0811 6362 805 (formal)" class="{{ $field }}">
                <p class="mt-1 text-[12px] text-on-surface-variant">Boleh sampai 5 admin, pisahkan dengan koma. Format: Nama: nomor (gaya). Gaya pesan WhatsApp-nya: ceria (imut, pakai emotikon), formal (korporat) atau kuno (bahasa Inggris zaman dulu, thee dan thy); kosongkan untuk ceria. Kalau lebih dari satu, pembeli memilih sendiri mau chat siapa.</p>
            </div>
            <div>
                <label for="instagram_handle" class="{{ $label }}">Instagram</label>
                <input id="instagram_handle" name="instagram_handle" type="text" required maxlength="31" autocapitalize="none" autocorrect="off" value="{{ old('instagram_handle', $contact['instagram']) }}" placeholder="brread.official" class="{{ $field }}">
            </div>
            <div class="md:col-span-2 flex flex-wrap items-center justify-between gap-space-sm">
                <a href="{{ route('contact') }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 font-label-lg text-label-lg text-primary underline underline-offset-4 decoration-dotted">
                    <span class="material-symbols-outlined text-[18px]">open_in_new</span>See the Contact page
                </a>
                <button type="submit" class="press-scale inline-flex items-center gap-1.5 px-space-lg py-space-xs rounded-full bg-primary-container text-on-primary-container font-label-lg text-label-lg">
                    <span class="material-symbols-outlined text-[18px]">check</span>Save contact
                </button>
            </div>
        </form>
    </section>

    @php
        $area = 'w-full rounded-2xl border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-md text-body-md text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:ring-2 focus:ring-primary';
        $save = 'press-scale inline-flex items-center gap-1.5 px-space-lg py-space-xs rounded-full bg-primary-container text-on-primary-container font-label-lg text-label-lg';
        $toggle = 'h-5 w-5 rounded border-outline-variant text-primary focus:ring-primary';
    @endphp

    {{-- ORDERS: the one switch to pause the shop --}}
    <section class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 overflow-hidden mb-space-lg">
        <div class="p-space-md border-b border-outline-variant/30 flex flex-wrap items-center justify-between gap-space-sm">
            <div>
                <h2 class="font-title-md text-title-md text-on-surface">Taking orders</h2>
                <p class="font-body-sm text-body-sm text-on-surface-variant">Pause checkout when the counter cannot make orders (sold out, a holiday, a broken freezer). The builder still works, but customers cannot place an order and see your message instead.</p>
            </div>
            <span @class(['shrink-0 inline-flex items-center gap-1 rounded-full px-3 py-1 font-label-md text-label-md', 'bg-[#e3f3e6] text-[#1e5a2b]' => $shop['orders_open'], 'bg-error-container text-on-error-container' => ! $shop['orders_open']])>
                <span class="material-symbols-outlined text-[16px]">{{ $shop['orders_open'] ? 'check_circle' : 'block' }}</span>{{ $shop['orders_open'] ? 'Open for orders' : 'Orders paused' }}
            </span>
        </div>
        <form method="POST" action="{{ route('staff.site.shop', 'orders') }}" class="p-space-md grid grid-cols-1 gap-space-md">
            @csrf
            @method('PUT')
            <label class="inline-flex items-center gap-space-sm font-label-lg text-label-lg text-on-surface">
                <input type="checkbox" name="orders_open" value="1" class="{{ $toggle }}" @checked(old('orders_open', $shop['orders_open']))>
                Customers can place orders
            </label>
            <div>
                <label for="orders_paused_message" class="{{ $label }}">Message while paused</label>
                <input id="orders_paused_message" name="orders_paused_message" type="text" maxlength="160" value="{{ old('orders_paused_message', $shop['orders_paused_message']) }}" class="{{ $field }}">
            </div>
            <div class="flex justify-end"><button type="submit" class="{{ $save }}"><span class="material-symbols-outlined text-[18px]">check</span>Save</button></div>
        </form>
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-space-lg mb-space-lg">
        {{-- LOCATION --}}
        <section class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 overflow-hidden">
            <div class="p-space-md border-b border-outline-variant/30">
                <h2 class="font-title-md text-title-md text-on-surface">Where the counter is</h2>
                <p class="font-body-sm text-body-sm text-on-surface-variant">Used on Contact (with the map and its links), About, the footer and the receipt.</p>
            </div>
            <form method="POST" action="{{ route('staff.site.shop', 'location') }}" class="p-space-md grid grid-cols-1 gap-space-md">
                @csrf
                @method('PUT')
                <div>
                    <label for="address_short" class="{{ $label }}">Short name</label>
                    <input id="address_short" name="address_short" type="text" required maxlength="40" value="{{ old('address_short', $shop['address_short']) }}" class="{{ $field }}">
                </div>
                <div>
                    <label for="address_lines" class="{{ $label }}">Full address (one line per row)</label>
                    <textarea id="address_lines" name="address_lines" rows="3" required maxlength="300" class="{{ $area }}">{{ old('address_lines', implode("\n", $shop['address_lines'])) }}</textarea>
                </div>
                <div>
                    <label for="maps_query" class="{{ $label }}">What Google Maps should search for</label>
                    <input id="maps_query" name="maps_query" type="text" required maxlength="300" value="{{ old('maps_query', $shop['maps_query']) }}" class="{{ $field }}">
                </div>
                <div class="flex justify-end"><button type="submit" class="{{ $save }}"><span class="material-symbols-outlined text-[18px]">check</span>Save address</button></div>
            </form>
        </section>

        {{-- HOURS --}}
        <section class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 overflow-hidden">
            <div class="p-space-md border-b border-outline-variant/30">
                <h2 class="font-title-md text-title-md text-on-surface">Opening hours</h2>
                <p class="font-body-sm text-body-sm text-on-surface-variant">The OPEN/CLOSED sign on Contact follows these hours in Jakarta time, and so do the week strip, About and the footer.</p>
            </div>
            <form method="POST" action="{{ route('staff.site.shop', 'hours') }}" class="p-space-md grid grid-cols-2 gap-space-md">
                @csrf
                @method('PUT')
                <div>
                    <label for="hours_open" class="{{ $label }}">Opens</label>
                    <input id="hours_open" name="hours_open" type="time" required value="{{ old('hours_open', $shop['hours_open']) }}" class="{{ $field }}">
                </div>
                <div>
                    <label for="hours_close" class="{{ $label }}">Closes</label>
                    <input id="hours_close" name="hours_close" type="time" required value="{{ old('hours_close', $shop['hours_close']) }}" class="{{ $field }}">
                </div>
                <label class="col-span-2 inline-flex items-center gap-space-sm font-label-lg text-label-lg text-on-surface">
                    <input type="checkbox" name="counter_closed" value="1" class="{{ $toggle }}" @checked(old('counter_closed', $shop['counter_closed']))>
                    The counter is closed for now (a holiday, a day off)
                </label>
                <div class="col-span-2">
                    <label for="counter_closed_note" class="{{ $label }}">What the sign says while closed</label>
                    <input id="counter_closed_note" name="counter_closed_note" type="text" maxlength="80" value="{{ old('counter_closed_note', $shop['counter_closed_note']) }}" placeholder="back on Monday" class="{{ $field }}">
                </div>
                <div class="col-span-2 flex justify-end"><button type="submit" class="{{ $save }}"><span class="material-symbols-outlined text-[18px]">check</span>Save hours</button></div>
            </form>
        </section>
    </div>

    {{-- PROMISES --}}
    <section class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 overflow-hidden mb-space-lg">
        <div class="p-space-md border-b border-outline-variant/30">
            <h2 class="font-title-md text-title-md text-on-surface">Delivery promises and promo</h2>
            <p class="font-body-sm text-body-sm text-on-surface-variant">The numbers Home, About and Contact promise, and the line next to "Made by our customers" on Home (it follows the Instagram handle).</p>
        </div>
        <form method="POST" action="{{ route('staff.site.shop', 'promises') }}" class="p-space-md grid grid-cols-1 md:grid-cols-2 gap-space-md">
            @csrf
            @method('PUT')
            <div>
                <label for="delivery_minutes" class="{{ $label }}">Delivery in Jakarta (minutes)</label>
                <input id="delivery_minutes" name="delivery_minutes" type="number" min="5" max="240" required value="{{ old('delivery_minutes', $shop['delivery_minutes']) }}" class="{{ $field }}">
            </div>
            <div>
                <label for="frozen_minutes" class="{{ $label }}">Stays frozen in the box (minutes)</label>
                <input id="frozen_minutes" name="frozen_minutes" type="number" min="10" max="600" required value="{{ old('frozen_minutes', $shop['frozen_minutes']) }}" class="{{ $field }}">
            </div>
            <div class="md:col-span-2">
                <label for="promo_text" class="{{ $label }}">Promo after "Tag {{ '@'.$contact['instagram'] }}"</label>
                <input id="promo_text" name="promo_text" type="text" required maxlength="160" value="{{ old('promo_text', $shop['promo_text']) }}" class="{{ $field }}">
            </div>
            <div class="md:col-span-2 flex justify-end"><button type="submit" class="{{ $save }}"><span class="material-symbols-outlined text-[18px]">check</span>Save</button></div>
        </form>
    </section>

    {{-- CUSTOMER REVIEWS --}}
    <section class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 overflow-hidden">
        <div class="p-space-md border-b border-outline-variant/30">
            <h2 class="font-title-md text-title-md text-on-surface">Customer reviews</h2>
            <p class="font-body-sm text-body-sm text-on-surface-variant">The newest reviews, as they appear on Home, About and the customer photos page (first name and initial only). Hide one to keep it off the site; the rating stays in the reports. Take a photo down if it is not about the sandwich; the words stay. The Reviews page in the sidebar has the full list with filters and search.</p>
        </div>
        @if ($reviews->isEmpty())
            <p class="p-space-md font-body-md text-body-md text-on-surface-variant">No reviews yet.</p>
        @else
            <ul class="p-space-md grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-space-md">
                @foreach ($reviews as $review)
                    <li class="rounded-lg border border-outline-variant/40 overflow-hidden bg-surface flex flex-col {{ $review->review_hidden_at ? 'opacity-60' : '' }}">
                        @if ($review->reviewPhoto)
                            <img src="{{ $review->reviewPhoto->url() }}" alt="Customer photo for order #{{ $review->order_number }}" class="w-full aspect-[4/3] object-cover" loading="lazy">
                        @endif
                        <div class="p-space-sm flex flex-col gap-1 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <p class="font-label-lg text-label-lg text-on-surface truncate">#{{ $review->order_number }} · {{ \App\Support\ReviewFeed::shortName((string) $review->customer_name) }}</p>
                                <span class="shrink-0 font-label-md text-label-md text-primary tabular-nums">{{ $review->rating }}/5</span>
                            </div>
                            @if ($review->review_hidden_at)
                                <span class="self-start rounded-full bg-surface-container px-2 py-0.5 font-label-md text-label-md text-on-surface-variant">Hidden from the site</span>
                            @endif
                            <p class="font-body-sm text-body-sm text-on-surface-variant line-clamp-3 [overflow-wrap:anywhere]">{{ $review->review_text ? '“'.$review->review_text.'”' : 'No words, rating only.' }}</p>
                            <div class="mt-auto pt-1 flex flex-wrap gap-x-space-md gap-y-1">
                                <form method="POST" action="{{ route('staff.site.reviews.toggle', $review) }}">
                                    @csrf
                                    <button type="submit" class="press-scale inline-flex items-center gap-1 font-label-md text-label-md text-primary">
                                        <span class="material-symbols-outlined text-[16px]">{{ $review->review_hidden_at ? 'visibility' : 'visibility_off' }}</span>{{ $review->review_hidden_at ? 'Show on site' : 'Hide from site' }}
                                    </button>
                                </form>
                                @if ($review->reviewPhoto)
                                    <form method="POST" action="{{ route('staff.site.photos.remove', $review->reviewPhoto) }}" data-confirm="The photo is removed for good. The rating and the review text stay." data-confirm-title="Take this photo down?" data-confirm-label="Take down" data-cancel-label="Keep it" data-confirm-tone="danger">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="press-scale inline-flex items-center gap-1 font-label-md text-label-md text-error">
                                            <span class="material-symbols-outlined text-[16px]">delete</span>Take photo down
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

@endsection
