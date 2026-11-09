<?php
declare(strict_types=1);

function all_products(): array {
    static $products;
    return $products ??= sb('GET', 'rest/v1/productos?select=*&order=created_at.desc');
}
function get_product(int $id): ?array {
    $rows = sb('GET', 'rest/v1/productos?select=*&id=eq.' . $id . '&limit=1');
    return $rows[0] ?? null;
}
function cart_data(): array {
    $cart = $_SESSION['cart'] ?? [];
    if (!$cart) return ['items' => [], 'total' => 0, 'units' => 0, 'valid' => true];
    $ids = array_map('intval', array_keys($cart));
    $rows = sb('GET', 'rest/v1/productos?select=*&id=in.(' . implode(',', $ids) . ')');
    $byId = [];
    foreach ($rows as $row) $byId[(int)$row['id']] = $row;
    $result = ['items' => [], 'total' => 0, 'units' => 0, 'valid' => true];
    foreach ($cart as $id => $quantity) {
        $p = $byId[(int)$id] ?? null;
        $subtotal = $p ? round((float)$p['precio'] * (int)$quantity, 2) : 0;
        $result['items'][] = ['id' => (int)$id, 'product' => $p, 'quantity' => (int)$quantity, 'subtotal' => $subtotal];
        $result['total'] += $subtotal;
        $result['units'] += (int)$quantity;
        if (!$p || (int)$quantity > (int)$p['stock']) $result['valid'] = false;
    }
    $result['total'] = round($result['total'], 2);
    return $result;
}
