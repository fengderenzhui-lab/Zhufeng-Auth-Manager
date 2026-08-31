/* ZF License Management Platform — 前端核心逻辑
   等保约束：凭据仅存 sessionStorage；请求 HMAC-SHA256 签名；随机后台路径归一化 */
(function () {
    "use strict";

    var TOKEN_KEY = "zf_token";
    var USER_KEY = "zf_user";
    var HMAC_KEY = "zf_hmac_secret";
    var SIDEBAR_COLLAPSE_KEY = "zf-sidebar-collapsed";

    var adminMeta = document.querySelector('meta[name="zf-admin-path"]');
    var adminBase = "/" + ((adminMeta && adminMeta.getAttribute("content")) || "admin").replace(/^\/+|\/+$/g, "");

    /* ---------- 工具 ---------- */
    function esc(s) {
        if (s == null) return "";
        return String(s).replace(/[&<>"']/g, function (c) {
            return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
        });
    }

    function nonce() {
        var b = new Uint8Array(16);
        if (window.crypto && crypto.getRandomValues) crypto.getRandomValues(b);
        var hex = "";
        for (var i = 0; i < b.length; i++) hex += (b[i] < 16 ? "0" : "") + b[i].toString(16);
        return hex;
    }

    function getToken() { return sessionStorage.getItem(TOKEN_KEY); }
    function setToken(t) { sessionStorage.setItem(TOKEN_KEY, t); }
    function clearToken() {
        sessionStorage.removeItem(TOKEN_KEY);
        sessionStorage.removeItem(USER_KEY);
        sessionStorage.removeItem(HMAC_KEY);
    }
    function getUser() {
        try { return JSON.parse(sessionStorage.getItem(USER_KEY) || "null"); }
        catch (e) { return null; }
    }
    function setUser(u) { sessionStorage.setItem(USER_KEY, JSON.stringify(u || {})); }
    function getHmacSecret() { return sessionStorage.getItem(HMAC_KEY) || ""; }
    function setHmacSecret(s) { s ? sessionStorage.setItem(HMAC_KEY, s) : sessionStorage.removeItem(HMAC_KEY); }

    function hmacSha256Hex(key, data) {
        if (!window.crypto || !crypto.subtle || !crypto.subtle.importKey) {
            return Promise.reject(new Error("当前环境不支持 Web Crypto（需 HTTPS 或 localhost）"));
        }
        var enc = new TextEncoder();
        return crypto.subtle
            .importKey("raw", enc.encode(key), { name: "HMAC", hash: "SHA-256" }, false, ["sign"])
            .then(function (k) { return crypto.subtle.sign("HMAC", k, enc.encode(data)); })
            .then(function (sig) {
                var buf = new Uint8Array(sig), hex = "";
                for (var i = 0; i < buf.length; i++) hex += (buf[i] < 16 ? "0" : "") + buf[i].toString(16);
                return hex;
            });
    }

    /* ---------- 带签名请求 ---------- */
    function request(path, opts) {
        opts = opts || {};
        var headers = opts.headers || {};
        headers.Accept = "application/json";
        var ts = String(Math.floor(Date.now() / 1000));
        var nz = nonce();
        headers["X-Timestamp"] = ts;
        headers["X-Nonce"] = nz;
        var token = getToken();
        if (token) headers.Authorization = "Bearer " + token;

        var body = opts.body;
        if (body != null && !(body instanceof FormData) && typeof body !== "string") {
            headers["Content-Type"] = "application/json";
            body = JSON.stringify(body);
            opts.body = body;
        }

        var urlPath = path;
        if (urlPath.indexOf("/" + adminBase) !== 0) {
            urlPath = urlPath.replace(/^\/admin(?=\/|$)/, adminBase);
        }
        var apiUrl = "/api/v1" + urlPath;
        var method = (opts.method || "GET").toUpperCase();
        var signing = Promise.resolve();
        var secret = getHmacSecret();
        if (secret) {
            var canonical = apiUrl.split("?")[0].replace(/^\//, "");
            signing = hmacSha256Hex(secret, [method, canonical, ts, nz, body || ""].join("\n"))
                .then(function (sig) { headers["X-Signature"] = sig; })
                .catch(function (err) { console.warn("HMAC 签名不可用：", err.message || err); });
        }
        return signing.then(function () {
            return fetch(apiUrl, {
                method: method,
                headers: headers,
                body: opts.body,
                credentials: "same-origin"
            }).then(function (resp) {
                return resp.json().catch(function () {
                    return { success: false, message: "服务响应格式异常", status: resp.status };
                }).then(function (data) {
                    data._status = resp.status;
                    var isLogin = path.indexOf("/login") !== -1;
                    if (resp.status === 401 && !isLogin) {
                        clearToken();
                        if (location.pathname !== adminBase + "/login") location.href = adminBase + "/login";
                        throw new Error("未授权，请重新登录。");
                    }
                    return data;
                });
            });
        });
    }

    function get(path) { return request(path); }
    function post(path, body) { return request(path, { method: "POST", body: body }); }
    function put(path, body) { return request(path, { method: "PUT", body: body }); }
    function del(path) { return request(path, { method: "DELETE" }); }

    /* ---------- Toast ---------- */
    function toast(msg, type) {
        type = type || "ok";
        var root = document.getElementById("toast-root");
        if (!root) return;
        var el = document.createElement("div");
        el.className = "toast " + type;
        var txt = document.createElement("span");
        txt.className = "toast-txt";
        txt.textContent = msg;
        var bar = document.createElement("span");
        bar.className = "toast-progress";
        el.appendChild(txt);
        el.appendChild(bar);
        root.appendChild(el);
        setTimeout(function () { el.classList.add("out"); }, 3200);
        setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 3600);
    }

    /* ---------- Modal ---------- */
    var maskEl = null, boxEl = null;
    function ensureModal() {
        if (!maskEl) {
            maskEl = document.getElementById("modalMask");
            boxEl = document.getElementById("modalBox");
            if (maskEl) {
                document.getElementById("modalClose").addEventListener("click", closeModal);
                maskEl.addEventListener("click", function (e) { if (e.target === maskEl) closeModal(); });
            }
        }
    }
    function openModal(title, bodyHtml, footHtml, size) {
        ensureModal();
        if (!maskEl) return;
        maskEl.classList.remove("closing");
        document.getElementById("modalTitle").textContent = title;
        document.getElementById("modalBody").innerHTML = bodyHtml;
        document.getElementById("modalFoot").innerHTML = footHtml || "";
        boxEl.className = "modal" + (size === "lg" ? " modal-lg" : "");
        maskEl.classList.add("open");
    }
    function closeModal() {
        if (!maskEl || !maskEl.classList.contains("open") || maskEl.classList.contains("closing")) return;
        maskEl.classList.add("closing");
        if (boxEl) {
            var form = boxEl.querySelector("form");
            if (form) form.reset();
        }
        setTimeout(function () { maskEl.classList.remove("open", "closing"); }, 230);
    }

    var BADGES = {
        active: { label: "有效", cls: "badge-green" },
        expired: { label: "已过期", cls: "badge-yellow" },
        revoked: { label: "已吊销", cls: "badge-red" },
        blacklisted: { label: "已拉黑", cls: "badge-red" },
        pending: { label: "待激活", cls: "badge-yellow" },
        online: { label: "在线", cls: "badge-green" },
        offline: { label: "离线", cls: "badge-gray" },
        super_admin: { label: "超级管理员", cls: "badge-purple" },
        admin: { label: "管理员", cls: "badge-olive" }
    };
    function badge(key) {
        var b = BADGES[key] || { label: key || "-", cls: "badge-gray" };
        return '<span class="badge ' + b.cls + '">' + esc(b.label) + "</span>";
    }

    function confirmModal(title, text, okLabel, onOk, danger) {
        openModal(title, '<div style="line-height:1.8;">' + esc(text) + "</div>",
            '<button class="btn btn-ghost" type="button" data-close>取消</button>' +
            '<button class="btn ' + (danger ? "btn-danger" : "btn-primary") + '" type="button" id="confirmOk">' + esc(okLabel || "确认") + "</button>");
        maskEl.querySelector("[data-close]").addEventListener("click", closeModal);
        document.getElementById("confirmOk").addEventListener("click", function () {
            var btn = this;
            btn.classList.add("btn-loading");
            btn.disabled = true;
            var ret = null;
            try { ret = onOk(); } catch (e) { ret = null; }
            if (ret && typeof ret.then === "function") {
                ret.then(function () { closeModal(); }, function () {
                    btn.classList.remove("btn-loading");
                    btn.disabled = false;
                });
            } else {
                closeModal();
            }
        });
    }

    /* ---------- 分页 ---------- */
    function pager(p, cb) {
        if (!p) return "";
        var cur = p.current_page, last = p.last_page;
        var html = '<div class="pagination"><span class="page-info">' + esc("共 " + p.total + " 条 / 第 " + cur + " 页") + "</span>";
        function btn(page, label, isCur) {
            return '<button class="' + ("btn page-btn" + (isCur ? " current" : "")) + '" type="button" data-page="' + page + '">' + esc(label) + "</button>";
        }
        html += btn(Math.max(1, cur - 1), "‹", false);
        for (var i = Math.max(1, cur - 2), end = Math.min(last, cur + 2); i <= end; i++) html += btn(i, i, i === cur);
        html += btn(Math.min(last, cur + 1), "›", false);
        return html + "</div>";
    }
    function bindPager(container, cb) {
        if (!container) return;
        container.querySelectorAll("[data-page]").forEach(function (b) {
            b.addEventListener("click", function () { cb(parseInt(b.getAttribute("data-page"), 10)); });
        });
    }

    /* ---------- 格式化 ---------- */
    function fmtDate(v) {
        if (!v) return "-";
        var d = new Date(v);
        if (isNaN(d.getTime())) return esc(v);
        var p = function (n) { return n < 10 ? "0" + n : "" + n; };
        return d.getFullYear() + "-" + p(d.getMonth() + 1) + "-" + p(d.getDate()) + " " +
               p(d.getHours()) + ":" + p(d.getMinutes()) + ":" + p(d.getSeconds());
    }
    function empty(colspan) {
        return '<tr><td class="table-empty" colspan="' + colspan + '">暂无数据</td></tr>';
    }

    /* ---------- 退出登录 ---------- */
    function logout() {
        post(adminBase + "/logout").catch(function () {}).finally(function () {
            clearToken();
            location.href = adminBase + "/login";
        });
    }

    /* ---------- 初始化认证与界面填充 ---------- */
    function initAuth(done) {
        if (!getToken()) { location.href = adminBase + "/login"; return; }
        var fill = function (u) {
            setUser(u);
            var role = u.role || "admin";
            if (u.must_change_password && document.body.getAttribute("data-page") !== "password-change") {
                location.href = adminBase + "/password-change";
                return;
            }
            // 侧边栏菜单按角色显隐
            document.querySelectorAll(".nav-item[data-require]").forEach(function (el) {
                if (el.getAttribute("data-require") === "super" && role !== "super_admin") el.style.display = "none";
            });
            // 顶部用户区
            var nameEl = document.getElementById("topbar-name");
            var roleEl = document.getElementById("topbar-role");
            var avatarEl = document.getElementById("topbar-avatar");
            if (nameEl) nameEl.textContent = u.name || u.email || "-";
            if (roleEl) roleEl.textContent = role === "super_admin" ? "超级管理员" : "管理员";
            if (avatarEl) avatarEl.textContent = (u.name || u.email || "?").charAt(0).toUpperCase();
            if (done) done(u);
        };
        var cached = getUser();
        if (cached && cached.role) { fill(cached); return; }
        get(adminBase + "/me").then(function (res) {
            if (res.success && res.data) fill(res.data);
            else { clearToken(); location.href = adminBase + "/login"; }
        }).catch(function () {});
    }

    /* ---------- 用户下拉 ---------- */
    function initUserDropdown() {
        var trigger = document.getElementById("userTrigger");
        var panel = document.getElementById("userPanel");
        var dd = document.getElementById("userDropdown");
        if (!trigger || !panel || !dd) return;
        trigger.addEventListener("click", function (e) {
            e.stopPropagation();
            var open = dd.classList.toggle("open");
            trigger.setAttribute("aria-expanded", open ? "true" : "false");
        });
        document.addEventListener("click", function (e) {
            if (!dd.classList.contains("open")) return;
            if (!dd.contains(e.target)) {
                dd.classList.remove("open");
                trigger.setAttribute("aria-expanded", "false");
            }
        });
        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape" && dd.classList.contains("open")) {
                dd.classList.remove("open");
                trigger.setAttribute("aria-expanded", "false");
            }
        });
        var ddLogout = document.getElementById("ddLogout");
        if (ddLogout) ddLogout.addEventListener("click", logout);
    }

    /* ---------- 侧边栏折叠 / 抽屉 ---------- */
    function initSidebar() {
        var sidebar = document.getElementById("sidebar");
        var toggle = document.getElementById("menuToggle");
        if (!sidebar || !toggle) return;
        var isMobile = function () { return window.matchMedia && window.matchMedia("(max-width: 860px)").matches; };
        var backdrop = null;
        function ensureBackdrop() {
            if (!backdrop) {
                backdrop = document.createElement("div");
                backdrop.className = "sidebar-backdrop";
                backdrop.addEventListener("click", function () {
                    sidebar.classList.remove("open");
                    backdrop.classList.remove("show");
                });
                document.body.appendChild(backdrop);
            }
            return backdrop;
        }
        toggle.addEventListener("click", function () {
            if (isMobile()) {
                var open = sidebar.classList.toggle("open");
                ensureBackdrop().classList.toggle("show", open);
            } else {
                var collapsed = sidebar.classList.toggle("collapsed");
                try { localStorage.setItem(SIDEBAR_COLLAPSE_KEY, collapsed ? "1" : "0"); } catch (e) {}
                toggle.setAttribute("aria-label", collapsed ? "展开侧边栏" : "折叠侧边栏");
            }
        });
        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape") {
                if (maskEl && maskEl.classList.contains("open")) { closeModal(); return; }
                if (sidebar.classList.contains("open")) {
                    sidebar.classList.remove("open");
                    if (backdrop) backdrop.classList.remove("show");
                }
            }
        });
        // 恢复折叠状态
        try {
            if (localStorage.getItem(SIDEBAR_COLLAPSE_KEY) === "1" && !isMobile()) {
                sidebar.classList.add("collapsed");
                toggle.setAttribute("aria-label", "展开侧边栏");
            }
        } catch (e) {}
        window.addEventListener("resize", function () {
            if (!isMobile()) sidebar.classList.remove("open");
            else sidebar.classList.remove("collapsed");
        });
    }

    document.addEventListener("DOMContentLoaded", function () {
        ensureModal();
        initSidebar();
        initUserDropdown();
        var logoutBtn = document.getElementById("logoutBtn");
        if (logoutBtn) logoutBtn.addEventListener("click", logout);
        if (document.body.getAttribute("data-page") !== "login") initAuth();
    });

    window.ZF = {
        api: request,
        get: get,
        post: post,
        put: put,
        del: del,
        esc: esc,
        toast: toast,
        badge: badge,
        openModal: openModal,
        closeModal: closeModal,
        confirmModal: confirmModal,
        pager: pager,
        bindPager: bindPager,
        fmtDate: fmtDate,
        empty: empty,
        getToken: getToken,
        setToken: setToken,
        clearToken: clearToken,
        getUser: getUser,
        setUser: setUser,
        logout: logout,
        initAuth: initAuth,
        getHmacSecret: getHmacSecret,
        setHmacSecret: setHmacSecret,
        hmacSha256Hex: hmacSha256Hex,
        adminBase: adminBase,
        nonce: nonce
    };
})();
