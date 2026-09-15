const CART_KEY = 'kb_cart';

function buildItemKey(id, note, addons) {
    var notePart = (note || '').trim().toLowerCase();
    var addonsPart = (addons || []).map(function (a) { return a.name; }).sort().join('|');
    return id + '::' + notePart + '::' + addonsPart;
}

function getCart() {
    try {
        var raw = localStorage.getItem(CART_KEY);
        return raw ? JSON.parse(raw) : [];
    } catch (e) {
        return [];
    }
}

function saveCart(cart) {
    localStorage.setItem(CART_KEY, JSON.stringify(cart));
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
        addons: []
    });
    showToast(product.name + ' added to cart');
}

function addLineToCart(item) {
    var cart = getCart();
    var key = buildItemKey(item.id, item.note, item.addons);
    var existing = cart.find(function (i) { return i.key === key; });

    if (existing) {
        existing.qty += item.qty;
    } else {
        cart.push({
            key: key,
            id: item.id,
            name: item.name,
            price: item.price,
            image: item.image,
            qty: item.qty,
            note: item.note || '',
            addons: item.addons || []
        });
    }
    saveCart(cart);
}

function updateQty(key, delta) {
    var cart = getCart();
    var item = cart.find(function (i) { return i.key === key; });
    if (!item) return;
    item.qty += delta;
    if (item.qty <= 0) {
        cart = cart.filter(function (i) { return i.key !== key; });
    }
    saveCart(cart);
}

function removeFromCart(key) {
    var cart = getCart().filter(function (i) { return i.key !== key; });
    saveCart(cart);
}

function cartTotal(cart) {
    return cart.reduce(function (sum, item) { return sum + (item.price * item.qty); }, 0);
}

function formatPeso(amount) {
    return '₱' + amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function updateCartCount() {
    var countEls = document.querySelectorAll('#cartCount');
    var cart = getCart();
    var total = cart.reduce(function (sum, i) { return sum + i.qty; }, 0);
    countEls.forEach(function (el) { el.textContent = total; });
}

function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
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
            var addonsLine = (item.addons && item.addons.length)
                ? '<div class="cart-item-price" style="margin-top:2px;">+ ' + item.addons.map(function (a) { return escapeHtml(a.name); }).join(', ') + '</div>'
                : '';
            var noteLine = item.note
                ? '<div class="cart-item-price" style="font-style:italic; margin-top:2px;">Note: ' + escapeHtml(item.note) + '</div>'
                : '';
            return '<div class="cart-item">' +
                '<img src="' + (window.BASE_URL || '') + '/assets/images/products/' + item.image + '" alt="' + escapeHtml(item.name) + '">' +
                '<div class="cart-item-info">' +
                '<div class="cart-item-name">' + escapeHtml(item.name) + '</div>' +
                '<div class="cart-item-price">' + formatPeso(item.price) + ' each</div>' +
                addonsLine + noteLine +
                '<div class="qty-control">' +
                '<button class="qty-btn" type="button" onclick="updateQty(\'' + item.key + '\', -1)">-</button>' +
                '<span class="qty-value">' + item.qty + '</span>' +
                '<button class="qty-btn" type="button" onclick="updateQty(\'' + item.key + '\', 1)">+</button>' +
                '</div>' +
                '<button class="remove-item-btn" type="button" onclick="removeFromCart(\'' + item.key + '\')">Remove</button>' +
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
var modalSelectedAddons = [];

function openProductModal(product) {
    modalProduct = product;
    modalQty = 1;
    modalSelectedAddons = [];

    document.getElementById('modalProductImage').src = (window.BASE_URL || '') + '/assets/images/products/' + product.image;
    document.getElementById('modalProductImage').alt = product.name;
    document.getElementById('modalProductName').textContent = product.name;
    document.getElementById('modalProductDesc').textContent = product.description || '';
    document.getElementById('modalProductPrice').textContent = formatPeso(product.price);
    document.getElementById('modalQtyValue').textContent = '1';
    document.getElementById('modalNoteInput').value = '';

    var addonsSection = document.getElementById('modalAddonsSection');
    var addonsList = document.getElementById('modalAddonsList');
    if (product.addons && product.addons.length > 0) {
        addonsSection.classList.remove('is-empty');
        addonsList.innerHTML = product.addons.map(function (addon, idx) {
            return '<label class="addon-option">' +
                '<input type="checkbox" data-addon-index="' + idx + '">' +
                '<span class="addon-name">' + escapeHtml(addon.name) + '</span>' +
                '<span class="addon-price">+' + formatPeso(addon.price) + '</span>' +
                '</label>';
        }).join('');
        addonsList.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
            cb.addEventListener('change', onModalAddonToggle);
        });
    } else {
        addonsSection.classList.add('is-empty');
        addonsList.innerHTML = '';
    }

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

function onModalAddonToggle() {
    if (!modalProduct) return;
    var checked = document.querySelectorAll('#modalAddonsList input[type="checkbox"]:checked');
    modalSelectedAddons = Array.from(checked).map(function (cb) {
        return modalProduct.addons[parseInt(cb.dataset.addonIndex, 10)];
    });
    updateModalTotal();
}

function updateModalTotal() {
    if (!modalProduct) return;
    var addonsTotal = modalSelectedAddons.reduce(function (sum, a) { return sum + a.price; }, 0);
    var unitPrice = modalProduct.price + addonsTotal;
    document.getElementById('modalTotalPrice').textContent = formatPeso(unitPrice * modalQty);
}

document.addEventListener('DOMContentLoaded', function () {
    updateCartCount();
    renderCartDrawer();

    var navbar = document.getElementById('navbar');
    if (navbar) {
        window.addEventListener('scroll', function () {
            if (window.scrollY > 8) navbar.classList.add('scrolled');
            else navbar.classList.remove('scrolled');
        });
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
            navLinks.classList.toggle('open');
        });
    }

    document.querySelectorAll('.add-to-cart-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
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
        function openFromCard() {
            var addons = [];
            try { addons = JSON.parse(card.dataset.addons || '[]'); } catch (e) { addons = []; }
            openProductModal({
                id: parseInt(card.dataset.id, 10),
                name: card.dataset.name,
                description: card.dataset.description,
                price: parseFloat(card.dataset.price),
                image: card.dataset.image,
                addons: addons
            });
        }
        card.addEventListener('click', openFromCard);
        card.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openFromCard();
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
            modalQty += 1;
            document.getElementById('modalQtyValue').textContent = modalQty;
            updateModalTotal();
        });
    }

    if (modalAddBtn) {
        modalAddBtn.addEventListener('click', function () {
            if (!modalProduct) return;
            var note = document.getElementById('modalNoteInput').value.trim();
            var addonsTotal = modalSelectedAddons.reduce(function (sum, a) { return sum + a.price; }, 0);

            addLineToCart({
                id: modalProduct.id,
                name: modalProduct.name,
                price: modalProduct.price + addonsTotal,
                image: modalProduct.image,
                qty: modalQty,
                note: note,
                addons: modalSelectedAddons
            });

            showToast(modalProduct.name + ' added to cart');
            closeProductModal();
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeProductModal();
    });

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
                    headers: { 'Content-Type': 'application/json' },
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
