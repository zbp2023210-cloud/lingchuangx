/* 视频文案提取工具 —— 前端交互 */
(function(){
  var form = document.querySelector('.vt-form');
  if (!form) return;

  var authed    = form.getAttribute('data-vt-auth') === '1';
  var urlInput  = document.getElementById('vtUrl');
  var submitBtn = document.getElementById('vtSubmit');
  var clearBtn  = document.getElementById('vtClear');
  var statusEl  = document.getElementById('vtStatus');
  var emptyEl   = document.getElementById('vtEmpty');
  var resultEl  = document.getElementById('vtResult');
  var langSel   = document.getElementById('vtLang');
  var modelSel  = document.getElementById('vtModel');
  var busy = false;

  function toast(text, bad) {
    var n = document.createElement('div');
    n.className = 'flash' + (bad ? ' error' : '');
    n.textContent = text;
    document.body.appendChild(n);
    setTimeout(function(){ if (n.parentNode) n.parentNode.removeChild(n); }, 6000);
  }
  function setStatus(t) { if (statusEl) statusEl.textContent = t; }
  function refreshSubmit() {
    if (!submitBtn) return;
    if (!authed) { submitBtn.disabled = true; submitBtn.textContent = '请先登录'; return; }
    submitBtn.disabled = busy || !urlInput.value.trim();
  }
  function copyText(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).catch(function(){ fallbackCopy(text); });
    } else { fallbackCopy(text); }
  }
  function fallbackCopy(text) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.cssText = 'position:fixed;left:-9999px;top:0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); } catch(e){}
    if (ta.parentNode) ta.parentNode.removeChild(ta);
  }

  if (urlInput) {
    urlInput.addEventListener('input', refreshSubmit);
    urlInput.addEventListener('paste', function(){ setTimeout(refreshSubmit, 50); });
  }

  function renderResult(data) {
    if (!resultEl) return;
    resultEl.innerHTML = '';

    // Title
    if (data.title) {
      var h = document.createElement('div');
      h.style.cssText = 'font-size:15px;font-weight:700;margin-bottom:12px;line-height:1.5;color:#dbe3ef';
      h.textContent = data.title;
      resultEl.appendChild(h);
    }

    // Transcript
    var out = document.createElement('div');
    out.className = 'pe-out';
    out.style.whiteSpace = 'pre-wrap';
    out.textContent = data.transcript || '';
    resultEl.appendChild(out);

    // Actions
    var actions = document.createElement('div');
    actions.className = 'pe-actions';

    var copyBtn = document.createElement('button');
    copyBtn.type = 'button';
    copyBtn.className = 'pe-primary';
    copyBtn.textContent = '复制文案';
    copyBtn.addEventListener('click', function(){
      copyText(data.transcript || '');
      copyBtn.textContent = '已复制';
      setTimeout(function(){ copyBtn.textContent = '复制文案'; }, 1800);
    });
    actions.appendChild(copyBtn);

    var dlTxt = document.createElement('button');
    dlTxt.type = 'button';
    dlTxt.textContent = '下载 TXT';
    dlTxt.addEventListener('click', function(){
      var blob = new Blob([data.transcript || ''], { type: 'text/plain;charset=utf-8' });
      var a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = (data.title || 'transcript').replace(/[^\w\u4e00-\u9fff.-]/g,'_').slice(0,80) + '.txt';
      document.body.appendChild(a);
      a.click();
      setTimeout(function(){ URL.revokeObjectURL(a.href); if(a.parentNode) a.parentNode.removeChild(a); }, 3000);
    });
    actions.appendChild(dlTxt);

    var again = document.createElement('button');
    again.type = 'button';
    again.textContent = '重新提取';
    again.addEventListener('click', function(){ if (submitBtn && !busy) submitBtn.click(); });
    actions.appendChild(again);

    resultEl.appendChild(actions);
    resultEl.classList.add('visible');
    if (emptyEl) emptyEl.style.display = 'none';
  }

  form.addEventListener('submit', function(e){
    e.preventDefault();
    if (busy) return;
    if (!authed) { toast('请先登录后再使用该工具', true); window.location.href = '/?page=login'; return; }
    var url = urlInput.value.trim();
    if (!url) { toast('请输入视频链接', true); return; }

    busy = true;
    refreshSubmit();
    submitBtn.textContent = '提取中…';
    setStatus('AI 正在识别视频语音内容，可能需要 30-120 秒…');
    if (emptyEl) emptyEl.style.display = 'none';
    if (resultEl) {
      resultEl.classList.add('visible');
      resultEl.innerHTML = '<div class="pe-loading"><span class="pe-spinner"></span><span>AI 正在识别视频中的语音并生成文字稿，请耐心等待…</span></div>';
    }

    fetch('/api.php?action=video-transcript', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({
        url: url,
        lang: langSel ? langSel.value : 'zh',
        model: modelSel ? modelSel.value : ''
      })
    }).then(function(r){
      return r.text().then(function(raw){
        var body = null;
        try { body = JSON.parse(raw); } catch(e){}
        return { status: r.status, body: body };
      });
    }).then(function(res){
      if (!res.body || res.body.ok !== true) {
        var msg = (res.body && res.body.message) || (res.body && res.body.error) || ('请求失败（HTTP ' + res.status + '）');
        if (res.status === 401) msg = '登录状态已失效，请重新登录';
        throw new Error(msg);
      }
      var data = res.body.data || {};
      if (!data.transcript) throw new Error('AI 未能识别该视频的语音内容，请确认视频包含可识别的语音');
      renderResult(data);
      setStatus('提取完成 · ' + (data.model || '') + ' · ' + (data.platform || ''));
      toast('文案提取成功');
    }).catch(function(err){
      if (resultEl) { resultEl.classList.remove('visible'); resultEl.innerHTML = ''; }
      if (emptyEl) emptyEl.style.display = '';
      setStatus('提取失败');
      toast(err.message || '提取失败，请稍后重试', true);
    }).then(function(){
      busy = false;
      submitBtn.textContent = '提取文案';
      refreshSubmit();
    });
  });

  if (clearBtn) {
    clearBtn.addEventListener('click', function(){
      if (busy) return;
      if (urlInput) urlInput.value = '';
      if (resultEl) { resultEl.classList.remove('visible'); resultEl.innerHTML = ''; }
      if (emptyEl) emptyEl.style.display = '';
      setStatus('等待输入链接');
      refreshSubmit();
    });
  }

  refreshSubmit();
})();
