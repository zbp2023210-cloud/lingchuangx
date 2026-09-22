/* 无痕无水印工具 —— 前端交互 */
(function(){
  var form = document.querySelector('.wm-form');
  if (!form) return;

  var authed    = form.getAttribute('data-wm-auth') === '1';
  var urlInput  = document.getElementById('wmUrl');
  var submitBtn = document.getElementById('wmSubmit');
  var clearBtn  = document.getElementById('wmClear');
  var statusEl  = document.getElementById('wmStatus');
  var emptyEl   = document.getElementById('wmEmpty');
  var resultEl  = document.getElementById('wmResult');
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
      h.className = 'pe-out';
      h.style.cssText = 'font-size:15px;font-weight:700;margin-bottom:12px;line-height:1.5';
      h.textContent = data.title;
      resultEl.appendChild(h);
    }

    // Video player
    if (data.video_url) {
      var vw = document.createElement('div');
      vw.style.cssText = 'position:relative;width:100%;max-width:640px;margin:0 auto 16px;border-radius:12px;overflow:hidden;background:#000';
      var video = document.createElement('video');
      video.src = data.video_url;
      video.controls = true;
      video.playsInline = true;
      video.preload = 'metadata';
      video.style.cssText = 'width:100%;display:block;max-height:400px';
      video.onerror = function(){ toast('视频加载失败，链接可能已过期', true); };
      vw.appendChild(video);
      resultEl.appendChild(vw);
    }

    // Actions
    var actions = document.createElement('div');
    actions.className = 'pe-actions';

    if (data.video_url) {
      var dl = document.createElement('a');
      dl.href = data.video_url;
      dl.target = '_blank';
      dl.rel = 'noopener noreferrer';
      dl.className = 'pe-primary';
      dl.textContent = '下载视频';
      dl.addEventListener('click', function(e){
        // Try to trigger download via fetch+blob for better filename
        e.preventDefault();
        toast('正在准备下载…');
        fetch(data.video_url).then(function(r){ return r.blob(); }).then(function(blob){
          var a = document.createElement('a');
          a.href = URL.createObjectURL(blob);
          a.download = (data.title || 'video').replace(/[^\w\u4e00-\u9fff.-]/g,'_').slice(0,80) + '.mp4';
          document.body.appendChild(a);
          a.click();
          setTimeout(function(){ URL.revokeObjectURL(a.href); if(a.parentNode) a.parentNode.removeChild(a); }, 3000);
        }).catch(function(){ window.open(data.video_url, '_blank'); });
      });
      actions.appendChild(dl);

      var copyLink = document.createElement('button');
      copyLink.type = 'button';
      copyLink.textContent = '复制链接';
      copyLink.addEventListener('click', function(){
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(data.video_url).catch(function(){});
        } else {
          var ta = document.createElement('textarea');
          ta.value = data.video_url;
          ta.style.cssText = 'position:fixed;left:-9999px';
          document.body.appendChild(ta);
          ta.select();
          try { document.execCommand('copy'); } catch(e){}
          if (ta.parentNode) ta.parentNode.removeChild(ta);
        }
        copyLink.textContent = '已复制';
        setTimeout(function(){ copyLink.textContent = '复制链接'; }, 1800);
      });
      actions.appendChild(copyLink);
    }

    var again = document.createElement('button');
    again.type = 'button';
    again.textContent = '重新解析';
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
    submitBtn.textContent = '解析中…';
    setStatus('正在解析视频链接…');
    if (emptyEl) emptyEl.style.display = 'none';
    if (resultEl) {
      resultEl.classList.add('visible');
      resultEl.innerHTML = '<div class="pe-loading"><span class="pe-spinner"></span><span>正在解析视频链接，请稍候…</span></div>';
    }

    fetch('/api.php?action=resolve-video-link', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ url: url })
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
      if (!data.video_url) throw new Error('未能解析到视频地址，请确认链接正确且视频未被删除');
      renderResult(data);
      setStatus('解析成功 · ' + (data.platform || '未知平台'));
      toast('视频解析成功');
    }).catch(function(err){
      if (resultEl) { resultEl.classList.remove('visible'); resultEl.innerHTML = ''; }
      if (emptyEl) emptyEl.style.display = '';
      setStatus('解析失败');
      toast(err.message || '解析失败，请稍后重试', true);
    }).then(function(){
      busy = false;
      submitBtn.textContent = '解析视频';
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
