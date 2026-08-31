/* ZF License Management Platform - settings page */
!function(){"use strict";
var t=!1;

/* ---------- HMAC 密钥展示 / 轮换（保留原能力） ---------- */
function e(){var e=document.getElementById("hmacSecretBox"),a=document.getElementById("showHmacBtn");e.dataset.secret?(t=!t,e.classList.toggle("masked",!t),e.textContent=t?e.dataset.secret:"••••••••••••••••••••••••••••••••",a.textContent=t?"隐藏密钥":"显示密钥"):ZF.toast("密钥尚未加载，请稍后重试","warn")}
function a(){ZF.confirmModal("轮换 HMAC 密钥","轮换后使用旧密钥的请求将立即失效（签名校验失败）。当前浏览器会自动切换到新密钥；其他使用旧密钥的端需同步更新。确定继续？","确认轮换",function(){return ZF.post("/admin/security/hmac-secret/rotate").then(function(e){if(!(e.success&&e.data&&e.data.hmac_secret))throw ZF.toast(e.message||"轮换失败","err"),new Error("fail");var a=document.getElementById("hmacSecretBox");a.dataset.secret=e.data.hmac_secret,a.classList.add("masked"),a.textContent="••••••••••••••••••••••••••••••••",t=!1,document.getElementById("showHmacBtn").textContent="显示密钥",ZF.setHmacSecret(e.data.hmac_secret||e.data.secret),ZF.toast("密钥已轮换并同步到当前会话","ok")}).catch(function(t){throw ZF.toast(t&&t.message?t.message:"轮换失败","err"),t})},!0)}

/* ---------- 运行参数（settings 表读写，V1.32） ---------- */
function runtimeInputs(){return Array.prototype.slice.call(document.querySelectorAll("[data-key]"))}

/* 加载 DB 已保存值覆盖表单（未保存项保持 data-default 兜底显示） */
function loadRuntimeSettings(){
    return ZF.get("/admin/settings").then(function(res){
        if(!res.success){ if(res.message) ZF.toast(res.message,"warn"); return; }
        var map={};
        (res.data||[]).forEach(function(s){ map[s.key]=s; });
        runtimeInputs().forEach(function(el){
            var st=map[el.dataset.key];
            if(!st) return;
            if(el.type==="checkbox"){ el.checked = st.value===true || st.value===1 || st.value==="1" || st.value==="true"; }
            else { el.value = String(st.value); }
        });
    }).catch(function(){});
}

/* 收集并逐个保存（白名单后端校验；成功清空“已修改”状态） */
function saveRuntimeSettings(){
    var state=document.getElementById("runtimeSaveState");
    var fields=runtimeInputs().filter(function(el){
        if(el.type==="checkbox") return true;
        return el.value!=="" && el.value!==null;
    });
    var items=fields.map(function(el){
        var value=el.type==="checkbox" ? (el.checked?"1":"0") : el.value.trim();
        return {key:el.dataset.key, value:value, type:el.dataset.type||"string", description:el.dataset.desc||""};
    });
    if(state) state.textContent="";
    if(!items.length){ ZF.toast("没有可保存的运行参数","warn"); return; }

    var p=Promise.resolve();
    items.forEach(function(item){
        p=p.then(function(){ return ZF.post("/admin/settings", item); }).then(function(res){
            if(!res.success){ throw new Error(res.message||("保存失败："+item.key)); }
        });
    });
    p.then(function(){
        ZF.toast("运行参数已保存并生效","ok");
        if(state) state.textContent="已保存（settings 表生效，无需重启）";
    }).catch(function(err){
        ZF.toast(err&&err.message?err.message:"保存失败","err");
        if(state) state.textContent="";
    });
}

document.addEventListener("DOMContentLoaded",function(){
    ZF.initAuth(function(){
        /* HMAC 区域 */
        ZF.get("/admin/security/hmac-secret").then(function(t){
            t.success&&t.data&&t.data.hmac_secret?document.getElementById("hmacSecretBox").dataset.secret=t.data.hmac_secret:t.message&&ZF.toast(t.message,"warn")
        }).catch(function(){});
        document.getElementById("showHmacBtn").addEventListener("click",e);
        document.getElementById("rotateHmacBtn").addEventListener("click",a);

        /* 运行参数区域 */
        loadRuntimeSettings();
        var btn=document.getElementById("saveRuntimeBtn");
        if(btn) btn.addEventListener("click",saveRuntimeSettings);
    });
});
}();
