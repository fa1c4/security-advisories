<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

require '/opt/racktables/wwwroot/inc/secret.php';
$pdo = new PDO($pdo_dsn, $db_username, $db_password, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));

$objectName = 'csrf-lab-object';
$portName = 'eth0';
$initialComment = 'initial reservation';

$pdo->beginTransaction();

$stmt = $pdo->prepare('SELECT id FROM Object WHERE name = ? LIMIT 1');
$stmt->execute(array($objectName));
$objectId = $stmt->fetchColumn();

if (!$objectId) {
    $stmt = $pdo->prepare("INSERT INTO Object (name, label, objtype_id, asset_no, has_problems, comment) VALUES (?, NULL, 4, NULL, 'no', 'CSRF local lab object')");
    $stmt->execute(array($objectName));
    $objectId = $pdo->lastInsertId();
}

$row = $pdo->query('SELECT iif_id, oif_id FROM PortInterfaceCompat LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    throw new RuntimeException('PortInterfaceCompat is empty; RackTables dictionary/bootstrap did not initialize correctly.');
}

$stmt = $pdo->prepare('SELECT id FROM Port WHERE object_id = ? AND name = ? LIMIT 1');
$stmt->execute(array($objectId, $portName));
$portId = $stmt->fetchColumn();

if (!$portId) {
    $stmt = $pdo->prepare('INSERT INTO Port (object_id, name, iif_id, type, l2address, reservation_comment, label) VALUES (?, ?, ?, ?, NULL, ?, NULL)');
    $stmt->execute(array($objectId, $portName, $row['iif_id'], $row['oif_id'], $initialComment));
    $portId = $pdo->lastInsertId();
} else {
    $stmt = $pdo->prepare('UPDATE Port SET reservation_comment = ? WHERE id = ?');
    $stmt->execute(array($initialComment, $portId));
}

$pdo->commit();

file_put_contents('/opt/evidence/fixture.env', "OBJECT_ID={$objectId}\nPORT_ID={$portId}\n");
file_put_contents('/opt/poc/csrf-generated.html', str_replace('PORT_ID_PLACEHOLDER', $portId, file_get_contents('/opt/poc/csrf.html')));

echo "[+] Fixture object_id={$objectId}\n";
echo "[+] Fixture port_id={$portId}\n";
echo "[+] Initial reservation_comment='{$initialComment}'\n";
