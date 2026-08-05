// ============================================================================
// General Functions
// ============================================================================



// ============================================================================
// Page Load (jQuery)
// ============================================================================

$(() => {

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

    // AJAX Add to Cart (product list / detail / buy again)
    $(document).on('submit', 'form.ajax-cart', function (e) {
        e.preventDefault();
        const $form = $(this);
        const id = $form.find('[name=id]').val();
        const unit = $form.find('[name=unit]').val() || 1;
        const mode = $form.data('cart-mode') || 'set'; // set | add
        const $btn = $form.find('button[type=submit]');

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
function showConfirm(title, body, onConfirm) {
    // Remove any existing modal
    $('.modal-overlay').remove();

    const warnIcon =
        "<svg viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' " +
        "stroke-linecap='round' stroke-linejoin='round'>" +
        "<path d='M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z'/>" +
        "<path d='M12 9v4'/><path d='M12 17h.01'/></svg>";

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
