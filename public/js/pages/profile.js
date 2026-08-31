/* ZF 个人中心 */
(function () {
    "use strict";

    function errMsg(res) {
        return (res && res.message) || "加载失败";
    }

    function load() {
        ZF.get("/admin/profile").then(function (res) {
            if (!res.success) { ZF.toast(errMsg(res), "err"); return; }
            var u = res.data || {};
            document.getElementById("pName").textContent = u.name || "-";
            document.getElementById("pEmail").textContent = u.email || "-";
            document.getElementById("pRole").innerHTML = ZF.badge(u.role || "admin");
            document.getElementById("pLastLogin").textContent = ZF.fmtDate(u.last_login_at);
            document.getElementById("pLastIp").textContent = u.last_login_ip || "-";
            document.getElementById("pCreated").textContent = ZF.fmtDate(u.created_at);
            var avatar = document.getElementById("profileAvatar");
            if (avatar) avatar.textContent = (u.name || u.email || "?").charAt(0).toUpperCase();
        }).catch(function () {});
    }

    function init() {
        var go = document.getElementById("goPwd");
        if (go) go.href = ZF.adminBase + "/password-change";
    }

    document.addEventListener("DOMContentLoaded", function () {
        ZF.initAuth(function () { init(); load(); });
    });
})();
