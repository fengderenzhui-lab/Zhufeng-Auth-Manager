/* ZF 授权范围管理 */
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
        if (st !== "") q.push("is_active=" + st);
        q.push("page=" + page, "per_page=20");
        var body = document.getElementById("scopeBody");
        body.innerHTML = '<tr><td colspan="7" class="loading">加载中…</td></tr>';
        ZF.get("/admin/license-scopes?" + q.join("&")).then(function (res) {
            if (!res.success) { body.innerHTML = ZF.empty(7); ZF.toast(errMsg(res), "err"); return; }
            var list = res.data || [];
            if (!list.length) { body.innerHTML = ZF.empty(7); document.getElementById("pagerBox").innerHTML = ZF.pager(res.meta, load); ZF.bindPager(document.getElementById("pagerBox"), load); return; }
            body.innerHTML = list.map(function (s) {
                var actions = '<button class="btn btn-ghost btn-sm" data-act="edit" data-id="' + s.id + '">编辑</button> ' +
                    '<button class="btn btn-ghost btn-sm" data-act="toggle" data-id="' + s.id + '">' + (s.is_active ? "停用" : "启用") + "</button> " +
                    '<button class="btn btn-danger btn-sm" data-act="del" data-id="' + s.id + '">删除</button>';
                return '<tr><td>' + s.id + '</td><td>' + ZF.esc(s.name) + '</td><td class="mono">' + ZF.esc(s.slug) + "</td><td>" + ZF.esc(s.description || "-") +
                    "</td><td>" + (s.template_count || 0) + "</td><td>" + (s.is_active ? '<span class="badge badge-green">启用</span>' : '<span class="badge badge-gray">停用</span>') +
                    '</td><td style="white-space:nowrap;">' + actions + "</td></tr>";
            }).join("");
            document.getElementById("pagerBox").innerHTML = ZF.pager(res.meta, load);
            ZF.bindPager(document.getElementById("pagerBox"), load);
        }).catch(function () {});
    }

    function openEdit(id) {
        var doOpen = function (s) {
            document.getElementById("eName").value = s ? s.name : "";
            document.getElementById("eSlug").value = s ? s.slug : "";
            document.getElementById("eDesc").value = s && s.description ? s.description : "";
            document.getElementById("eActive").checked = s ? !!s.is_active : true;
            ZF.openModal(id ? "编辑授权范围 #" + id : "新增授权范围", document.getElementById("editModal").innerHTML,
                '<button class="btn btn-ghost" data-close>取消</button><button class="btn btn-primary" id="saveOk">保存</button>');
            document.getElementById("modalMask").querySelector("[data-close]").addEventListener("click", ZF.closeModal);
            var form = document.querySelector("#modalBody form");
            form.addEventListener("submit", function (e) { e.preventDefault(); save(id); });
            document.getElementById("saveOk").addEventListener("click", function () { save(id); });
        };
        if (id) {
            ZF.get("/admin/license-scopes/" + id).then(function (res) {
                if (res.success) doOpen(res.data);
                else ZF.toast(errMsg(res), "err");
            }).catch(function () {});
        } else {
            doOpen(null);
        }
    }

    function save(id) {
        var payload = {
            name: document.getElementById("eName").value.trim(),
            slug: document.getElementById("eSlug").value.trim(),
            description: document.getElementById("eDesc").value.trim() || null,
            is_active: document.getElementById("eActive").checked ? 1 : 0
        };
        if (!payload.name) { ZF.toast("请填写名称", "err"); return; }
        if (!payload.slug) { ZF.toast("请填写标识 slug", "err"); return; }
        var req = id ? ZF.put("/admin/license-scopes/" + id, payload) : ZF.post("/admin/license-scopes", payload);
        req.then(function (res) {
            if (res.success) { ZF.closeModal(); ZF.toast(id ? "已保存" : "已创建"); load(page); }
            else ZF.toast(errMsg(res), "err");
        }).catch(function () {});
    }

    function toggle(id, cur) {
        ZF.post("/admin/license-scopes/" + id + "/toggle", {}).then(function (res) {
            if (res.success) { ZF.toast(cur ? "已停用" : "已启用"); load(page); }
            else ZF.toast(errMsg(res), "err");
        }).catch(function () {});
    }

    function remove(id) {
        ZF.confirmModal("删除授权范围", "确认删除授权范围 #" + id + "？删除后不可恢复。", "确认删除", function () {
            return ZF.del("/admin/license-scopes/" + id).then(function (res) {
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
            load(1);
        });
        document.getElementById("fKeyword").addEventListener("keydown", function (e) { if (e.key === "Enter") load(1); });
        document.getElementById("createBtn").addEventListener("click", function () { openEdit(null); });
        document.getElementById("scopeBody").addEventListener("click", function (e) {
            var btn = e.target.closest("[data-act]");
            if (!btn) return;
            var id = parseInt(btn.getAttribute("data-id"), 10);
            var act = btn.getAttribute("data-act");
            if (act === "edit") openEdit(id);
            else if (act === "toggle") toggle(id, btn.textContent === "停用");
            else if (act === "del") remove(id);
        });
    }

    document.addEventListener("DOMContentLoaded", function () {
        ZF.initAuth(function () { init(); load(1); });
    });
})();
