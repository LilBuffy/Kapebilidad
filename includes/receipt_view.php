<?php
require_once __DIR__ . '/pricing.php';

function receiptAmount($value): string
{
    return number_format((float)$value, 2);
}

function receiptDate(string $rawTimestamp): string
{
    try {
        $dt = new DateTime($rawTimestamp, new DateTimeZone('Asia/Manila'));
    } catch (Throwable $e) {
        $dt = new DateTime('now', new DateTimeZone('Asia/Manila'));
    }
    return $dt->format('M d, Y g:i A');
}

function renderReceiptPage(array $order, array $orderItems, string $backUrl, array $extra = []): void
{
    $b = orderBreakdown($order);
    $badge = statusBadge($order['status']);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Receipt <?= h($order['order_code']) ?>, Kapebilidad</title>
<style>
    :root {
        --rc-ink: #e8dfd4;
        --rc-line: #50453a;
        --rc-muted: #b1a497;
    }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        padding: 26px 14px 50px;
        background: #191715;
        color: var(--rc-ink);
        font-family: 'Courier New', Courier, monospace;
        font-size: 12.5px;
        line-height: 1.5;
    }

    .receipt {
        width: 80mm;
        max-width: 100%;
        margin: 0 auto;
        background: #24211e;
        padding: 16px 14px 22px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
    }

    .rc-head { text-align: center; margin-bottom: 12px; }
    .rc-logo { width: 46px; height: auto; margin: 0 auto 8px; display: block; }
    .rc-shop { font-size: 16px; font-weight: bold; letter-spacing: 0.06em; }
    .rc-sub { font-size: 11px; color: var(--rc-muted); }

    .rc-divider { border-top: 1px dashed var(--rc-line); margin: 10px 0; }

    .rc-row {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        align-items: baseline;
    }
    .rc-row .rc-label { color: var(--rc-muted); }

    .rc-code {
        text-align: center;
        font-size: 15px;
        font-weight: bold;
        letter-spacing: 0.08em;
        margin: 4px 0 2px;
    }

    .rc-item { margin-bottom: 9px; }
    .rc-item-top { display: flex; justify-content: space-between; gap: 10px; font-weight: bold; }
    .rc-item-meta { font-size: 11px; color: var(--rc-muted); padding-left: 10px; }

    .rc-total {
        display: flex;
        justify-content: space-between;
        font-size: 14px;
        font-weight: bold;
        padding-top: 7px;
        border-top: 1px solid var(--rc-ink);
        margin-top: 4px;
    }

    .rc-note {
        font-size: 11px;
        border: 1px dashed var(--rc-line);
        padding: 6px 8px;
        margin-top: 8px;
    }

    .rc-footer {
        text-align: center;
        font-size: 11px;
        color: var(--rc-muted);
        margin-top: 14px;
    }

    .rc-actions {
        width: 80mm;
        max-width: 100%;
        margin: 18px auto 0;
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .rc-actions a, .rc-actions button {
        flex: 1;
        min-width: 84px;
        padding: 11px 8px;
        border-radius: 9px;
        border: 1.5px solid #75573a;
        background: #49372a;
        color: #f3e9de;
        font-family: 'Segoe UI', system-ui, sans-serif;
        font-size: 12.5px;
        font-weight: 700;
        text-align: center;
        text-decoration: none;
        cursor: pointer;
    }
    .rc-actions a { background: transparent; color: #e8dfd4; }
    .rc-actions button:disabled { opacity: 0.6; cursor: default; }

    .rc-pdf-status {
        display: none;
        width: 80mm;
        max-width: 100%;
        margin: 10px auto 0;
        padding: 10px 12px;
        border-radius: 9px;
        background: #3d2926;
        border: 1px solid #67403a;
        color: #e1aaa2;
        font-family: 'Segoe UI', system-ui, sans-serif;
        font-size: 12px;
        line-height: 1.5;
    }
    .rc-pdf-status.show { display: block; }

    @media print {
        body { background: #fff; padding: 0; font-size: 12px; }
        .receipt { width: auto; box-shadow: none; padding: 0; }
        .rc-actions { display: none; }
        @page { margin: 6mm; }
    }

    .rc-block { margin: 6px 0; font-size: 11.5px; }
    .rc-block .rc-label { color: var(--rc-muted); display: block; }
    .rc-line { display: flex; justify-content: space-between; gap: 10px; font-size: 11px; color: var(--rc-muted); padding-left: 10px; }
</style>
</head>
<body>

<div class="receipt" id="receiptContent" data-order-code="<?= h($order['order_code']) ?>">
    <div class="rc-head">
        <img class="rc-logo" src="<?= BASE_URL ?>/assets/images/logo.png" alt="">
        <div class="rc-shop">KAPEBILIDAD</div>
        <div class="rc-sub">Cafe and Kitchen</div>
        <div class="rc-sub">Quezon City, Philippines</div>
    </div>

    <div class="rc-divider"></div>

    <div class="rc-code"><?= h($order['order_code']) ?></div>

    <div class="rc-row"><span class="rc-label">Order ID</span><span>#<?= (int)$order['id'] ?></span></div>
    <div class="rc-row"><span class="rc-label">Customer</span><span><?= h($order['customer_name']) ?></span></div>
    <?php if (!empty($extra['account_line'])): ?>
        <div class="rc-row"><span class="rc-label">Account</span><span><?= h($extra['account_line']) ?></span></div>
    <?php endif; ?>
    <div class="rc-row"><span class="rc-label">Date</span><span><?= h(receiptDate($order['created_at'])) ?></span></div>
    <div class="rc-row"><span class="rc-label">Status</span><span><?= h($badge['label']) ?></span></div>
    <div class="rc-row"><span class="rc-label">Payment</span><span><?= h(paymentLabel($order['payment_method'] ?? 'cash')) ?></span></div>

    <div class="rc-divider"></div>

    <div class="rc-block">
        <span class="rc-label">Deliver to</span>
        <?= !empty($order['delivery_address']) ? nl2br(h($order['delivery_address'])) : 'Not recorded' ?>
    </div>
    <?php if (!empty($order['delivery_instructions'])): ?>
        <div class="rc-block"><span class="rc-label">Delivery instructions</span><?= nl2br(h($order['delivery_instructions'])) ?></div>
    <?php endif; ?>

    <div class="rc-divider"></div>

    <?php foreach ($orderItems as $item): ?>
        <?php $addonLines = itemAddonLines($item); ?>
        <div class="rc-item">
            <div class="rc-item-top">
                <span><?= (int)$item['quantity'] ?>x <?= h($item['product_name']) ?><?= !empty($item['size_name']) ? ' (' . h($item['size_name']) . ')' : '' ?></span>
                <span><?= receiptAmount($item['subtotal']) ?></span>
            </div>
            <div class="rc-line"><span>Base</span><span><?= receiptAmount($item['base_price'] ?? ((float)$item['price'] - (float)$item['size_price'])) ?></span></div>
            <?php if (!empty($item['size_name'])): ?>
                <div class="rc-line"><span>Size: <?= h($item['size_name']) ?></span><span><?= (float)$item['size_price'] > 0 ? '+' . receiptAmount($item['size_price']) : '0.00' ?></span></div>
            <?php endif; ?>
            <?php foreach ($addonLines as $line): ?>
                <div class="rc-line"><span><?= h($line['name']) ?><?= $line['qty'] > 1 ? ' x' . $line['qty'] : '' ?></span><span>+<?= receiptAmount($line['total']) ?></span></div>
            <?php endforeach; ?>
            <?php if (empty($addonLines) && !empty($item['addons_summary'])): ?>
                <div class="rc-item-meta">Add-ons: <?= h($item['addons_summary']) ?></div>
            <?php endif; ?>
            <div class="rc-line"><span>Each</span><span><?= receiptAmount($item['price']) ?></span></div>
            <?php if (!empty($item['item_note'])): ?>
                <div class="rc-item-meta">Note: <?= h($item['item_note']) ?></div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <div class="rc-divider"></div>

    <div class="rc-row"><span>Subtotal</span><span><?= receiptAmount($b['subtotal']) ?></span></div>
    <?php if ($b['discount'] > 0): ?>
        <div class="rc-row">
            <span><?= h($order['discount_label'] ?? 'Discount') ?> (<?= h(percentLabel($order['discount_percent'])) ?>%)</span>
            <span>-<?= receiptAmount($b['discount']) ?></span>
        </div>
    <?php endif; ?>
    <div class="rc-total"><span>TOTAL (PHP)</span><span><?= receiptAmount($b['total']) ?></span></div>

    <?php if (!empty($order['order_note'])): ?>
        <div class="rc-note">Order note: <?= h($order['order_note']) ?></div>
    <?php endif; ?>

    <?php if ($b['discount'] > 0): ?>
        <div class="rc-note">
            <?= h($order['discount_label'] ?? 'Discount') ?> claimed. Valid ID presented to staff.
        </div>
    <?php endif; ?>

    <div class="rc-footer">
        Salamat sa iyong order!<br>
        This serves as your order slip.
    </div>
</div>

<div class="rc-actions">
    <a href="<?= h($backUrl) ?>">Back</a>
    <button type="button" onclick="window.print();">Print</button>
    <button type="button" id="downloadPdfBtn">Download PDF</button>
</div>

<div class="rc-pdf-status" id="pdfStatus"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
(function () {
    var btn = document.getElementById('downloadPdfBtn');
    var statusEl = document.getElementById('pdfStatus');
    if (!btn) return;

    btn.addEventListener('click', function () {
        if (typeof html2pdf === 'undefined') {
            statusEl.textContent = 'Could not load the PDF tool, probably no internet connection on this device. Use Print instead and choose "Save as PDF" in the print dialog.';
            statusEl.classList.add('show');
            return;
        }

        var source = document.getElementById('receiptContent');
        var orderCode = source.dataset.orderCode || 'receipt';
        var originalLabel = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Preparing...';

        html2pdf()
            .set({
                margin: 4,
                filename: 'Kapebilidad-' + orderCode + '.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'mm', format: [80, 200], orientation: 'portrait' }
            })
            .from(source)
            .save()
            .then(function () {
                btn.disabled = false;
                btn.textContent = originalLabel;
            })
            .catch(function () {
                statusEl.textContent = 'Could not generate the PDF. Use Print instead and choose "Save as PDF" in the print dialog.';
                statusEl.classList.add('show');
                btn.disabled = false;
                btn.textContent = originalLabel;
            });
    });
})();
</script>


</body>
</html>
    <?php
}
