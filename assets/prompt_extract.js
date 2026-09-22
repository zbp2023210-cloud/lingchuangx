/* 视频转提示词 / 图片转提示词 —— 前端交互（浏览器本地抽帧与压缩，服务端仅接收帧数据） */
(function(){
  var form = document.querySelector('.pe-form');
  if (!form) { return; }

  var mode       = form.getAttribute('data-pe-mode') === 'video' ? 'video' : 'image';
  var isVideo    = mode === 'video';
  var authed     = form.getAttribute('data-pe-auth') === '1';
  var drop       = document.getElementById('peDrop');
  var fileInput  = document.getElementById('peFile');
  var preview    = document.getElementById('pePreview');
  var meta       = document.getElementById('peMeta');
  var statusEl   = document.getElementById('peStatus');
  var emptyEl    = document.getElementById('peEmpty');
  var resultEl   = document.getElementById('peResult');
  var submitBtn  = document.getElementById('peSubmit');
  var clearBtn   = document.getElementById('peClear');
  var langSel    = document.getElementById('peLang');
  var modelSel   = document.getElementById('peModel');
  var styleInput = document.getElementById('peStyle');

  var MAX_IMAGE_MB   = 10;
  var MAX_VIDEO_MB   = 50;
  var MAX_IMAGES     = 4;
  var MAX_FRAMES     = 8;
  var IMAGE_MAX_SIDE = 1280;
  var FRAME_MAX_SIDE = 768;
  var HISTORY_KEY    = 'lc_prompt_extract_' + mode;

  var assets = [];      // [{ url: dataURL, label: string }]
  var busy = false;

  function toast(text, bad) {
    var n = document.createElement('div');
    n.className = 'flash' + (bad ? ' error' : '');
    n.textContent = text;
    document.body.appendChild(n);
    setTimeout(function(){ if (n.parentNode) { n.parentNode.removeChild(n); } }, 6000);
  }

  function setStatus(text) { if (statusEl) { statusEl.textContent = text; } }
  function show(el, on) { if (el) { el.hidden = !on; } }
  function refreshSubmit() {
    if (!submitBtn) { return; }
    if (!authed) {
      submitBtn.disabled = true;
      submitBtn.textContent = '请先登录';
      return;
    }
    submitBtn.disabled = busy || assets.length === 0;
  }

  function humanSize(bytes) {
    if (!bytes && bytes !== 0) { return ''; }
    if (bytes >= 1048576) { return (bytes / 1048576).toFixed(1) + 'MB'; }
    if (bytes >= 1024) { return (bytes / 1024).toFixed(0) + 'KB'; }
    return bytes + 'B';
  }

  function formatTime(sec) {
    if (!isFinite(sec) || sec < 0) { return ''; }
    var m = Math.floor(sec / 60);
    var s = Math.floor(sec % 60);
    return m + ':' + (s < 10 ? '0' + s : s);
  }

  /* ---------- 预览 ---------- */
  function renderPreview() {
    if (!preview) { return; }
    preview.innerHTML = '';
    if (!assets.length) { show(preview, false); return; }
    show(preview, true);
    assets.forEach(function(a){
      var box = document.createElement('div');
      box.className = 'pe-thumb';
      var img = document.createElement('img');
      img.src = a.url;
      img.alt = a.label || '素材';
      img.loading = 'lazy';
      box.appendChild(img);
      if (a.label) {
        var tag = document.createElement('em');
        tag.textContent = a.label;
        box.appendChild(tag);
      }
      preview.appendChild(box);
    });
  }

  /* ---------- 图片：本地压缩 ---------- */
  function readImageFile(file) {
    return new Promise(function(resolve, reject){
      if (!/^image\//.test(file.type || '')) { reject(new Error('「' + file.name + '」不是受支持的图片文件')); return; }
      if (file.size > MAX_IMAGE_MB * 1048576) { reject(new Error('「' + file.name + '」超过 ' + MAX_IMAGE_MB + 'MB 限制')); return; }
      var objUrl = URL.createObjectURL(file);
      var img = new Image();
      img.onload = function(){
        var out;
        try {
          var w = img.naturalWidth, h = img.naturalHeight;
          if (!w || !h) { throw new Error('无法读取图片尺寸'); }
          var scale = Math.min(1, IMAGE_MAX_SIDE / Math.max(w, h));
          var canvas = document.createElement('canvas');
          canvas.width = Math.max(1, Math.round(w * scale));
          canvas.height = Math.max(1, Math.round(h * scale));
          var ctx = canvas.getContext('2d');
          ctx.fillStyle = '#ffffff';
          ctx.fillRect(0, 0, canvas.width, canvas.height);
          ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
          out = canvas.toDataURL('image/jpeg', 0.85);
        } catch (e) {
          URL.revokeObjectURL(objUrl);
          reject(new Error('处理「' + file.name + '」失败：' + e.message));
          return;
        }
        URL.revokeObjectURL(objUrl);
        if (!out || out.length < 200) { reject(new Error('「' + file.name + '」编码失败，请更换图片')); return; }
        resolve(out);
      };
      img.onerror = function(){
        URL.revokeObjectURL(objUrl);
        reject(new Error('无法解析「' + file.name + '」，请更换图片或另存为 JPG / PNG'));
      };
      img.src = objUrl;
    });
  }

  function handleImageFiles(list) {
    var files = Array.prototype.slice.call(list || []);
    if (!files.length) { return; }
    var room = MAX_IMAGES - assets.length;
    if (room <= 0) { toast('最多上传 ' + MAX_IMAGES + ' 张图片', true); return; }
    if (files.length > room) { toast('仅接收前 ' + room + ' 张图片（上限 ' + MAX_IMAGES + ' 张）', true); }
    files = files.slice(0, room);
    setStatus('正在处理图片…');
    var chain = Promise.resolve();
    var added = 0;
    files.forEach(function(file){
      chain = chain.then(function(){
        return readImageFile(file).then(function(dataUrl){
          assets.push({ url: dataUrl, label: '' });
          added++;
        }).catch(function(err){
          toast(err.message, true);
        });
      });
    });
    chain.then(function(){
      renderPreview();
      refreshSubmit();
      if (meta) {
        meta.hidden = assets.length === 0;
        meta.textContent = assets.length ? ('已选择 ' + assets.length + ' 张图片，将在浏览器本地压缩后送交 AI 分析') : '';
      }
      setStatus(assets.length ? ('已就绪 · ' + assets.length + ' 张图片') : '等待上传素材');
    });
  }

  /* ---------- 视频：本地抽帧 ---------- */
  function captureAt(video, canvas, ctx, time, cb) {
    var settled = false;
    function fire() {
      if (settled) { return; }
      settled = true;
      try {
        var w = video.videoWidth, h = video.videoHeight;
        if (!w || !h) { cb(''); return; }
        var scale = Math.min(1, FRAME_MAX_SIDE / Math.max(w, h));
        canvas.width = Math.max(2, Math.round(w * scale));
        canvas.height = Math.max(2, Math.round(h * scale));
        ctx.fillStyle = '#000000';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        cb(canvas.toDataURL('image/jpeg', 0.72));
      } catch (e) {
        cb('');
      }
    }
    function onSeeked() {
      video.removeEventListener('seeked', onSeeked);
      setTimeout(fire, 60);
    }
    video.addEventListener('seeked', onSeeked);
    setTimeout(function(){ video.removeEventListener('seeked', onSeeked); fire(); }, 2500);
    try { video.currentTime = time; } catch (e) { video.removeEventListener('seeked', onSeeked); fire(); }
  }

  function extractFrames(file) {
    return new Promise(function(resolve, reject){
      var objUrl = URL.createObjectURL(file);
      var video = document.createElement('video');
      video.preload = 'auto';
      video.muted = true;
      video.setAttribute('playsinline', '');
      video.setAttribute('webkit-playsinline', '');
      video.style.cssText = 'position:fixed;left:-9999px;top:0;width:1px;height:1px;opacity:0;pointer-events:none';
      document.body.appendChild(video);

      var settled = false;
      var guard = setTimeout(function(){ fail('视频加载超时，请更换更小的文件或改用 MP4 格式'); }, 45000);

      function cleanup() {
        try { video.pause(); } catch (e) {}
        if (video.parentNode) { video.parentNode.removeChild(video); }
        URL.revokeObjectURL(objUrl);
      }
      function fail(msg) {
        if (settled) { return; }
        settled = true;
        clearTimeout(guard);
        cleanup();
        reject(new Error(msg));
      }
      function done(frames) {
        if (settled) { return; }
        settled = true;
        clearTimeout(guard);
        cleanup();
        resolve(frames);
      }

      video.addEventListener('error', function(){
        fail('无法解码该视频，请改用 MP4（H.264）或 WebM 格式');
      });

      video.addEventListener('loadedmetadata', function(){
        var dur = video.duration;
        if (!isFinite(dur) || dur <= 0) { fail('无法获取视频时长，请更换视频文件'); return; }
        var canvas = document.createElement('canvas');
        var ctx = canvas.getContext('2d');
        var start = dur * 0.03;
        var end = Math.max(start, dur * 0.97);
        var times = [];
        for (var i = 0; i < MAX_FRAMES; i++) {
          var t = MAX_FRAMES === 1 ? (start + end) / 2 : start + (end - start) * i / (MAX_FRAMES - 1);
          times.push(Math.max(0, Math.min(t, Math.max(0, dur - 0.05))));
        }
        var frames = [];
        var idx = 0;
        function next() {
          if (idx >= times.length) {
            if (!frames.length) { fail('抽帧失败，请更换视频文件或格式后重试'); return; }
            done({ frames: frames, duration: dur });
            return;
          }
          setStatus('正在本地抽帧 ' + (idx + 1) + '/' + times.length + '…');
          captureAt(video, canvas, ctx, times[idx], function(dataUrl){
            if (dataUrl) {
              frames.push({ url: dataUrl, label: formatTime(times[idx]), seconds: times[idx] });
            }
            idx++;
            next();
          });
        }
        next();
      });

      video.src = objUrl;
      try { video.load(); } catch (e) { fail('视频加载失败，请更换文件'); }
    });
  }

  function handleVideoFile(file) {
    if (!/^video\//.test(file.type || '')) {
      toast('「' + file.name + '」不是受支持的视频文件', true);
      return;
    }
    if (file.size > MAX_VIDEO_MB * 1048576) {
      toast('视频超过 ' + MAX_VIDEO_MB + 'MB 限制', true);
      return;
    }
    busy = true;
    refreshSubmit();
    if (drop) { drop.classList.add('pe-busy'); }
    assets = [];
    renderPreview();
    setStatus('正在读取视频…');
    if (meta) { meta.hidden = false; meta.textContent = file.name + ' · ' + humanSize(file.size) + ' · 正在抽帧…'; }

    extractFrames(file).then(function(res){
      assets = res.frames.map(function(f){ return { url: f.url, label: f.label }; });
      renderPreview();
      if (meta) {
        meta.textContent = file.name + ' · ' + humanSize(file.size) + ' · 时长 ' + formatTime(res.duration) + ' · 已抽取 ' + assets.length + ' 帧';
      }
      setStatus('已就绪 · ' + assets.length + ' 帧关键帧');
      toast('已抽取 ' + assets.length + ' 帧关键帧，可开始解析');
    }).catch(function(err){
      assets = [];
      renderPreview();
      if (meta) { meta.hidden = true; meta.textContent = ''; }
      setStatus('抽帧失败');
      toast(err.message || '视频抽帧失败', true);
    }).then(function(){
      busy = false;
      if (drop) { drop.classList.remove('pe-busy'); }
      refreshSubmit();
    });
  }

  /* ---------- 上传入口 ---------- */
  if (drop && fileInput) {
    drop.addEventListener('click', function(){
      if (busy) { return; }
      fileInput.click();
    });
    fileInput.addEventListener('change', function(){
      var files = fileInput.files;
      if (files && files.length) {
        if (isVideo) { handleVideoFile(files[0]); } else { handleImageFiles(files); }
      }
      fileInput.value = '';
    });
    ['dragenter', 'dragover'].forEach(function(evt){
      drop.addEventListener(evt, function(e){
        e.preventDefault();
        e.stopPropagation();
        if (!busy) { drop.classList.add('pe-over'); }
      });
    });
    ['dragleave', 'drop'].forEach(function(evt){
      drop.addEventListener(evt, function(e){
        e.preventDefault();
        e.stopPropagation();
        drop.classList.remove('pe-over');
      });
    });
    drop.addEventListener('drop', function(e){
      if (busy) { return; }
      var dt = e.dataTransfer;
      if (!dt || !dt.files || !dt.files.length) { return; }
      if (isVideo) { handleVideoFile(dt.files[0]); } else { handleImageFiles(dt.files); }
    });
  }

  /* ---------- 结果渲染 ---------- */
  function corePrompt(text) {
    var m = String(text || '').match(/(?:完整提示词|Final Prompt)\s*[:：]\s*([\s\S]+)$/i);
    return (m ? m[1] : String(text || '')).replace(/\s+/g, ' ').trim();
  }

  function copyText(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).catch(function(){ fallbackCopy(text); });
    } else {
      fallbackCopy(text);
    }
  }
  function fallbackCopy(text) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.cssText = 'position:fixed;left:-9999px;top:0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); } catch (e) {}
    if (ta.parentNode) { ta.parentNode.removeChild(ta); }
  }

  function loadHistory() {
    try {
      var raw = localStorage.getItem(HISTORY_KEY);
      var arr = raw ? JSON.parse(raw) : [];
      return Object.prototype.toString.call(arr) === '[object Array]' ? arr : [];
    } catch (e) { return []; }
  }
  function pushHistory(text, model) {
    try {
      var list = loadHistory();
      list.unshift({ t: text, m: model || '', ts: Date.now() });
      localStorage.setItem(HISTORY_KEY, JSON.stringify(list.slice(0, 15)));
    } catch (e) {}
  }

  function renderHistory() {
    var list = loadHistory();
    if (!list.length) { return null; }
    var wrap = document.createElement('div');
    wrap.className = 'pe-hist';
    var head = document.createElement('div');
    head.className = 'pe-hist-title';
    head.textContent = '历史记录 · ' + list.length + ' 条';
    wrap.appendChild(head);
    list.forEach(function(item){
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'pe-hist-item';
      var when = '';
      try { when = new Date(item.ts || Date.now()).toLocaleString(); } catch (e) {}
      var brief = String(item.t || '').replace(/\s+/g, ' ').slice(0, 54);
      btn.textContent = when + '　' + brief;
      btn.addEventListener('click', function(){
        renderResult(String(item.t || ''), null, true);
        setStatus('已载入历史记录');
      });
      wrap.appendChild(btn);
    });
    return wrap;
  }

  function renderResult(text, info, skipHistory) {
    if (!resultEl) { return; }
    resultEl.innerHTML = '';

    var out = document.createElement('div');
    out.className = 'pe-out';
    out.textContent = text;
    resultEl.appendChild(out);

    var actions = document.createElement('div');
    actions.className = 'pe-actions';

    var copyBtn = document.createElement('button');
    copyBtn.type = 'button';
    copyBtn.className = 'pe-primary';
    copyBtn.textContent = '复制完整提示词';
    copyBtn.addEventListener('click', function(){
      copyText(text);
      copyBtn.textContent = '已复制';
      setTimeout(function(){ copyBtn.textContent = '复制完整提示词'; }, 1800);
    });
    actions.appendChild(copyBtn);

    var core = corePrompt(text);
    if (core && core !== text) {
      var copyCore = document.createElement('button');
      copyCore.type = 'button';
      copyCore.textContent = '仅复制最终提示词';
      copyCore.addEventListener('click', function(){
        copyText(core);
        copyCore.textContent = '已复制';
        setTimeout(function(){ copyCore.textContent = '仅复制最终提示词'; }, 1800);
      });
      actions.appendChild(copyCore);
    }

    var useImg = document.createElement('a');
    useImg.href = '/?page=image&prompt=' + encodeURIComponent(core.slice(0, 600));
    useImg.textContent = '去 AI 绘图';
    actions.appendChild(useImg);

    if (isVideo) {
      var useVid = document.createElement('a');
      useVid.href = '/?page=video&prompt=' + encodeURIComponent(core.slice(0, 600));
      useVid.textContent = '去生成视频';
      actions.appendChild(useVid);
    }

    var again = document.createElement('button');
    again.type = 'button';
    again.textContent = '重新解析';
    again.addEventListener('click', function(){
      if (submitBtn && !busy) { submitBtn.click(); }
    });
    actions.appendChild(again);

    resultEl.appendChild(actions);

    if (!skipHistory) { pushHistory(text, info && info.model); }

    var hist = renderHistory();
    if (hist) { resultEl.appendChild(hist); }

    resultEl.classList.add('visible');
    if (emptyEl) { emptyEl.style.display = 'none'; }
  }

  /* ---------- 提交 ---------- */
  form.addEventListener('submit', function(e){
    e.preventDefault();
    if (busy) { return; }
    if (!authed) {
      toast('请先登录后再使用 AI 解析', true);
      window.location.href = '/?page=login';
      return;
    }
    if (!assets.length) {
      toast(isVideo ? '请先选择视频文件' : '请先选择图片文件', true);
      return;
    }
    busy = true;
    refreshSubmit();
    if (submitBtn) { submitBtn.textContent = '解析中…'; }
    setStatus('AI 正在逆向解析…');

    var payloadImages = assets.map(function(a){ return a.url; });
    var totalBytes = 0;
    payloadImages.forEach(function(s){ totalBytes += s.length; });
    if (totalBytes > 20 * 1048576) {
      busy = false;
      refreshSubmit();
      if (submitBtn) { submitBtn.textContent = '开始解析'; }
      setStatus('素材体积过大');
      toast('素材体积过大，请减少' + (isVideo ? '视频时长' : '图片数量') + '后重试', true);
      return;
    }

    if (emptyEl) { emptyEl.style.display = 'none'; }
    if (resultEl) {
      resultEl.classList.add('visible');
      resultEl.innerHTML = '<div class="pe-loading"><span class="pe-spinner"></span><span>AI 正在逐项分析' +
        (isVideo ? '关键帧的运镜、光线与风格' : '画面的构图、光线与风格') + '，请稍候…</span></div>';
    }

    var payload = {
      mode: mode,
      images: payloadImages,
      lang: langSel ? langSel.value : 'zh',
      model: modelSel ? modelSel.value : '',
      style: styleInput ? styleInput.value.trim() : ''
    };

    fetch('/api.php?action=prompt-extract', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(payload)
    }).then(function(r){
      return r.text().then(function(raw){
        var body = null;
        try { body = JSON.parse(raw); } catch (e) {}
        return { status: r.status, body: body };
      });
    }).then(function(res){
      if (!res.body || res.body.ok !== true) {
        var msg = (res.body && res.body.message) || (res.body && res.body.error) || ('请求失败（HTTP ' + res.status + '）');
        if (res.status === 401) { msg = '登录状态已失效，请重新登录后再使用该工具'; }
        throw new Error(msg);
      }
      var data = res.body.data || {};
      var text = data.prompt || '';
      if (!text) { throw new Error('AI 未返回有效提示词，请更换素材或模型后重试'); }
      renderResult(text, data, false);
      setStatus('解析完成 · 已分析 ' + (data.frames || assets.length) + ' 帧素材 · ' + (data.model || ''));
    }).catch(function(err){
      if (resultEl) {
        resultEl.classList.remove('visible');
        resultEl.innerHTML = '';
      }
      if (emptyEl) { emptyEl.style.display = ''; }
      setStatus('解析失败');
      toast(err.message || '解析失败，请稍后重试', true);
    }).then(function(){
      busy = false;
      if (submitBtn) { submitBtn.textContent = '开始解析'; }
      refreshSubmit();
    });
  });

  /* ---------- 清空 ---------- */
  if (clearBtn) {
    clearBtn.addEventListener('click', function(){
      if (busy) { return; }
      assets = [];
      renderPreview();
      if (meta) { meta.hidden = true; meta.textContent = ''; }
      if (resultEl) {
        resultEl.classList.remove('visible');
        resultEl.innerHTML = '';
      }
      if (emptyEl) { emptyEl.style.display = ''; }
      setStatus('等待上传素材');
      refreshSubmit();
    });
  }

  refreshSubmit();
})();
