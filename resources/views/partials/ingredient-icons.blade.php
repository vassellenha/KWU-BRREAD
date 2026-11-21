{{--
    Hand-drawn ingredient icons (24 x 24), included once per page by layouts/app.blade.php so any Blade view
    or Alpine template can draw one with <svg class="ing-icon"><use href="#ing-..."/></svg> without a request.
    Outlines follow currentColor; the fills are two tones from the brand palette and the menu's own colours,
    plus one highlight. Toppings differ by shape (drips, rods, cylinders, bears), not only by colour.
    The names they belong to live in App\Support\IngredientIcon and INGREDIENT_ICONS in resources/js/app.js.
--}}
<svg class="ing-sprite" aria-hidden="true" focusable="false" width="0" height="0" style="position:absolute;width:0;height:0;overflow:hidden">
    <defs>
        {{-- Chocolate Sauce: a poured band with three drips of different lengths and a thin swirl through it --}}
        <symbol id="ing-chocolate-sauce" viewBox="0 0 24 24">
            <g class="ing-part ing-part--sauce">
                <path fill="#3d1b13" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round" d="M3 6.8C3 5.2 4.3 4 5.8 4h12.4C19.8 4 21 5.2 21 6.8c0 1.1-.6 2-1.6 2.4v5.6a2 2 0 0 1-4 0V9.4h-1.6v2.4a2 2 0 0 1-4 0V9.4H8.4v8.8a2.3 2.3 0 0 1-4.6 0V9.1C3.3 8.5 3 7.7 3 6.8z"/>
                <path fill="#6b3a24" d="M5.8 5.2h12.4c.9 0 1.6.7 1.6 1.6s-.7 1.4-1.6 1.4H5.8c-.9 0-1.6-.6-1.6-1.4s.7-1.6 1.6-1.6z"/>
                <path fill="none" stroke="#fff" stroke-opacity=".8" stroke-width="1.1" stroke-linecap="round" d="M6 6.7c1.3-.9 2.6.9 3.9 0s2.6-.9 3.9 0 2.6.9 3.9 0"/>
                <ellipse cx="5.6" cy="16.4" rx=".75" ry="1.1" fill="#fff" fill-opacity=".55"/>
            </g>
        </symbol>

        {{-- Rainbow Sprinkles: five rounded rods lying at different angles --}}
        <symbol id="ing-rainbow-sprinkles" viewBox="0 0 24 24">
            <g class="ing-part ing-part--sprinkles" stroke="currentColor" stroke-width="1.1">
                <rect x="2.4" y="4.6" width="9" height="3.4" rx="1.7" fill="#c74659" transform="rotate(-28 6.9 6.3)"/>
                <rect x="13" y="3.4" width="8.4" height="3.4" rx="1.7" fill="#5d787d" transform="rotate(38 17.2 5.1)"/>
                <rect x="9.4" y="10.2" width="6.6" height="3.4" rx="1.7" fill="#f4b6c2" transform="rotate(84 12.7 11.9)"/>
                <rect x="2.6" y="15" width="8.6" height="3.4" rx="1.7" fill="#5d787d" transform="rotate(48 6.9 16.7)"/>
                <rect x="13.2" y="15.6" width="8.8" height="3.4" rx="1.7" fill="#c74659" transform="rotate(-16 17.6 17.3)"/>
            </g>
            <g fill="none" stroke="#fff" stroke-opacity=".75" stroke-width=".9" stroke-linecap="round">
                <path d="M4.3 6.4l3.6-1.9"/>
                <path d="M15.1 3.6l2.5 2"/>
                <path d="M14.9 16.6l3.6-1"/>
            </g>
        </symbol>

        {{-- Marshmallow Mini: one standing in cream, one lying in pastel pink, each with a lighter round face --}}
        <symbol id="ing-marshmallow-mini" viewBox="0 0 24 24">
            <g class="ing-part ing-part--marshmallow" stroke="currentColor" stroke-width="1.15" stroke-linejoin="round">
                <path fill="#f4ebdd" d="M2.6 6.4v7.4c0 1.3 2.2 2.4 4.9 2.4s4.9-1.1 4.9-2.4V6.4"/>
                <ellipse cx="7.5" cy="6.4" rx="4.9" ry="2.3" fill="#fffaf2"/>
                <g transform="rotate(-14 15.2 16.4)">
                    <rect x="8.4" y="12.2" width="11" height="8.4" rx="2.6" fill="#f4b6c2"/>
                    <ellipse cx="18.9" cy="16.4" rx="2.4" ry="4.2" fill="#fbd9e0"/>
                </g>
            </g>
            <g fill="none" stroke="#fff" stroke-opacity=".85" stroke-width="1" stroke-linecap="round">
                <path d="M4.6 10.2v2.8"/>
                <path d="M10.6 14.9l4.6-1.1"/>
            </g>
        </symbol>

        {{-- Yupi Gummy Bear Mini: a jelly bear from the front (ears, head, arms, body, feet) and a smaller one behind it.
             Each bear is drawn twice: outlined first, then filled on top, so only its outer edge carries the line. --}}
        <symbol id="ing-yupi-gummy-bear-mini" viewBox="0 0 24 24">
            <g class="ing-part ing-part--bear-back" transform="translate(13.9 .6) scale(.6)">
                <g stroke="currentColor" stroke-width="3.6" fill="currentColor">
                    <circle cx="6.8" cy="4.6" r="1.8"/><circle cx="14.2" cy="4.6" r="1.8"/><circle cx="10.5" cy="7.8" r="3.6"/>
                    <ellipse cx="10.5" cy="14.8" rx="4.1" ry="4.5"/><ellipse cx="8.2" cy="19.6" rx="2" ry="1.7"/><ellipse cx="12.8" cy="19.6" rx="2" ry="1.7"/>
                </g>
                <g fill="#afccd1">
                    <circle cx="6.8" cy="4.6" r="1.8"/><circle cx="14.2" cy="4.6" r="1.8"/><circle cx="10.5" cy="7.8" r="3.6"/>
                    <ellipse cx="10.5" cy="14.8" rx="4.1" ry="4.5"/><ellipse cx="8.2" cy="19.6" rx="2" ry="1.7"/><ellipse cx="12.8" cy="19.6" rx="2" ry="1.7"/>
                </g>
            </g>
            <g class="ing-part ing-part--bear">
                <g stroke="currentColor" stroke-width="2.3" fill="currentColor">
                    <circle cx="6.9" cy="4.7" r="1.75"/><circle cx="13.7" cy="4.7" r="1.75"/>
                    <circle cx="10.3" cy="8" r="3.6"/>
                    <ellipse cx="6.2" cy="13.2" rx="1.5" ry="2.2" transform="rotate(32 6.2 13.2)"/>
                    <ellipse cx="14.4" cy="13.2" rx="1.5" ry="2.2" transform="rotate(-32 14.4 13.2)"/>
                    <ellipse cx="10.3" cy="15" rx="4" ry="4.4"/>
                    <ellipse cx="8" cy="19.7" rx="1.95" ry="1.6"/><ellipse cx="12.6" cy="19.7" rx="1.95" ry="1.6"/>
                </g>
                <g fill="#e4472f">
                    <circle cx="6.9" cy="4.7" r="1.75"/><circle cx="13.7" cy="4.7" r="1.75"/>
                    <circle cx="10.3" cy="8" r="3.6"/>
                    <ellipse cx="6.2" cy="13.2" rx="1.5" ry="2.2" transform="rotate(32 6.2 13.2)"/>
                    <ellipse cx="14.4" cy="13.2" rx="1.5" ry="2.2" transform="rotate(-32 14.4 13.2)"/>
                    <ellipse cx="10.3" cy="15" rx="4" ry="4.4"/>
                    <ellipse cx="8" cy="19.7" rx="1.95" ry="1.6"/><ellipse cx="12.6" cy="19.7" rx="1.95" ry="1.6"/>
                </g>
                <ellipse cx="10.3" cy="15.8" rx="2.4" ry="2.7" fill="#f07a52"/>
                <ellipse cx="9" cy="6.7" rx="1.2" ry=".75" fill="#fff" fill-opacity=".85" transform="rotate(-24 9 6.7)"/>
                <path fill="none" stroke="#fff" stroke-opacity=".7" stroke-width=".9" stroke-linecap="round" d="M7.6 13.1c.2-1 .8-1.7 1.6-2.1"/>
            </g>
        </symbol>

        {{-- Flavor swatches that replace the plain colour dot for the multi-part flavors. Each fills a 22 px circle. --}}
        <symbol id="ing-matcha" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="11" fill="#8a9f5b"/>
            <path fill="none" stroke="#c3cf9b" stroke-width="2.4" stroke-linecap="round" stroke-dasharray=".1 1.6" d="M3.6 8.8A9.2 9.2 0 0 1 12 2.8"/>
            <g fill="#4f6b33">
                <circle cx="8" cy="11" r=".85"/><circle cx="12.6" cy="8.2" r=".6"/><circle cx="15.8" cy="12.4" r=".9"/><circle cx="10.4" cy="15.6" r=".7"/>
                <circle cx="14.2" cy="17.2" r=".55"/><circle cx="17.6" cy="8.6" r=".55"/><circle cx="6.6" cy="16" r=".5"/><circle cx="12.4" cy="12.2" r=".45"/>
            </g>
            <path fill="none" stroke="#fff" stroke-opacity=".55" stroke-width="1.4" stroke-linecap="round" d="M5.2 7.4A8 8 0 0 1 9 4.2"/>
        </symbol>

        <symbol id="ing-neapolitan" viewBox="0 0 24 24">
            <path fill="#f3e9d2" d="M8.33 1.63A11 11 0 0 0 8.33 22.37z"/>
            <path fill="#ee8aa3" d="M8.33 1.63A11 11 0 0 1 15.67 1.63V22.37A11 11 0 0 1 8.33 22.37z"/>
            <path fill="#5c3a21" d="M15.67 1.63A11 11 0 0 1 15.67 22.37z"/>
            <path fill="none" stroke="#fff" stroke-opacity=".6" stroke-width="1.4" stroke-linecap="round" d="M4.4 8A8.6 8.6 0 0 1 7 5"/>
        </symbol>

        <symbol id="ing-durian-musang-king" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="11" fill="#d6dc7a"/>
            <path fill="#e8b93f" stroke="#c9952a" stroke-width=".8" d="M7 8.6c1.4-1.9 4.2-1.4 4.6.6.4 1.8-1.4 3.3-3.3 3.2C6.4 12.3 5.8 10.2 7 8.6z"/>
            <path fill="#e8b93f" stroke="#c9952a" stroke-width=".8" d="M13.2 13.6c1.5-1.2 4.4-.6 4.6 1.5.2 1.9-1.9 3.2-3.7 2.7-1.9-.5-2.3-3-.9-4.2z"/>
            <circle cx="16.2" cy="8" r="1.2" fill="#e8b93f"/>
            <path fill="none" stroke="#fff" stroke-opacity=".55" stroke-width="1.4" stroke-linecap="round" d="M4.4 8A8.6 8.6 0 0 1 7 5"/>
        </symbol>

        <symbol id="ing-es-teler" viewBox="0 0 24 24">
            <path fill="#f5b82e" d="M8.33 1.63A11 11 0 0 0 8.33 22.37z"/>
            <path fill="#ef8fa6" d="M8.33 1.63A11 11 0 0 1 15.67 1.63V22.37A11 11 0 0 1 8.33 22.37z"/>
            <path fill="#a8c66c" d="M15.67 1.63A11 11 0 0 1 15.67 22.37z"/>
            <g fill="none" stroke="#fff" stroke-width=".9" stroke-linecap="round">
                <path d="M5 13.4l1.4-.9"/><path d="M11 7.8l1.6.5"/><path d="M11.4 16.4l1.2-1.1"/><path d="M17.8 11l1.2.9"/>
            </g>
            <path fill="none" stroke="#fff" stroke-opacity=".55" stroke-width="1.4" stroke-linecap="round" d="M4.4 8A8.6 8.6 0 0 1 7 5"/>
        </symbol>
    </defs>
</svg>
