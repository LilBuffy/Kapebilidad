<?php
require_once __DIR__ . '/../includes/customer_auth.php';
require_once __DIR__ . '/../includes/pricing.php';

$customer = requireCustomerApi();
$data = readJsonBody();

$orderCode = cleanInput($data['order_code'] ?? '');
$itemId = (int)($data['item_id'] ?? 0);
$newSizeId = (int)($data['size_id'] ?? 0);
$version = (int)($data['version'] ?? 0);

if ($orderCode === '' || $itemId <= 0 || $newSizeId <= 0) {
    jsonResponse(['success' => false, 'error' => 'Please choose a size to change to.'], 422);
}

$db = getDB();

try {
    $db->beginTransaction();

    $orderStmt = $db->prepare('SELECT * FROM orders WHERE order_code = :code AND customer_id = :cid LIMIT 1 FOR UPDATE');
    $orderStmt->execute([':code' => $orderCode, ':cid' => $customer['id']]);
    $order = $orderStmt->fetch();
    if (!$order) {
        $db->rollBack();
        jsonResponse(['success' => false, 'error' => 'Order not found.'], 404);
    }
    if (!orderIsEditable($order)) {
        $db->rollBack();
        jsonResponse(['success' => false, 'error' => orderEditBlockedReason($order), 'locked' => true], 409);
    }
    if ($version !== (int)$order['version']) {
        $db->rollBack();
        jsonResponse(['success' => false, 'error' => 'This order was just updated. Please refresh the page and try again.', 'stale' => true], 409);
    }

    $itemStmt = $db->prepare('SELECT * FROM order_items WHERE id = :id AND order_id = :oid LIMIT 1 FOR UPDATE');
    $itemStmt->execute([':id' => $itemId, ':oid' => $order['id']]);
    $item = $itemStmt->fetch();
    if (!$item) {
        $db->rollBack();
        jsonResponse(['success' => false, 'error' => 'That item is not part of this order.'], 404);
    }
    if (empty($item['product_id']) || $item['size_name'] === null) {
        $db->rollBack();
        jsonResponse(['success' => false, 'error' => 'The size of this item cannot be changed.'], 422);
    }

    $sizeStmt = $db->prepare(
        'SELECT s.id, s.name, ps.price_adjustment
           FROM product_sizes ps
           JOIN sizes s ON s.id = ps.size_id
          WHERE ps.product_id = :pid AND ps.size_id = :sid AND ps.is_available = 1 AND s.is_active = 1
          LIMIT 1'
    );
    $sizeStmt->execute([':pid' => $item['product_id'], ':sid' => $newSizeId]);
    $newSize = $sizeStmt->fetch();
    if (!$newSize) {
        $db->rollBack();
        jsonResponse(['success' => false, 'error' => 'That size is not available for this drink.'], 422);
    }

    if ((int)$item['size_id'] === (int)$newSize['id'] || (empty($item['size_id']) && $item['size_name'] === $newSize['name'])) {
        $db->rollBack();
        jsonResponse(['success' => true, 'unchanged' => true, 'message' => 'That size is already selected.', 'version' => (int)$order['version']]);
    }

    $baseCents = $item['base_price'] !== null ? moneyToCents($item['base_price']) : max(0, moneyToCents($item['price']) - moneyToCents($item['size_price']));
    $addonsCents = moneyToCents($item['addons_total']);
    $sizeCents = moneyToCents($newSize['price_adjustment']);
    $unitCents = $baseCents + $sizeCents + $addonsCents;
    $subtotalCents = $unitCents * (int)$item['quantity'];

    $oldSubtotal = (string)$item['subtotal'];
    $oldTotal = (string)$order['total_price'];
    $oldSize = $item['size_name'];

    $db->prepare(
        'UPDATE order_items
            SET size_id = :sid, size_name = :sname, size_price = :sprice, base_price = :base,
                price = :price, subtotal = :subtotal
          WHERE id = :id'
    )->execute([
        ':sid' => $newSize['id'],
        ':sname' => $newSize['name'],
        ':sprice' => centsToMoney($sizeCents),
        ':base' => centsToMoney($baseCents),
        ':price' => centsToMoney($unitCents),
        ':subtotal' => centsToMoney($subtotalCents),
        ':id' => $item['id'],
    ]);

    $totals = recalculateOrderTotals($db, (int)$order['id']);

    $newVersion = (int)$order['version'] + 1;
    $db->prepare('UPDATE orders SET version = :v, updated_at = NOW() WHERE id = :id')
       ->execute([':v' => $newVersion, ':id' => $order['id']]);

    $db->prepare(
        'INSERT INTO order_item_changes
            (order_id, order_item_id, product_name, old_size, new_size, old_item_subtotal, new_item_subtotal,
             old_order_total, new_order_total, changed_by_customer_id)
         VALUES (:oid, :iid, :pname, :osize, :nsize, :osub, :nsub, :ototal, :ntotal, :cid)'
    )->execute([
        ':oid' => $order['id'],
        ':iid' => $item['id'],
        ':pname' => $item['product_name'],
        ':osize' => $oldSize,
        ':nsize' => $newSize['name'],
        ':osub' => $oldSubtotal,
        ':nsub' => centsToMoney($subtotalCents),
        ':ototal' => $oldTotal,
        ':ntotal' => centsToMoney($totals['total_cents']),
        ':cid' => $customer['id'],
    ]);

    $db->commit();

    jsonResponse([
        'success' => true,
        'message' => $item['product_name'] . ' is now ' . $newSize['name'] . '. Your order total was updated.',
        'size_id' => (int)$newSize['id'],
        'size_name' => $newSize['name'],
        'size_extra' => $sizeCents / 100,
        'unit' => $unitCents / 100,
        'item_subtotal' => $subtotalCents / 100,
        'subtotal' => $totals['subtotal_cents'] / 100,
        'discount_amount' => $totals['discount_cents'] / 100,
        'total' => $totals['total_cents'] / 100,
        'version' => $newVersion,
    ]);
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    jsonResponse(['success' => false, 'error' => 'Could not update your order. Nothing was changed, please try again.'], 500);
}
