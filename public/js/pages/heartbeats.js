/* ZF 心跳监控 */
(function () {
    "use strict";
    var page = 1;

    function errMsg(res) {
        if (res && res.errors) {
            var k = Object.keys(res.errors)[0];
            if (res.errors[k] && res.errors[k][0]) return res.errors[k][0];
        }
        return (res && res.message) || "操作失败";
    }

    function load(p) {
        page = p || 1;
        var q = [];
        var st = document.getElementById("fStatus").value;
        if (st) q.push("status=" + st);
        var kw = document.getElementById("fKeyword").value.trim();
        if (kw) q.push("keyword=" + encodeURIComponent(kw));
        if (document.getElementById("fTimeout").checked) q.push("timeout=1");
        q.push("page=" + page, "per_page=20");
        var body = document.getElementById("hbBody");
        body.innerHTML = '<tr><td colspan="9" class="loading">加载中…</td></tr>';
        ZF.get("/admin/heartbeats?" + q.join("&")).then(function (res) {
            if (!res.success) { body.innerHTML = ZF.empty(9); ZF.toast(errMsg(res), "err"); return; }
            var list = res.data || [];
            if (!list.length) { body.innerHTML = ZF.empty(9); document.getElementById("pagerBox").innerHTML = ZF.pager(res.meta, load); ZF.bindPager(document.getElementById("pagerBox"), load); return; }
            body.innerHTML = list.map(function (h) {
                var danger = h.timeout ? ' class="row-danger"' : "";
                var loadTxt = h.client_ua ? h.client_ua : "-";
                return "<tr" + danger + "><td>" + h.license_id + '</td><td class="mono">' + ZF.esc(h.key || "-") +
                    "</td><td>" + ZF.esc(h.product ? h.product.slug : "-") + "</td><td>" + ZF.badge(h.status) +
                    "</td><td>" + (h.device_count || 0) + "</td><td>" + ZF.fmtDate(h.last_heartbeat_at) +
                    "</td><td>" + ZF.esc(h.client_ip || "-") + "</td><td>" + ZF.esc(loadTxt) +
                    "</td><td>" + ZF.fmtDate(h.expires_at) + "</td></tr>";
            }).join("");
            document.getElementById("pagerBox").innerHTML = ZF.pager(res.meta, load);
            ZF.bindPager(document.getElementById("pagerBox"), load);
        }).catch(function () {});
    }

    function init() {
        document.getElementById("searchBtn").addEventListener("click", function () { load(1); });
        document.getElementById("resetBtn").addEventListener("click", function () {
            document.getElementById("fStatus").value = "";
            document.getElementById("fKeyword").value = "";
            document.getElementById("fTimeout").checked = false;
            load(1);
        });
        document.getElementById("refreshBtn").addEventListener("click", function () { load(page); });
        document.getElementById("fKeyword").addEventListener("keydown", function (e) { if (e.key === "Enter") load(1); });
        document.getElementById("fTimeout").addEventListener("change", function () { load(1); });
    }

    document.addEventListener("DOMContentLoaded", function () {
        ZF.initAuth(function () { init(); load(1); });
    });
})();
