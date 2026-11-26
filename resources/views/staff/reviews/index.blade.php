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
        <ul class="grid grid-cols-1 gap-space-md sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($reviews as $review)
                <li class="flex flex-col overflow-hidden rounded-lg border border-outline-variant/40 bg-surface-container-lowest {{ $review->review_hidden_at ? 'opacity-60' : '' }}">
                    @if ($review->reviewPhoto)
                        <img src="{{ $review->reviewPhoto->url() }}" alt="Foto ulasan pesanan #{{ $review->order_number }}" class="aspect-[4/3] w-full object-cover" loading="lazy">
                    @endif
                    <div class="flex flex-1 flex-col gap-1.5 p-space-sm">
                        <div class="flex items-center justify-between gap-2">
                            <p class="truncate font-label-lg text-label-lg text-on-surface">#{{ $review->order_number }} · {{ $review->customer_name }}</p>
                            <span class="shrink-0 font-label-md text-label-md tabular-nums text-primary">{{ str_repeat('★', (int) $review->rating) }}<span class="text-outline-variant">{{ str_repeat('★', 5 - (int) $review->rating) }}</span></span>
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5">
                            @if ($review->review_hidden_at)
                                <span class="rounded-full bg-surface-container px-2 py-0.5 font-label-md text-[11px] text-on-surface-variant">Disembunyikan dari situs</span>
                            @else
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 font-label-md text-[11px] text-emerald-800 border border-emerald-300">Tampil di situs</span>
                            @endif
                            @if ($review->reviewed_at)
                                <span class="font-label-md text-[11px] text-on-surface-variant" title="{{ $review->reviewed_at->format('d M Y, H:i') }} WIB">{{ $review->reviewed_at->diffForHumans() }}</span>
                            @endif
                        </div>
                        <p class="font-body-sm text-body-sm text-on-surface-variant [overflow-wrap:anywhere]">{{ $review->review_text ? '“'.$review->review_text.'”' : 'Tanpa teks, hanya bintang.' }}</p>

                        <div class="mt-auto flex flex-wrap gap-x-space-md gap-y-1 pt-1">
                            <form method="POST" action="{{ route('staff.site.reviews.toggle', $review) }}">
                                @csrf
                                <button type="submit" class="press-scale inline-flex items-center gap-1 font-label-md text-label-md text-primary">
                                    <span class="material-symbols-outlined text-[16px]">{{ $review->review_hidden_at ? 'visibility' : 'visibility_off' }}</span>{{ $review->review_hidden_at ? 'Tampilkan' : 'Sembunyikan' }}
                                </button>
                            </form>
                            @if ($review->reviewPhoto)
                                <form method="POST" action="{{ route('staff.site.photos.remove', $review->reviewPhoto) }}" data-confirm="Foto dihapus permanen. Bintang dan teks ulasan tetap ada." data-confirm-title="Turunkan foto ini?" data-confirm-label="Turunkan" data-cancel-label="Biarkan" data-confirm-tone="danger">
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
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="mt-space-md">
            {{ $reviews->links() }}
        </div>
    @endif

@endsection
