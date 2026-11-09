@extends('layouts.app')

@section('title', 'Riwayat Transaksi — Brread!')

@section('content')
<div class="max-w-[1000px] mx-auto py-space-xl px-margin md:px-margin-tablet" x-data="orderHistoryModal()">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-space-md mb-space-lg">
        <div>
            <div class="inline-flex items-center gap-1.5 bg-primary-fixed text-primary px-3 py-1 rounded-full font-label-sm text-label-sm mb-2">
                <span class="material-symbols-outlined text-[16px]">receipt_long</span>
                Daftar Pesanan & Status
            </div>
            <h1 class="font-headline-md text-headline-md text-on-surface">Riwayat Transaksi</h1>
            <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
                Pantau proses pembuatan roti es krim Anda dan berikan ulasan setelah menerima pesanan.
            </p>
        </div>
        <a
            href="{{ route('build') }}"
            class="press-scale inline-flex items-center gap-2 bg-primary-container text-on-primary-container px-5 py-2.5 rounded-full font-label-md text-label-md shadow-[0_4px_12px_rgba(199,70,89,0.25)] hover:opacity-95 transition-opacity"
        >
            <span class="material-symbols-outlined text-[18px]">auto_awesome</span>
            <span>Rakit Sandwich Baru</span>
        </a>
    </div>

    {{-- Feedback Messages --}}
    @if (session('order_status'))
        <div class="mb-space-md p-space-sm rounded-xl bg-primary-container text-on-primary-container font-body-sm text-body-sm flex items-center gap-2">
            <span class="material-symbols-outlined text-[20px]">check_circle</span>
            <span>{{ session('order_status') }}</span>
        </div>
    @endif

    @if (session('order_error'))
        <div role="alert" class="mb-space-md p-space-sm rounded-xl bg-error-container text-on-error-container font-body-sm text-body-sm flex items-center gap-2">
            <span class="material-symbols-outlined text-[20px]">error</span>
            <span>{{ session('order_error') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div role="alert" class="mb-space-md p-space-sm rounded-xl bg-error-container text-on-error-container font-body-sm text-body-sm flex items-start gap-2">
            <span class="material-symbols-outlined text-[20px] shrink-0 mt-0.5">error</span>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    @if ($orders->isEmpty())
        {{-- Empty State --}}
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 shadow-[0_4px_20px_rgba(113,59,44,0.06)] p-space-xl text-center flex flex-col items-center justify-center my-space-lg">
            <div class="w-20 h-20 rounded-full bg-surface-container flex items-center justify-center text-on-surface-variant mb-space-sm">
                <span class="material-symbols-outlined text-[40px] text-primary">shopping_bag</span>
            </div>
            <h2 class="font-headline-sm text-headline-sm text-on-surface">Belum Ada Transaksi</h2>
            <p class="font-body-md text-body-md text-on-surface-variant max-w-md mt-1 mb-space-md">
                Anda belum pernah memesan di Brread!. Kunjungi Brread! Studio untuk memilih roti favorit, rasa es krim lembut, dan taburan topping lezat.
            </p>
            <a
                href="{{ route('build') }}"
                class="press-scale inline-flex items-center gap-2 bg-primary-container text-on-primary-container px-6 py-3 rounded-full font-label-lg text-label-lg shadow-[0_4px_16px_rgba(199,70,89,0.3)] hover:opacity-95 transition-opacity"
            >
                <span class="material-symbols-outlined text-[20px]">auto_awesome</span>
                <span>Mulai Buat Sandwich Sekarang</span>
            </a>
        </div>
    @else
        <div class="flex flex-col gap-space-lg">
            @foreach ($orders as $order)
                @php
                    $isPending = $order->status === 'pending';
                    $isProcessing = $order->status === 'processing';
                    $isCompleted = $order->status === 'completed';

                    // Group order items by creation_index
                    $creations = $order->orderItems->groupBy('creation_index');
                @endphp

                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 shadow-[0_4px_20px_rgba(113,59,44,0.06)] overflow-hidden transition-all duration-200">
                    {{-- Card Header --}}
                    <div class="bg-surface-container-low/60 px-space-md py-space-sm border-b border-outline-variant/20 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[22px] text-primary">receipt</span>
                            <div>
                                <span class="font-mono font-bold text-on-surface text-base">{{ $order->order_number }}</span>
                                <span class="text-xs text-on-surface-variant block">{{ $order->created_at->translatedFormat('d M Y, H:i') }} WIB</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 flex-wrap">
                            {{-- Pickup Badge --}}
                            <span class="inline-flex items-center gap-1 bg-surface-container-high text-on-surface text-xs font-semibold px-2.5 py-1 rounded-full">
                                <span class="material-symbols-outlined text-[14px] text-tertiary">storefront</span>
                                Pickup di Kampus
                            </span>

                            {{-- Status Badge --}}
                            @if ($isPending)
                                <span class="inline-flex items-center gap-1 bg-amber-100 text-amber-900 border border-amber-300 px-3 py-1 rounded-full text-xs font-bold">
                                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                                    Menunggu Konfirmasi
                                </span>
                            @elseif ($isProcessing)
                                <span class="inline-flex items-center gap-1 bg-blue-100 text-blue-900 border border-blue-300 px-3 py-1 rounded-full text-xs font-bold">
                                    <span class="w-2 h-2 rounded-full bg-blue-500 animate-pulse"></span>
                                    Sedang Disiapkan
                                </span>
                            @elseif ($isCompleted)
                                <span class="inline-flex items-center gap-1 bg-emerald-100 text-emerald-900 border border-emerald-300 px-3 py-1 rounded-full text-xs font-bold">
                                    <span class="material-symbols-outlined text-[14px] text-emerald-700">check_circle</span>
                                    Pesanan Selesai
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 bg-surface-container text-on-surface px-3 py-1 rounded-full text-xs font-bold">
                                    {{ ucfirst($order->status) }}
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Creations Breakdown --}}
                    <div class="p-space-md sm:p-space-lg divide-y divide-outline-variant/15">
                        @foreach ($creations as $creationIndex => $items)
                            @php
                                $breadItem = $items->first(fn($i) => $i->component?->category === 'bread');
                                $flavorItem = $items->first(fn($i) => $i->component?->category === 'ice_cream');
                                $toppingItems = $items->filter(fn($i) => $i->component?->category === 'topping');
                                $quantity = $items->first()->quantity ?? 1;
                                $creationSubtotal = $items->sum(fn($i) => $i->unit_price) * $quantity;
                            @endphp

                            <div class="py-3 first:pt-0 last:pb-0 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div class="flex items-start gap-3">
                                    <div class="w-12 h-12 rounded-xl bg-primary-container/30 border border-primary/10 flex items-center justify-center shrink-0 text-primary">
                                        <span class="material-symbols-outlined text-[24px]">bakery_dining</span>
                                    </div>
                                    <div>
                                        <p class="font-title-sm text-title-sm text-on-surface">
                                            {{ $breadItem->component->name ?? 'Bread' }}
                                            <span class="text-on-surface-variant font-normal">·</span>
                                            <span class="text-primary font-bold">{{ $flavorItem->component->name ?? 'Ice Cream' }}</span>
                                        </p>
                                        <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5">
                                            Topping:
                                            @if ($toppingItems->isNotEmpty())
                                                {{ $toppingItems->pluck('component.name')->join(', ') }}
                                            @else
                                                <span class="italic text-on-surface-variant/70">Tanpa topping tambahan</span>
                                            @endif
                                        </p>
                                        <span class="text-xs font-medium text-on-surface-variant/80 mt-0.5 inline-block">
                                            Jumlah: <strong class="text-on-surface">{{ $quantity }} sandwich</strong>
                                        </span>
                                    </div>
                                </div>
                                <div class="sm:text-right">
                                    <span class="text-xs text-on-surface-variant block">Subtotal</span>
                                    <span class="font-title-sm text-title-sm text-primary font-bold tabular-nums">
                                        Rp {{ number_format($creationSubtotal, 0, ',', '.') }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Customer review display if already reviewed --}}
                    @if ($order->review)
                        <div class="mx-space-md sm:mx-space-lg mb-space-md p-space-md rounded-2xl bg-secondary-fixed/30 border border-secondary/20 flex flex-col sm:flex-row items-start gap-space-md">
                            @if ($order->review->image_path)
                                <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-xl overflow-hidden shrink-0 border border-outline-variant/30 shadow-sm">
                                    <img
                                        src="{{ $order->review->imageUrl() }}"
                                        alt="Foto ulasan sandwich"
                                        class="w-full h-full object-cover hover:scale-105 transition-transform"
                                    >
                                </div>
                            @endif
                            <div class="flex-1">
                                <div class="flex items-center gap-1 text-primary mb-1">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' {{ $i <= $order->review->rating ? 1 : 0 }};">
                                            star
                                        </span>
                                    @endfor
                                    <span class="text-xs font-bold text-on-surface ml-1.5">{{ $order->review->rating }}.0 / 5.0</span>
                                </div>
                                <p class="font-body-sm text-body-sm text-on-surface italic">"{{ $order->review->comment }}"</p>
                                <span class="text-[11px] text-on-surface-variant mt-1.5 block">
                                    Diulas pada {{ $order->review->created_at->translatedFormat('d M Y, H:i') }} · Ditampilkan di #BrreadSquad
                                </span>
                            </div>
                        </div>
                    @endif

                    {{-- Card Footer --}}
                    <div class="bg-surface-container-low/40 px-space-md sm:px-space-lg py-space-sm border-t border-outline-variant/20 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <span class="font-body-md text-body-md text-on-surface-variant">Total Pembayaran:</span>
                            <span class="font-headline-sm text-headline-sm text-primary font-bold tabular-nums">
                                Rp {{ number_format($order->total_price, 0, ',', '.') }}
                            </span>
                        </div>

                        <div class="flex items-center gap-2.5 flex-wrap">
                            {{-- Button "Sudah Menerima Produk" --}}
                            @if ($order->canBeReceived())
                                <form
                                    method="POST"
                                    action="{{ route('orders.receive', $order) }}"
                                    onsubmit="return confirm('Apakah Anda sudah menerima seluruh pesanan #{{ $order->order_number }} di booth kampus?');"
                                >
                                    @csrf
                                    <button
                                        type="submit"
                                        class="press-scale inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-full font-label-md text-label-md shadow-[0_2px_8px_rgba(5,150,105,0.3)] transition-all"
                                    >
                                        <span class="material-symbols-outlined text-[18px]">verified</span>
                                        <span>Sudah Menerima Produk</span>
                                    </button>
                                </form>
                            @endif

                            {{-- Button "Beri Rating & Ulasan" --}}
                            @if ($order->isCompleted() && !$order->review)
                                <button
                                    type="button"
                                    @click="openReviewModal({{ $order->id }}, '{{ $order->order_number }}')"
                                    class="press-scale inline-flex items-center gap-1.5 bg-primary-container text-on-primary-container px-4 py-2 rounded-full font-label-md text-label-md shadow-[0_2px_8px_rgba(199,70,89,0.3)] hover:opacity-95 transition-all"
                                >
                                    <span class="material-symbols-outlined text-[18px]">rate_review</span>
                                    <span>Beri Rating & Ulasan</span>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach

            {{-- Pagination Links --}}
            <div class="mt-space-md">
                {{ $orders->links() }}
            </div>
        </div>
    @endif

    {{-- Review Modal Dialog --}}
    <div
        x-show="modalOpen"
        x-cloak
        class="fixed inset-0 z-[70] flex items-center justify-center p-margin"
        @keydown.escape.window="closeModal()"
    >
        {{-- Scrim --}}
        <div
            x-show="modalOpen"
            x-transition:enter="transition-opacity duration-200 ease-out"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity duration-150 ease-in"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="closeModal()"
            class="fixed inset-0 bg-on-surface/50 backdrop-blur-xs"
        ></div>

        {{-- Modal Box --}}
        <div
            x-show="modalOpen"
            x-transition:enter="transition-all duration-200 ease-out"
            x-transition:enter-start="opacity-0 scale-95 translate-y-2"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition-all duration-150 ease-in"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-2"
            class="relative w-full max-w-lg bg-surface rounded-3xl shadow-[0_20px_60px_rgba(113,59,44,0.25)] border border-outline-variant/30 p-space-lg sm:p-space-xl z-10 font-plus-jakarta max-h-[90vh] overflow-y-auto"
        >
            <div class="flex items-center justify-between pb-space-sm border-b border-outline-variant/20 mb-space-md">
                <div class="flex items-center gap-2">
                    <span class="w-8 h-8 rounded-full bg-primary-container text-on-primary-container flex items-center justify-center">
                        <span class="material-symbols-outlined text-[18px]">star</span>
                    </span>
                    <div>
                        <h2 class="font-title-md text-title-md text-on-surface">Ulasan & Rating Produk</h2>
                        <p class="text-xs text-on-surface-variant">Pesanan <span class="font-mono font-bold" x-text="'#' + activeOrderNumber"></span></p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="closeModal()"
                    class="w-8 h-8 rounded-full bg-surface-container hover:bg-surface-container-high flex items-center justify-center text-on-surface-variant transition-colors"
                >
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            <form
                :action="'/orders/' + activeOrderId + '/review'"
                method="POST"
                enctype="multipart/form-data"
                class="space-y-space-md"
            >
                @csrf
                {{-- Interactive Star Rating --}}
                <div class="text-center py-2">
                    <label class="block font-label-md text-label-md text-on-surface mb-2">Beri Nilai Kreasi Sandwich</label>
                    <div class="flex items-center justify-center gap-2 text-primary" @mouseleave="hoverRating = 0">
                        <template x-for="star in [1, 2, 3, 4, 5]" :key="star">
                            <button
                                type="button"
                                @click="rating = star"
                                @mouseenter="hoverRating = star"
                                class="focus:outline-none p-1 transition-transform hover:scale-125 active:scale-95"
                            >
                                <span
                                    class="material-symbols-outlined text-[36px]"
                                    :style="'font-variation-settings: \'FILL\' ' + ((hoverRating || rating) >= star ? 1 : 0)"
                                >star</span>
                            </button>
                        </template>
                    </div>
                    <input type="hidden" name="rating" :value="rating">
                    <p class="text-xs font-semibold text-primary mt-1" x-text="ratingLabels[rating]"></p>
                </div>

                {{-- Testimonial Comment --}}
                <div>
                    <label for="review-comment" class="block font-label-md text-label-md text-on-surface mb-1">
                        Testimoni & Pengalaman Anda
                    </label>
                    <textarea
                        id="review-comment"
                        name="comment"
                        rows="3"
                        required
                        maxlength="1000"
                        placeholder="Ceritakan rasa roti lembutnya, manisnya es krim, dan kesegarannya..."
                        class="w-full rounded-2xl border border-outline-variant bg-surface-container-lowest p-space-sm font-body-md text-body-md text-on-surface placeholder:text-on-surface-variant/50 focus:outline-none focus:ring-2 focus:ring-primary"
                    ></textarea>
                </div>

                {{-- Photo Upload with Preview --}}
                <div>
                    <label class="block font-label-md text-label-md text-on-surface mb-1">
                        Foto Sandwich yang Diterima (Opsional)
                    </label>
                    <div
                        class="relative rounded-2xl border-2 border-dashed border-outline-variant bg-surface-container-lowest p-4 text-center cursor-pointer hover:border-primary/60 transition-colors"
                        @click="$refs.photoInput.click()"
                    >
                        <input
                            x-ref="photoInput"
                            type="file"
                            name="image"
                            accept="image/*"
                            class="hidden"
                            @change="handleImagePreview($event)"
                        >
                        <div x-show="!imagePreview" class="flex flex-col items-center gap-1.5 py-2">
                            <span class="material-symbols-outlined text-[32px] text-on-surface-variant/60">add_photo_alternate</span>
                            <span class="font-label-md text-label-md text-primary font-bold">Upload Foto Kreasi Anda</span>
                            <span class="text-xs text-on-surface-variant">PNG, JPG, JPEG atau WebP (Maks. 4MB)</span>
                        </div>
                        <div x-show="imagePreview" class="relative inline-block" x-cloak>
                            <img :src="imagePreview" alt="Preview Foto" class="max-h-36 rounded-xl object-contain shadow-sm border border-outline-variant/30">
                            <button
                                type="button"
                                @click.stop="imagePreview = null; $refs.photoInput.value = ''"
                                class="absolute -top-2 -right-2 w-6 h-6 rounded-full bg-error text-white flex items-center justify-center shadow hover:scale-110"
                            >
                                <span class="material-symbols-outlined text-[16px]">close</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="pt-space-xs flex items-center justify-end gap-2">
                    <button
                        type="button"
                        @click="closeModal()"
                        class="px-5 py-2.5 rounded-full font-label-md text-label-md text-on-surface-variant hover:bg-surface-container transition-colors"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="press-scale inline-flex items-center gap-2 rounded-full bg-primary-container px-6 py-2.5 font-label-lg text-label-lg text-on-primary-container shadow-[0_4px_16px_rgba(199,70,89,0.3)] hover:opacity-95 transition-opacity"
                    >
                        <span class="material-symbols-outlined text-[18px]">send</span>
                        <span>Kirim Ulasan</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function orderHistoryModal() {
    return {
        modalOpen: false,
        activeOrderId: null,
        activeOrderNumber: '',
        rating: 5,
        hoverRating: 0,
        imagePreview: null,
        ratingLabels: {
            1: '1 Bintang — Kurang Memuaskan',
            2: '2 Bintang — Cukup',
            3: '3 Bintang — Enak',
            4: '4 Bintang — Sangat Lezat!',
            5: '5 Bintang — Sempurna & Bikin Nagih! ⭐',
        },
        openReviewModal(id, number) {
            this.activeOrderId = id;
            this.activeOrderNumber = number;
            this.rating = 5;
            this.hoverRating = 0;
            this.imagePreview = null;
            this.modalOpen = true;
        },
        closeModal() {
            this.modalOpen = false;
        },
        handleImagePreview(event) {
            const file = event.target.files[0];
            if (file) {
                this.imagePreview = URL.createObjectURL(file);
            }
        }
    };
}
</script>
@endsection

