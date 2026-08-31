/* ZF 授权模板管理 */
(function () {
    "use strict";
    var page = 1;
    var scopeCache = null;

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
        var body = document.getElementById("tplBody");
        body.innerHTML = '<tr><td colspan="8" class="loading">加载中…</td></tr>';
        ZF.get("/admin/license-templates?" + q.join("&")).then(function (res) {
            if (!res.success) { body.innerHTML = ZF.empty(8); ZF.toast(errMsg(res), "err"); return; }
            var list = res.data || [];
            if (!list.length) { body.innerHTML = ZF.empty(8); document.getElementById("pagerBox").innerHTML = ZF.pager(res.meta, load); ZF.bindPager(document.getElementById("pagerBox"), load); return; }
            body.innerHTML = list.map(function (t) {
                var scopes = (t.scopes || []).map(function (s) { return ZF.esc(s.name); }).join("、") || "-";
                var actions = '<button class="btn btn-ghost btn-sm" data-act="edit" data-id="' + t.id + '">编辑</button> ' +
                    '<button class="btn btn-ghost btn-sm" data-act="toggle" data-id="' + t.id + '">' + (t.is_active ? "停用" : "启用") + "</button> " +
                    '<button class="btn btn-danger btn-sm" data-act="del" data-id="' + t.id + '">删除</button>';
                return '<tr><td>' + t.id + '</td><td>' + ZF.esc(t.name) + '</td><td>' + ZF.esc(t.description || "-") +
                    "</td><td>" + (t.duration_days ? t.duration_days + " 天" : "永久") + '</td><td>' + (t.max_devices || 1) +
                    "</td><td>" + scopes + "</td><td>" + (t.is_active ? '<span class="badge badge-green">启用</span>' : '<span class="badge badge-gray">停用</span>') +
                    '</td><td style="white-space:nowrap;">' + actions + "</td></tr>";
            }).join("");
            document.getElementById("pagerBox").innerHTML = ZF.pager(res.meta, load);
            ZF.bindPager(document.getElementById("pagerBox"), load);
        }).catch(function () {});
    }

    function fetchScopes() {
        if (scopeCache) return Promise.resolve(scopeCache);
        return ZF.get("/admin/license-scopes?per_page=100").then(function (res) {
            scopeCache = res.success ? (res.data || []) : [];
            return scopeCache;
        });
    }

    function renderScopeChips(selected) {
        var box = document.getElementById("scopeChips");
        if (!box) return;
        var sel = selected || [];
        fetchScopes().then(function (list) {
            if (!list.length) { box.innerHTML = '<span class="badge badge-gray">暂无授权范围，可先到"授权范围"页创建</span>'; return; }
            box.innerHTML = list.map(function (s) {
                var on = sel.indexOf(s.id) !== -1;
                return '<label class="scope-chip' + (on ? " checked" : "") + '"><input class="checkbox" type="checkbox" value="' + s.id + '"' + (on ? " checked" : "") + ' onchange="this.parentNode.classList.toggle(\'checked\', this.checked)">' + ZF.esc(s.name) + '</label>';
            }).join("");
        });
    }

    function collectScopeIds() {
        var ids = [];
        document.querySelectorAll("#scopeChips input[type=checkbox]:checked").forEach(function (c) {
            ids.push(parseInt(c.value, 10));
        });
        return ids;
    }

    function openEdit(id) {
        fetchScopes();
        var doOpen = function (tpl) {
            renderScopeChips(tpl ? (tpl.scope_ids || []) : []);
            document.getElementById("eName").value = tpl ? tpl.name : "";
            document.getElementById("eDesc").value = tpl && tpl.description ? tpl.description : "";
            document.getElementById("eDuration").value = tpl && tpl.duration_days ? tpl.duration_days : "";
            document.getElementById("eMaxDev").value = tpl ? (tpl.max_devices || 1) : 1;
            document.getElementById("eFeatures").value = tpl && tpl.features ? (typeof tpl.features === "string" ? tpl.features : JSON.stringify(tpl.features)) : "";
            document.getElementById("eActive").checked = tpl ? !!tpl.is_active : true;
            ZF.openModal(id ? "编辑授权模板 #" + id : "新增授权模板", document.getElementById("editModal").innerHTML,
                '<button class="btn btn-ghost" data-close>取消</button><button class="btn btn-primary" id="saveOk">保存</button>', "lg");
            document.getElementById("modalMask").querySelector("[data-close]").addEventListener("click", ZF.closeModal);
            var form = document.querySelector("#modalBody form");
            form.addEventListener("submit", function (e) { e.preventDefault(); save(id); });
            document.getElementById("saveOk").addEventListener("click", function () { save(id); });
        };
        if (id) {
            ZF.get("/admin/license-templates/" + id).then(function (res) {
                if (res.success) doOpen(res.data);
                else ZF.toast(errMsg(res), "err");
            }).catch(function () {});
        } else {
            doOpen(null);
        }
    }

    function save(id) {
        var featuresRaw = document.getElementById("eFeatures").value.trim();
        var features = null;
        if (featuresRaw) {
            try { features = JSON.parse(featuresRaw); }
            catch (e) { ZF.toast("功能范围 features 不是合法 JSON", "err"); return; }
        }
        var payload = {
            name: document.getElementById("eName").value.trim(),
            description: document.getElementById("eDesc").value.trim() || null,
            duration_days: document.getElementById("eDuration").value ? parseInt(document.getElementById("eDuration").value, 10) : null,
            max_devices: parseInt(document.getElementById("eMaxDev").value, 10) || 1,
            features: features,
            scope_ids: collectScopeIds(),
            is_active: document.getElementById("eActive").checked ? 1 : 0
        };
        if (!payload.name) { ZF.toast("请填写模板名称", "err"); return; }
        var req = id ? ZF.put("/admin/license-templates/" + id, payload) : ZF.post("/admin/license-templates", payload);
        req.then(function (res) {
            if (res.success) { ZF.closeModal(); ZF.toast(id ? "已保存" : "已创建"); load(page); }
            else ZF.toast(errMsg(res), "err");
        }).catch(function () {});
    }

    function toggle(id, cur) {
        ZF.post("/admin/license-templates/" + id + "/toggle", {}).then(function (res) {
            if (res.success) { ZF.toast(cur ? "已停用" : "已启用"); load(page); }
            else ZF.toast(errMsg(res), "err");
        }).catch(function () {});
    }

    function remove(id) {
        ZF.confirmModal("删除授权模板", "确认删除授权模板 #" + id + "？删除后不可恢复。", "确认删除", function () {
            return ZF.del("/admin/license-templates/" + id).then(function (res) {
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
        document.getElementById("tplBody").addEventListener("click", function (e) {
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
