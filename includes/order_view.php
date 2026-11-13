<?php
require_once __DIR__ . '/pricing.php';

function orderDateLabel(string $raw): string
{
    return date('M d, Y g:i A', strtotime($raw));
}

function renderOrderItemsHtml(array $items, array $sizeOptions = [], bool $editable = false): string
{
    ob_start();
    foreach ($items as $item) {
        $id = (int)$item['id'];
        $addonLines = itemAddonLines($item);
        $options = $sizeOptions[(int)($item['product_id'] ?? 0)] ?? [];
        $canEdit = $editable && !empty($item['product_id']) && $item['size_name'] !== null && count($options) > 1;
        ?>
        <div class="oi" data-item-id="<?= $id ?>" data-qty="<?= (int)$item['quantity'] ?>" data-base="<?= h(centsToMoney(moneyToCents($item['base_price'] ?? max(0, (float)$item['price'] - (float)$item['size_price'])))) ?>" data-addons="<?= h(centsToMoney(moneyToCents($item['addons_total']))) ?>">
          <div class="oi-top">
            <span class="oi-name">
              <span class="qty-tag"><?= (int)$item['quantity'] ?>x</span> <?= h($item['product_name']) ?>
              <?php if (!empty($item['size_name'])): ?><span class="size-tag" id="item-size-<?= $id ?>"><?= h($item['size_name']) ?></span><?php endif; ?>
            </span>
            <span class="oi-subtotal" id="item-sub-<?= $id ?>"><?= formatPrice($item['subtotal']) ?></span>
          </div>
          <div class="oi-breakdown">
            <div class="oi-line"><span>Base price</span><span><?= formatPrice($item['base_price'] ?? ((float)$item['price'] - (float)$item['size_price'])) ?></span></div>
            <?php if (!empty($item['size_name'])): ?>
              <div class="oi-line"><span>Size: <span id="item-size-name-<?= $id ?>"><?= h($item['size_name']) ?></span></span><span id="item-size-extra-<?= $id ?>"><?= (float)$item['size_price'] > 0 ? '+' . formatPrice($item['size_price']) : 'No extra charge' ?></span></div>
            <?php endif; ?>
            <?php foreach ($addonLines as $line): ?>
              <div class="oi-line"><span>Add-on: <?= h($line['name']) ?><?= $line['qty'] > 1 ? ' x' . $line['qty'] : '' ?></span><span>+<?= formatPrice($line['total']) ?></span></div>
            <?php endforeach; ?>
            <?php if (empty($addonLines) && !empty($item['addons_summary'])): ?>
              <div class="oi-line"><span>Add-ons: <?= h($item['addons_summary']) ?></span><span></span></div>
            <?php endif; ?>
            <div class="oi-line oi-unit"><span>Price each</span><span id="item-unit-<?= $id ?>"><?= formatPrice($item['price']) ?></span></div>
          </div>
          <?php if (!empty($item['item_note'])): ?>
            <div class="oi-note">Note: <?= h($item['item_note']) ?></div>
          <?php endif; ?>
          <?php if ($canEdit): ?>
            <form class="size-edit-form" data-item-id="<?= $id ?>">
              <label for="size-select-<?= $id ?>">Change size</label>
              <div class="size-edit-row">
                <select id="size-select-<?= $id ?>" class="size-select">
                  <?php foreach ($options as $opt): ?>
                    <option value="<?= (int)$opt['id'] ?>" data-adjust="<?= h(centsToMoney(moneyToCents($opt['price']))) ?>" <?= ((int)$item['size_id'] === (int)$opt['id'] || (empty($item['size_id']) && $item['size_name'] === $opt['name'])) ? 'selected' : '' ?>><?= h($opt['name']) ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-secondary size-save-btn">Update size</button>
              </div>
              <div class="size-preview" aria-live="polite"></div>
            </form>
          <?php endif; ?>
        </div>
        <?php
    }
    return ob_get_clean();
}

function renderOrderTotalsHtml(array $order): string
{
    $b = orderBreakdown($order);
    ob_start();
    ?>
    <div class="oi-totals">
      <div class="oi-line"><span>Subtotal</span><span id="order-subtotal"><?= formatPrice($b['subtotal']) ?></span></div>
      <div class="oi-line oi-discount" id="order-discount-row" <?= $b['discount'] > 0 ? '' : 'style="display:none"' ?>>
        <span><?= h($order['discount_label'] ?? 'Discount') ?> (<?= h(percentLabel($order['discount_percent'] ?? 0)) ?>% off)</span>
        <span id="order-discount">-<?= formatPrice($b['discount']) ?></span>
      </div>
      <div class="oi-line oi-final"><span>Total</span><span id="order-total"><?= formatPrice($b['total']) ?></span></div>
    </div>
    <?php
    return ob_get_clean();
}

function fetchCustomerOrder(PDO $db, string $code, int $customerId): ?array
{
    $stmt = $db->prepare('SELECT * FROM orders WHERE order_code = :code AND customer_id = :cid LIMIT 1');
    $stmt->execute([':code' => $code, ':cid' => $customerId]);
    return $stmt->fetch() ?: null;
}

function sizeOptionsForItems(PDO $db, array $items): array
{
    $ids = [];
    foreach ($items as $item) {
        if (!empty($item['product_id'])) {
            $ids[(int)$item['product_id']] = true;
        }
    }
    if (empty($ids)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare(
        "SELECT ps.product_id, s.id, s.name, ps.price_adjustment AS price
           FROM product_sizes ps JOIN sizes s ON s.id = ps.size_id
          WHERE ps.product_id IN ($placeholders) AND ps.is_available = 1 AND s.is_active = 1
          ORDER BY s.display_order ASC, s.id ASC"
    );
    $stmt->execute(array_keys($ids));
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[(int)$row['product_id']][] = ['id' => (int)$row['id'], 'name' => $row['name'], 'price' => (float)$row['price']];
    }
    return $out;
}
