(function(){
  function closeModal(id){var el=document.getElementById(id);if(el)el.remove();}
  var checkin=document.getElementById('dailyCheckinModal');
  if(checkin){checkin.querySelectorAll('[data-checkin-close]').forEach(function(x){x.addEventListener('click',function(){closeModal('dailyCheckinModal');});});var btn=document.getElementById('dailyCheckinBtn');if(btn)btn.addEventListener('click',function(){btn.disabled=true;btn.textContent='签到中…';fetch('/api.php?action=daily-checkin',{headers:{Accept:'application/json'}}).then(function(r){return r.json();}).then(function(x){if(!x.ok)throw new Error(x.message||'签到失败');btn.textContent='已签到 +'+(x.data.reward_points||1)+' 积分';btn.classList.add('done');setTimeout(function(){closeModal('dailyCheckinModal');},900);}).catch(function(e){btn.disabled=false;btn.textContent='立即签到';alert(e.message||'签到失败，请稍后重试');});});}
  var announcement=document.getElementById('systemAnnouncementModal');
  if(announcement){announcement.querySelectorAll('[data-announcement-close]').forEach(function(x){x.addEventListener('click',function(){closeModal('systemAnnouncementModal');});});}
  document.addEventListener('click',function(e){
    var a=e.target.closest&&e.target.closest('.clone-menu a.has-submenu');
    if(!a)return;
    e.preventDefault();
    var sub=a.nextElementSibling;
    if(sub&&sub.classList.contains('clone-submenu'))sub.classList.toggle('open');
    a.classList.toggle('expanded');
  });

  document.addEventListener('click',function(e){
    var btn=e.target.closest&&e.target.closest('.mode-switch button,.model-options button,.record-tabs button');
    if(!btn)return;
    var group=btn.closest('.mode-switch,.model-options,.record-tabs');
    if(!group)return;
    group.querySelectorAll('button').forEach(function(x){x.classList.remove('active');});
    btn.classList.add('active');
    var form=btn.closest('form');
    if(group.classList.contains('mode-switch')&&form){
      var ref=form.querySelector('.ref-upload');
      var text=btn.textContent||'';
      if(ref)ref.style.display=/文生|文本|音乐|爆款/.test(text)?'none':'flex';
    }
    if(group.classList.contains('model-options')&&form){
      var label=(btn.querySelector('b')||btn).textContent.trim();
      var size=form.querySelector('input[name=size]');
      if(size){var map={'1:1':'1024x1024','2:3':'1024x1536','3:2':'1536x1024','16:9':'1920x1088'}; if(map[label])size.value=map[label];}
      var duration=form.querySelector('input[name=duration]');
      if(!duration&&form.getAttribute('data-ai-form')==='video'){duration=document.createElement('input');duration.type='hidden';duration.name='duration';form.appendChild(duration);}
      if(duration){var m=label.match(/-(\d+)s/); if(m)duration.value=m[1];}
      var ratio=form.querySelector('input[name=ratio]');
      if(!ratio&&form.getAttribute('data-ai-form')==='video'){ratio=document.createElement('input');ratio.type='hidden';ratio.name='ratio';form.appendChild(ratio);}
      if(ratio){ratio.value=label.indexOf('720p')>=0?'16:9':'16:9';}
    }
  });
  var currentPrompt='',currentType='text',busy=false,pollTimer=null,activeTaskKey='lingchuangx_active_media_task';
  function postJson(url,payload,retry){retry=retry||0;return fetch(url,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify(payload)}).then(function(r){return r.json().then(function(x){var apiError=x&&x.error;var mediaError=x&&x.code&&x.code!==200;var failed=!r.ok||x.ok===false||mediaError||apiError;if(failed){var raw=(x&&x.msg)||(x&&x.message)||(apiError&&apiError.message)||'请求失败';var e=new Error(/concurrency|concurrent|rate[ ._-]*limit|too many|429/i.test(raw)?'当前已有生成任务正在处理中，请等待任务完成后再生成。':raw);e.status=r.status;e.retryable=false;throw e;}return x&&Object.prototype.hasOwnProperty.call(x,'data')?x.data:x;});}).catch(function(e){var isMedia=/media-generate/.test(url);if(e.retryable&&!isMedia&&retry<1){var wait=(retry+1)*12000;flash('当前服务繁忙，稍后自动重试…');return new Promise(function(resolve){setTimeout(function(){resolve(postJson(url,payload,retry+1));},wait);});}throw e;});}
  function flash(text,bad){var n=document.createElement('div');n.className='flash'+(bad?' error':'');n.textContent=text;document.body.appendChild(n);setTimeout(function(){n.remove();},6500);}
  function release(button,label){busy=false;if(pollTimer){clearTimeout(pollTimer);pollTimer=null;}if(button){button.disabled=false;button.textContent=label||'✦ 生成';}}
  function showResult(text,image){var box=document.querySelector('.ai-result');if(!box)return;var empty=document.querySelector('.canvas-empty');if(empty)empty.style.display='none';box.innerHTML='';if(image){var mediaType=box.getAttribute('data-media-result');if(mediaType==='audio'){var audio=document.createElement('audio');audio.src=image;audio.controls=true;audio.autoplay=true;box.appendChild(audio);}else if(mediaType==='video'){var video=document.createElement('video');video.src=image;video.controls=true;video.autoplay=true;video.loop=true;video.playsInline=true;box.appendChild(video);}else{var img=document.createElement('img');img.src=image;img.alt='灵境 AI 生成结果';box.appendChild(img);}currentResult=image;}else{var pre=document.createElement('div');pre.className='result-text';pre.textContent=text;box.appendChild(pre);currentResult=text;}var save=document.createElement('button');save.className='save-result';save.type='button';save.textContent='保存作品';save.onclick=function(){save.disabled=true;postJson('/api.php?action=save',{type:currentType,prompt:currentPrompt,result_url:currentType==='image'||currentType==='video'?currentResult:''}).then(function(){flash('作品已保存');save.textContent='已保存';}).catch(function(e){save.disabled=false;flash(e.message,true);});};box.appendChild(save);box.classList.add('visible');}
  function applyPromptFilter(f){document.querySelectorAll('.prompt-card').forEach(function(card){var ok=f==='all'||((f==='image'||f==='video')&&card.getAttribute('data-prompt-type')===f);card.classList.toggle('is-hidden',!ok);});}
  var initialPromptType=new URLSearchParams(window.location.search).get('type');
  document.querySelectorAll('.prompt-filter').forEach(function(b){b.addEventListener('click',function(){var f=b.getAttribute('data-filter');document.querySelectorAll('.prompt-filter').forEach(function(x){x.classList.remove('active');});b.classList.add('active');applyPromptFilter(f);});if(initialPromptType&&b.getAttribute('data-filter')===initialPromptType){document.querySelectorAll('.prompt-filter').forEach(function(x){x.classList.remove('active');});b.classList.add('active');applyPromptFilter(initialPromptType);}});
  if(initialPromptType&&!document.querySelector('.prompt-filter.active[data-filter="'+initialPromptType+'"]')) applyPromptFilter(initialPromptType);
  document.querySelectorAll('.use-prompt').forEach(function(b){b.addEventListener('click',function(e){e.stopPropagation();var card=b.closest('.prompt-card,.clone-work-card'),text=(b.getAttribute('data-prompt')||(card&&card.getAttribute('data-prompt'))||''),type=(b.getAttribute('data-type')||(card&&card.getAttribute('data-type'))||'image');window.location.href='/?page='+(type==='video'?'video':'image')+'&prompt='+encodeURIComponent(text);});});
  document.querySelectorAll('.prompt-card').forEach(function(card){card.addEventListener('click',function(e){if(e.target.closest('button'))return;var text=card.getAttribute('data-prompt')||'',type=card.getAttribute('data-type')||'image';window.location.href='/?page='+(type==='video'?'video':'image')+'&prompt='+encodeURIComponent(text);});});
  document.querySelectorAll('.copy-prompt').forEach(function(b){b.addEventListener('click',function(e){e.stopPropagation();var card=b.closest('.prompt-card,.clone-work-card'),text=b.getAttribute('data-prompt')||(card&&card.getAttribute('data-prompt'))||'';if(navigator.clipboard)navigator.clipboard.writeText(text);else{var t=document.createElement('textarea');t.value=text;document.body.appendChild(t);t.select();document.execCommand('copy');t.remove();}var orig=b.textContent;b.textContent='已复制';setTimeout(function(){b.textContent=orig;},1800);});});
  var homeModal=document.getElementById('home-prompt-modal'),homePrompt=null;
  function homeCards(){return Array.prototype.slice.call(document.querySelectorAll('[data-home-prompt-card]'));}
  function filterHomePrompts(){var active=document.querySelector('[data-home-filter].active'),type=active?active.getAttribute('data-home-filter'):'all',kw=(document.querySelector('[data-home-prompt-search]')||{}).value||'';kw=kw.trim().toLowerCase();homeCards().forEach(function(card){var text=((card.getAttribute('data-prompt-title')||'')+' '+(card.getAttribute('data-prompt')||'')).toLowerCase(),okType=type==='all'||card.getAttribute('data-prompt-type')===type,okKw=!kw||text.indexOf(kw)>=0;card.classList.toggle('is-hidden',!(okType&&okKw));});}
  if(homeModal){var hi=homeModal.querySelector('.home-prompt-img'),thumb=homeModal.querySelector('.home-prompt-thumb'),ht=homeModal.querySelector('h3'),hp=homeModal.querySelector('p'),source=homeModal.querySelector('.home-prompt-source'),badge=homeModal.querySelector('.home-prompt-badge'),use=homeModal.querySelector('.home-prompt-use'),closeBtn=homeModal.querySelector('.home-prompt-close-btn');function closeHomePrompt(){homeModal.hidden=true;document.body.classList.remove('home-prompt-open');}function openHomePrompt(card){homePrompt={title:card.getAttribute('data-prompt-title')||'',text:card.getAttribute('data-prompt')||'',type:card.getAttribute('data-prompt-type')||'image',cover:card.getAttribute('data-prompt-cover')||''};hi.src=homePrompt.cover;hi.alt=homePrompt.title;if(thumb){thumb.src=homePrompt.cover;thumb.alt=homePrompt.title;}ht.textContent=homePrompt.title;hp.textContent=homePrompt.text;if(source)source.textContent='来自 '+(homePrompt.type==='video'?'视频':'Seedream5.0')+' 应用';if(badge)badge.textContent=homePrompt.type==='video'?'video':'Seedream5.0';homeModal.hidden=false;document.body.classList.add('home-prompt-open');}homeCards().forEach(function(card){card.addEventListener('click',function(e){if(e.target.closest('button'))return;openHomePrompt(card);});card.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();openHomePrompt(card);}});});homeModal.querySelector('.home-prompt-close').addEventListener('click',closeHomePrompt);if(closeBtn)closeBtn.addEventListener('click',closeHomePrompt);homeModal.addEventListener('click',function(e){if(e.target===homeModal)closeHomePrompt();});document.addEventListener('keydown',function(e){if(e.key==='Escape'&&!homeModal.hidden)closeHomePrompt();});if(use)use.addEventListener('click',function(){if(!homePrompt)return;var page=homePrompt.type==='video'?'video':'image';window.location.href='/?page='+page+'&prompt='+encodeURIComponent(homePrompt.text);});document.querySelectorAll('[data-home-filter]').forEach(function(btn){btn.addEventListener('click',function(e){if(e) e.preventDefault();document.querySelectorAll('[data-home-filter]').forEach(function(x){x.classList.remove('active');});btn.classList.add('active');filterHomePrompts();});});var hs=document.querySelector('[data-home-prompt-search]');if(hs)hs.addEventListener('input',filterHomePrompts);}
  function findMediaUrl(value){if(!value)return '';if(typeof value==='string'&&/^https?:\/\//i.test(value))return value;if(typeof value!=='object')return '';var keys=['result_url','video_url','image_url','url','download_url','output_url'];for(var k=0;k<keys.length;k++)if(typeof value[keys[k]]==='string'&&/^https?:\/\//i.test(value[keys[k]]))return value[keys[k]];for(var p in value){var found=findMediaUrl(value[p]);if(found)return found;}return '';}
  var queryPrompt=new URLSearchParams(window.location.search).get('prompt');if(queryPrompt){var target=document.querySelector('form[data-ai-form] [name=prompt]');if(target){target.value=queryPrompt;target.focus();if(window.history&&window.history.replaceState){var cleanUrl=window.location.pathname+'?page='+(new URLSearchParams(window.location.search).get('page')||'image');window.history.replaceState({},document.title,cleanUrl);}setTimeout(function(){var form=target.closest('form');if(form&&!busy){var submit=form.querySelector('button[type=submit]');if(submit)submit.click();}},350);}} else {var savedTask=null;try{savedTask=JSON.parse(localStorage.getItem(activeTaskKey)||'null');}catch(e){}if(savedTask&&savedTask.id&&document.querySelector('form[data-ai-form="'+savedTask.type+'"]')){currentPrompt=savedTask.prompt||'';currentType=savedTask.type;busy=true;var savedButton=document.querySelector('form[data-ai-form="'+savedTask.type+'"] button[type=submit]');if(savedButton){savedButton.disabled=true;savedButton.textContent='继续生成中…';}poll(savedTask.id,savedButton,savedTask.type);}}
  document.querySelectorAll('.quick-prompts button').forEach(function(b){b.addEventListener('click',function(){var t=document.querySelector('.image-control-panel [name=prompt]');if(t)t.value=b.textContent;});});
  var forms=document.querySelectorAll('form[data-ai-form]');for(var i=0;i<forms.length;i++)forms[i].addEventListener('submit',function(e){e.preventDefault();if(busy){flash('已有任务正在处理中，请等待完成',true);return;}var f=e.currentTarget,button=f.querySelector('button[type=submit]'),prompt=(f.querySelector('[name=prompt]')||{}).value||'',type=f.getAttribute('data-ai-form');if(!prompt.trim()){flash('请先描述你的灵感',true);return;}busy=true;currentPrompt=prompt;currentType=type;button.disabled=true;button.textContent='正在连接灵境 AI…';var model=f.querySelector('[name=model]')?f.querySelector('[name=model]').value:'';var modelOption=f.querySelector('[name=model] option:checked');var format=modelOption?modelOption.getAttribute('data-format'):'openai';var payload=type==='video'?{model:model||'doubao-seedance-2-5-260628',prompt:prompt,params:{duration:Number((f.querySelector('[name=duration]')||{}).value||5),aspect_ratio:(f.querySelector('[name=ratio]')||{}).value||'16:9',resolution:'720p'}}:type==='image'?{model:model||'tt-image-2',prompt:prompt,params:{prompt:prompt,size:(f.querySelector('[name=size]')||{}).value||'1024x1024',quality:(f.querySelector('[name=quality]')||{}).value||'medium'}}:{model:model||'tt-5.6-luna',messages:[{role:'user',content:prompt}],stream:false,_format:format};var endpoint=type==='image'||type==='video'||type==='audio'?'/api.php?action=media-generate':'/api.php?action=chat';postJson(endpoint,payload).then(function(data){if(type==='image'||type==='video'||type==='audio'){var task=(data&&data.task_id)||(data&&data.data&&data.data.task_id)||(data&&data.task&&data.task.id);var direct=findMediaUrl(data);if(direct){localStorage.removeItem(activeTaskKey);showResult('',direct);release(button,type==='video'?'✦ 开始生成':'✦ 生成');return;}if(!task)throw new Error('未获得生成任务 ID');try{localStorage.setItem(activeTaskKey,JSON.stringify({id:task,type:type,prompt:prompt}));}catch(e){}showResult('任务已创建，正在生成…');poll(task,button,type);}else{var content=data.choices&&data.choices[0]&&data.choices[0].message&&data.choices[0].message.content;if(!content&&data.candidates)content=data.candidates[0]&&data.candidates[0].content&&data.candidates[0].content.parts&&data.candidates[0].content.parts[0]&&data.candidates[0].content.parts[0].text;showResult(content||JSON.stringify(data));release(button,'✦ 发送');}}).catch(function(err){flash(err.retryable?'灵境 AI 当前仍繁忙，请稍后再试':err.message,true);release(button,type==='chat'?'↑　发送':'✦ 生成');});});
  function poll(id,button,type){fetch('/api.php?action=task-status&task_id='+encodeURIComponent(id),{headers:{Accept:'application/json'}}).then(function(r){return r.json();}).then(function(x){if(x.ok===false)throw new Error(x.message||x.error||'任务查询失败');var d=x.data||x;var state=d.state||d.status||d.task_status||'';var media=findMediaUrl(d);if(media){localStorage.removeItem(activeTaskKey);showResult('',media);release(button,type==='video'?'✦ 开始生成':'✦ 生成');return;}if(d.is_final===true||/^(success|completed|succeeded|failed|error)$/i.test(state)){localStorage.removeItem(activeTaskKey);if(/failed|error/i.test(state))flash(d.error||d.message||'生成失败',true);else flash('任务已完成，但接口未返回媒体地址',true);release(button,type==='video'?'✦ 开始生成':'✦ 生成');return;}button.textContent='生成中 '+(d.progress||d.percent||0)+'%';pollTimer=setTimeout(function(){poll(id,button,type);},5000);}).catch(function(e){flash(e.message,true);release(button,type==='video'?'✦ 开始生成':'✦ 生成');});}

  var stageTabs=document.querySelectorAll('.agent-v2-stage-tab');
  stageTabs.forEach(function(tab){
    tab.addEventListener('click',function(){
      stageTabs.forEach(function(t){t.classList.remove('active');});
      tab.classList.add('active');
      var num=document.getElementById('stageCardNum');
      var icon=document.getElementById('stageCardIcon');
      var title=document.getElementById('stageCardTitle');
      var desc=document.getElementById('stageCardDesc');
      var tags=document.getElementById('stageCardTags');
      var idx=Number(tab.getAttribute('data-stage-index')||0);
      if(num)num.textContent=tab.getAttribute('data-step')||'01';
      if(icon)icon.textContent=['📋','📄','🖥','📱'][idx]||'✦';
      if(title)title.textContent=tab.getAttribute('data-title')||'';
      if(desc)desc.textContent=tab.getAttribute('data-desc')||'';
      if(tags){
        var list=(tab.getAttribute('data-tags')||'').split(',').filter(Boolean);
        tags.innerHTML=list.map(function(tg){return '<span>'+tg+'</span>';}).join('');
      }
      var inds=document.querySelectorAll('.stage-card-indicators .ind');
      inds.forEach(function(ind,i){ind.classList.toggle('active',i===idx);});
    });
  });

  var modeCapsules=document.querySelectorAll('.mode-capsule');
  modeCapsules.forEach(function(cap){
    cap.addEventListener('click',function(){
      modeCapsules.forEach(function(c){c.classList.remove('active');});
      cap.classList.add('active');
    });
  });

  /* == 漫剧智能体交互 == */
  var comicTabs=document.querySelectorAll('.comic-tab-item');
  comicTabs.forEach(function(tab){
    tab.addEventListener('click',function(){
      comicTabs.forEach(function(t){t.classList.remove('active');});
      tab.classList.add('active');
      var num=document.getElementById('comicCardNum');
      var icon=document.getElementById('comicCardIcon');
      var title=document.getElementById('comicCardTitle');
      var desc=document.getElementById('comicCardDesc');
      if(num)num.textContent=tab.getAttribute('data-step')||'01';
      if(icon)icon.textContent=tab.getAttribute('data-icon')||'☀️';
      if(title)title.textContent=tab.getAttribute('data-title')||'';
      if(desc)desc.textContent=tab.getAttribute('data-desc')||'';
    });
  });

  /* == 提示词工具条增强：爆款复刻 / AI润色 / 示例 / 风格参考 == */
  document.addEventListener('click',function(e){
    var btn=e.target.closest&&e.target.closest('.prompt-toolbar button');
    if(!btn)return;
    var form=btn.closest('form');
    if(!form)return;
    var ta=form.querySelector('textarea[name=prompt]');
    if(!ta)return;
    var txt=btn.textContent.trim();
    if(txt==='AI润色'||txt==='爆款复刻'||txt==='风格参考'||txt==='示例'){
      var current=ta.value.trim();
      if(!current){
        var examples={
          'image':'超高清电影感特写，国风仙侠少女，身穿流云白丝霓裳，手持青色玉笛，仙雾缭绕，柔和逆光，虚化背景，8k resolution, cinematic lighting',
          'video':'一架无人机穿过壮丽的赛博朋克未来城市天际线，霓虹灯光倒映在雨夜街道，4K电影级运镜，平滑推进，HDR',
          'audio':'史诗级电影预告片配乐，激昂交响乐与重低音鼓点融合，由舒缓逐渐推向高潮，震撼宏大氛围',
          'text':'请以爆款短视频文案风格，围绕“普通人如何用AI开启副业”撰写一段极具吸引力的黄金前3秒钩子与3个实用核心步骤。'
        };
        var type=form.getAttribute('data-ai-form')||'text';
        ta.value=examples[type]||examples['text'];
        flash('已自动填充爆款示范提示词');
        ta.focus();
      }else{
        btn.disabled=true;
        var oldText=btn.textContent;
        btn.textContent='正在润色…';
        postJson('/api.php?action=chat',{
          model:'tt-5.6-luna',
          messages:[
            {role:'system',content:'你是一名专业的提示词与文案优化专家。请直接对用户的输入进行润色与专业度增强（增加光影、构图、细节或紧凑度），直接返回润色后的单段内容，不要添加任何多余的开场白或解释。'},
            {role:'user',content:current}
          ],
          stream:false
        }).then(function(d){
          var res=d&&d.choices&&d.choices[0]&&d.choices[0].message&&d.choices[0].message.content;
          if(!res&&d&&d.candidates)res=d.candidates[0]&&d.candidates[0].content&&d.candidates[0].content.parts&&d.candidates[0].content.parts[0]&&d.candidates[0].content.parts[0].text;
          if(res){ta.value=res.trim();flash('已完成智能润色');}
        }).catch(function(err){
          flash('润色失败：'+err.message,true);
        }).finally(function(){
          btn.disabled=false;
          btn.textContent=oldText;
        });
      }
    }
  });

  /* == 电商智能体多选/单选标签与图片上传 == */
  document.querySelectorAll('[data-multi] .ecom-chip-btn').forEach(function(btn){
    btn.addEventListener('click',function(e){
      e.preventDefault();
      var isReq = btn.querySelector('.req');
      if(isReq) return; // 必选项不允许取消
      btn.classList.toggle('active');
      var val = btn.getAttribute('data-val') || btn.textContent.trim();
      if(btn.classList.contains('active')){
        if(!btn.textContent.startsWith('✓')) btn.textContent = '✓ ' + val;
      } else {
        btn.textContent = val.replace(/^✓\s*/, '');
      }
    });
  });

  document.querySelectorAll('[data-single] .ecom-chip-btn').forEach(function(btn){
    btn.addEventListener('click',function(e){
      e.preventDefault();
      var p = btn.closest('[data-single]');
      if(!p) return;
      p.querySelectorAll('.ecom-chip-btn').forEach(function(b){ b.classList.remove('active'); });
      btn.classList.add('active');
    });
  });

  var ecomImgInput = document.getElementById('ecomImageUpload');
  var ecomPreview = document.getElementById('ecomUploadPreview');
  var uploadedEcomImages = [];
  if(ecomImgInput && ecomPreview){
    ecomImgInput.addEventListener('change', function(){
      if(!ecomImgInput.files || !ecomImgInput.files.length) return;
      Array.prototype.forEach.call(ecomImgInput.files, function(file){
        if(!/^image\//.test(file.type)) return;
        var reader = new FileReader();
        reader.onload = function(evt){
          uploadedEcomImages.push(evt.target.result);
          ecomPreview.style.display = 'flex';
          ecomPreview.innerHTML = '<img src="' + evt.target.result + '" alt="商品图">';
        };
        reader.readAsDataURL(file);
      });
      flash('已添加商品参考图');
    });
  }

  var agentForm=document.querySelector('[data-agent-form]');
  if(agentForm){
    agentForm.addEventListener('submit',function(e){
      e.preventDefault();
      var btn=agentForm.querySelector('button[type=submit]'),
          box=document.querySelector('[data-agent-result]'),
          prompt=(agentForm.querySelector('[name=prompt]')||{}).value||'',
          model=(agentForm.querySelector('[name=model]')||{}).value||'';
      
      if(!prompt.trim()){
        flash('请先输入商品或创作需求',true);
        return;
      }
      btn.disabled=true;
      btn.textContent='正在生成方案…';
      box.classList.add('visible');
      box.innerHTML='<div class="result-text">⚡ AI 专家团队正在协同拆解、规划并生成方案，请稍候…</div>';
      
      var curAgent=new URLSearchParams(location.search).get('agent');
      if(!curAgent){
        if(document.querySelector('.ecom-agent-stage.is-catering')) curAgent='catering-visual';
        else if(document.querySelector('.ecom-agent-stage.is-image')) curAgent='ecommerce-image';
        else if(document.querySelector('.ecom-agent-stage.is-video')) curAgent='ecommerce-video';
        else if(document.querySelector('.comic-agent-workspace')) curAgent='comic-drama';
        else curAgent='catering-visual';
      }

      var platforms = [];
      document.querySelectorAll('[data-multi="platform"] .ecom-chip-btn.active').forEach(function(b){
        platforms.push(b.getAttribute('data-val') || b.textContent.replace(/^✓\s*/,''));
      });
      var deliverables = [];
      document.querySelectorAll('[data-multi="deliverable"] .ecom-chip-btn.active').forEach(function(b){
        deliverables.push(b.getAttribute('data-val') || b.textContent.replace(/^✓\s*/,''));
      });
      var channels = [];
      document.querySelectorAll('[data-multi="channel"] .ecom-chip-btn.active').forEach(function(b){
        channels.push(b.getAttribute('data-val') || b.textContent.replace(/^✓\s*/,''));
      });
      var categories = [];
      document.querySelectorAll('[data-multi="category"] .ecom-chip-btn.active').forEach(function(b){
        categories.push(b.getAttribute('data-val') || b.textContent.replace(/^✓\s*/,''));
      });
      var focuses = [];
      document.querySelectorAll('[data-multi="focus"] .ecom-chip-btn.active').forEach(function(b){
        focuses.push(b.getAttribute('data-val') || b.textContent.replace(/^✓\s*/,''));
      });
      var styles = [];
      document.querySelectorAll('[data-multi="style"] .ecom-chip-btn.active').forEach(function(b){
        styles.push(b.getAttribute('data-val') || b.textContent.replace(/^✓\s*/,''));
      });
      var customChannel = (document.querySelector('input[name="custom_channel"]') || {}).value || '';
      if(customChannel.trim()) channels.push(customChannel.trim());
      var customCat = (document.querySelector('input[name="custom_category"]') || {}).value || '';
      if(customCat.trim()) categories.push(customCat.trim());
      var customFocus = (document.querySelector('input[name="custom_focus"]') || {}).value || '';
      if(customFocus.trim()) focuses.push(customFocus.trim());
      var customStyle = (document.querySelector('input[name="custom_style"]') || {}).value || '';
      if(customStyle.trim()) styles.push(customStyle.trim());

      var aspect_ratio = (document.querySelector('[data-single="aspect_ratio"] .ecom-chip-btn.active')||{}).dataset?.val || '';
      var style = (document.querySelector('[data-single="style"] .ecom-chip-btn.active')||{}).dataset?.val || '';

      fetch('/api.php?action=agent-chat',{
        method:'POST',
        headers:{'Content-Type':'application/json','Accept':'application/json'},
        body:JSON.stringify({
          agent_key:curAgent,
          prompt:prompt,
          model:model,
          platforms:platforms,
          deliverables:deliverables,
          channels:channels,
          categories:categories,
          focuses:focuses,
          styles:styles,
          aspect_ratio:aspect_ratio,
          style:style,
          images:uploadedEcomImages,
          _format:(agentForm.querySelector('[name=model] option:checked')||{}).dataset?.format||'openai'
        })
      }).then(function(r){return r.json();}).then(function(x){
        if(!x.ok)throw new Error(x.message||'生成失败');
        var d=x.data||x,c=d.choices&&d.choices[0]&&d.choices[0].message&&d.choices[0].message.content;
        if(!c&&d.candidates)c=d.candidates[0]&&d.candidates[0].content&&d.candidates[0].content.parts&&d.candidates[0].content.parts[0]&&d.candidates[0].content.parts[0].text;
        box.innerHTML='<div class="result-text agent-markdown"></div>';
        box.querySelector('.result-text').textContent=c||JSON.stringify(d);
        btn.textContent='已生成方案';
      }).catch(function(err){
        box.innerHTML='';
        flash(err.message||'生成失败',true);
        btn.textContent='↑';
      }).finally(function(){
        btn.disabled=false;
      });
    });
  }

  /* == 更多工具页：搜索 + 分类过滤 == */
  var toolInput=document.querySelector('.tool-search');
  var toolCats=document.querySelectorAll('.tool-categories [data-cat]');
  var toolCards=document.querySelectorAll('.showcase-card');
  function filterTools(){var kw=(toolInput?toolInput.value.trim().toLowerCase():'');var cat=document.querySelector('.tool-categories [data-cat].active');var c=cat?cat.getAttribute('data-cat'):'all';toolCards.forEach(function(card){var okCat=c==='all'||card.getAttribute('data-cat')===c;var txt=((card.getAttribute('data-name')||'')+' '+(card.getAttribute('data-desc')||'')).toLowerCase();var okKw=!kw||txt.indexOf(kw)>=0;card.style.display=(okCat&&okKw)?'':'none';});}
  if(toolInput)toolInput.addEventListener('input',filterTools);
  toolCats.forEach(function(el){el.addEventListener('click',function(){toolCats.forEach(function(x){x.classList.remove('active');});el.classList.add('active');filterTools();});});

  /* == 专业绘画页：模式切换 == */
  var studioModes=document.querySelectorAll('.studio-switch [data-mode]');
  studioModes.forEach(function(el){el.addEventListener('click',function(){studioModes.forEach(function(x){x.classList.remove('active');});el.classList.add('active');var ref=document.getElementById('reference-upload');if(ref){ref.style.display=(el.getAttribute('data-mode')==='txt2img')?'none':'flex';}});});
  var refUpload=document.getElementById('reference-upload');
  if(refUpload){var thumbs=document.getElementById('ref-thumbs');var refInput=refUpload.querySelector('input[type=file]');var refCount=0;refInput.addEventListener('change',function(){Array.prototype.forEach.call(refInput.files,function(f){if(!/^image\//.test(f.type))return;if(refCount>=14)return;var reader=new FileReader();reader.onload=function(e){var wrap=document.createElement('span');wrap.className='thumb-item';var img=document.createElement('img');img.src=e.target.result;var rm=document.createElement('button');rm.type='button';rm.textContent='×';rm.addEventListener('click',function(ev){ev.preventDefault();ev.stopPropagation();wrap.parentNode.removeChild(wrap);refCount--;});wrap.appendChild(img);wrap.appendChild(rm);thumbs.appendChild(wrap);refCount++;};reader.readAsDataURL(f);});refInput.value='';});}

  /* == 智能助手页：新建对话 + 建议填词 == */
  var newChat=document.querySelector('.new-chat');
  if(newChat){newChat.addEventListener('click',function(){var cf=document.querySelector('.chat-composer textarea[name=prompt]');if(cf){cf.value='';cf.focus();}var box=document.querySelector('.chat-workspace .ai-result');if(box){box.innerHTML='';box.classList.remove('visible');}var es=document.querySelector('.canvas-empty');if(es)es.style.display='';});}
  document.querySelectorAll('.suggestion-chip').forEach(function(b){b.addEventListener('click',function(){var cf=document.querySelector('.chat-composer textarea[name=prompt]');if(cf){cf.value=b.textContent;cf.focus();}});});
  // 移除历史绑定以防双重触发冲突
  var themeToggle=document.querySelector('[data-theme-toggle]');
  if(themeToggle){
    try{
      if(localStorage.getItem('lingchuang_theme')==='light'){
        document.body.classList.add('clone-light');
        document.documentElement.classList.add('clone-light');
        themeToggle.textContent = '☾';
        themeToggle.setAttribute('title','切换为暗黑模式');
      } else {
        document.body.classList.remove('clone-light');
        document.documentElement.classList.remove('clone-light');
        themeToggle.textContent = '☼';
        themeToggle.setAttribute('title','切换为明亮模式');
      }
    }catch(e){}
  }

  /* == 全局顶栏搜索框与快捷键 (Ctrl+K) == */
  document.addEventListener('keydown', function(e){
    if((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')){
      var searchInput = document.getElementById('topbarSearchInput');
      if(searchInput){
        e.preventDefault();
        searchInput.focus();
        searchInput.select();
      }
    }
  });

  // 检查首页加载时 URL 是否携带 ?q= 参数，自动同步过滤
  var urlParams = new URLSearchParams(window.location.search);
  var initialSearchQuery = urlParams.get('q');
  if(initialSearchQuery){
    var topSearch = document.getElementById('topbarSearchInput');
    if(topSearch) topSearch.value = initialSearchQuery;
    var homeSearch = document.querySelector('[data-home-prompt-search]');
    if(homeSearch){
      homeSearch.value = initialSearchQuery;
      if(typeof filterHomePrompts === 'function') filterHomePrompts();
    }
  }

  // 顶栏搜索输入即时联动（若当前在首页，直接即时过滤卡片）
  var topbarInput = document.getElementById('topbarSearchInput');
  if(topbarInput){
    topbarInput.addEventListener('input', function(){
      var homeSearch = document.querySelector('[data-home-prompt-search]');
      if(homeSearch){
        homeSearch.value = topbarInput.value;
        if(typeof filterHomePrompts === 'function') filterHomePrompts();
      }
    });
  }

})();

/* == 全局顶栏搜索处理与快速填词 == */
function handleTopbarSearch(e, form){
  if(e && e.preventDefault) e.preventDefault();
  var input = document.getElementById('topbarSearchInput') || (form ? form.querySelector('input[name="q"]') : null);
  var kw = input ? input.value.trim() : '';
  
  var homeSearch = document.querySelector('[data-home-prompt-search]');
  if(homeSearch){
    // 在首页：联动首页提示词搜索框并过滤，平滑滚动至广场
    homeSearch.value = kw;
    var event = new Event('input', { bubbles: true });
    homeSearch.dispatchEvent(event);
    var promptHead = document.querySelector('.clone-prompt-head') || document.querySelector('.clone-prompt-tabs');
    if(promptHead){
      promptHead.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    return false;
  } else {
    // 非首页：跳转至首页带搜索参数
    window.location.href = '/?q=' + encodeURIComponent(kw);
    return false;
  }
}

function quickFillSearch(kw){
  var topSearch = document.getElementById('topbarSearchInput');
  if(topSearch) topSearch.value = kw;
  var form = document.getElementById('topbarSearchForm');
  handleTopbarSearch(null, form);
}

function openSystemAnnouncementModalDirect(){
  var existing = document.getElementById('systemAnnouncementModal');
  if(existing){
    existing.style.display = 'block';
    existing.removeAttribute('hidden');
    return;
  }
  fetch('/api.php?action=latest-announcement-modal')
    .then(function(r){ return r.json(); })
    .then(function(d){
      if(d && d.ok && d.data && d.data.html){
        var wrapper = document.createElement('div');
        wrapper.innerHTML = d.data.html;
        var modal = wrapper.firstElementChild;
        if(modal){
          document.body.appendChild(modal);
          modal.style.display = 'block';
        }
      }
    })
    .catch(function(){});
}

