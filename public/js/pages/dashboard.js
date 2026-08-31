/* ZF License Management Platform — 数据看板
   柱状图：暖灰渐变（--chart-bar-2 -> --chart-bar），随 data-theme 主题切换重建 */
(function () {
    "use strict";

    var charts = [];

    function cssVar(name, fallback) {
        return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
    }

    function themeColors() {
        return {
            bar: cssVar("--chart-bar", "#33302b"),
            bar2: cssVar("--chart-bar-2", "#d6cfc3"),
            grid: cssVar("--chart-grid", "rgba(43,39,35,0.08)"),
            tick: cssVar("--chart-tick", "#8a8378")
        };
    }

    // 柱体垂直渐变（顶部 bar2 浅灰 -> 底部 bar 主色），深浅主题各取对应 token
    function barGradient(context, colors) {
        var chart = context.chart;
        var area = chart.chartArea;
        var bottom = area ? area.bottom : 300;
        var g = chart.ctx.createLinearGradient(0, 0, 0, bottom);
        g.addColorStop(0, colors.bar2);
        g.addColorStop(1, colors.bar);
        return g;
    }

    function createChart(id, config) {
        var canvas = document.getElementById(id);
        if (!canvas) return null;
        if (typeof Chart === "undefined") {
            var box = canvas.closest(".card");
            var holder = box && box.querySelector(".chart-box");
            if (holder) holder.innerHTML = '<div class="chart-empty">图表库加载失败，请检查 public/vendor/chartjs/chart.umd.min.js</div>';
            return null;
        }
        var chart = new Chart(canvas.getContext("2d"), config);
        charts.push(chart);
        return chart;
    }

    function renderStatus(colors) {
        createChart("statusChart", {
            type: "bar",
            data: {
                labels: ["待激活", "有效", "已过期", "已吊销", "已拉黑"],
                datasets: [{
                    label: "授权数量",
                    data: [0, 0, 0, 0, 0],
                    backgroundColor: function (ctx) { return barGradient(ctx, colors); },
                    borderColor: colors.bar,
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { backgroundColor: colors.bar, titleColor: colors.tick, bodyColor: "#fff" }
                },
                scales: {
                    x: { grid: { color: colors.grid }, ticks: { color: colors.tick } },
                    y: { beginAtZero: true, grid: { color: colors.grid }, ticks: { color: colors.tick, precision: 0 } }
                }
            }
        });
    }

    function renderProducts(colors) {
        createChart("productChart", {
            type: "bar",
            data: {
                labels: [],
                datasets: [{
                    label: "授权数量",
                    data: [],
                    backgroundColor: function (ctx) { return barGradient(ctx, colors); },
                    borderColor: colors.bar,
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                indexAxis: "y",
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { backgroundColor: colors.bar, titleColor: colors.tick, bodyColor: "#fff" }
                },
                scales: {
                    x: { beginAtZero: true, grid: { color: colors.grid }, ticks: { color: colors.tick, precision: 0 } },
                    y: { grid: { display: false }, ticks: { color: colors.tick } }
                }
            }
        });
    }

    function destroyCharts() {
        charts.forEach(function (c) { try { c.destroy(); } catch (e) {} });
        charts = [];
    }

    function loadStats(colors) {
        ZF.get("/admin/stats").then(function (res) {
            if (!res.success) {
                document.querySelectorAll(".stat-value").forEach(function (el) { el.textContent = "-"; });
                ZF.toast(res.message || "统计加载失败", "err");
                return;
            }
            var data = res.data || {};
            var byStatus = data.licenses_by_status || {};
            document.getElementById("statTotal").textContent = data.licenses != null ? data.licenses : "-";
            document.getElementById("statActive").textContent = byStatus.active != null ? byStatus.active : "-";
            document.getElementById("statExpired").textContent = byStatus.expired != null ? byStatus.expired : "-";
            document.getElementById("statDevices").textContent = data.devices != null ? data.devices : "-";
            document.getElementById("statHeartbeats").textContent = data.heartbeats_today != null ? data.heartbeats_today : "-";

            var statusCounts = ["pending", "active", "expired", "revoked", "blacklisted"].map(function (k) {
                return byStatus[k] || 0;
            });
            renderStatus(colors);
            var sc = charts[charts.length - 1];
            if (sc) sc.data.datasets[0].data = statusCounts, sc.update();
        }).catch(function () {});

        ZF.get("/admin/products?per_page=100").then(function (res) {
            if (!res.success) return;
            var list = res.data || [];
            var rows = list.slice(0, 12).reverse();
            renderProducts(colors);
            var pc = charts[charts.length - 1];
            if (pc) {
                pc.data.labels = rows.map(function (p) { return p.name || p.slug || "#" + p.id; });
                pc.data.datasets[0].data = rows.map(function (p) { return p.licenses_count || 0; });
                pc.update();
            }
        }).catch(function () {});
    }

    function renderRecentAudits(list) {
        var tbody = document.getElementById("recentAuditBody");
        if (!tbody) return;
        if (!list || !list.length) { tbody.innerHTML = ZF.empty(6); return; }
        tbody.innerHTML = list.slice(0, 10).map(function (r) {
            return "<tr><td>" + r.id + '</td><td><span class="badge badge-gray">' + ZF.esc(r.action || "-") +
                "</span></td><td>" + ZF.esc(r.actor_type || "-") + (r.actor_id ? " #" + r.actor_id : "") +
                "</td><td>" + ZF.esc(r.resource_type || "-") + (r.resource_id ? " #" + r.resource_id : "") +
                '</td><td class="mono">' + ZF.esc(r.ip_address || "-") + "</td><td>" +
                ZF.fmtDate(r.created_at) + "</td></tr>";
        }).join("");
    }

    document.addEventListener("DOMContentLoaded", function () {
        ZF.initAuth(function () {
            loadStats(themeColors());
        });
        document.addEventListener("theme-changed", function (e) {
            var colors = themeColors();
            destroyCharts();
            loadStats(colors);
        });
        ZF.get("/admin/stats/recent-audits").then(function (res) {
            renderRecentAudits(res.success ? res.data : null);
        }).catch(function () {});
    });
})();
