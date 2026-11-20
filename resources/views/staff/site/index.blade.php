@extends('layouts.staff')

@section('title', 'Website')

@section('content')

    @php
        $field = 'w-full rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-md text-body-md text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:ring-2 focus:ring-primary';
        $label = 'block font-label-md text-label-md text-on-surface-variant uppercase tracking-wide mb-1';
    @endphp

    <x-staff.page-header icon="storefront" title="Website">
        Who customers reach on the public site, and the photos customers added to their reviews.
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
                <input id="contact_whatsapp" name="contact_whatsapp" type="tel" inputmode="tel" value="{{ old('contact_whatsapp', $contact['whatsapp']) }}" placeholder="0812 3456 7890" class="{{ $field }}">
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

    {{-- CUSTOMER REVIEWS --}}
    <section class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 overflow-hidden">
        <div class="p-space-md border-b border-outline-variant/30">
            <h2 class="font-title-md text-title-md text-on-surface">Customer reviews</h2>
            <p class="font-body-sm text-body-sm text-on-surface-variant">The newest reviews, as they appear on Home, About and the customer photos page (first name and initial only). Hide one to keep it off the site; the rating stays in the reports. Take a photo down if it is not about the sandwich; the words stay.</p>
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
