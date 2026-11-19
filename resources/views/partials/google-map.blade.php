@php
    $maps = config('services.google_maps');
    $key = $maps['key'] ?? null;
    $query = $maps['query'];
    $zoom = $maps['zoom'];

    // With a key: the official Maps Embed API. Without: the keyless embed, same map, no API quota.
    $src = $key
        ? 'https://www.google.com/maps/embed/v1/place?'.http_build_query(['key' => $key, 'q' => $query, 'zoom' => $zoom, 'language' => 'en', 'region' => 'ID'])
        : 'https://maps.google.com/maps?'.http_build_query(['q' => $query, 'z' => $zoom, 'hl' => 'en', 'output' => 'embed']);
@endphp
<iframe
    src="{{ $src }}"
    title="Google Map showing the Brread! counter at Telkom University Jakarta, Kampus C Minangkabau"
    class="contact-map-frame"
    loading="lazy"
    referrerpolicy="strict-origin-when-cross-origin"
    allowfullscreen
></iframe>
