@extends('layouts.staff')

@section('title', 'Reviews')

@section('content')

    @php
        $fieldClasses = 'rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-sm text-body-sm text-on-surface placeholder:text-on-surface-variant focus:outline-none focus:ring-2 focus:ring-primary';
        $filtersOn = $filter !== 'all' || $search !== '';
    @endphp

    <x-staff.page-header icon="star" title="Reviews">
        Pantau semua ulasan pelanggan. Sembunyikan yang iseng dari situs, turunkan fotonya, atau hapus ulasannya.
    </x-staff.page-header>

    {{-- ADD A REVIEW for a finished order --}}
    <details class="mb-space-md rounded-lg border border-outline-variant/30 bg-surface-container-lowest" @if ($errors->any() && old('order_id') !== null) open @endif>
        <summary class="flex cursor-pointer items-center gap-2 p-space-sm font-label-lg text-label-lg text-on-surface">
            <span class="material-symbols-outlined text-[20px] text-primary">edit</span>Tambah ulasan untuk pesanan selesai
            <span class="ml-auto font-label-md text-label-md text-on-surface-variant">{{ $reviewable->count() }} pesanan belum diulas</span>
        </summary>
        <form method="POST" action="{{ route('staff.reviews.store') }}" enctype="multipart/form-data" x-data="reviewAdder()" class="grid gap-space-sm border-t border-outline-variant/30 p-space-sm md:grid-cols-2">
            @csrf
            <label class="flex flex-col gap-1 font-label-md text-label-md text-on-surface-variant">Pesanan
                <select name="order_id" required class="{{ $fieldClasses }}">
                    <option value="">Pilih pesanan siap atau selesai...</option>
                    @foreach ($reviewable as $candidate)
                        <option value="{{ $candidate->id }}" @selected((string) old('order_id') === (string) $candidate->id)>#{{ $candidate->order_number }} · {{ $candidate->customer_name }} ({{ $candidate->isCompleted() ? 'selesai' : 'siap diambil' }})</option>
                    @endforeach
                </select>
                @error('order_id') <span class="text-error">{{ $message }}</span> @enderror
            </label>
            <label class="flex flex-col gap-1 font-label-md text-label-md text-on-surface-variant">Bintang
                <select name="rating" required class="{{ $fieldClasses }}">
                    @foreach ([5, 4, 3, 2, 1] as $stars)
                        <option value="{{ $stars }}" @selected((int) old('rating', 5) === $stars)>{{ str_repeat('★', $stars) }} ({{ $stars }})</option>
                    @endforeach
                </select>
                @error('rating') <span class="text-error">{{ $message }}</span> @enderror
            </label>
            <label class="flex flex-col gap-1 font-label-md text-label-md text-on-surface-variant md:col-span-2">Isi ulasan
                <textarea name="review_text" rows="3" maxlength="1000" required class="rounded-2xl border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary">{{ old('review_text') }}</textarea>
                @error('review_text') <span class="text-error">{{ $message }}</span> @enderror
            </label>
            <label class="flex flex-col gap-1 font-label-md text-label-md text-on-surface-variant">Foto (opsional, bisa diatur framenya setelah disimpan)
                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" @change="pick($event)" class="font-body-sm text-body-sm">
                @error('photo') <span class="text-error">{{ $message }}</span> @enderror
                <span x-show="error" x-text="error" x-cloak class="text-error"></span>
            </label>
            <div class="flex items-end justify-end">
                <button type="submit" class="press-scale inline-flex items-center gap-1 rounded-full bg-primary-container px-space-md py-space-xs font-label-md text-label-md font-bold text-on-primary-container">
                    <span class="material-symbols-outlined text-[16px]">check</span>Simpan ulasan
                </button>
            </div>
        </form>
    </details>

    {{-- NUMBERS --}}
    <div class="mb-space-md grid grid-cols-2 gap-space-sm md:grid-cols-5">
        @foreach ([
            ['Total ulasan', $stats['total']],
            ['Rata-rata', $stats['average'].' / 5'],
            ['24 jam terakhir', $stats['fresh']],
            ['Dengan foto', $stats['photos']],
            ['Disembunyikan', $stats['hidden']],
        ] as [$label, $value])
            <div class="rounded-lg border border-outline-variant/30 bg-surface-container-lowest p-space-sm">
                <p class="font-label-md text-label-md text-on-surface-variant">{{ $label }}</p>
                <p class="font-title-md text-title-md text-on-surface tabular-nums">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    {{-- FILTER BAR --}}
    <form method="GET" action="{{ route('staff.reviews.index') }}" x-data class="mb-space-md flex flex-wrap items-center gap-space-xs">
        <select name="show" aria-label="Filter ulasan" @change="$el.form.requestSubmit()" class="{{ $fieldClasses }}">
            @foreach ($filters as $key => $label)
                <option value="{{ $key }}" @selected($filter === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <input type="search" name="q" value="{{ $search }}" placeholder="Cari nama, nomor pesanan, atau isi ulasan" class="{{ $fieldClasses }} min-w-[16rem] flex-1">
        <button type="submit" class="press-scale inline-flex items-center gap-1 rounded-full bg-primary-container px-space-md py-space-xs font-label-md text-label-md font-bold text-on-primary-container">
            <span class="material-symbols-outlined text-[16px]">search</span>Cari
        </button>
        @if ($filtersOn)
            <a href="{{ route('staff.reviews.index') }}" class="font-label-md text-label-md text-primary underline">Reset</a>
        @endif
    </form>

    @if ($reviews->isEmpty())
        <p class="rounded-lg border border-outline-variant/30 bg-surface-container-lowest p-space-md font-body-md text-body-md text-on-surface-variant">
            {{ $filtersOn ? 'Tidak ada ulasan yang cocok dengan filter ini.' : 'Belum ada ulasan.' }}
        </p>
    @else
        <ul class="grid grid-cols-1 gap-space-md md:grid-cols-2 2xl:grid-cols-3">
            @foreach ($reviews as $review)
                @php $photo = $review->reviewPhoto; @endphp
                <li
                    x-data="reviewPhotoEditor({{ \Illuminate\Support\Js::from([
                        'src' => $photo?->url() ?? '',
                        'width' => $photo?->width,
                        'height' => $photo?->height,
                        'fit' => $photo?->fit ?? 'auto',
                        'zoom' => $photo?->zoom ?? 100,
                        'fx' => $photo?->focus_x ?? 50,
                        'fy' => $photo?->focus_y ?? 50,
                    ]) }})"
                    class="flex flex-col overflow-hidden rounded-lg border border-outline-variant/40 bg-surface-container-lowest"
                >
                    <div class="review-frame {{ $review->review_hidden_at ? 'opacity-60' : '' }}" :style="frameStyle" x-show="src" @if (! $photo) x-cloak @endif style="{{ $photo?->frameStyle() }}">
                        <img class="review-frame__img" :src="src" src="{{ $photo?->url() }}" alt="Foto ulasan pesanan #{{ $review->order_number }}" loading="lazy">
                    </div>
                    <div class="flex flex-1 flex-col gap-1.5 p-space-sm {{ $review->review_hidden_at ? 'opacity-60' : '' }}">
                        <div class="flex items-center justify-between gap-2">
                            <p class="truncate font-label-lg text-label-lg text-on-surface">#{{ $review->order_number }} · {{ $review->customer_name }}</p>
                            <span class="shrink-0 font-label-md text-label-md tabular-nums text-primary">{{ str_repeat('★', (int) $review->rating) }}<span class="text-outline-variant">{{ str_repeat('★', 5 - (int) $review->rating) }}</span></span>
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5">
                            @if ($review->review_hidden_at)
                                <span class="rounded-full bg-surface-container px-2 py-0.5 font-label-md text-[11px] text-on-surface-variant">Disembunyikan dari situs</span>
                            @else
                                <span class="rounded-full border border-emerald-300 bg-emerald-50 px-2 py-0.5 font-label-md text-[11px] text-emerald-800">Tampil di situs</span>
                            @endif
                            @if ($review->reviewed_at)
                                <span class="font-label-md text-[11px] text-on-surface-variant" title="{{ $review->reviewed_at->format('d M Y, H:i') }} WIB">{{ $review->reviewed_at->diffForHumans() }}</span>
                            @endif
                        </div>
                        <p class="font-body-sm text-body-sm text-on-surface-variant [overflow-wrap:anywhere]">{{ $review->review_text ? '“'.$review->review_text.'”' : 'Tanpa teks, hanya bintang.' }}</p>
                    </div>

                    {{-- PHOTO EDITOR: the frame above changes live as the controls move --}}
                    <form method="POST" action="{{ route('staff.reviews.photo', $review) }}" enctype="multipart/form-data" x-show="open" x-cloak class="grid gap-space-xs border-t border-outline-variant/30 bg-surface-container-low p-space-sm">
                        @csrf
                        <label class="flex flex-col gap-1 font-label-md text-label-md text-on-surface-variant">Ganti atau tambah foto
                            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" @change="pick($event)" class="font-body-sm text-body-sm">
                        </label>
                        <p x-show="error" x-text="error" x-cloak class="font-label-md text-label-md text-error"></p>

                        <input type="hidden" name="fit" :value="fit">
                        <div class="flex flex-wrap gap-1.5" role="group" aria-label="Cara foto mengisi frame">
                            @foreach (['auto' => 'Otomatis', 'cover' => 'Penuhi frame', 'contain' => 'Foto utuh + latar Brread'] as $mode => $label)
                                <button type="button" @click="fit = '{{ $mode }}'" :aria-pressed="fit === '{{ $mode }}'" :class="fit === '{{ $mode }}' ? 'bg-primary-container text-on-primary-container font-bold' : 'bg-surface-container text-on-surface-variant'" class="press-scale rounded-full px-space-sm py-1 font-label-md text-label-md">{{ $label }}</button>
                            @endforeach
                        </div>
                        <p class="font-label-md text-[11px] text-on-surface-variant" x-text="resolved === 'cover' ? 'Sekarang: foto memenuhi frame.' : 'Sekarang: foto utuh di atas latar sprinkle Brread.'"></p>

                        <label class="grid grid-cols-[5rem_1fr_3rem] items-center gap-2 font-label-md text-label-md text-on-surface-variant">Zoom
                            <input type="range" name="zoom" min="100" max="300" step="5" x-model.number="zoom" class="accent-[#c74659]">
                            <span class="tabular-nums" x-text="zoom + '%'"></span>
                        </label>
                        <label class="grid grid-cols-[5rem_1fr_3rem] items-center gap-2 font-label-md text-label-md text-on-surface-variant">Geser kiri-kanan
                            <input type="range" name="focus_x" min="0" max="100" step="1" x-model.number="fx" class="accent-[#c74659]">
                            <span class="tabular-nums" x-text="fx + '%'"></span>
                        </label>
                        <label class="grid grid-cols-[5rem_1fr_3rem] items-center gap-2 font-label-md text-label-md text-on-surface-variant">Geser atas-bawah
                            <input type="range" name="focus_y" min="0" max="100" step="1" x-model.number="fy" class="accent-[#c74659]">
                            <span class="tabular-nums" x-text="fy + '%'"></span>
                        </label>
                        <div class="flex items-center justify-between gap-2 pt-1">
                            <button type="button" @click="fit = 'auto'; zoom = 100; fx = 50; fy = 50" class="font-label-md text-label-md text-primary underline">Reset</button>
                            <div class="flex gap-2">
                                <button type="button" @click="open = false" class="press-scale rounded-full bg-surface-container px-space-md py-1 font-label-md text-label-md text-on-surface-variant">Tutup</button>
                                <button type="submit" class="press-scale inline-flex items-center gap-1 rounded-full bg-primary-container px-space-md py-1 font-label-md text-label-md font-bold text-on-primary-container"><span class="material-symbols-outlined text-[16px]">check</span>Simpan foto</button>
                            </div>
                        </div>
                    </form>

                    <div class="flex flex-wrap gap-x-space-md gap-y-1 border-t border-outline-variant/30 p-space-sm">
                        <button type="button" @click="open = !open" class="press-scale inline-flex items-center gap-1 font-label-md text-label-md text-primary">
                            <span class="material-symbols-outlined text-[16px]">crop</span><span x-text="src ? 'Edit foto' : 'Tambah foto'">Edit foto</span>
                        </button>
                        <form method="POST" action="{{ route('staff.site.reviews.toggle', $review) }}">
                            @csrf
                            <button type="submit" class="press-scale inline-flex items-center gap-1 font-label-md text-label-md text-primary">
                                <span class="material-symbols-outlined text-[16px]">{{ $review->review_hidden_at ? 'visibility' : 'visibility_off' }}</span>{{ $review->review_hidden_at ? 'Tampilkan' : 'Sembunyikan' }}
                            </button>
                        </form>
                        @if ($photo)
                            <form method="POST" action="{{ route('staff.site.photos.remove', $photo) }}" data-confirm="Foto dihapus permanen. Bintang dan teks ulasan tetap ada." data-confirm-title="Turunkan foto ini?" data-confirm-label="Turunkan" data-cancel-label="Biarkan" data-confirm-tone="danger">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="press-scale inline-flex items-center gap-1 font-label-md text-label-md text-error">
                                    <span class="material-symbols-outlined text-[16px]">photo_camera</span>Turunkan foto
                                </button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('staff.reviews.destroy', $review) }}" data-confirm="Bintang, teks, dan foto ulasan pesanan #{{ $review->order_number }} dihapus permanen. Pesanannya tetap ada dan pelanggan bisa mengulas lagi." data-confirm-title="Hapus ulasan ini?" data-confirm-label="Ya, hapus" data-cancel-label="Kembali" data-confirm-tone="danger">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="press-scale inline-flex items-center gap-1 font-label-md text-label-md text-error">
                                <span class="material-symbols-outlined text-[16px]">delete</span>Hapus
                            </button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="mt-space-md">
            {{ $reviews->links() }}
        </div>
    @endif

@endsection
