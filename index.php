<?php
session_start();
$config = require __DIR__ . '/config.php';
$isLogin = isset($_SESSION['is_login']) && $_SESSION['is_login'];
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <title>Cloudflare DNS 管理面板</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="pic.jpg" type="image/jpeg">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body { background-color: #f8f9fa; }
        .container { max-width: 1200px; margin-top: 20px; }
        .card { margin-bottom: 20px; }
        .btn { font-size: 1.2rem; padding: 12px 18px; }
        table { word-break: break-word; }
        .table-responsive { max-height: 500px; overflow-y: auto; }
        .nav-tabs .nav-link { font-size: 1.1rem; padding: 12px; }
        .modal-body label { font-weight: bold; }
    </style>
</head>
<body>
<div class="container">
    <h2 class="text-center my-4">🌐 Cloudflare DNS 管理面板</h2>

    <?php if (!$isLogin): ?>
        <!-- 登录界面 -->
        <div class="card">
            <div class="card-body">
                <h4 class="card-title text-center">登录</h4>
                <form id="loginForm">
                    <div class="mb-3">
                        <label class="form-label">用户名</label>
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">密码</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">登录</button>
                </form>
            </div>
        </div>
        <script>
            $("#loginForm").submit(function (e) {
                e.preventDefault();
                $.post("api.php?action=login", $(this).serialize(), function (res) {
                    if (res.success) location.reload();
                    else alert(res.message);
                }, "json");
            });
        </script>
    <?php else: ?>
        <!-- 登录后界面 -->
        <ul class="nav nav-tabs" id="mainTabs">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#domainsTab">域名管理</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#settingsTab">设置</button>
            </li>
            <li class="nav-item ms-auto">
                <button class="btn btn-danger" id="logoutBtn">退出登录</button>
            </li>
        </ul>

        <div class="tab-content mt-3">
            <!-- 域名管理 -->
            <div class="tab-pane fade show active" id="domainsTab">
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <h5>域名列表</h5>
                        <button class="btn btn-success btn-sm" id="reloadZones">刷新</button>
                    </div>
                    <div class="card-body">
                        <div id="zoneList" class="list-group"></div>
                    </div>
                </div>
                <div id="dnsSection" class="card d-none">
                    <div class="card-header d-flex justify-content-between">
                        <h5>DNS 记录 - <span id="currentDomain"></span></h5>
                        <button class="btn btn-primary btn-sm" id="addDnsBtn">添加记录</button>
                    </div>
                    <div class="card-body table-responsive">
                        <table class="table table-striped">
                            <thead>
                            <tr>
                                <th>类型</th>
                                <th>名称</th>
                                <th>内容</th>
                                <th>TTL</th>
                                <th>代理</th>
                                <th>操作</th>
                            </tr>
                            </thead>
                            <tbody id="dnsTable"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 设置 -->
            <div class="tab-pane fade" id="settingsTab">
                <div class="card">
                    <div class="card-body">
                        <form id="settingsForm">
                            <div class="mb-3">
                                <label class="form-label">Cloudflare 邮箱</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($config['cloudflare']['email']) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">API Key</label>
                                <input type="text" name="global_api_key" class="form-control" value="<?= htmlspecialchars($config['cloudflare']['global_api_key']) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">新密码（可选）</label>
                                <input type="text" name="new_password" class="form-control">
                            </div>
                            <button type="submit" class="btn btn-success w-100">保存配置</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- 添加 / 编辑 DNS 弹窗 -->
        <div class="modal fade" id="dnsModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="dnsModalTitle">添加 DNS 记录</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="dnsForm">
                            <input type="hidden" name="zone_id">
                            <input type="hidden" name="id">
                            <div class="mb-3">
                                <label class="form-label">记录类型</label>
                                <select name="type" class="form-control">
                                    <option>A</option>
                                    <option>AAAA</option>
                                    <option>CNAME</option>
                                    <option>TXT</option>
                                    <option>MX</option>
                                    <option>NS</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">名称</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">内容</label>
                                <input type="text" name="content" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">TTL</label>
                                <input type="number" name="ttl" class="form-control" value="120" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">代理</label>
                                <select name="proxied" class="form-control">
                                    <option value="false">关闭</option>
                                    <option value="true">开启</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">保存</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <script>
            let currentZoneId = null;
            let currentDomain = null;

            // 退出登录
            $("#logoutBtn").click(() => {
                $.get("api.php?action=logout", () => location.reload());
            });

            // 加载域名
            function loadZones() {
                $("#zoneList").html('<div class="text-center">加载中...</div>');
                $.get("api.php?action=list_zones", function (res) {
                    if (res.success) {
                        $("#zoneList").empty();
                        res.result.forEach(zone => {
                            $("#zoneList").append(`<button class="list-group-item list-group-item-action zoneItem" data-id="${zone.id}" data-name="${zone.name}">${zone.name}</button>`);
                        });
                    } else {
                        $("#zoneList").html('<div class="text-danger">加载失败，请检查API配置</div>');
                    }
                }, "json");
            }
            loadZones();

            // 点击域名 → 加载 DNS
            $(document).on("click", ".zoneItem", function () {
                currentZoneId = $(this).data("id");
                currentDomain = $(this).data("name");
                $("#currentDomain").text(currentDomain);
                $("#dnsSection").removeClass("d-none");
                loadDnsRecords();
            });

            // 加载 DNS 记录
            function loadDnsRecords() {
                $("#dnsTable").html('<tr><td colspan="6" class="text-center">加载中...</td></tr>');
                $.get("api.php?action=list_dns&zone_id=" + currentZoneId, function (res) {
                    if (res.success) {
                        $("#dnsTable").empty();
                        res.result.forEach(record => {
                            $("#dnsTable").append(`
                                <tr>
                                    <td>${record.type}</td>
                                    <td>${record.name}</td>
                                    <td>${record.content}</td>
                                    <td>${record.ttl}</td>
                                    <td>${record.proxied ? "开启" : "关闭"}</td>
                                    <td>
                                        <button class="btn btn-warning btn-sm editDnsBtn" data-id="${record.id}">编辑</button>
                                        <button class="btn btn-danger btn-sm deleteDnsBtn" data-id="${record.id}">删除</button>
                                    </td>
                                </tr>`);
                        });
                    } else {
                        $("#dnsTable").html('<tr><td colspan="6" class="text-danger text-center">加载失败</td></tr>');
                    }
                }, "json");
            }

            // 添加 DNS
            $("#addDnsBtn").click(() => {
                $("#dnsModalTitle").text("添加 DNS 记录");
                $("#dnsForm")[0].reset();
                $("#dnsForm input[name='zone_id']").val(currentZoneId);
                $("#dnsModal").modal("show");
            });

            // 编辑 DNS
            $(document).on("click", ".editDnsBtn", function () {
                const id = $(this).data("id");
                $.get("api.php?action=list_dns&zone_id=" + currentZoneId, function (res) {
                    if (res.success) {
                        const record = res.result.find(r => r.id === id);
                        if (record) {
                            $("#dnsModalTitle").text("编辑 DNS 记录");
                            $("#dnsForm input[name='zone_id']").val(currentZoneId);
                            $("#dnsForm input[name='id']").val(record.id);
                            $("#dnsForm select[name='type']").val(record.type);
                            $("#dnsForm input[name='name']").val(record.name);
                            $("#dnsForm input[name='content']").val(record.content);
                            $("#dnsForm input[name='ttl']").val(record.ttl);
                            $("#dnsForm select[name='proxied']").val(record.proxied ? "true" : "false");
                            $("#dnsModal").modal("show");
                        }
                    }
                }, "json");
            });

            // 删除 DNS
            $(document).on("click", ".deleteDnsBtn", function () {
                if (!confirm("确定要删除此记录吗？")) return;
                const id = $(this).data("id");
                $.post("api.php?action=delete_dns", {zone_id: currentZoneId, id}, function (res) {
                    if (res.success) loadDnsRecords();
                    else alert("删除失败：" + res.errors[0].message);
                }, "json");
            });

            // 提交 DNS 表单
            $("#dnsForm").submit(function (e) {
                e.preventDefault();
                const action = $("#dnsForm input[name='id']").val() ? "edit_dns" : "add_dns";
                $.post("api.php?action=" + action, $(this).serialize(), function (res) {
                    if (res.success) {
                        $("#dnsModal").modal("hide");
                        loadDnsRecords();
                    } else {
                        alert("保存失败：" + res.errors[0].message);
                    }
                }, "json");
            });

            // 更新配置
            $("#settingsForm").submit(function (e) {
                e.preventDefault();
                $.post("api.php?action=update_config", $(this).serialize(), function (res) {
                    alert(res.message);
                    if (res.success) location.reload();
                }, "json");
            });
        </script>
    <?php endif; ?>
</div>

<!-- 页面底部 -->
<footer style="margin-top: 40px; padding: 15px; text-align: center; font-size: 14px; color: #666; background-color: #f8f9fa; border-top: 1px solid #e9ecef;">
    <div style="margin-bottom: 5px;">
        🚀 喵服务：<a href="https://喵.wang" target="_blank" style="color: #007bff; text-decoration: none;">喵.wang</a>
    </div>
    <div>
        💻 GitHub 项目地址：
        <a href="https://github.com/daliangbibi/Cloudflare-Manager/" target="_blank" style="color: #007bff; text-decoration: none;">
            https://github.com/daliangbibi/WhyHere
        </a>
    </div>
</footer>

</body>
</html>