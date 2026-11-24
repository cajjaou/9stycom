</div>

<footer>© 2026 9sty.com — جميع الحقوق محفوظة</footer>

<script>
/**
 * Kayjib banners f l khalfiya (async), mora ma tal3at l page. Chi
 * fonction f les 3 (feed/sidebar/inline) kaydir ghir ila l slots
 * dyalha kaynin f had page — bla conflit bin home/post/category.
 */
document.addEventListener('DOMContentLoaded', function () {
    fetch('/banners-api.php')
        .then(function (res) { return res.json(); })
        .then(function (products) {
            if (!products || !products.length) {
                document.querySelectorAll('.jb-slot').forEach(function (el) { el.remove(); });
                return;
            }
            // Shuffle products in JavaScript for guaranteed freshness on every page load
            products.sort(function () { return Math.random() - 0.5; });
            window.jbProducts = products; // Store globally for infinite scroll
            fillFeedSlots(products);
            fillSidebarSlots(products);
            fillInlineSlots(products);
        })
        .catch(function (err) {
            console.error('jb banners failed to load:', err);
            document.querySelectorAll('.jb-slot').forEach(function (el) { el.remove(); });
        });

    function pickProduct(products, i) {
        return products[i % products.length];
    }

    function priceHtml(p) {
        if (!p.price) return '';
        var promo = p.promo_price ? '<s>' + p.promo_price + ' MAD</s> ' : '';
        return promo + p.price + ' MAD';
    }

    function trackUrl(productId, postId) {
        var data = {
            p: productId,
            art: postId || '',
            u: location.href
        };
        var jsonStr = JSON.stringify(data);
        var encoded = btoa(unescape(encodeURIComponent(jsonStr)))
            .replace(/\+/g, '-')
            .replace(/\//g, '_')
            .replace(/=+$/, '');
        return '/go/JBpid' + encoded;
    }

    function fillFeedSlots(products) {
        var slots = document.querySelectorAll('.feed [data-jb-slot]:not(.jb-loaded)');
        if (!slots.length) return;
        slots.forEach(function (slot) {
            var slotIndex = parseInt(slot.getAttribute('data-jb-slot'), 10) || 0;
            var p = pickProduct(products, slotIndex);
            slot.classList.add('sponsored');
            
            var badgeText = p.tag || (p.price ? 'إعلان' : 'موصى به');
            var ctaText = 'اقرأ المزيد ←';
            if (p.price && parseFloat(p.price) > 0) {
                ctaText = 'شري دابا ←';
            }
            
            slot.innerHTML =
                '<a href="' + trackUrl(p.id) + '" target="_blank" rel="nofollow sponsored" data-product-id="' + p.id + '">' +
                '  <div class="thumb">' +
                '    <img src="' + p.image + '" alt="' + p.name + '" loading="lazy" onerror="this.onerror=null; this.src=\'https://cdn.9sty.com/uploads/no-images-news.webp\';">' +
                '    <span class="badge sponsored-badge">' + badgeText + '</span>' +
                '  </div>' +
                '  <div class="post-body">' +
                '    <h3>' + p.name + '</h3>' +
                '    <div class="post-meta">' +
                '      <span class="price">' + priceHtml(p) + '</span>' +
                '      <span class="cta">' + ctaText + '</span>' +
                '    </div>' +
                '  </div>' +
                '</a>';
            slot.classList.add('jb-loaded');
        });
    }
    window.fillFeedSlots = fillFeedSlots; // Expose globally for infinite scroll

    function fillSidebarSlots(products) {
        var slots = document.querySelectorAll('#jb-sidebar-promos [data-jb-sidebar-slot]');
        if (!slots.length) return;
        slots.forEach(function (slot, i) {
            var p = pickProduct(products, i + 3);
            slot.innerHTML =
                '<a href="' + trackUrl(p.id) + '" target="_blank" rel="nofollow sponsored" data-product-id="' + p.id + '" style="display:flex;gap:10px;">' +
                '  <img src="' + p.image + '" alt="" onerror="this.onerror=null; this.src=\'https://cdn.9sty.com/uploads/no-images-news.webp\';" style="width:64px;height:64px;object-fit:cover;border-radius:4px;flex-shrink:0;">' +
                '  <div>' +
                '    <h4>' + p.name + '</h4>' +
                '    <span class="price">' + priceHtml(p) + '</span>' +
                '  </div>' +
                '</a>';
            slot.classList.add('jb-loaded');
        });
    }

    function fillInlineSlots(products) {
        var slots = document.querySelectorAll('[data-jb-inline-slot]:not(.jb-loaded)');
        if (!slots.length) return;
        // shuffle bach l ikhtiyar dyal produit ykon 3achwa2i f kol load
        var pool = products.slice().sort(function () { return Math.random() - 0.5; });
        slots.forEach(function (slot, i) {
            var postContainer = slot.closest('.single-post-container');
            var postId = postContainer ? (postContainer.getAttribute('data-post-id') || '') : '';
            var p = pool[i % pool.length];
            
            var priceDisplay = '';
            var ctaText = 'اقرأ المزيد ←';
            if (p.price && parseFloat(p.price) > 0) {
                var promo = p.promo_price ? '<s>' + p.promo_price + ' MAD</s> ' : '';
                priceDisplay = '<span class="price">' + promo + p.price + ' MAD</span>';
                ctaText = 'شري دابا ←';
            }
            var tagText = p.tag || (p.price ? 'إعلان' : 'موصى به');

            slot.innerHTML =
                '<a href="' + trackUrl(p.id, postId) + '" target="_blank" rel="nofollow sponsored" data-product-id="' + p.id + '" style="display:flex;align-items:center;gap:14px;width:100%;">' +
                '  <img src="' + p.image + '" alt="' + p.name + '" onerror="this.onerror=null; this.src=\'https://cdn.9sty.com/uploads/no-images-news.webp\';">' +
                '  <div class="body">' +
                '    <span class="tag">' + tagText + '</span>' +
                '    <h4>' + p.name + '</h4>' +
                '    ' + priceDisplay +
                '  </div>' +
                '  <span class="cta">' + ctaText + '</span>' +
                '</a>';
            slot.classList.add('jb-loaded');
        });
    }
    window.fillInlineSlots = fillInlineSlots; // Expose globally for single post infinite scroll
});
</script>

</body>
</html>
