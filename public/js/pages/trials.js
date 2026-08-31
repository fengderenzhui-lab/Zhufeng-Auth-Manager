/* ZF 试用授权管理 */
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
        var kw = document.getElementById("fKeyword").value.trim();
        if (kw) q.push("keyword=" + encodeURIComponent(kw));
        var st = document.getElementById("fStatus").value;
        if (st) q.push("status=" + st);
        var pid = document.getElementById("fProduct").value;
        if (pid) q.push("product_id=" + pid);
        q.push("page=" + page, "per_page=20");
        var body = document.getElementById("trialBody");
        body.innerHTML = '<tr><td colspan="10" class="loading">加载中…</td></tr>';
        ZF.get("/admin/trials?" + q.join("&")).then(function (res) {
            if (!res.success) { body.innerHTML = ZF.empty(10); ZF.toast(errMsg(res), "err"); return; }
            var list = res.data || [];
            if (!list.length) { body.innerHTML = ZF.empty(10); document.getElementById("pagerBox").innerHTML = ZF.pager(res.meta, load); ZF.bindPager(document.getElementById("pagerBox"), load); return; }
            body.innerHTML = list.map(function (t) {
                var actions = '<button class="btn btn-ghost btn-sm" data-act="edit" data-id="' + t.id + '">编辑</button> ' +
                    ('active' === t.status || 'pending' === t.status ? '<button class="btn btn-danger btn-sm" data-act="revoke" data-id="' + t.id + '">吊销</button> ' : "") +
                    '<button class="btn btn-danger btn-sm" data-act="del" data-id="' + t.id + '">删除</button>';
                var end = t.starts_at ? ZF.fmtDate(new Date(new Date(t.starts_at).getTime() + (t.trial_days || 0) * 86400000).toISOString()) : "-";
                return "<tr><td>" + t.id + '</td><td>' + ZF.esc(t.product ? t.product.slug : "-") + "</td><td>" + ZF.esc(t.customer || "-") +
                    '</td><td class="mono">' + ZF.esc(t.trial_code || "-") + "</td><td>" + (t.trial_days || "-") +
                    "</td><td>" + ZF.fmtDate(t.starts_at) + "</td><td>" + end + "</td><td>" + ZF.badge(t.status) +
                    "</td><td>" + ZF.esc(t.creator ? t.creator.name : "-") + '</td><td style="white-space:nowrap;">' + actions + "</td></tr>";
            }).join("");
            document.getElementById("pagerBox").innerHTML = ZF.pager(res.meta, load);
            ZF.bindPager(document.getElementById("pagerBox"), load);
        }).catch(function () {});
    }

    function fillProducts(selectId, val) {
        ZF.get("/admin/products?per_page=100").then(function (res) {
            var sel = document.getElementById(selectId);
            if (!sel) return;
            if (res.success && (res.data || []).length) {
                sel.innerHTML = (res.data || []).map(function (p) {
                    return '<option value="' + p.id + '">' + ZF.esc(p.name) + " (" + ZF.esc(p.slug) + ")</option>";
                }).join("");
            } else {
                sel.innerHTML = '<option value="">暂无产品</option>';
            }
            if (val) sel.value = String(val);
        });
    }

    function openCreate() {
        fillProducts("eProduct");
        document.getElementById("eDays").value = 7;
        document.getElementById("eCustomer").value = "";
        document.getElementById("eStart").value = "";
        document.getElementById("eStatus").value = "pending";
        document.getElementById("eRemark").value = "";
        ZF.openModal("新建试用授权", document.getElementById("editModal").innerHTML,
            '<button class="btn btn-ghost" data-close>取消</button><button class="btn btn-primary" id="saveOk">生成试用授权码</button>', "lg");
        document.getElementById("modalMask").querySelector("[data-close]").addEventListener("click", ZF.closeModal);
        var form = document.querySelector("#modalBody form");
        form.addEventListener("submit", function (e) { e.preventDefault(); create(); });
        document.getElementById("saveOk").addEventListener("click", create);
    }

    function create() {
        var payload = {
            product_id: parseInt(document.getElementById("eProduct").value, 10),
            customer: document.getElementById("eCustomer").value.trim() || null,
            trial_days: parseInt(document.getElementById("eDays").value, 10) || 7,
            starts_at: document.getElementById("eStart").value || null,
            status: document.getElementById("eStatus").value,
            remark: document.getElementById("eRemark").value.trim() || null
        };
        if (!payload.product_id) { ZF.toast("请选择产品", "err"); return; }
        ZF.post("/admin/trials", payload).then(function (res) {
            if (!res.success) { ZF.toast(errMsg(res), "err"); return; }
            ZF.closeModal();
            var key = res.data && res.data.trial_code_plain ? res.data.trial_code_plain : "";
            if (key) {
                var html = '<div style="margin-bottom:10px;color:var(--accent-2);font-weight:600;">试用授权码明文仅本次显示，系统不保存明文，请立即复制保存。</div>' +
                    '<div class="form-row"><label class="form-label">试用授权码</label><input class="input mono" style="user-select:all;" readonly value="' + ZF.esc(key) + '"></div>' +
                    '<div style="margin-top:12px;text-align:right;"><button class="btn btn-primary btn-sm" id="copyKey">复制授权码</button></div>';
                ZF.openModal("试用授权创建成功", html, '<button class="btn btn-ghost" data-close>关闭</button>', "lg");
                document.getElementById("modalMask").querySelector("[data-close]").addEventListener("click", ZF.closeModal);
                var cp = document.getElementById("copyKey");
                cp.addEventListener("click", function () {
                    var ta = document.createElement("textarea");
                    ta.value = key;
                    document.body.appendChild(ta);
                    ta.select();
                    try { document.execCommand("copy"); ZF.toast("已复制"); } catch (e) { ZF.toast("复制失败，请手动复制", "warn"); }
                    document.body.removeChild(ta);
                });
            } else {
                ZF.toast("试用授权已创建");
            }
            load(page);
        }).catch(function () {});
    }

    function openEdit(id) {
        ZF.get("/admin/trials/" + id).then(function (res) {
            if (!res.success) { ZF.toast(errMsg(res), "err"); return; }
            var t = res.data;
            fillProducts("eProduct", t.product_id);
            document.getElementById("eDays").value = t.trial_days || 7;
            document.getElementById("eCustomer").value = t.customer || "";
            document.getElementById("eStart").value = t.starts_at ? t.starts_at.slice(0, 16) : "";
            document.getElementById("eStatus").value = t.status || "pending";
            document.getElementById("eRemark").value = t.remark || "";
            ZF.openModal("编辑试用授权 #" + id, document.getElementById("editModal").innerHTML,
                '<button class="btn btn-ghost" data-close>取消</button><button class="btn btn-primary" id="saveOk">保存</button>', "lg");
            document.getElementById("modalMask").querySelector("[data-close]").addEventListener("click", ZF.closeModal);
            var form = document.querySelector("#modalBody form");
            form.addEventListener("submit", function (e) { e.preventDefault(); save(id); });
            document.getElementById("saveOk").addEventListener("click", function () { save(id); });
        }).catch(function () {});
    }

    function save(id) {
        var payload = {
            product_id: parseInt(document.getElementById("eProduct").value, 10),
            customer: document.getElementById("eCustomer").value.trim() || null,
            trial_days: parseInt(document.getElementById("eDays").value, 10) || 7,
            starts_at: document.getElementById("eStart").value || null,
            status: document.getElementById("eStatus").value,
            remark: document.getElementById("eRemark").value.trim() || null
        };
        ZF.put("/admin/trials/" + id, payload).then(function (res) {
            if (res.success) { ZF.closeModal(); ZF.toast("已保存"); load(page); }
            else ZF.toast(errMsg(res), "err");
        }).catch(function () {});
    }

    function revoke(id) {
        ZF.confirmModal("吊销试用授权", "确认吊销试用授权 #" + id + "？吊销后该试用码将无法通过验证。", "确认吊销", function () {
            return ZF.post("/admin/trials/" + id + "/revoke", {}).then(function (res) {
                if (!res.success) { ZF.toast(errMsg(res), "err"); throw new Error("fail"); }
                ZF.toast("已吊销");
                load(page);
            });
        }, true);
    }

    function remove(id) {
        ZF.confirmModal("删除试用授权", "确认删除试用授权 #" + id + "？删除后不可恢复。", "确认删除", function () {
            return ZF.del("/admin/trials/" + id).then(function (res) {
                if (!res.success) { ZF.toast(errMsg(res), "err"); throw new Error("fail"); }
                ZF.toast("已删除");
                load(page);
            });
        }, true);
    }

    function init() {
        document.getElementById("searchBtn").addEventListener("click", function () { load(1); });
        document.getElementById("resetBtn").addEventListener("click", function () {
            document.getElementById("fKeyword").value = "";
            document.getElementById("fStatus").value = "";
            document.getElementById("fProduct").value = "";
            load(1);
        });
        document.getElementById("fKeyword").addEventListener("keydown", function (e) { if (e.key === "Enter") load(1); });
        document.getElementById("createBtn").addEventListener("click", openCreate);
        document.getElementById("trialBody").addEventListener("click", function (e) {
            var btn = e.target.closest("[data-act]");
            if (!btn) return;
            var id = parseInt(btn.getAttribute("data-id"), 10);
            var act = btn.getAttribute("data-act");
            if (act === "edit") openEdit(id);
            else if (act === "revoke") revoke(id);
            else if (act === "del") remove(id);
        });
        ZF.get("/admin/products?per_page=100").then(function (res) {
            var sel = document.getElementById("fProduct");
            if (sel && res.success) {
                sel.innerHTML = '<option value="">全部产品</option>' + (res.data || []).map(function (p) {
                    return '<option value="' + p.id + '">' + ZF.esc(p.name) + " (" + ZF.esc(p.slug) + ")</option>";
                }).join("");
            }
        });
    }

    document.addEventListener("DOMContentLoaded", function () {
        ZF.initAuth(function () { init(); load(1); });
    });
})();
