<?php
require_once __DIR__ . '/functions.php';

function menuXmlSchemaPath(): string
{
    return __DIR__ . '/../xml/menu.xsd';
}

function menuXmlMaxBytes(): int
{
    return 1024 * 1024;
}

function xmlBool($value): string
{
    return $value ? 'true' : 'false';
}

function buildMenuXml(PDO $db, bool $publicOnly): DOMDocument
{
    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->formatOutput = true;

    $root = $doc->createElement('menuCatalog');
    $root->setAttribute('version', '1.0');
    $root->setAttribute('generatedAt', date('c'));
    $doc->appendChild($root);
    $root->appendChild($doc->createElement('currency', 'PHP'));

    $sizeFilter = $publicOnly ? 'WHERE is_active = 1' : '';
    $sizes = $db->query("SELECT * FROM sizes $sizeFilter ORDER BY display_order, id")->fetchAll();
    $sizesEl = $root->appendChild($doc->createElement('sizes'));
    foreach ($sizes as $s) {
        $el = $sizesEl->appendChild($doc->createElement('size'));
        $el->setAttribute('id', (string)$s['id']);
        $el->setAttribute('available', xmlBool($s['is_active']));
        $el->setAttribute('displayOrder', (string)$s['display_order']);
        $el->appendChild($doc->createElement('name', htmlspecialchars($s['name'], ENT_XML1)));
        $el->appendChild($doc->createElement('defaultExtraPrice', number_format((float)$s['default_price_adjustment'], 2, '.', '')));
    }

    $addonFilter = $publicOnly ? 'WHERE is_active = 1' : '';
    $addons = $db->query("SELECT * FROM addons $addonFilter ORDER BY display_order, id")->fetchAll();
    $addonsEl = $root->appendChild($doc->createElement('addons'));
    $addonIds = [];
    foreach ($addons as $a) {
        $addonIds[(int)$a['id']] = true;
        $el = $addonsEl->appendChild($doc->createElement('addon'));
        $el->setAttribute('id', (string)$a['id']);
        $el->setAttribute('available', xmlBool($a['is_active']));
        $el->setAttribute('displayOrder', (string)$a['display_order']);
        $el->appendChild($doc->createElement('name', htmlspecialchars($a['name'], ENT_XML1)));
        $el->appendChild($doc->createElement('price', number_format((float)$a['price'], 2, '.', '')));
        $el->appendChild($doc->createElement('maxQuantity', (string)max(1, (int)$a['max_quantity'])));
    }

    $sizeActive = [];
    foreach ($db->query('SELECT id, is_active FROM sizes')->fetchAll() as $r) {
        $sizeActive[(int)$r['id']] = (bool)$r['is_active'];
    }

    $productSizes = [];
    foreach ($db->query('SELECT product_id, size_id, price_adjustment, is_available FROM product_sizes ORDER BY size_id')->fetchAll() as $r) {
        $productSizes[(int)$r['product_id']][] = $r;
    }
    $productAddons = [];
    foreach ($db->query('SELECT addon_id, product_id FROM addon_products ORDER BY addon_id')->fetchAll() as $r) {
        $productAddons[(int)$r['product_id']][] = (int)$r['addon_id'];
    }

    $productsByCat = [];
    $productFilter = $publicOnly ? 'WHERE is_available = 1' : '';
    foreach ($db->query("SELECT * FROM products $productFilter ORDER BY id")->fetchAll() as $p) {
        $productsByCat[(int)$p['category_id']][] = $p;
    }

    $catsEl = $root->appendChild($doc->createElement('categories'));
    foreach ($db->query('SELECT * FROM categories ORDER BY display_order, id')->fetchAll() as $c) {
        $cEl = $catsEl->appendChild($doc->createElement('category'));
        $cEl->setAttribute('id', (string)$c['id']);
        $cEl->setAttribute('slug', $c['slug']);
        $cEl->setAttribute('drink', xmlBool($c['is_drink']));
        $cEl->setAttribute('displayOrder', (string)$c['display_order']);
        $cEl->appendChild($doc->createElement('name', htmlspecialchars($c['name'], ENT_XML1)));
        $psEl = $cEl->appendChild($doc->createElement('products'));
        foreach ($productsByCat[(int)$c['id']] ?? [] as $p) {
            $pid = (int)$p['id'];
            $pEl = $psEl->appendChild($doc->createElement('product'));
            $pEl->setAttribute('id', (string)$pid);
            $pEl->setAttribute('available', xmlBool($p['is_available']));
            $pEl->setAttribute('bestseller', xmlBool($p['is_bestseller']));
            $pEl->appendChild($doc->createElement('name', htmlspecialchars($p['name'], ENT_XML1)));
            $pEl->appendChild($doc->createElement('description', htmlspecialchars((string)$p['description'], ENT_XML1)));
            $pEl->appendChild($doc->createElement('basePrice', number_format((float)$p['price'], 2, '.', '')));
            if (!empty($p['image'])) {
                $pEl->appendChild($doc->createElement('image', htmlspecialchars($p['image'], ENT_XML1)));
            }
            $szEl = $pEl->appendChild($doc->createElement('sizes'));
            foreach ($productSizes[$pid] ?? [] as $r) {
                $sid = (int)$r['size_id'];
                $isAvail = $r['is_available'] && ($sizeActive[$sid] ?? false);
                if ($publicOnly && !$isAvail) {
                    continue;
                }
                $e = $szEl->appendChild($doc->createElement('size'));
                $e->setAttribute('sizeId', (string)$sid);
                $e->setAttribute('available', xmlBool($r['is_available']));
                $e->appendChild($doc->createElement('extraPrice', number_format((float)$r['price_adjustment'], 2, '.', '')));
            }
            $adEl = $pEl->appendChild($doc->createElement('addons'));
            foreach ($productAddons[$pid] ?? [] as $aid) {
                if ($publicOnly && !isset($addonIds[$aid])) {
                    continue;
                }
                $adEl->appendChild($doc->createElement('addonRef'))->setAttribute('addonId', (string)$aid);
            }
        }
    }

    return $doc;
}

function menuXmlString(PDO $db, bool $publicOnly): string
{
    return buildMenuXml($db, $publicOnly)->saveXML();
}

function loadMenuXmlSafely(string $raw, array &$errors): ?DOMDocument
{
    if ($raw === '') {
        $errors[] = 'The file is empty.';
        return null;
    }
    if (strlen($raw) > menuXmlMaxBytes()) {
        $errors[] = 'The file is too large. The limit is 1 MB.';
        return null;
    }
    if (!mb_check_encoding($raw, 'UTF-8')) {
        $errors[] = 'The file is not valid UTF-8.';
        return null;
    }
    if (preg_match('/<!(DOCTYPE|ENTITY)/i', $raw)) {
        $errors[] = 'DOCTYPE and ENTITY declarations are not allowed.';
        return null;
    }

    $previous = libxml_use_internal_errors(true);
    libxml_clear_errors();
    $doc = new DOMDocument();
    $ok = $doc->loadXML($raw, LIBXML_NONET | LIBXML_NOBLANKS);
    if (!$ok) {
        foreach (array_slice(libxml_get_errors(), 0, 5) as $e) {
            $errors[] = 'Malformed XML on line ' . $e->line . ': ' . trim($e->message);
        }
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return null;
    }

    if (!$doc->schemaValidate(menuXmlSchemaPath())) {
        foreach (array_slice(libxml_get_errors(), 0, 10) as $e) {
            $errors[] = 'Schema error on line ' . $e->line . ': ' . trim($e->message);
        }
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return null;
    }
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return $doc;
}

function toBool(string $v): bool
{
    return $v === 'true' || $v === '1';
}

function parseMenuXml(PDO $db, DOMDocument $doc, array &$errors): ?array
{
    $xp = new DOMXPath($doc);
    $dbSizes = array_map('intval', $db->query('SELECT id FROM sizes')->fetchAll(PDO::FETCH_COLUMN));
    $dbAddons = array_map('intval', $db->query('SELECT id FROM addons')->fetchAll(PDO::FETCH_COLUMN));
    $dbCats = [];
    foreach ($db->query('SELECT id, slug FROM categories')->fetchAll() as $r) {
        $dbCats[(int)$r['id']] = $r['slug'];
    }
    $dbProducts = [];
    foreach ($db->query('SELECT id, category_id FROM products')->fetchAll() as $r) {
        $dbProducts[(int)$r['id']] = (int)$r['category_id'];
    }

    $data = ['sizes' => [], 'addons' => [], 'categories' => [], 'products' => []];
    $seen = ['size' => [], 'addon' => [], 'category' => [], 'product' => [], 'addonname' => [], 'sizename' => []];

    foreach ($xp->query('/menuCatalog/sizes/size') as $n) {
        $id = (int)$n->getAttribute('id');
        $name = trim($xp->evaluate('string(name)', $n));
        if (!in_array($id, $dbSizes, true)) {
            $errors[] = "Size id $id does not exist.";
            continue;
        }
        if (isset($seen['size'][$id])) {
            $errors[] = "Size id $id appears more than once.";
            continue;
        }
        if (isset($seen['sizename'][mb_strtolower($name)])) {
            $errors[] = "Size name '$name' is duplicated.";
        }
        $seen['size'][$id] = true;
        $seen['sizename'][mb_strtolower($name)] = true;
        $data['sizes'][$id] = ['name' => $name, 'default' => $xp->evaluate('string(defaultExtraPrice)', $n), 'active' => toBool($n->getAttribute('available'))];
    }

    foreach ($xp->query('/menuCatalog/addons/addon') as $n) {
        $id = (int)$n->getAttribute('id');
        $name = trim($xp->evaluate('string(name)', $n));
        if (!in_array($id, $dbAddons, true)) {
            $errors[] = "Add-on id $id does not exist.";
            continue;
        }
        if (isset($seen['addon'][$id])) {
            $errors[] = "Add-on id $id appears more than once.";
            continue;
        }
        if (mb_strlen($name) > 60) {
            $errors[] = "Add-on $id name is longer than 60 characters.";
        }
        if (isset($seen['addonname'][mb_strtolower($name)])) {
            $errors[] = "Add-on name '$name' is duplicated.";
        }
        $seen['addon'][$id] = true;
        $seen['addonname'][mb_strtolower($name)] = true;
        $data['addons'][$id] = [
            'name' => $name,
            'price' => $xp->evaluate('string(price)', $n),
            'max' => (int)$xp->evaluate('string(maxQuantity)', $n),
            'active' => toBool($n->getAttribute('available')),
        ];
    }

    foreach ($xp->query('/menuCatalog/categories/category') as $c) {
        $cid = (int)$c->getAttribute('id');
        if (!isset($dbCats[$cid])) {
            $errors[] = "Category id $cid does not exist.";
            continue;
        }
        if (isset($seen['category'][$cid])) {
            $errors[] = "Category id $cid appears more than once.";
            continue;
        }
        if ($c->getAttribute('slug') !== $dbCats[$cid]) {
            $errors[] = "Category $cid slug does not match the existing category.";
        }
        $seen['category'][$cid] = true;
        $cname = trim($xp->evaluate('string(name)', $c));
        if (mb_strlen($cname) > 50) {
            $errors[] = "Category $cid name is longer than 50 characters.";
        }
        $data['categories'][$cid] = ['name' => $cname, 'drink' => toBool($c->getAttribute('drink'))];

        foreach ($xp->query('products/product', $c) as $p) {
            $pid = (int)$p->getAttribute('id');
            if (!isset($dbProducts[$pid])) {
                $errors[] = "Product id $pid does not exist.";
                continue;
            }
            if ($dbProducts[$pid] !== $cid) {
                $errors[] = "Product id $pid belongs to a different category in the database.";
                continue;
            }
            if (isset($seen['product'][$pid])) {
                $errors[] = "Product id $pid appears more than once.";
                continue;
            }
            $seen['product'][$pid] = true;

            $name = trim($xp->evaluate('string(name)', $p));
            $desc = trim($xp->evaluate('string(description)', $p));
            if (mb_strlen($name) > 100 || mb_strlen($desc) > 255) {
                $errors[] = "Product $pid has a name or description that is too long.";
            }

            $psizes = [];
            foreach ($xp->query('sizes/size', $p) as $s) {
                $sid = (int)$s->getAttribute('sizeId');
                if (!in_array($sid, $dbSizes, true)) {
                    $errors[] = "Product $pid uses unknown size id $sid.";
                    continue;
                }
                if (isset($psizes[$sid])) {
                    $errors[] = "Product $pid lists size id $sid more than once.";
                    continue;
                }
                $psizes[$sid] = ['extra' => $xp->evaluate('string(extraPrice)', $s), 'available' => toBool($s->getAttribute('available'))];
            }

            $paddons = [];
            foreach ($xp->query('addons/addonRef', $p) as $r) {
                $aid = (int)$r->getAttribute('addonId');
                if (!in_array($aid, $dbAddons, true)) {
                    $errors[] = "Product $pid uses unknown add-on id $aid.";
                    continue;
                }
                if (isset($paddons[$aid])) {
                    $errors[] = "Product $pid lists add-on id $aid more than once.";
                    continue;
                }
                $paddons[$aid] = true;
            }

            $data['products'][$pid] = [
                'name' => $name,
                'description' => $desc,
                'price' => $xp->evaluate('string(basePrice)', $p),
                'available' => toBool($p->getAttribute('available')),
                'bestseller' => toBool($p->getAttribute('bestseller')),
                'sizes' => $psizes,
                'addons' => array_keys($paddons),
            ];
        }
    }

    if (empty($data['products']) && empty($data['addons']) && empty($data['sizes']) && empty($errors)) {
        $errors[] = 'The file contains nothing to import.';
    }
    return empty($errors) ? $data : null;
}

function applyMenuImport(PDO $db, array $d): array
{
    $counts = ['categories' => 0, 'sizes' => 0, 'addons' => 0, 'products' => 0];
    $db->beginTransaction();
    try {
        $up = $db->prepare('UPDATE categories SET name = :n, is_drink = :d WHERE id = :id');
        foreach ($d['categories'] as $id => $c) {
            $up->execute([':n' => $c['name'], ':d' => $c['drink'] ? 1 : 0, ':id' => $id]);
            $counts['categories']++;
        }
        $up = $db->prepare('UPDATE sizes SET name = :n, default_price_adjustment = :p, is_active = :a WHERE id = :id');
        foreach ($d['sizes'] as $id => $s) {
            $up->execute([':n' => $s['name'], ':p' => $s['default'], ':a' => $s['active'] ? 1 : 0, ':id' => $id]);
            $counts['sizes']++;
        }
        $up = $db->prepare('UPDATE addons SET name = :n, price = :p, max_quantity = :m, is_active = :a WHERE id = :id');
        foreach ($d['addons'] as $id => $a) {
            $up->execute([':n' => $a['name'], ':p' => $a['price'], ':m' => $a['max'], ':a' => $a['active'] ? 1 : 0, ':id' => $id]);
            $counts['addons']++;
        }
        $up = $db->prepare('UPDATE products SET name = :n, description = :d, price = :p, is_available = :a, is_bestseller = :b WHERE id = :id');
        $delSizes = $db->prepare('DELETE FROM product_sizes WHERE product_id = :id');
        $insSize = $db->prepare('INSERT INTO product_sizes (product_id, size_id, price_adjustment, is_available) VALUES (:p, :s, :x, :v)');
        $delAddons = $db->prepare('DELETE FROM addon_products WHERE product_id = :id');
        $insAddon = $db->prepare('INSERT INTO addon_products (addon_id, product_id) VALUES (:a, :p)');
        foreach ($d['products'] as $id => $p) {
            $up->execute([
                ':n' => $p['name'], ':d' => $p['description'] !== '' ? $p['description'] : null, ':p' => $p['price'],
                ':a' => $p['available'] ? 1 : 0, ':b' => $p['bestseller'] ? 1 : 0, ':id' => $id,
            ]);
            $delSizes->execute([':id' => $id]);
            foreach ($p['sizes'] as $sid => $s) {
                $insSize->execute([':p' => $id, ':s' => $sid, ':x' => $s['extra'], ':v' => $s['available'] ? 1 : 0]);
            }
            $delAddons->execute([':id' => $id]);
            foreach ($p['addons'] as $aid) {
                $insAddon->execute([':a' => $aid, ':p' => $id]);
            }
            $counts['products']++;
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
    return $counts;
}
