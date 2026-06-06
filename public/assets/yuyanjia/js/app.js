(function () {
    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    onReady(function () {
        var tabs = document.querySelectorAll('[data-group-target]');
        var cards = document.querySelectorAll('[data-product-name]');
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                tabs.forEach(function (item) { item.classList.remove('active'); });
                tab.classList.add('active');
                var group = tab.getAttribute('data-group-target');
                cards.forEach(function (card) {
                    card.style.display = group === 'all' || card.getAttribute('data-group') === group ? '' : 'none';
                });
            });
        });

        var search = document.querySelector('[data-product-search]');
        if (search) {
            search.addEventListener('input', function () {
                var value = search.value.trim().toLowerCase();
                cards.forEach(function (card) {
                    var name = (card.getAttribute('data-product-name') || '').toLowerCase();
                    card.style.display = !value || name.indexOf(value) !== -1 ? '' : 'none';
                });
            });
        }

        var noticeModal = document.querySelector('[data-notice-modal]');
        if (noticeModal) {
            var today = new Date().toISOString().slice(0, 10);
            var todayKey = 'yuyanjia_notice_today_' + today;
            var foreverKey = 'yuyanjia_notice_forever';
            var canStore = true;

            try {
                window.localStorage.getItem(foreverKey);
            } catch (error) {
                canStore = false;
            }

            function stored(key) {
                if (!canStore) return false;
                return window.localStorage.getItem(key) === '1';
            }

            function remember(key) {
                if (!canStore) return;
                window.localStorage.setItem(key, '1');
            }

            function openNotice() {
                noticeModal.classList.add('is-open');
                noticeModal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }

            function closeNotice() {
                noticeModal.classList.remove('is-open');
                noticeModal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }

            document.querySelectorAll('[data-open-notice]').forEach(function (button) {
                button.addEventListener('click', openNotice);
            });

            document.querySelectorAll('[data-close-notice]').forEach(function (button) {
                button.addEventListener('click', closeNotice);
            });

            var todayButton = document.querySelector('[data-notice-today]');
            if (todayButton) {
                todayButton.addEventListener('click', function () {
                    remember(todayKey);
                    closeNotice();
                });
            }

            var foreverButton = document.querySelector('[data-notice-forever]');
            if (foreverButton) {
                foreverButton.addEventListener('click', function () {
                    remember(foreverKey);
                    closeNotice();
                });
            }

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && noticeModal.classList.contains('is-open')) {
                    closeNotice();
                }
            });

            if (!stored(todayKey) && !stored(foreverKey)) {
                window.setTimeout(openNotice, 450);
            }
        }

        var cartDrawer = document.querySelector('[data-cart-drawer]');
        var cartItemsTarget = document.querySelector('[data-cart-items]');
        var cartEmpty = document.querySelector('[data-cart-empty]');
        var cartTotal = document.querySelector('[data-cart-total]');
        var cartCounts = document.querySelectorAll('[data-cart-count]');
        var cartKey = 'yuyanjia_cart_items';

        function readCart() {
            try {
                var value = window.localStorage.getItem(cartKey);
                var items = value ? JSON.parse(value) : [];
                return Array.isArray(items) ? items : [];
            } catch (error) {
                return [];
            }
        }

        function writeCart(items) {
            try {
                window.localStorage.setItem(cartKey, JSON.stringify(items));
            } catch (error) {}
        }

        function openCart() {
            if (!cartDrawer) return;
            cartDrawer.classList.add('is-open');
            cartDrawer.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        function closeCart() {
            if (!cartDrawer) return;
            cartDrawer.classList.remove('is-open');
            cartDrawer.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }

        function escapeHtml(text) {
            return String(text || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function cartQuantity(items) {
            return items.reduce(function (sum, item) {
                return sum + Number(item.qty || 0);
            }, 0);
        }

        function renderCart() {
            if (!cartItemsTarget) return;
            var items = readCart();
            var total = items.reduce(function (sum, item) {
                return sum + Number(item.price || 0) * Number(item.qty || 0);
            }, 0);

            cartCounts.forEach(function (target) {
                target.textContent = String(cartQuantity(items));
            });
            if (cartTotal) cartTotal.textContent = total.toFixed(2);
            if (cartEmpty) cartEmpty.style.display = items.length ? 'none' : '';

            cartItemsTarget.innerHTML = items.map(function (item) {
                return '' +
                    '<article class="cart-item" data-cart-row="' + escapeHtml(item.id) + '">' +
                        '<img src="' + escapeHtml(item.image) + '" alt="' + escapeHtml(item.name) + '">' +
                        '<div>' +
                            '<h3>' + escapeHtml(item.name) + '</h3>' +
                            '<p>' + escapeHtml(item.category || '商品') + '</p>' +
                            '<div class="cart-item-row">' +
                                '<strong>' + Number(item.price || 0).toFixed(2) + ' CNY</strong>' +
                                '<div class="qty-control">' +
                                    '<button type="button" data-cart-dec="' + escapeHtml(item.id) + '">-</button>' +
                                    '<span>' + Number(item.qty || 0) + '</span>' +
                                    '<button type="button" data-cart-inc="' + escapeHtml(item.id) + '">+</button>' +
                                '</div>' +
                            '</div>' +
                            '<div class="cart-item-actions">' +
                                '<a href="' + escapeHtml(item.url) + '">去购买</a>' +
                                '<button type="button" data-cart-remove="' + escapeHtml(item.id) + '">移除</button>' +
                            '</div>' +
                        '</div>' +
                    '</article>';
            }).join('');
        }

        function updateCartItem(id, updater) {
            var items = readCart();
            items = items.map(function (item) {
                if (String(item.id) !== String(id)) return item;
                return updater(item);
            }).filter(function (item) {
                return Number(item.qty || 0) > 0;
            });
            writeCart(items);
            renderCart();
        }

        document.querySelectorAll('[data-open-cart]').forEach(function (button) {
            button.addEventListener('click', openCart);
        });

        document.querySelectorAll('[data-close-cart]').forEach(function (button) {
            button.addEventListener('click', closeCart);
        });

        document.querySelectorAll('[data-cart-add]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                var id = button.getAttribute('data-cart-id');
                var items = readCart();
                var existing = items.find(function (item) { return String(item.id) === String(id); });
                if (existing) {
                    existing.qty = Number(existing.qty || 0) + 1;
                } else {
                    items.push({
                        id: id,
                        name: button.getAttribute('data-cart-name') || '商品',
                        category: button.getAttribute('data-cart-category') || '',
                        price: Number(button.getAttribute('data-cart-price') || 0),
                        stock: Number(button.getAttribute('data-cart-stock') || 0),
                        image: button.getAttribute('data-cart-image') || '',
                        url: button.getAttribute('data-cart-url') || '/',
                        qty: 1
                    });
                }
                writeCart(items);
                renderCart();
                openCart();
            });
        });

        if (cartItemsTarget) {
            cartItemsTarget.addEventListener('click', function (event) {
                var inc = event.target.closest('[data-cart-inc]');
                var dec = event.target.closest('[data-cart-dec]');
                var remove = event.target.closest('[data-cart-remove]');
                if (inc) {
                    updateCartItem(inc.getAttribute('data-cart-inc'), function (item) {
                        item.qty = Number(item.qty || 0) + 1;
                        return item;
                    });
                }
                if (dec) {
                    updateCartItem(dec.getAttribute('data-cart-dec'), function (item) {
                        item.qty = Number(item.qty || 0) - 1;
                        return item;
                    });
                }
                if (remove) {
                    updateCartItem(remove.getAttribute('data-cart-remove'), function (item) {
                        item.qty = 0;
                        return item;
                    });
                }
            });
        }

        var clearCart = document.querySelector('[data-cart-clear]');
        if (clearCart) {
            clearCart.addEventListener('click', function () {
                writeCart([]);
                renderCart();
            });
        }

        renderCart();

        var skuButtons = document.querySelectorAll('[data-sku-option]');
        var skuInput = document.querySelector('[data-sku-input]');
        var priceTarget = document.querySelector('[data-sku-price-label]');
        var stockTarget = document.querySelector('[data-sku-stock]');
        var imageTarget = document.getElementById('skuPicture');
        var amountInput = document.querySelector('[data-buy-amount]');
        var submitButton = document.querySelector('[data-submit-order]');

        function preloadImage(src) {
            if (!src) return;
            var img = new Image();
            img.decoding = 'async';
            img.src = src;
        }

        function swapSkuImage(src) {
            if (!imageTarget || !src || imageTarget.getAttribute('src') === src) return;

            imageTarget.classList.add('is-loading');
            var nextImage = new Image();
            nextImage.decoding = 'async';
            nextImage.onload = function () {
                imageTarget.src = src;
                window.requestAnimationFrame(function () {
                    imageTarget.classList.remove('is-loading');
                });
            };
            nextImage.onerror = function () {
                imageTarget.src = src;
                imageTarget.classList.remove('is-loading');
            };
            nextImage.src = src;
        }

        skuButtons.forEach(function (button) {
            preloadImage(button.getAttribute('data-sku-picture'));
        });

        skuButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                skuButtons.forEach(function (item) { item.classList.remove('active'); });
                button.classList.add('active');
                if (skuInput) skuInput.value = button.getAttribute('data-sku-id') || '';
                if (priceTarget) priceTarget.textContent = Number(button.getAttribute('data-sku-price') || 0).toFixed(2);
                var stockValue = Number(button.getAttribute('data-sku-stock') || 0);
                if (stockTarget) stockTarget.textContent = String(stockValue);
                swapSkuImage(button.getAttribute('data-sku-picture'));
                if (amountInput) {
                    amountInput.max = stockValue > 0 ? String(stockValue) : '';
                    if (stockValue > 0 && Number(amountInput.value || 1) > stockValue) {
                        amountInput.value = String(stockValue);
                    }
                    if (Number(amountInput.value || 1) < 1) {
                        amountInput.value = '1';
                    }
                }
                if (submitButton) submitButton.disabled = stockValue <= 0;
            });
        });
        if (skuButtons[0]) skuButtons[0].click();

        var payButtons = document.querySelectorAll('[data-pay-option]');
        var payInput = document.querySelector('[data-pay-input]');
        payButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                payButtons.forEach(function (item) { item.classList.remove('active'); });
                button.classList.add('active');
                if (payInput) payInput.value = button.getAttribute('data-pay-id');
            });
        });

        var buyForm = document.querySelector('[data-buy-form]');
        if (buyForm) {
            buyForm.addEventListener('submit', function (event) {
                var amount = Number(amountInput && amountInput.value ? amountInput.value : 1);
                var activeSku = document.querySelector('[data-sku-option].active');
                var stock = activeSku ? Number(activeSku.getAttribute('data-sku-stock') || 0) : 0;
                if (stock <= 0) {
                    alert('当前规格库存不足，请换一个规格。');
                    event.preventDefault();
                    return;
                }
                if (stock > 0 && amount > stock) {
                    alert('库存不足，请减少购买数量。');
                    event.preventDefault();
                    return;
                }
                if (window.YUYANJIA_BUY_LIMIT && amount > window.YUYANJIA_BUY_LIMIT) {
                    alert('已超过本商品限购数量。');
                    event.preventDefault();
                }
            });
        }

        document.querySelectorAll('[data-copy-target]').forEach(function (button) {
            button.addEventListener('click', function () {
                var source = document.querySelector('[data-copy-source="' + button.getAttribute('data-copy-target') + '"]');
                if (!source) return;
                source.select();
                var text = source.value;
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(text).then(function () { alert('复制成功'); });
                } else {
                    document.execCommand('copy');
                    alert('复制成功');
                }
            });
        });

        var toastClose = document.querySelector('[data-close-toast]');
        if (toastClose) {
            toastClose.addEventListener('click', function () {
                var toast = document.querySelector('[data-toast-note]');
                if (toast) toast.style.display = 'none';
            });
        }

        if (window.YUYANJIA_ORDER_CHECK_URL) {
            var timer = window.setInterval(function () {
                fetch(window.YUYANJIA_ORDER_CHECK_URL, { credentials: 'same-origin' })
                    .then(function (res) { return res.json(); })
                    .then(function (res) {
                        if (res.code === 400001) {
                            window.clearInterval(timer);
                            alert('订单已过期');
                            window.location.href = '/';
                        }
                        if (res.code === 200) {
                            window.clearInterval(timer);
                            alert('支付成功');
                            window.location.href = window.YUYANJIA_ORDER_DETAIL_URL || '/';
                        }
                    })
                    .catch(function () {});
            }, 5000);
        }
    });
})();
