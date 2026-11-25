<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>SFX lab | Brread!</title>
    @include('partials.favicon')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .lab { max-width: 960px; margin: 0 auto; padding: 24px 16px 64px; font-family: system-ui, sans-serif; color: #3b2a30; }
        .lab h1 { font-size: 1.6rem; font-weight: 800; margin: 0 0 4px; }
        .lab p.note { margin: 0 0 20px; color: #7a6168; font-size: .9rem; }
        .lab-bar { display: flex; flex-wrap: wrap; gap: 12px 24px; align-items: center; padding: 14px 16px; border: 2px solid #3b2a30; border-radius: 14px; background: #fff7f2; margin-bottom: 24px; position: sticky; top: 8px; z-index: 5; }
        .lab-bar label { display: flex; align-items: center; gap: 8px; font-size: .9rem; font-weight: 600; }
        .lab h2 { font-size: 1.05rem; font-weight: 800; margin: 24px 0 8px; }
        .lab-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 10px; }
        .lab-card { border: 2px solid #3b2a30; border-radius: 12px; padding: 10px 12px; background: #fff; display: flex; flex-direction: column; gap: 6px; }
        .lab-card b { font-size: .95rem; }
        .lab-card small { color: #7a6168; font-size: .75rem; line-height: 1.3; min-height: 2em; }
        .lab-card .row { display: flex; gap: 6px; }
        .lab button { font: inherit; font-weight: 700; font-size: .85rem; padding: 6px 12px; border: 2px solid #3b2a30; border-radius: 10px; background: #f7a3b6; color: #3b2a30; cursor: pointer; }
        .lab button.alt { background: #fff; }
        .lab button:active { transform: translateY(1px); }
        .lab button:focus-visible { outline: 3px solid #3b2a30; outline-offset: 2px; }
        .lab-level { font-size: .75rem; color: #3b2a30; font-variant-numeric: tabular-nums; min-height: 1.2em; }
        .lab-level.hot { color: #b3261e; font-weight: 700; }
        .lab-status { font-size: .85rem; font-weight: 600; min-height: 1.2em; }
    </style>
</head>
<body class="lab-page" style="background:#fff3ec">
<main class="lab" id="lab">
    <h1>SFX lab</h1>
    <p class="note">Dev only. Every sound is built live in the browser. Press play, or measure the level of everything offline.</p>

    <div class="lab-bar">
        <label>Master
            <input type="range" id="lab-volume" min="0" max="1" step="0.01" value="0.6" aria-label="Master volume">
            <span id="lab-volume-text">0.60</span>
        </label>
        <label><input type="checkbox" id="lab-small"> Small laptop speaker (lowpass 8 kHz, highpass 150 Hz)</label>
        <button type="button" id="lab-measure">Measure levels</button>
        <button type="button" id="lab-copy" class="alt">Copy summary</button>
        <span class="lab-status" id="lab-status" role="status"></span>
    </div>

    <div id="lab-groups"></div>
</main>

@verbatim
<script>
(function () {
    var GROUPS = [
        ['Clicks', ['click', 'tock', 'tick', 'softtick', 'tockup', 'tockdown', 'blipup', 'blipdown', 'pop', 'popsoft', 'plop']],
        ['Hover', ['hover']],
        ['Materials', ['thump', 'clink', 'snap', 'puf', 'scoop', 'sprinkle', 'squish', 'gummy', 'drizzle', 'swish', 'whoosh', 'poof']],
        ['Confirmations', ['ding', 'bell', 'unlock', 'register', 'chime', 'marimba', 'sparkle', 'plopding']]
    ];
    var measured = {};
    var sfx = null;
    var status = document.getElementById('lab-status');

    function say(text) { status.textContent = text; }
    function db(x) { return 20 * Math.log10(Math.max(x, 1e-9)); }

    function ready(fn) {
        var tries = 0;
        (function wait() {
            sfx = window.brreadSfx;
            if (sfx && sfx.render) return fn();
            if (++tries > 100) return say('The sound engine did not load.');
            setTimeout(wait, 50);
        })();
    }

    function start() {
        var info = sfx.describe();
        var holder = document.getElementById('lab-groups');

        GROUPS.forEach(function (group) {
            var h = document.createElement('h2');
            h.textContent = group[0];
            var grid = document.createElement('div');
            grid.className = 'lab-grid';

            group[1].forEach(function (name) {
                if (!info[name]) return;
                var card = document.createElement('div');
                card.className = 'lab-card';
                card.innerHTML = '<b></b><small></small><div class="row"></div><div class="lab-level" data-level="' + name + '"></div>';
                card.querySelector('b').textContent = name;
                card.querySelector('small').textContent = info[name].text;
                var row = card.querySelector('.row');

                var play = document.createElement('button');
                play.type = 'button';
                play.setAttribute('data-sfx', 'none');
                play.textContent = 'Play';
                play.addEventListener('click', function () { sfx.context(); sfx.play(name); });

                var many = document.createElement('button');
                many.type = 'button';
                many.className = 'alt';
                many.setAttribute('data-sfx', 'none');
                many.textContent = 'Play 8x';
                many.addEventListener('click', function () {
                    sfx.context();
                    for (var i = 0; i < 8; i++) setTimeout(function () { sfx.play(name); }, i * 140);
                });

                row.appendChild(play);
                row.appendChild(many);
                grid.appendChild(card);
            });

            holder.appendChild(h);
            holder.appendChild(grid);
        });

        document.getElementById('lab-volume').addEventListener('input', function (e) {
            sfx.setVolume(e.target.value);
            document.getElementById('lab-volume-text').textContent = Number(e.target.value).toFixed(2);
        });
        document.getElementById('lab-small').addEventListener('change', function (e) {
            sfx.context();
            sfx.setSmallSpeaker(e.target.checked);
        });

        document.getElementById('lab-measure').addEventListener('click', async function () {
            say('Measuring...');
            for (var i = 0; i < GROUPS.length; i++) {
                for (var j = 0; j < GROUPS[i][1].length; j++) {
                    var name = GROUPS[i][1][j];
                    if (!info[name]) continue;
                    var peak = 0, energy = 0, count = 0;
                    for (var run = 0; run < 4; run++) {
                        var buffer = await sfx.render(name);
                        if (!buffer) continue;
                        var data = buffer.getChannelData(0), last = 0, e = 0;
                        for (var k = 0; k < data.length; k++) {
                            var a = Math.abs(data[k]);
                            if (a > peak) peak = a;
                            e += data[k] * data[k];
                            if (a > 0.001) last = k;
                        }
                        energy += Math.sqrt(e / Math.max(last, 1));
                        count++;
                    }
                    measured[name] = { peak: db(peak), rms: db(energy / Math.max(count, 1)) };
                    var cell = document.querySelector('[data-level="' + name + '"]');
                    if (cell) {
                        cell.textContent = 'peak ' + measured[name].peak.toFixed(1) + ' dBFS, rms ' + measured[name].rms.toFixed(1);
                        cell.classList.toggle('hot', measured[name].peak > -1);
                    }
                }
            }
            say('Done. Peaks above -1 dBFS are marked red.');
        });

        document.getElementById('lab-copy').addEventListener('click', function () {
            var lines = ['SFX summary', 'master ' + document.getElementById('lab-volume').value + ', small speaker ' + (document.getElementById('lab-small').checked ? 'on' : 'off'), ''];
            GROUPS.forEach(function (group) {
                lines.push(group[0]);
                group[1].forEach(function (name) {
                    if (!info[name]) return;
                    var m = measured[name];
                    lines.push('- ' + name + ' (' + Math.round(info[name].duration * 1000) + ' ms): ' + info[name].text + (m ? ', peak ' + m.peak.toFixed(1) + ' dBFS, rms ' + m.rms.toFixed(1) : ''));
                });
                lines.push('');
            });
            var text = lines.join('\n');
            (navigator.clipboard ? navigator.clipboard.writeText(text) : Promise.reject()).then(function () {
                say('Summary copied.');
            }, function () {
                window.prompt('Copy this summary:', text);
            });
        });
    }

    ready(start);
})();
</script>
@endverbatim
</body>
</html>
