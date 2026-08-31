/* ZF 转让与续期 */
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
        var ty = document.getElementById("fType").value;
        if (ty) q.push("type=" + ty);
        var kw = document.getElementById("fKeyword").value.trim();
        if (kw) q.push("keyword=" + encodeURIComponent(kw));
        q.push("page=" + page, "per_page=20");
        var body = document.getElementById("transferBody");
        body.innerHTML = '<tr><td colspan="11" class="loading">加载中…</td></tr>';
        ZF.get("/admin/transfers?" + q.join("&")).then(function (res) {
            if (!res.success) { body.innerHTML = ZF.empty(11); ZF.toast(errMsg(res), "err"); return; }
            var list = res.data || [];
            if (!list.length) { body.innerHTML = ZF.empty(11); document.getElementById("pagerBox").innerHTML = ZF.pager(res.meta, load); ZF.bindPager(document.getElementById("pagerBox"), load); return; }
            body.innerHTML = list.map(function (r) {
                var typeBadge = r.type === "transfer" ? '<span class="badge badge-olive">转让</span>' : '<span class="badge badge-purple">续期</span>';
                var lic = r.license || {};
                return "<tr><td>" + r.id + "</td><td>" + typeBadge + '</td><td class="mono">' + ZF.esc(lic.key_prefix || "-") +
                    "</td><td>" + ZF.esc(lic.product ? lic.product.slug : "-") + "</td><td>" + ZF.esc(r.customer_before || "-") +
                    "</td><td>" + ZF.esc(r.customer_after || "-") + "</td><td>" + ZF.fmtDate(r.original_expires_at) +
                    "</td><td>" + ZF.fmtDate(r.new_expires_at) + "</td><td>" + ZF.esc(r.operator ? r.operator.name : "-") +
                    "</td><td>" + ZF.esc(r.reason || "-") + "</td><td>" + ZF.fmtDate(r.created_at) + "</td></tr>";
            }).join("");
            document.getElementById("pagerBox").innerHTML = ZF.pager(res.meta, load);
            ZF.bindPager(document.getElementById("pagerBox"), load);
        }).catch(function () {});
    }

    function openTransfer() {
        document.getElementById("tLicenseId").value = "";
        document.getElementById("tCustomer").value = "";
        document.getElementById("tReason").value = "";
        ZF.openModal("转让授权", document.getElementById("transferModal").innerHTML,
            '<button class="btn btn-ghost" data-close>取消</button><button class="btn btn-primary" id="transferOk">确认转让</button>');
        document.getElementById("modalMask").querySelector("[data-close]").addEventListener("click", ZF.closeModal);
        var form = document.querySelector("#modalBody form");
        form.addEventListener("submit", function (e) { e.preventDefault(); doTransfer(); });
        document.getElementById("transferOk").addEventListener("click", doTransfer);
    }

    function doTransfer() {
        var payload = {
            license_id: parseInt(document.getElementById("tLicenseId").value, 10),
            new_customer: document.getElementById("tCustomer").value.trim(),
            reason: document.getElementById("tReason").value.trim() || null
        };
        if (!payload.license_id) { ZF.toast("请填写授权码 ID", "err"); return; }
        if (!payload.new_customer) { ZF.toast("请填写新客户名称", "err"); return; }
        ZF.post("/admin/transfers/transfer", payload).then(function (res) {
            if (res.success) { ZF.closeModal(); ZF.toast("转让成功"); load(page); }
            else ZF.toast(errMsg(res), "err");
        }).catch(function () {});
    }

    function openRenew() {
        document.getElementById("rLicenseId").value = "";
        document.getElementById("rExpires").value = "";
        document.getElementById("rReason").value = "";
        ZF.openModal("续期授权", document.getElementById("renewModal").innerHTML,
            '<button class="btn btn-ghost" data-close>取消</button><button class="btn btn-primary" id="renewOk">确认续期</button>');
        document.getElementById("modalMask").querySelector("[data-close]").addEventListener("click", ZF.closeModal);
        var form = document.querySelector("#modalBody form");
        form.addEventListener("submit", function (e) { e.preventDefault(); doRenew(); });
        document.getElementById("renewOk").addEventListener("click", doRenew);
    }

    function doRenew() {
        var payload = {
            license_id: parseInt(document.getElementById("rLicenseId").value, 10),
            new_expires_at: document.getElementById("rExpires").value || null,
            reason: document.getElementById("rReason").value.trim() || null
        };
        if (!payload.license_id) { ZF.toast("请填写授权码 ID", "err"); return; }
        if (!payload.new_expires_at) { ZF.toast("请填写新的有效期至", "err"); return; }
        ZF.post("/admin/transfers/renew", payload).then(function (res) {
            if (res.success) { ZF.closeModal(); ZF.toast("续期成功"); load(page); }
            else ZF.toast(errMsg(res), "err");
        }).catch(function () {});
    }

    function init() {
        document.getElementById("searchBtn").addEventListener("click", function () { load(1); });
        document.getElementById("resetBtn").addEventListener("click", function () {
            document.getElementById("fType").value = "";
            document.getElementById("fKeyword").value = "";
            load(1);
        });
        document.getElementById("fKeyword").addEventListener("keydown", function (e) { if (e.key === "Enter") load(1); });
        document.getElementById("transferBtn").addEventListener("click", openTransfer);
        document.getElementById("renewBtn").addEventListener("click", openRenew);
    }

    document.addEventListener("DOMContentLoaded", function () {
        ZF.initAuth(function () { init(); load(1); });
    });
})();
