{{--
    $title     card heading
    $note      optional line under the heading
    $owners    list of ['name' => ?string, 'orders' => int, 'sandwiches' => int, 'revenue' => float, 'share' => float], busiest first
    $emptyName label for orders where nobody was recorded
--}}
<section class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 p-space-md">
    <h2 class="font-title-md text-title-md text-on-surface">{{ $title }}</h2>
    @isset($note)
        <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5">{{ $note }}</p>
    @endisset

    <ul class="mt-space-sm space-y-space-sm">
        @foreach ($owners as $owner)
            <li>
                <div class="flex items-baseline justify-between gap-space-sm">
                    <span class="font-label-lg text-label-lg min-w-0 {{ $owner['name'] === null ? 'text-on-surface-variant' : 'text-on-surface' }}">{{ $owner['name'] ?? $emptyName }}</span>
                    <span class="font-body-sm text-body-sm text-on-surface-variant tabular-nums whitespace-nowrap">{{ number_format($owner['orders'], 0, ',', '.') }} {{ Str::plural('order', $owner['orders']) }} · {{ round($owner['share']) }}%</span>
                </div>
                <div class="mt-1 h-2 rounded-full bg-surface-container">
                    <div class="h-2 rounded-full {{ $owner['name'] === null ? 'bg-outline-variant' : 'bg-primary-container' }}" style="width: {{ max($owner['share'], 1) }}%"></div>
                </div>
                <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant tabular-nums">{{ number_format($owner['sandwiches'], 0, ',', '.') }} sandwiches · Rp {{ number_format($owner['revenue'], 0, ',', '.') }}</p>
            </li>
        @endforeach
    </ul>
</section>
