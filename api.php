<?php
header('Content-Type: application/json');
session_start();

$configFile = __DIR__ . '/config.php';
$config = require $configFile;

// Cloudflare API 凭据
$cfEmail = $config['cloudflare']['email'];
$cfKey   = $config['cloudflare']['global_api_key'];

// Cloudflare API 请求函数
function cfRequest($method, $endpoint, $data = null) {
    global $cfEmail, $cfKey;

    $url = "https://api.cloudflare.com/client/v4" . $endpoint;
    $headers = [
        "X-Auth-Email: $cfEmail",
        "X-Auth-Key: $cfKey",
        "Content-Type: application/json"
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'message' => "CURL 错误: $error"];
    }

    return json_decode($response, true);
}

// 保存配置
function saveConfig($newConfig) {
    global $configFile;
    $configContent = "<?php\nreturn " . var_export($newConfig, true) . ";\n";
    return file_put_contents($configFile, $configContent) !== false;
}

$action = $_GET['action'] ?? $_POST['action'] ?? null;

if (!$action) {
    echo json_encode(['success' => false, 'message' => '缺少操作类型']);
    exit;
}

// 公共接口
$publicActions = ['login'];
if (!in_array($action, $publicActions)) {
    if (!isset($_SESSION['is_login']) || !$_SESSION['is_login']) {
        echo json_encode(['success' => false, 'message' => '未登录']);
        exit;
    }
}

// ========================= 登录 =========================
if ($action === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    if ($username === $config['auth']['username'] && $password === $config['auth']['password']) {
        $_SESSION['is_login'] = true;
        echo json_encode(['success' => true, 'message' => '登录成功']);
    } else {
        echo json_encode(['success' => false, 'message' => '用户名或密码错误']);
    }
    exit;
}

// ========================= 退出登录 =========================
if ($action === 'logout') {
    session_destroy();
    echo json_encode(['success' => true, 'message' => '退出成功']);
    exit;
}

// ========================= 获取域名列表 =========================
if ($action === 'list_zones') {
    $res = cfRequest('GET', '/zones');
    echo json_encode($res);
    exit;
}

// ========================= 获取 DNS 记录 =========================
if ($action === 'list_dns') {
    $zone_id = $_GET['zone_id'] ?? '';
    if (!$zone_id) {
        echo json_encode(['success' => false, 'message' => '缺少 zone_id']);
        exit;
    }

    $res = cfRequest('GET', "/zones/$zone_id/dns_records?per_page=200");
    echo json_encode($res);
    exit;
}

// ========================= 添加 DNS 记录 =========================
if ($action === 'add_dns') {
    $zone_id = $_POST['zone_id'] ?? '';
    $type    = $_POST['type'] ?? 'A';
    $name    = $_POST['name'] ?? '';
    $content = $_POST['content'] ?? '';
    $ttl     = intval($_POST['ttl'] ?? 120);
    $proxied = ($_POST['proxied'] ?? 'false') === 'true';

    if (!$zone_id || !$name || !$content) {
        echo json_encode(['success' => false, 'message' => '缺少必填参数']);
        exit;
    }

    $data = compact('type', 'name', 'content', 'ttl', 'proxied');
    $res = cfRequest('POST', "/zones/$zone_id/dns_records", $data);
    echo json_encode($res);
    exit;
}

// ========================= 编辑 DNS 记录 =========================
if ($action === 'edit_dns') {
    $zone_id = $_POST['zone_id'] ?? '';
    $id      = $_POST['id'] ?? '';
    $type    = $_POST['type'] ?? 'A';
    $name    = $_POST['name'] ?? '';
    $content = $_POST['content'] ?? '';
    $ttl     = intval($_POST['ttl'] ?? 120);
    $proxied = ($_POST['proxied'] ?? 'false') === 'true';

    if (!$zone_id || !$id) {
        echo json_encode(['success' => false, 'message' => '缺少参数']);
        exit;
    }

    $data = compact('type', 'name', 'content', 'ttl', 'proxied');
    $res = cfRequest('PUT', "/zones/$zone_id/dns_records/$id", $data);
    echo json_encode($res);
    exit;
}

// ========================= 删除 DNS 记录 =========================
if ($action === 'delete_dns') {
    $zone_id = $_POST['zone_id'] ?? '';
    $id      = $_POST['id'] ?? '';

    if (!$zone_id || !$id) {
        echo json_encode(['success' => false, 'message' => '缺少参数']);
        exit;
    }

    $res = cfRequest('DELETE', "/zones/$zone_id/dns_records/$id");
    echo json_encode($res);
    exit;
}

// ========================= 更新配置（邮箱、API、密码） =========================
if ($action === 'update_config') {
    $newEmail   = trim($_POST['email'] ?? '');
    $newKey     = trim($_POST['global_api_key'] ?? '');
    $newPass    = trim($_POST['new_password'] ?? '');

    if ($newEmail) $config['cloudflare']['email'] = $newEmail;
    if ($newKey)   $config['cloudflare']['global_api_key'] = $newKey;
    if ($newPass)  $config['auth']['password'] = $newPass;

    if (saveConfig($config)) {
        echo json_encode(['success' => true, 'message' => '配置已更新']);
    } else {
        echo json_encode(['success' => false, 'message' => '配置保存失败']);
    }
    exit;
}

// ========================= 默认 =========================
echo json_encode(['success' => false, 'message' => '未知操作']);