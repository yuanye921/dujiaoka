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
            var noticeTriggers = document.querySelectorAll('[data-open-notice]');
            var lastNoticeTrigger = null;
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

            function openNotice(trigger) {
                lastNoticeTrigger = trigger || document.activeElement;
                noticeModal.classList.add('is-open');
                noticeModal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }

            function restoreNoticeFocus() {
                var active = document.activeElement;
                if (active && noticeModal.contains(active)) {
                    active.blur();
                }

                var fallback = lastNoticeTrigger && document.contains(lastNoticeTrigger)
                    ? lastNoticeTrigger
                    : noticeTriggers[0];

                if (fallback && typeof fallback.focus === 'function') {
                    fallback.focus({ preventScroll: true });
                }
            }

            function closeNotice() {
                restoreNoticeFocus();
                noticeModal.classList.remove('is-open');
                noticeModal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }

            noticeTriggers.forEach(function (button) {
                button.addEventListener('click', function () {
                    openNotice(button);
                });
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
        var cartCheckout = document.querySelector('[data-cart-checkout]');
        var cartCounts = document.querySelectorAll('[data-cart-count]');
        var cartKey = 'yuyanjia_cart_items';

        var skuPicker = document.querySelector('[data-sku-picker]');
        var skuPickerName = document.querySelector('[data-sku-picker-name]');
        var skuPickerImage = document.querySelector('[data-sku-picker-image]');
        var skuPickerOptions = document.querySelector('[data-sku-picker-options]');
        var skuPickerQty = document.querySelector('[data-sku-picker-qty]');
        var skuPickerPrice = document.querySelector('[data-sku-picker-price]');
        var skuPickerStock = document.querySelector('[data-sku-picker-stock]');
        var skuPickerSubmit = document.querySelector('[data-sku-picker-submit]');
        var activeSkuPickerProduct = null;
        var activeSkuPickerIndex = 0;

        function toNumber(value, fallback) {
            var number = Number(value);
            return isFinite(number) ? number : fallback;
        }

        function toInt(value, fallback) {
            var number = parseInt(value, 10);
            return isFinite(number) ? number : fallback;
        }

        function escapeHtml(text) {
            return String(text || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function makeCartKey(goodsId, skuId) {
            return String(goodsId || '') + ':' + String(skuId || '');
        }

        function normalizeCartItem(item) {
            if (!item || typeof item !== 'object') return null;

            var goodsId = item.goodsId || item.goods_id || item.id || '';
            var skuId = item.skuId || item.sku_id || '';
            var key = item.key || (item.goodsId || item.skuId ? makeCartKey(goodsId, skuId) : item.id);
            if (!key && goodsId) key = makeCartKey(goodsId, skuId);
            if (!key) return null;

            return {
                key: String(key),
                id: String(key),
                goodsId: String(goodsId || ''),
                skuId: String(skuId || ''),
                skuName: item.skuName || item.sku_name || '',
                name: item.name || '商品',
                category: item.category || '',
                price: toNumber(item.price, 0),
                stock: toInt(item.stock, 0),
                buyLimit: toInt(item.buyLimit || item.buy_limit, 0),
                image: item.image || '',
                url: item.url || '/',
                qty: Math.max(1, toInt(item.qty, 1))
            };
        }

        function readCart() {
            try {
                var value = window.localStorage.getItem(cartKey);
                var items = value ? JSON.parse(value) : [];
                return Array.isArray(items) ? items.map(normalizeCartItem).filter(Boolean) : [];
            } catch (error) {
                return [];
            }
        }

        function writeCart(items) {
            try {
                window.localStorage.setItem(cartKey, JSON.stringify(items.map(normalizeCartItem).filter(Boolean)));
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

        function maxAllowedQty(item) {
            var stock = toInt(item.stock, 0);
            var buyLimit = toInt(item.buyLimit, 0);
            var max = stock > 0 ? stock : 0;
            if (buyLimit > 0) {
                max = max > 0 ? Math.min(max, buyLimit) : buyLimit;
            }
            return max;
        }

        function clampCartQty(item, qty) {
            qty = Math.max(1, toInt(qty, 1));
            var max = maxAllowedQty(item);
            return max > 0 ? Math.min(qty, max) : qty;
        }

        function appendPurchaseParams(url, item) {
            try {
                var link = new URL(url || '/', window.location.origin);
                if (item.skuId) link.searchParams.set('sku_id', item.skuId);
                if (item.qty) link.searchParams.set('qty', String(item.qty));
                if (link.origin === window.location.origin) {
                    return link.pathname + link.search + link.hash;
                }
                return link.href;
            } catch (error) {
                return url || '/';
            }
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
            if (cartCheckout) {
                cartCheckout.disabled = !items.length;
                cartCheckout.textContent = items.length > 1 ? '去结算 · ' + cartQuantity(items) + '件' : '去结算';
            }
            if (cartEmpty) cartEmpty.style.display = items.length ? 'none' : '';

            cartItemsTarget.innerHTML = items.map(function (item) {
                var purchaseUrl = appendPurchaseParams(item.url, item);
                var max = maxAllowedQty(item);
                var atMax = max > 0 && Number(item.qty || 0) >= max;
                var skuText = item.skuName ? '<span>规格：' + escapeHtml(item.skuName) + '</span>' : '';
                var categoryText = item.category ? '<span>' + escapeHtml(item.category) + '</span>' : '';
                var metaText = skuText + categoryText || '<span>商品</span>';

                return '' +
                    '<article class="cart-item" data-cart-row="' + escapeHtml(item.key) + '">' +
                        '<img src="' + escapeHtml(item.image) + '" alt="' + escapeHtml(item.name) + '">' +
                        '<div>' +
                            '<h3>' + escapeHtml(item.name) + '</h3>' +
                            '<p class="cart-item-meta">' + metaText + '</p>' +
                            '<div class="cart-item-row">' +
                                '<strong>' + Number(item.price || 0).toFixed(2) + ' CNY</strong>' +
                                '<div class="qty-control">' +
                                    '<button type="button" data-cart-dec="' + escapeHtml(item.key) + '">-</button>' +
                                    '<span>' + Number(item.qty || 0) + '</span>' +
                                    '<button type="button" data-cart-inc="' + escapeHtml(item.key) + '"' + (atMax ? ' disabled' : '') + '>+</button>' +
                                '</div>' +
                            '</div>' +
                            '<div class="cart-item-actions">' +
                                '<a href="' + escapeHtml(purchaseUrl) + '">去购买</a>' +
                                '<button type="button" data-cart-remove="' + escapeHtml(item.key) + '">移除</button>' +
                            '</div>' +
                        '</div>' +
                    '</article>';
            }).join('');
        }

        function updateCartItem(key, updater) {
            var items = readCart();
            items = items.map(function (item) {
                if (String(item.key) !== String(key)) return item;
                var nextItem = normalizeCartItem(updater(item));
                if (nextItem) nextItem.qty = clampCartQty(nextItem, nextItem.qty);
                return nextItem;
            }).filter(function (item) {
                return item && Number(item.qty || 0) > 0;
            });
            writeCart(items);
            renderCart();
        }

        function parseSkus(button) {
            try {
                var skus = JSON.parse(button.getAttribute('data-cart-skus') || '[]');
                return Array.isArray(skus) ? skus.map(function (sku) {
                    return {
                        id: String(sku.id || ''),
                        name: sku.name || '默认规格',
                        price: toNumber(sku.price, toNumber(button.getAttribute('data-cart-price'), 0)),
                        stock: toInt(sku.stock, toInt(button.getAttribute('data-cart-stock'), 0)),
                        image: sku.image || button.getAttribute('data-cart-image') || '',
                        url: sku.url || button.getAttribute('data-cart-url') || '/'
                    };
                }) : [];
            } catch (error) {
                return [];
            }
        }

        function payloadFromProduct(button, sku, qty) {
            var goodsId = button.getAttribute('data-cart-id') || '';
            var skuId = sku && sku.id ? String(sku.id) : '';

            return normalizeCartItem({
                key: makeCartKey(goodsId, skuId),
                goodsId: goodsId,
                skuId: skuId,
                skuName: sku && sku.name ? sku.name : '默认规格',
                name: button.getAttribute('data-cart-name') || '商品',
                category: button.getAttribute('data-cart-category') || '',
                price: sku ? sku.price : toNumber(button.getAttribute('data-cart-price'), 0),
                stock: sku ? sku.stock : toInt(button.getAttribute('data-cart-stock'), 0),
                buyLimit: toInt(button.getAttribute('data-cart-buy-limit'), 0),
                image: sku && sku.image ? sku.image : button.getAttribute('data-cart-image') || '',
                url: sku && sku.url ? sku.url : button.getAttribute('data-cart-url') || '/',
                qty: qty || 1
            });
        }

        function addCartItem(payload, qty) {
            var item = normalizeCartItem(payload);
            if (!item) return false;
            if (toInt(item.stock, 0) <= 0) {
                alert('当前规格库存不足，请换一个规格。');
                return false;
            }

            item.qty = clampCartQty(item, qty || item.qty || 1);
            var items = readCart();
            var existing = items.find(function (cartItem) {
                return String(cartItem.key) === String(item.key);
            });

            if (existing) {
                var nextQty = Number(existing.qty || 0) + Number(item.qty || 1);
                var max = maxAllowedQty(item);
                existing.qty = max > 0 ? Math.min(nextQty, max) : nextQty;
                existing.price = item.price;
                existing.stock = item.stock;
                existing.buyLimit = item.buyLimit;
                existing.image = item.image;
                existing.url = item.url;
                existing.skuName = item.skuName;
                if (max > 0 && nextQty > max) {
                    alert('已达到库存或限购数量。');
                }
            } else {
                items.push(item);
            }

            writeCart(items);
            renderCart();
            return true;
        }

        function closeSkuPicker() {
            if (!skuPicker) return;
            skuPicker.classList.remove('is-open');
            skuPicker.setAttribute('aria-hidden', 'true');
            if (!cartDrawer || !cartDrawer.classList.contains('is-open')) {
                document.body.style.overflow = '';
            }
        }

        function selectSkuPickerOption(index) {
            if (!activeSkuPickerProduct) return;
            activeSkuPickerIndex = index;
            var sku = activeSkuPickerProduct.skus[index];
            if (!sku) return;

            document.querySelectorAll('[data-sku-picker-option]').forEach(function (button) {
                button.classList.toggle('active', Number(button.getAttribute('data-sku-picker-option')) === index);
            });

            if (skuPickerImage) {
                skuPickerImage.src = sku.image || activeSkuPickerProduct.image || '';
                skuPickerImage.alt = activeSkuPickerProduct.name || '';
            }
            if (skuPickerPrice) skuPickerPrice.textContent = Number(sku.price || 0).toFixed(2);
            if (skuPickerStock) skuPickerStock.textContent = String(toInt(sku.stock, 0));
            if (skuPickerQty) {
                var pickerItem = {
                    stock: sku.stock,
                    buyLimit: activeSkuPickerProduct.buyLimit
                };
                var max = maxAllowedQty(pickerItem);
                skuPickerQty.max = max > 0 ? String(max) : '';
                skuPickerQty.value = String(clampCartQty(pickerItem, skuPickerQty.value || 1));
            }
            if (skuPickerSubmit) skuPickerSubmit.disabled = toInt(sku.stock, 0) <= 0;
        }

        function openSkuPicker(button, skus) {
            if (!skuPicker || !skuPickerOptions || !skus.length) return;

            activeSkuPickerProduct = {
                button: button,
                name: button.getAttribute('data-cart-name') || '商品',
                image: button.getAttribute('data-cart-image') || '',
                buyLimit: toInt(button.getAttribute('data-cart-buy-limit'), 0),
                skus: skus
            };
            activeSkuPickerIndex = Math.max(0, skus.findIndex(function (sku) { return toInt(sku.stock, 0) > 0; }));

            if (skuPickerName) skuPickerName.textContent = activeSkuPickerProduct.name;
            if (skuPickerQty) skuPickerQty.value = '1';
            skuPickerOptions.innerHTML = skus.map(function (sku, index) {
                var disabled = toInt(sku.stock, 0) <= 0;
                return '' +
                    '<button type="button" class="sku-picker-option" data-sku-picker-option="' + index + '"' + (disabled ? ' disabled' : '') + '>' +
                        '<span>' + escapeHtml(sku.name) + '</span>' +
                        '<strong>' + Number(sku.price || 0).toFixed(2) + ' CNY</strong>' +
                        '<em>库存 ' + toInt(sku.stock, 0) + '</em>' +
                    '</button>';
            }).join('');

            skuPicker.classList.add('is-open');
            skuPicker.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            selectSkuPickerOption(activeSkuPickerIndex);
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

                var skus = parseSkus(button);
                if (skus.length > 1) {
                    openSkuPicker(button, skus);
                    return;
                }

                var sku = skus[0] || {
                    id: '',
                    name: '默认规格',
                    price: toNumber(button.getAttribute('data-cart-price'), 0),
                    stock: toInt(button.getAttribute('data-cart-stock'), 0),
                    image: button.getAttribute('data-cart-image') || '',
                    url: button.getAttribute('data-cart-url') || '/'
                };
                if (addCartItem(payloadFromProduct(button, sku, 1), 1)) openCart();
            });
        });

        document.querySelectorAll('[data-sku-picker-close]').forEach(function (button) {
            button.addEventListener('click', closeSkuPicker);
        });

        if (skuPickerOptions) {
            skuPickerOptions.addEventListener('click', function (event) {
                var option = event.target.closest('[data-sku-picker-option]');
                if (!option || option.disabled) return;
                selectSkuPickerOption(Number(option.getAttribute('data-sku-picker-option') || 0));
            });
        }

        if (skuPickerQty) {
            skuPickerQty.addEventListener('input', function () {
                if (!activeSkuPickerProduct || skuPickerQty.value === '') return;
                var sku = activeSkuPickerProduct.skus[activeSkuPickerIndex];
                if (!sku) return;
                skuPickerQty.value = String(clampCartQty({
                    stock: sku.stock,
                    buyLimit: activeSkuPickerProduct.buyLimit
                }, skuPickerQty.value));
            });
        }

        if (skuPickerSubmit) {
            skuPickerSubmit.addEventListener('click', function () {
                if (!activeSkuPickerProduct) return;
                var sku = activeSkuPickerProduct.skus[activeSkuPickerIndex];
                if (!sku) return;
                var qty = toInt(skuPickerQty && skuPickerQty.value, 1);
                if (addCartItem(payloadFromProduct(activeSkuPickerProduct.button, sku, qty), qty)) {
                    closeSkuPicker();
                    openCart();
                }
            });
        }

        if (cartItemsTarget) {
            cartItemsTarget.addEventListener('click', function (event) {
                var inc = event.target.closest('[data-cart-inc]');
                var dec = event.target.closest('[data-cart-dec]');
                var remove = event.target.closest('[data-cart-remove]');

                if (inc) {
                    if (inc.disabled) return;
                    var reachedMax = false;
                    updateCartItem(inc.getAttribute('data-cart-inc'), function (item) {
                        var max = maxAllowedQty(item);
                        if (max > 0 && Number(item.qty || 0) >= max) {
                            reachedMax = true;
                            return item;
                        }
                        item.qty = Number(item.qty || 0) + 1;
                        return item;
                    });
                    if (reachedMax) alert('已达到库存或限购数量。');
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

        if (cartCheckout) {
            cartCheckout.addEventListener('click', function () {
                var items = readCart();
                if (!items.length) {
                    renderCart();
                    return;
                }

                if (items.length > 1) {
                    var confirmed = window.confirm('旧版发卡流程一次生成一个订单，我先带你结算购物车里的第一项；剩下的商品还会留在购物车里。');
                    if (!confirmed) return;
                }

                window.location.href = appendPurchaseParams(items[0].url, items[0]);
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
        var detailCartButton = document.querySelector('[data-detail-cart-add]');

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

        function detailBuyMax(stockValue) {
            var stock = toInt(stockValue, 0);
            var buyLimit = toInt(window.YUYANJIA_BUY_LIMIT || 0, 0);
            var max = stock > 0 ? stock : 0;
            if (buyLimit > 0) {
                max = max > 0 ? Math.min(max, buyLimit) : buyLimit;
            }
            return max;
        }

        function updateDetailAmountLimit(stockValue) {
            if (!amountInput) return;
            var max = detailBuyMax(stockValue);
            amountInput.max = max > 0 ? String(max) : '';
            if (amountInput.value === '') return;
            var amount = Math.max(1, toInt(amountInput.value, 1));
            if (max > 0 && amount > max) amount = max;
            amountInput.value = String(amount);
        }

        function activeDetailSku() {
            var activeSku = document.querySelector('[data-sku-option].active');
            if (!activeSku) return null;
            var nameNode = activeSku.querySelector('span');

            return {
                id: activeSku.getAttribute('data-sku-id') || '',
                name: activeSku.getAttribute('data-sku-name') || (nameNode ? nameNode.textContent : '默认规格'),
                price: toNumber(activeSku.getAttribute('data-sku-price'), 0),
                stock: toInt(activeSku.getAttribute('data-sku-stock'), 0),
                image: activeSku.getAttribute('data-sku-picture') || (imageTarget ? imageTarget.getAttribute('src') : ''),
                url: detailCartButton ? detailCartButton.getAttribute('data-cart-url') || window.location.pathname : window.location.pathname
            };
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
                updateDetailAmountLimit(stockValue);
                if (submitButton) submitButton.disabled = stockValue <= 0;
                if (detailCartButton) detailCartButton.disabled = stockValue <= 0;
            });
        });

        if (skuButtons.length) {
            var preselectSku = String(window.YUYANJIA_PRESELECT_SKU || '');
            var initialSkuButton = null;
            if (preselectSku) {
                skuButtons.forEach(function (button) {
                    if (!initialSkuButton && String(button.getAttribute('data-sku-id') || '') === preselectSku) {
                        initialSkuButton = button;
                    }
                });
            }
            (initialSkuButton || skuButtons[0]).click();
        }

        if (amountInput && window.YUYANJIA_PRESELECT_QTY) {
            amountInput.value = String(Math.max(1, toInt(window.YUYANJIA_PRESELECT_QTY, 1)));
            var selectedSku = activeDetailSku();
            updateDetailAmountLimit(selectedSku ? selectedSku.stock : 0);
        }

        if (amountInput) {
            amountInput.addEventListener('input', function () {
                if (amountInput.value === '') return;
                var selectedSku = activeDetailSku();
                updateDetailAmountLimit(selectedSku ? selectedSku.stock : 0);
            });
        }

        if (detailCartButton) {
            detailCartButton.addEventListener('click', function () {
                var sku = activeDetailSku();
                if (!sku) return;
                var qty = toInt(amountInput && amountInput.value, 1);
                var item = payloadFromProduct(detailCartButton, sku, qty);
                if (addCartItem(item, qty)) openCart();
            });
        }

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

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                if (skuPicker && skuPicker.classList.contains('is-open')) {
                    closeSkuPicker();
                    return;
                }
                if (cartDrawer && cartDrawer.classList.contains('is-open')) {
                    closeCart();
                }
            }
        });

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
