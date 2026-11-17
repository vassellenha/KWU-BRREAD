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
        check(false);
        addEventListener('pageshow', function (event) { if (event.persisted) check(true); });
    })();
</script>
