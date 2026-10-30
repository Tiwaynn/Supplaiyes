(function () {
  'use strict';
  var S = window.SPL || {};
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var esc = function (s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  };

  function toast(msg, type) {
    var box = $('#toasts');
    if (!box) return;
    var d = document.createElement('div');
    d.className = 'max-w-xs rounded-lg px-4 py-3 text-sm shadow-lg text-white ' + (type === 'err' ? 'bg-red-600' : 'bg-green-700');
    d.textContent = msg;
    box.appendChild(d);
    setTimeout(function () { d.remove(); }, 4500);
  }
  window.splToast = toast;

  function post(url, data) {
    var fd = new FormData();
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    fd.append('csrf', S.csrf);
    return fetch(S.base + url, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }

  /* ---------- Modal reservasi ---------- */
  var modal = $('#qty-modal'), cur = null;
  function openReserve(id, name, stock) {
    if (!modal) return;
    cur = { id: id, stock: stock };
    var max = Math.max(1, Math.min(S.maxQty, stock));
    $('#qty-food').textContent = name;
    var inp = $('#qty-input');
    inp.max = max; inp.value = 1;
    $('#qty-hint').textContent = 'Maksimal ' + max + ' porsi per reservasi.';
    modal.classList.remove('hidden'); modal.classList.add('flex');
    inp.focus();
  }
  function closeModal() { if (modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); } }
  if (modal) {
    $('#qty-cancel').addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
    $('#qty-ok').addEventListener('click', function () {
      var btn = this; btn.disabled = true;
      post('api/reserve.php', { food_id: cur.id, qty: $('#qty-input').value })
        .then(function (d) {
          if (d.ok) {
            toast('Berhasil! Kode reservasi: ' + d.code);
            closeModal();
            if (typeof window.splReload === 'function') window.splReload();
            if (document.body.dataset.page === 'food') setTimeout(function () { location.reload(); }, 900);
          } else {
            toast(d.message || 'Reservasi gagal.', 'err');
            if (typeof window.splReload === 'function') window.splReload();
          }
        })
        .catch(function () { toast('Koneksi bermasalah.', 'err'); })
        .then(function () { btn.disabled = false; });
    });
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-reserve]');
    if (b) { openReserve(b.dataset.reserve, b.dataset.name, parseInt(b.dataset.stock, 10) || 1); }
    var c = e.target.closest('[data-cancel]');
    if (c) {
      if (!confirm('Batalkan reservasi ini?')) return;
      post('api/cancel.php', { reservation_id: c.dataset.cancel }).then(function (d) {
        toast(d.message || (d.ok ? 'Dibatalkan.' : 'Gagal.'), d.ok ? 'ok' : 'err');
        if (d.ok) setTimeout(function () { location.reload(); }, 700);
      });
    }
  });
  document.addEventListener('submit', function (e) {
    var f = e.target.closest('form[data-confirm]');
    if (f && !confirm(f.dataset.confirm)) e.preventDefault();
  });

  /* ---------- Katalog user (polling) ---------- */
  var cat = $('#catalog');
  if (cat) {
    var form = $('#filters'), t;
    var render = function (items) {
      if (!items.length) {
        cat.innerHTML = '<div class="col-span-full rounded-xl border border-dashed border-gray-300 p-10 text-center text-gray-500">Belum ada makanan yang cocok dengan pencarian Anda.</div>';
        return;
      }
      cat.innerHTML = items.map(function (f) {
        var can = f.status === 'available' && f.stock > 0;
        var normal = f.normal_price ? '<span class="mr-1 text-xs text-gray-400 line-through">' + esc(f.normal_price_label) + '</span>' : '';
        return '<article class="flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">' +
          '<div class="flex h-28 items-center justify-center bg-gradient-to-br from-green-100 to-green-50 text-5xl">' + esc(f.icon) + '</div>' +
          '<div class="flex flex-1 flex-col p-4">' +
            '<div class="flex flex-wrap items-center gap-1.5">' +
              '<span class="' + esc(f.status_class) + ' rounded-full px-2.5 py-0.5 text-xs font-semibold">' + esc(f.status_label) + '</span>' +
              (f.priority ? '<span class="rounded-full bg-orange-100 px-2.5 py-0.5 text-xs font-semibold text-orange-800">⏱ Prioritas Distribusi</span>' : '') +
            '</div>' +
            '<h3 class="mt-2 font-bold text-gray-900">' + esc(f.name) + '</h3>' +
            '<p class="text-xs text-gray-500">' + esc(f.category) + ' · ' + esc(f.size_label) + '</p>' +
            '<p class="mt-2">' + normal + '<span class="text-lg font-extrabold text-green-700">' + esc(f.price_label) + '</span></p>' +
            '<dl class="mt-2 space-y-0.5 text-xs text-gray-600">' +
              '<div>Stok tersedia: <b>' + esc(f.stock) + '</b></div>' +
              '<div>Batas distribusi: <b>' + esc(f.deadline_label) + '</b></div>' +
              '<div>Sisa waktu: <b>' + esc(f.time_left) + '</b></div>' +
            '</dl>' +
            '<div class="mt-4 flex gap-2 pt-1">' +
              '<a href="' + S.base + 'food.php?id=' + encodeURIComponent(f.id) + '" class="flex-1 rounded-lg border border-green-700 px-3 py-2 text-center text-sm font-semibold text-green-700 hover:bg-green-50">Lihat Detail</a>' +
              '<button ' + (can ? '' : 'disabled ') + 'data-reserve="' + esc(f.id) + '" data-name="' + esc(f.name) + '" data-stock="' + esc(f.stock) + '" class="flex-1 rounded-lg px-3 py-2 text-sm font-semibold text-white ' + (can ? 'bg-green-700 hover:bg-green-800' : 'cursor-not-allowed bg-gray-300') + '">Reservasi</button>' +
            '</div>' +
          '</div></article>';
      }).join('');
    };
    var load = function () {
      var qs = new URLSearchParams(new FormData(form)).toString();
      return fetch(S.base + 'api/foods.php?' + qs, { credentials: 'same-origin' })
        .then(function (r) { if (r.status === 401) { location = S.base + 'login.php'; throw 0; } return r.json(); })
        .then(function (d) {
          render(d.items || []);
          var lu = $('#last-update'); if (lu) lu.textContent = 'Diperbarui ' + new Date().toLocaleTimeString('id-ID');
        }).catch(function () {});
    };
    window.splReload = load;
    form.addEventListener('input', function () { clearTimeout(t); t = setTimeout(load, 250); });
    form.addEventListener('submit', function (e) { e.preventDefault(); load(); });
    load();
    setInterval(function () { if (!document.hidden) load(); }, S.poll);
  }

  /* ---------- Admin: statistik & inventaris (polling) ---------- */
  var stats = document.querySelectorAll('[data-stat]');
  if (stats.length) {
    var loadStats = function () {
      fetch(S.base + 'api/admin_stats.php', { credentials: 'same-origin' }).then(function (r) { return r.json(); })
        .then(function (d) { stats.forEach(function (el) { if (d[el.dataset.stat] != null) el.textContent = d[el.dataset.stat]; }); })
        .catch(function () {});
    };
    setInterval(function () { if (!document.hidden) loadStats(); }, S.poll);
  }
  var body = $('#inv-body');
  if (body) {
    setInterval(function () {
      if (document.hidden) return;
      fetch(S.base + 'api/admin_inventory.php' + location.search, { credentials: 'same-origin' })
        .then(function (r) { return r.text(); })
        .then(function (html) { body.innerHTML = html; })
        .catch(function () {});
    }, S.poll);
  }
})();
