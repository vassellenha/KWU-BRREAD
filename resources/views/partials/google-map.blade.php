@php
    $maps = config('services.google_maps');
    $key = $maps['key'] ?? null;
    $query = \App\Support\ShopSettings::get()['maps_query'];
    $zoom = $maps['zoom'];

    // With a key: the official Maps Embed API. Without: the keyless embed, same map, no API quota.
    $src = $key
        ? 'https://www.google.com/maps/embed/v1/place?'.http_build_query(['key' => $key, 'q' => $query, 'zoom' => $zoom, 'language' => 'en', 'region' => 'ID'])
        : 'https://maps.google.com/maps?'.http_build_query(['q' => $query, 'z' => $zoom, 'hl' => 'en', 'output' => 'embed']);
@endphp
<iframe
    src="{{ $src }}"
    title="Google Map showing the Brread! counter at {{ \App\Support\ShopSettings::get()['address_short'] }}"
    class="contact-map-frame"
    loading="lazy"
    referrerpolicy="strict-origin-when-cross-origin"
    allowfullscreen
></iframe>
