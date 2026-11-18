{{-- A browser that set off the lockdown is pulled back to it from any page of the shop until
     the lock runs out. Runs before the page paints, and again when Back restores a page from
     the browser's cache. --}}
<script>
    (function () {
        function check(restored) {
            try {
                var lock = JSON.parse(sessionStorage.getItem('brread-lock') || localStorage.getItem('brread-lock') || 'null');
                if (!lock || lock.released || !(lock.until > Date.now())) return;
                var here = location.pathname + location.search;
                if (here !== lock.path) {
                    document.documentElement.style.background = '#000';
                    location.replace(lock.path);
                } else if (restored) {
                    location.reload();
                }
            } catch (e) {}
        }
        // The Kewer Kewer pause (the fourth wrong password) follows the browser to every page of the
        // shop too: until it runs out, any address sends it back to the sign-in page that set it off.
        function prank(restored) {
            try {
                var hold = JSON.parse(localStorage.getItem('brread-prank') || sessionStorage.getItem('brread-prank') || 'null');
                if (!hold || !(hold.until > Date.now())) return;
                if (location.pathname !== hold.path) {
                    try { sessionStorage.setItem('brread-bounce', '1'); } catch (e) {}
                    document.documentElement.style.background = '#000';
                    location.replace(hold.path);
                } else if (restored) {
                    location.reload();
                }
            } catch (e) {}
        }
        // Arriving from the "access granted" screen: its strawberry sheet is still over the screen
        // here, so the same pink is laid down at once and then melts on down, revealing the menu.
        function arrive() {
            try {
                var at = Number(sessionStorage.getItem('brread-arrive'));
                sessionStorage.removeItem('brread-arrive');
                if (!at || Date.now() - at > 20000 || matchMedia('(prefers-reduced-motion: reduce)').matches) return;

                var w = Math.max(innerWidth, 320), d = 'M0,0H' + w + 'V22', x = w, tips = '';
                while (x > 0) {
                    var r = 9 + Math.random() * 11, len = 14 + Math.random() * 62, gap = 34 + Math.random() * 120;
                    x -= gap;
                    tips += '<line x1="' + x + '" y1="10" x2="' + x + '" y2="' + (22 + len) + '" stroke="#f7a3b6" stroke-width="' + (r * 2) + '" stroke-linecap="round"/>';
                }
                var style = document.createElement('style');
                style.textContent = '@keyframes brread-arrive{0%,28%{transform:none}100%{transform:translateY(calc(100% + 130px))}}'
                    + '#brread-arrive{position:fixed;left:0;top:0;width:100%;height:100vh;z-index:2147483000;background:#f7a3b6;pointer-events:none;animation:brread-arrive 1100ms cubic-bezier(.65,0,.35,1) 150ms forwards}'
                    + '#brread-arrive svg{position:absolute;left:0;top:calc(100% - 1px);width:100%;height:130px}';
                var cover = document.createElement('div');
                cover.id = 'brread-arrive';
                cover.setAttribute('aria-hidden', 'true');
                cover.innerHTML = '<svg viewBox="0 0 ' + w + ' 130" preserveAspectRatio="none"><path d="M0,0H' + w + 'V22H0Z" fill="#f7a3b6"/>' + tips + '</svg>';
                document.documentElement.append(style, cover);
                var drop = function () { cover.remove(); style.remove(); };
                cover.addEventListener('animationend', drop);
                setTimeout(drop, 3000);
            } catch (e) {}
        }
        arrive();
        check(false);
        prank(false);
        addEventListener('pageshow', function (event) {
            if (event.persisted) { check(true); prank(true); }
        });
    })();
</script>
