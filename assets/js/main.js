const CART_KEY = 'kb_cart_v2';

function csrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
}

function buildItemKey(id, note, addons, size) {
    var notePart = (note || '').trim().toLowerCase();
    var addonsPart = (addons || []).map(function (a) { return a.id + 'x' + a.qty; }).sort().join('|');
    var sizePart = (size && size.id) ? size.id : '';
    return id + '::' + sizePart + '::' + notePart + '::' + addonsPart;
}

function formatExtra(amount) {
    var value = parseFloat(amount) || 0;
    return value > 0 ? '+' + formatPeso(value) : 'Free';
}

function formatSizeExtra(amount) {
    var value = parseFloat(amount) || 0;
    return value > 0 ? '+' + formatPeso(value) : '';
}

function getCart() {
    try {
        var raw = localStorage.getItem(CART_KEY);
        var parsed = raw ? JSON.parse(raw) : [];
        return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
        return [];
    }
}

function saveCart(cart) {
    try { localStorage.setItem(CART_KEY, JSON.stringify(cart)); } catch (e) {}
    updateCartCount();
    renderCartDrawer();
}

function addToCart(product) {
    addLineToCart({
        id: product.id,
        name: product.name,
        price: parseFloat(product.price),
        image: product.image,
        qty: 1,
        note: '',
        addons: [],
        size: null
    });
    showToast(product.name + ' added to cart');
}

function addLineToCart(item) {
    var cart = getCart();
    var key = buildItemKey(item.id, item.note, item.addons, item.size);
    var existing = cart.find(function (i) { return i.key === key; });

    if (existing) {
        existing.qty = Math.min(50, existing.qty + item.qty);
        existing.price = item.price;
    } else {
        cart.push({
            key: key,
            id: item.id,
            name: item.name,
            price: item.price,
            image: item.image,
            qty: item.qty,
            note: item.note || '',
            addons: item.addons || [],
            size: item.size || null
        });
    }
    saveCart(cart);
}

function updateQty(key, delta) {
    var cart = getCart();
    var item = cart.find(function (i) { return i.key === key; });
    if (!item) return;
    item.qty = Math.min(50, item.qty + delta);
    if (item.qty <= 0) {
        cart = cart.filter(function (i) { return i.key !== key; });
    }
    saveCart(cart);
}

function removeFromCart(key) {
    saveCart(getCart().filter(function (i) { return i.key !== key; }));
}

function cartTotal(cart) {
    return cart.reduce(function (sum, item) { return sum + (item.price * item.qty); }, 0);
}

function formatPeso(amount) {
    return '\u20B1' + Number(amount).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function updateCartCount() {
    var cart = getCart();
    var total = cart.reduce(function (sum, i) { return sum + i.qty; }, 0);
    document.querySelectorAll('#cartCount').forEach(function (el) { el.textContent = total; });
}

function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str == null ? '' : String(str);
    return div.innerHTML.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function addonText(addons) {
    return (addons || []).map(function (a) {
        return a.name + (a.qty > 1 ? ' x' + a.qty : '');
    }).join(', ');
}

function renderCartDrawer() {
    var wrap = document.getElementById('cartItemsWrap');
    var totalEl = document.getElementById('cartTotal');
    if (!wrap) return;

    var cart = getCart();

    if (cart.length === 0) {
        wrap.innerHTML = '<div class="cart-empty">Your cart is empty.<br>Add something delicious.</div>';
    } else {
        wrap.innerHTML = cart.map(function (item) {
            var sizeLine = (item.size && item.size.name)
                ? '<div class="cart-item-size">' + escapeHtml(item.size.name) + '</div>'
                : '';
            var addonsLine = (item.addons && item.addons.length)
                ? '<div class="cart-item-price" style="margin-top:2px;">+ ' + escapeHtml(addonText(item.addons)) + '</div>'
                : '';
            var noteLine = item.note
                ? '<div class="cart-item-price" style="font-style:italic; margin-top:2px;">Note: ' + escapeHtml(item.note) + '</div>'
                : '';
            var key = escapeHtml(item.key);
            return '<div class="cart-item">' +
                '<img src="' + (window.BASE_URL || '') + '/assets/images/products/' + escapeHtml(item.image) + '" alt="' + escapeHtml(item.name) + '">' +
                '<div class="cart-item-info">' +
                '<div class="cart-item-name">' + escapeHtml(item.name) + '</div>' +
                sizeLine +
                '<div class="cart-item-price">' + formatPeso(item.price) + ' each</div>' +
                addonsLine + noteLine +
                '<div class="qty-control">' +
                '<button class="qty-btn" type="button" data-cart-action="dec" data-key="' + key + '" aria-label="Decrease quantity">-</button>' +
                '<span class="qty-value">' + item.qty + '</span>' +
                '<button class="qty-btn" type="button" data-cart-action="inc" data-key="' + key + '" aria-label="Increase quantity">+</button>' +
                '</div>' +
                '<button class="remove-item-btn" type="button" data-cart-action="remove" data-key="' + key + '">Remove</button>' +
                '</div></div>';
        }).join('');
    }

    if (totalEl) totalEl.textContent = formatPeso(cartTotal(cart));

    var checkoutBtn = document.getElementById('goToCheckoutBtn');
    if (checkoutBtn) {
        if (cart.length === 0) {
            checkoutBtn.setAttribute('aria-disabled', 'true');
            checkoutBtn.style.pointerEvents = 'none';
            checkoutBtn.style.opacity = '0.5';
        } else {
            checkoutBtn.removeAttribute('aria-disabled');
            checkoutBtn.style.pointerEvents = 'auto';
            checkoutBtn.style.opacity = '1';
        }
    }

    document.dispatchEvent(new CustomEvent('cartUpdated', { detail: cart }));
}

var toastTimer = null;
function showToast(message) {
    var toast = document.getElementById('toast');
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toast.classList.remove('show'); }, 2200);
}

var modalProduct = null;
var modalQty = 1;
var modalSelectedAddons = {};
var modalSelectedSize = null;

function renderModalSizes(product) {
    var section = document.getElementById('modalSizesSection');
    var list = document.getElementById('modalSizesList');
    if (!section || !list) return;

    if (!product.sizes || product.sizes.length === 0) {
        section.classList.add('is-empty');
        list.innerHTML = '';
        modalSelectedSize = null;
        return;
    }

    section.classList.remove('is-empty');
    modalSelectedSize = product.sizes[0];

    list.innerHTML = product.sizes.map(function (size, idx) {
        var extra = formatSizeExtra(size.price);
        return '<label class="size-option' + (idx === 0 ? ' selected' : '') + '">' +
            '<input type="radio" name="kbSize" value="' + idx + '"' + (idx === 0 ? ' checked' : '') + '>' +
            '<span class="size-name">' + escapeHtml(size.name) + '</span>' +
            '<span class="size-price">' + formatPeso(product.price + (parseFloat(size.price) || 0)) + '</span>' +
            (extra ? '<span class="size-extra">' + extra + '</span>' : '') +
            '</label>';
    }).join('');

    list.querySelectorAll('input[type="radio"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            modalSelectedSize = modalProduct.sizes[parseInt(radio.value, 10)];
            list.querySelectorAll('.size-option').forEach(function (opt) { opt.classList.remove('selected'); });
            radio.closest('.size-option').classList.add('selected');
            updateModalTotal();
        });
    });
}

function renderModalAddons(product) {
    var section = document.getElementById('modalAddonsSection');
    var list = document.getElementById('modalAddonsList');
    if (!section || !list) return;

    if (!product.addons || product.addons.length === 0) {
        section.classList.add('is-empty');
        list.innerHTML = '';
        return;
    }

    section.classList.remove('is-empty');
    list.innerHTML = product.addons.map(function (addon, idx) {
        var isFree = !(parseFloat(addon.price) > 0);
        var multi = addon.max_quantity > 1;
        return '<div class="addon-option" data-addon-index="' + idx + '">' +
            '<label class="addon-pick">' +
            '<input type="checkbox" data-addon-index="' + idx + '" aria-label="' + escapeHtml(addon.name) + '">' +
            '<span class="addon-name">' + escapeHtml(addon.name) + '</span>' +
            '<span class="addon-price' + (isFree ? ' is-free' : '') + '">' + formatExtra(addon.price) + '</span>' +
            '</label>' +
            (multi ? '<div class="addon-qty" hidden>' +
                '<button type="button" class="qty-btn" data-addon-step="-1" aria-label="Fewer ' + escapeHtml(addon.name) + '">-</button>' +
                '<span class="qty-value addon-qty-value">1</span>' +
                '<button type="button" class="qty-btn" data-addon-step="1" aria-label="More ' + escapeHtml(addon.name) + '">+</button>' +
                '</div>' : '') +
            '</div>';
    }).join('');

    list.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var idx = parseInt(cb.dataset.addonIndex, 10);
            var row = cb.closest('.addon-option');
            var qtyBox = row.querySelector('.addon-qty');
            var addon = modalProduct.addons[idx];
            if (cb.checked) {
                modalSelectedAddons[idx] = 1;
                row.classList.add('selected');
                if (qtyBox) qtyBox.hidden = false;
                setModalFeedback(addon.name + ' added');
            } else {
                delete modalSelectedAddons[idx];
                row.classList.remove('selected');
                if (qtyBox) { qtyBox.hidden = true; qtyBox.querySelector('.addon-qty-value').textContent = '1'; }
                setModalFeedback(addon.name + ' removed');
            }
            updateModalTotal();
        });
    });

    list.querySelectorAll('[data-addon-step]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var row = btn.closest('.addon-option');
            var idx = parseInt(row.dataset.addonIndex, 10);
            var addon = modalProduct.addons[idx];
            var current = modalSelectedAddons[idx] || 1;
            var next = current + parseInt(btn.dataset.addonStep, 10);
            if (next < 1 || next > addon.max_quantity) {
                if (next > addon.max_quantity) setModalFeedback('You can add up to ' + addon.max_quantity + ' of ' + addon.name);
                return;
            }
            modalSelectedAddons[idx] = next;
            row.querySelector('.addon-qty-value').textContent = next;
            setModalFeedback(addon.name + ' x' + next);
            updateModalTotal();
        });
    });
}

function setModalFeedback(text) {
    var el = document.getElementById('modalFeedback');
    if (el) el.textContent = text || '';
}

function modalAddonList() {
    if (!modalProduct) return [];
    return Object.keys(modalSelectedAddons).map(function (idx) {
        var addon = modalProduct.addons[parseInt(idx, 10)];
        return { id: addon.id, name: addon.name, price: parseFloat(addon.price) || 0, qty: modalSelectedAddons[idx] };
    });
}

function openProductFromCard(card) {
    var addons = [];
    var sizes = [];
    try { addons = JSON.parse(card.dataset.addons || '[]'); } catch (e) { addons = []; }
    try { sizes = JSON.parse(card.dataset.sizes || '[]'); } catch (e) { sizes = []; }

    openProductModal({
        id: parseInt(card.dataset.id, 10),
        name: card.dataset.name,
        description: card.dataset.description,
        price: parseFloat(card.dataset.price),
        image: card.dataset.image,
        addons: addons,
        sizes: sizes
    });
}

function openProductModal(product) {
    modalProduct = product;
    modalQty = 1;
    modalSelectedAddons = {};
    modalSelectedSize = null;

    document.getElementById('modalProductImage').src = (window.BASE_URL || '') + '/assets/images/products/' + product.image;
    document.getElementById('modalProductImage').alt = product.name;
    document.getElementById('modalProductName').textContent = product.name;
    document.getElementById('modalProductDesc').textContent = product.description || '';
    document.getElementById('modalProductPrice').textContent = formatPeso(product.price);
    document.getElementById('modalQtyValue').textContent = '1';
    document.getElementById('modalNoteInput').value = '';
    setModalFeedback('');

    renderModalSizes(product);
    renderModalAddons(product);
    updateModalTotal();

    document.getElementById('productModalOverlay').classList.add('open');
    document.getElementById('productModal').classList.add('open');
    document.body.classList.add('modal-open');
}

function closeProductModal() {
    document.getElementById('productModalOverlay').classList.remove('open');
    document.getElementById('productModal').classList.remove('open');
    document.body.classList.remove('modal-open');
    modalProduct = null;
}

function modalUnitPrice() {
    if (!modalProduct) return 0;
    var addonsTotal = modalAddonList().reduce(function (sum, a) { return sum + a.price * a.qty; }, 0);
    var sizeExtra = modalSelectedSize ? (parseFloat(modalSelectedSize.price) || 0) : 0;
    return modalProduct.price + sizeExtra + addonsTotal;
}

function updateModalTotal() {
    if (!modalProduct) return;
    var addons = modalAddonList();
    var addonsTotal = addons.reduce(function (sum, a) { return sum + a.price * a.qty; }, 0);
    var sizeExtra = modalSelectedSize ? (parseFloat(modalSelectedSize.price) || 0) : 0;

    document.getElementById('modalTotalPrice').textContent = formatPeso(modalUnitPrice() * modalQty);
    document.getElementById('modalProductPrice').textContent = formatPeso(modalUnitPrice()) + ' each';

    var parts = ['Base ' + formatPeso(modalProduct.price)];
    if (modalSelectedSize) parts.push(modalSelectedSize.name + ' ' + (sizeExtra > 0 ? '+' + formatPeso(sizeExtra) : 'no extra'));
    if (addons.length) parts.push('Add-ons +' + formatPeso(addonsTotal));
    var breakdown = document.getElementById('modalBreakdown');
    if (breakdown) breakdown.textContent = parts.join('  |  ');
}

document.addEventListener('DOMContentLoaded', function () {
    updateCartCount();
    renderCartDrawer();

    var navbar = document.getElementById('navbar');
    if (navbar) {
        var syncNavbarHeight = function () {
            document.documentElement.style.setProperty('--navbar-height', navbar.offsetHeight + 'px');
        };
        syncNavbarHeight();
        window.addEventListener('resize', syncNavbarHeight);
        if ('ResizeObserver' in window) new ResizeObserver(syncNavbarHeight).observe(navbar);
    }

    if ('IntersectionObserver' in window) {
        var revealObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12 });
        document.querySelectorAll('.reveal').forEach(function (el) { revealObserver.observe(el); });
    } else {
        document.querySelectorAll('.reveal').forEach(function (el) { el.classList.add('is-visible'); });
    }

    var openBtn = document.getElementById('openCartBtn');
    var closeBtn = document.getElementById('closeCartBtn');
    var overlay = document.getElementById('cartOverlay');
    var drawer = document.getElementById('cartDrawer');

    function openCart() {
        if (drawer) drawer.classList.add('open');
        if (overlay) overlay.classList.add('open');
    }
    function closeCart() {
        if (drawer) drawer.classList.remove('open');
        if (overlay) overlay.classList.remove('open');
    }

    if (openBtn) openBtn.addEventListener('click', openCart);
    if (closeBtn) closeBtn.addEventListener('click', closeCart);
    if (overlay) overlay.addEventListener('click', closeCart);

    var navToggle = document.getElementById('navToggle');
    var navLinks = document.getElementById('navLinks');
    if (navToggle) {
        navToggle.addEventListener('click', function () {
            var isOpen = navLinks.classList.toggle('open');
            navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            navToggle.setAttribute('aria-label', isOpen ? 'Close navigation' : 'Open navigation');
        });
    }
    if (navLinks) {
        navLinks.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                navLinks.classList.remove('open');
                if (navToggle) {
                    navToggle.setAttribute('aria-expanded', 'false');
                    navToggle.setAttribute('aria-label', 'Open navigation');
                }
            });
        });
    }

    var cartWrap = document.getElementById('cartItemsWrap');
    if (cartWrap) {
        cartWrap.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-cart-action]');
            if (!btn) return;
            var key = btn.dataset.key;
            var action = btn.dataset.cartAction;
            if (action === 'inc') updateQty(key, 1);
            else if (action === 'dec') updateQty(key, -1);
            else if (action === 'remove') removeFromCart(key);
        });
    }

    function productNeedsCustomization(card) {
        var sizes = [];
        var addons = [];
        try { sizes = JSON.parse(card.dataset.sizes || '[]'); } catch (err) { sizes = []; }
        try { addons = JSON.parse(card.dataset.addons || '[]'); } catch (err) { addons = []; }
        return sizes.length > 0 || addons.length > 0;
    }

    document.querySelectorAll('.add-to-cart-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();

            var parentCard = btn.closest('.product-card');
            if (parentCard && productNeedsCustomization(parentCard)) {
                openProductFromCard(parentCard);
                return;
            }

            addToCart({
                id: parseInt(btn.dataset.id, 10),
                name: btn.dataset.name,
                price: btn.dataset.price,
                image: btn.dataset.image
            });
            btn.classList.add('added');
            var originalText = btn.innerHTML;
            btn.innerHTML = 'Added';
            setTimeout(function () {
                btn.classList.remove('added');
                btn.innerHTML = originalText;
            }, 900);
        });
    });

    document.querySelectorAll('.product-card').forEach(function (card) {
        card.addEventListener('click', function () { openProductFromCard(card); });
        card.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openProductFromCard(card);
            }
        });
    });

    var modalOverlay = document.getElementById('productModalOverlay');
    var modalCloseBtn = document.getElementById('modalCloseBtn');
    var modalQtyMinus = document.getElementById('modalQtyMinus');
    var modalQtyPlus = document.getElementById('modalQtyPlus');
    var modalAddBtn = document.getElementById('modalAddToOrderBtn');

    if (modalOverlay) modalOverlay.addEventListener('click', closeProductModal);
    if (modalCloseBtn) modalCloseBtn.addEventListener('click', closeProductModal);

    if (modalQtyMinus) {
        modalQtyMinus.addEventListener('click', function () {
            if (modalQty <= 1) return;
            modalQty -= 1;
            document.getElementById('modalQtyValue').textContent = modalQty;
            updateModalTotal();
        });
    }
    if (modalQtyPlus) {
        modalQtyPlus.addEventListener('click', function () {
            if (modalQty >= 50) return;
            modalQty += 1;
            document.getElementById('modalQtyValue').textContent = modalQty;
            updateModalTotal();
        });
    }

    if (modalAddBtn) {
        modalAddBtn.addEventListener('click', function () {
            if (!modalProduct) return;
            var note = document.getElementById('modalNoteInput').value.trim();

            addLineToCart({
                id: modalProduct.id,
                name: modalProduct.name,
                price: modalUnitPrice(),
                image: modalProduct.image,
                qty: modalQty,
                note: note,
                addons: modalAddonList(),
                size: modalSelectedSize ? { id: modalSelectedSize.id, name: modalSelectedSize.name, price: parseFloat(modalSelectedSize.price) || 0 } : null
            });

            showToast(modalProduct.name + ' added to cart');
            closeProductModal();
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeProductModal();
    });

    syncMenuFromXml();

    document.querySelectorAll('.category-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.category-tab').forEach(function (t) { t.classList.remove('active'); });
            tab.classList.add('active');
            var target = tab.dataset.target;
            document.querySelectorAll('.category-group').forEach(function (group) {
                if (target === 'all' || group.dataset.category === target) {
                    group.style.display = '';
                } else {
                    group.style.display = 'none';
                }
            });
        });
    });

    document.querySelectorAll('.delete-my-order-btn').forEach(function (btn) {
        btn.addEventListener('click', async function (e) {
            e.preventDefault();
            if (!confirm('Delete this order? This cannot be undone.')) return;

            btn.disabled = true;
            try {
                var res = await fetch(window.BASE_URL + '/api/delete_customer_order.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                    body: JSON.stringify({ order_code: btn.dataset.orderCode })
                });
                var data = await res.json();

                if (data.success) {
                    showToast('Order deleted.');
                    btn.closest('.order-history-card').remove();
                } else {
                    showToast(data.error || 'Could not delete this order.');
                    btn.disabled = false;
                }
            } catch (err) {
                showToast('Could not reach the server.');
                btn.disabled = false;
            }
        });
    });
});


function childrenByTag(parent, tag) {
    var list = Array.isArray(parent) ? parent : (parent ? [parent] : []);
    var out = [];
    list.forEach(function (node) {
        Array.prototype.forEach.call(node.children, function (child) {
            if (child.tagName === tag) out.push(child);
        });
    });
    return out;
}

async function syncMenuFromXml() {
    var cards = document.querySelectorAll('.product-card');
    if (!cards.length) return;
    try {
        var res = await fetch(window.BASE_URL + '/api/menu_xml.php', { cache: 'no-store' });
        if (!res.ok) return;
        var xml = new DOMParser().parseFromString(await res.text(), 'application/xml');
        if (xml.getElementsByTagName('parsererror').length) return;

        var addonDefs = {};
        Array.prototype.forEach.call(xml.querySelectorAll('menuCatalog > addons > addon'), function (a) {
            addonDefs[a.getAttribute('id')] = {
                id: parseInt(a.getAttribute('id'), 10),
                name: a.querySelector('name').textContent,
                price: parseFloat(a.querySelector('price').textContent),
                max_quantity: parseInt(a.querySelector('maxQuantity').textContent, 10)
            };
        });
        var sizeDefs = {};
        Array.prototype.forEach.call(xml.querySelectorAll('menuCatalog > sizes > size'), function (s) {
            sizeDefs[s.getAttribute('id')] = s.querySelector('name').textContent;
        });

        var products = {};
        Array.prototype.forEach.call(xml.querySelectorAll('product'), function (p) {
            products[p.getAttribute('id')] = p;
        });

        cards.forEach(function (card) {
            var p = products[card.dataset.id];
            if (!p) {
                var group = card.closest('.category-group');
                card.style.display = 'none';
                if (group && !group.querySelector('.product-card:not([style*="display: none"])')) group.style.display = 'none';
                return;
            }
            var price = parseFloat(p.querySelector('basePrice').textContent);
            card.dataset.price = price;
            var priceEl = card.querySelector('.product-price');
            if (priceEl) priceEl.textContent = formatPeso(price);
            var quick = card.querySelector('.add-to-cart-btn');
            if (quick) quick.dataset.price = price;

            var sizes = [];
            Array.prototype.forEach.call(childrenByTag(childrenByTag([p], 'sizes')[0], 'size'), function (s) {
                var id = s.getAttribute('sizeId');
                if (sizeDefs[id]) {
                    sizes.push({ id: parseInt(id, 10), name: sizeDefs[id], price: parseFloat(s.querySelector('extraPrice').textContent) });
                }
            });
            var addons = [];
            Array.prototype.forEach.call(childrenByTag(childrenByTag([p], 'addons')[0], 'addonRef'), function (r) {
                var def = addonDefs[r.getAttribute('addonId')];
                if (def) addons.push(def);
            });
            card.dataset.sizes = JSON.stringify(sizes);
            card.dataset.addons = JSON.stringify(addons);
        });
    } catch (err) {
        return;
    }
}
