// ============================================================================
// General Functions
// ============================================================================



// ============================================================================
// Page Load (jQuery)
// ============================================================================

$(() => {

    // Admin sidebar: remember expanded groups (current page's group stays open)
    const $adminNav = $('nav.nav-admin');
    if ($adminNav.length) {
        $adminNav.find('details.nav-group').each(function () {
            const el = this;
            const key = 'admin-nav-group-' + (el.dataset.group || '');
            const forced = el.dataset.forceOpen === '1';
            if (!forced) {
                const saved = localStorage.getItem(key);
                if (saved === '1') el.open = true;
                if (saved === '0') el.open = false;
            } else {
                el.open = true;
            }
            $(el).on('toggle', function () {
                if (el.dataset.forceOpen === '1' && !el.open) {
                    localStorage.setItem(key, '0');
                    return;
                }
                localStorage.setItem(key, el.open ? '1' : '0');
            });
        });
    }

    // Autofocus
    $('form :input:not(button):first').focus();
    $('.err:first').prev().focus();
    $('.err:first').prev().find(':input:first').focus();
    
    // Confirmation message (modern modal, replaces native confirm())
    $('[data-confirm]').on('click', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();

        const el = e.currentTarget;
        const raw = el.dataset.confirm || 'Are you sure?';
        const parts = raw.split('\n');
        const title = parts[0] || 'Are you sure?';
        const body = parts.slice(1).join('\n') || 'This action cannot be undone.';

        showConfirm(title, body, () => runConfirmedAction(el));
    });

    // Initiate GET request
    $('[data-get]').on('click', e => {
        e.preventDefault();
        const el = e.currentTarget;
        const url = el.dataset.get;
        location = url || location;
    });

    // Initiate POST request
    $('[data-post]').on('click', e => {
        e.preventDefault();
        const el = e.currentTarget;
        const url = el.dataset.post;
        const f = $('<form>').appendTo(document.body)[0];
        f.method = 'POST';
        f.action = url || location;
        f.submit();
    });

    // Reset form
    $('[type=reset]').on('click', e => {
        e.preventDefault();
        const f = e.target.form;
        if (f) {
            f.reset();
            $(f).find('label.upload img').each((i, img) => {
                if (img.dataset.src) {
                    img.src = img.dataset.src;
                }
            });
        }
    });

    // Auto uppercase
    $('[data-upper]').on('input', e => {
        const a = e.target.selectionStart;
        const b = e.target.selectionEnd;
        e.target.value = e.target.value.toUpperCase();
        e.target.setSelectionRange(a, b);
    });

    // Photo preview
    $('label.upload input[type=file]').on('change', e => {
        const f = e.target.files[0];
        const img = $(e.target).siblings('img')[0];

        if (!img) return;

        img.dataset.src ??= img.src;

        if (f?.type.startsWith('image/')) {
            img.src = URL.createObjectURL(f);
        }
        else {
            img.src = img.dataset.src;
            e.target.value = '';
        }
    });

    // Show custom quantity modal for Add to Cart
    $(document).on('click', 'button[data-ask-qty]', function (e) {
        e.preventDefault();
        const $btn = $(this);
        const $form = $btn.closest('form');
        const max = parseInt($btn.data('max')) || 10;
        const current = parseInt($form.find('[name=unit]').val()) || 1;

        showQuantityModal({
            max,
            value: current,
            onConfirm: qty => {
                $form.find('[name=unit]').val(qty);
                $form.submit();
            }
        });
    });

    // AJAX Add to Cart (product list / detail / buy again)
    $(document).on('submit', 'form.ajax-cart', function (e) {
        e.preventDefault();
        const $form = $(this);
        const id = $form.find('[name=id]').val();
        const unit = $form.find('[name=unit]').val() || 1;
        const mode = $form.data('cart-mode') || 'set'; // set | add
        const $btn = $form.find('button[data-ask-qty]');

        $btn.prop('disabled', true);

        $.ajax({
            url: '/order/cart_api.php',
            method: 'POST',
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            data: { action: mode === 'add' ? 'add' : 'update', id, unit }
        }).done(res => {
            if (!res.ok) {
                showToast(res.error || 'Could not add to cart');
                return;
            }
            updateNavCartBadge(res.line_count);
            showToast(res.message || 'Added to cart');

            const $card = $form.closest('.product, .product-detail');
            const item = (res.items || []).find(i => String(i.id) === String(id));
            if (item) {
                let $badge = $card.find('.badge.in-cart');
                if (!$badge.length && $card.find('.thumb').length) {
                    $badge = $('<span class="badge in-cart"></span>');
                    $card.find('.thumb').prepend($badge);
                }
                if ($badge.length) $badge.text(item.unit + ' in cart');

                let $pd = $form.find('.badge-status.success');
                if ($pd.length) {
                    $pd.text(item.unit + ' in cart');
                } else if ($form.hasClass('pd-buy')) {
                    $form.append('<span class="badge-status success">' + item.unit + ' in cart</span>');
                }

                const remaining = Math.max(0, Number(item.stock) - Number(item.unit));
                const $avail = $(`.avail-box[data-product-id="${id}"]`);
                if ($avail.length) {
                    if (remaining > 0) {
                        $avail.removeClass('out').addClass('in').text(remaining + ' available').attr('data-available', remaining);
                    } else {
                        $avail.removeClass('in').addClass('out').text('Out of stock').attr('data-available', 0);
                    }
                }
                const newMax = Math.min(remaining, 10);
                $btn.attr('data-max', newMax);
                if (remaining <= 0) {
                    $btn.prop('disabled', true).text('Sold Out');
                } else {
                    $btn.prop('disabled', false).text('Add to Cart');
                }
                $form.find('[name=unit]').val(1);
            }
        }).fail(() => {
            showToast('Network error');
        }).always(() => {
            $btn.prop('disabled', false);
        });
    });

});

// ============================================================================
// Cart helpers (AJAX)
// ============================================================================

function updateNavCartBadge(lineCount) {
    const $link = $('nav a[href="/order/cart.php"]');
    if (!$link.length) return;
    let $b = $link.find('.nav-badge');
    if (lineCount > 0) {
        if (!$b.length) $b = $('<span class="nav-badge"></span>').appendTo($link);
        $b.text(lineCount);
    } else {
        $b.remove();
    }
}

function showToast(msg) {
    if (!msg) return;
    let $t = $('#cart-toast');
    if (!$t.length) {
        $t = $('<div id="cart-toast" class="cart-toast"></div>').appendTo('body');
    }
    $t.text(msg).addClass('show');
    clearTimeout(window._cartToast);
    window._cartToast = setTimeout(() => $t.removeClass('show'), 1800);
}

// ============================================================================
// Confirmation Modal
// ============================================================================

// Perform the original action of a confirmed element (mirrors data-get/data-post)
function runConfirmedAction(el) {
    if (el.dataset.post !== undefined) {
        const f = document.createElement('form');
        f.method = 'POST';
        f.action = el.dataset.post || location.href;
        document.body.appendChild(f);
        f.submit();
    }
    else if (el.dataset.get !== undefined) {
        location = el.dataset.get || location;
    }
    else if (el.tagName === 'A' && el.getAttribute('href')) {
        location = el.getAttribute('href');
    }
    else if (el.form) {
        el.form.submit();
    }
}

// Show a modern confirmation modal
function showQuantityModal({ max = 10, value = 1, onConfirm }) {
    $('.modal-overlay').remove();

    const $overlay = $(
        "<div class='modal-overlay quantity-overlay' role='dialog' aria-modal='true'>" +
            "<div class='modal quantity-modal'>" +
                "<div class='modal-icon'>✔</div>" +
                "<h3>Select quantity</h3>" +
                "<p class='quantity-info'>Enter how many items to add to cart.</p>" +
                "<div class='quantity-controls'>" +
                    "<button type='button' class='qty-minus'>−</button>" +
                    "<input type='number' min='1' max='" + max + "' value='" + value + "' aria-label='Quantity' />" +
                    "<button type='button' class='qty-plus'>+</button>" +
                "</div>" +
                "<div class='quantity-available'>" +
                    "Available: " + max + " items" +
                "</div>" +
                "<div class='modal-actions'>" +
                    "<button type='button' class='secondary js-cancel'>Cancel</button>" +
                    "<button type='button' class='success js-confirm'>Add to Cart</button>" +
                "</div>" +
            "</div>" +
        "</div>"
    );

    const $input = $overlay.find('input');
    const updateValue = qty => {
        qty = parseInt(qty);
        if (isNaN(qty) || qty < 1) {
            showToast('Quantity must be at least 1');
            $input.val(1);
            return;
        }
        if (qty > max) {
            showToast('Quantity cannot exceed ' + max);
            $input.val(max);
            return;
        }
        $input.val(qty);
    };

    $overlay.find('.qty-minus').on('click', () => {
        const next = parseInt($input.val()) - 1;
        updateValue(next);
    });
    $overlay.find('.qty-plus').on('click', () => {
        const next = parseInt($input.val()) + 1;
        updateValue(next);
    });

    $input.on('input', () => {
        let qty = parseInt($input.val());
        if (isNaN(qty) || qty < 1) qty = 1;
        if (qty > max) qty = max;
        $input.val(qty);
    });

    $input.on('change', () => {
        const qty = parseInt($input.val());
        if (isNaN(qty) || qty < 1) {
            $input.val(1);
            showToast('Quantity must be at least 1');
            return;
        }
        if (qty > max) {
            $input.val(max);
            showToast('Quantity cannot exceed ' + max);
        }
    });

    const close = () => $overlay.remove();
    $overlay.find('.js-cancel').on('click', close);
    $overlay.find('.js-confirm').on('click', () => {
        const qty = parseInt($input.val());
        if (isNaN(qty) || qty < 1) {
            showToast('Please enter a valid quantity');
            return;
        }
        if (qty > max) {
            showToast('Quantity cannot exceed ' + max);
            updateValue(max);
            return;
        }
        close();
        onConfirm(qty);
    });
    $overlay.on('click', e => { if (e.target === $overlay[0]) close(); });
    $(document).on('keydown.quantityModal', e => {
        if (e.key === 'Escape') {
            close();
            $(document).off('keydown.quantityModal');
        }
    });

    $('body').append($overlay);
    $input.focus().select();
}

function showConfirm(title, body, onConfirm) {
    // Remove any existing modal
    $('.modal-overlay').remove();

    const warnIcon =
        "<svg viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' " +
        "stroke-linecap='round' stroke-linejoin='round'>" +
        "<path d='M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z'/>" +
        "<path d='M12 9v4'/><path d='M12 17h.01'/>" +
        "</svg>";

    const $overlay = $(
        "<div class='modal-overlay' role='dialog' aria-modal='true'>" +
            "<div class='modal'>" +
                "<div class='modal-icon'>" + warnIcon + "</div>" +
                "<h3></h3>" +
                "<p></p>" +
                "<div class='modal-actions'>" +
                    "<button type='button' class='secondary js-cancel'>Cancel</button>" +
                    "<button type='button' class='danger js-ok'>Confirm</button>" +
                "</div>" +
            "</div>" +
        "</div>"
    );

    $overlay.find('h3').text(title);
    $overlay.find('p').text(body);
    $('body').append($overlay);

    const close = () => $overlay.remove();

    $overlay.find('.js-ok').on('click', () => { close(); onConfirm(); }).focus();
    $overlay.find('.js-cancel').on('click', close);
    $overlay.on('click', e => { if (e.target === $overlay[0]) close(); });
    $(document).on('keydown.modal', e => {
        if (e.key === 'Escape') { close(); $(document).off('keydown.modal'); }
    });
}

