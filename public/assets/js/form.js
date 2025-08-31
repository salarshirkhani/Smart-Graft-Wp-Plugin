/* public/assets/js/form.js */
var $ = jQuery.noConflict();
$(function () {
  console.log("📌 form.js loaded!", window.shec_ajax);
  if (!window.shec_ajax || !shec_ajax.nonce) {
    console.error('[SHEC] shec_ajax.nonce is missing! Nonce check will fail.');
  }


  /* =========================
   * Utils
   * ========================= */
  const LS = {
    get(key, fallback=null){ try{ const v = localStorage.getItem(key); return v===null? fallback : v; }catch(e){ return fallback; } },
    set(key, val){ try{ localStorage.setItem(key, val); }catch(e){} },
    del(key){ try{ localStorage.removeItem(key); }catch(e){} },
    clear(){ try{ localStorage.clear(); }catch(e){} },
  };

  const Utils = {
    wpUnwrap(res){ return (res && res.data) ? res.data : res; },
    normalizeMobile(m){
      m = (''+m).trim().replace(/\s+/g,'').replace(/^\+98/,'0').replace(/^0098/,'0').replace(/^98/,'0').replace(/\D+/g,'');
      if (/^9\d{9}$/.test(m)) m = '0' + m;
      return m;
    },
    errorScroll(selector, msg, focusSelector){
      const $el = $(selector);
      $el.addClass('error shake');
      if ($el.length && $el.get(0) && $el.get(0).scrollIntoView) {
        $el.get(0).scrollIntoView({ behavior: 'smooth', block: 'center' });
      } else if ($el.length) {
        $('html, body').animate({ scrollTop: $el.offset().top - 120 }, 300);
      }
      if (msg) toastr.error(msg);
      if (focusSelector) $(focusSelector).trigger('focus');
      setTimeout(()=> $el.removeClass('shake'), 400);
    },
  };
  window.Utils = Utils;


  /* =========================
   * API
   * ========================= */
  const API = {
    post(action, data, dataType='json'){
      return $.post(
        shec_ajax.url,
        Object.assign({_nonce:shec_ajax.nonce, _wpnonce:shec_ajax.nonce, action}, data||{}),
        null,
        dataType
      );
    },
    step1(payload){ return this.post('shec_step1', payload); },
    step2(payload){ return this.post('shec_step2', payload); },
    step3Upload(formData){
      return $.ajax({ url:shec_ajax.url, type:'POST', data:formData, processData:false, contentType:false, dataType:'json' });
    },
    step4(payload){ return this.post('shec_step4', payload); },
    step5(payload){ return this.post('shec_step5', payload); },
    aiQuestions(user_id){
      return $.ajax({
        url: shec_ajax.url,
        type: 'POST',
        dataType: 'json',
        timeout: 25000,
        data: { action: 'shec_ai_questions', user_id, _nonce:shec_ajax.nonce, _wpnonce:shec_ajax.nonce }
      });
    },
    finalize(user_id, answers){ return this.post('shec_finalize', {user_id, answers}); },
  };

  /* =========================
   * Domain helpers (HE_*)
   * ========================= */
  const HE_GRAFT_TABLE = {
    male:   {1:8000, 2:10000, 3:12000, 4:14000, 5:16000, 6:18000},
    female: {1:4000, 2: 8000, 3:10000, 4:12000, 5:14000, 6:16000}
  };
  const HE_WARNINGS = {
    diabetes:    "اگر دچار دیابت هستید، کاشت مو تنها در صورتی ممکن است که بیماری تحت کنترل کامل باشد. دیابت می‌تواند بر روند بهبودی تأثیر بگذارد و خطر عفونت پس از جراحی را افزایش دهد. قبل از کاشت، تأییدیه کتبی پزشک معالج لازم است.",
    coagulation: "کاشت مو در بیماران مبتلا به اختلالات انعقاد خون ممکن است دشوار باشد و خونریزی را در طول عمل افزایش دهد و بر بقای گرافت‌ها تأثیر بگذارد. تأییدیه کتبی پزشک معالج لازم است.",
    cardiac:     "کاشت مو با وجود بیماری‌های قلبی/عروقی تنها در صورتی ممکن است که بیماری تحت کنترل کامل باشد و ممکن است ریسک داروی بی‌حسی و نقاهت بالاتر برود. تأییدیه کتبی پزشک لازم است.",
    thyroid:     "کاشت مو برای اختلالات تیروئید در صورت متعادل بودن سطح هورمون‌ها امکان‌پذیر است؛ حالت کنترل‌نشده می‌تواند بر رشد مو و بقای گرافت‌ها اثر بگذارد. تأییدیه کتبی پزشک لازم است.",
    immunodef:   "برای نقص سیستم ایمنی (مانند برخی موارد HIV یا شیمی‌درمانی) معمولاً کاشت توصیه نمی‌شود؛ بهبودی کندتر و عوارض بیشتر است. تصمیم نهایی با ارزیابی تخصصی و تأیید پزشک است.",
    autoimmune:  "در بیماری‌های خودایمنی، بسته به نوع و فعالیت بیماری، کاشت ممکن است دشوار یا غیرقابل انجام باشد و روی پذیرش گرافت‌ها اثر بگذارد. ارزیابی تخصصی و تأیید پزشک لازم است."
  };
  const HE_SCALP_WARNINGS = {
    active_infection: "اگر عفونت فعال پوست سر دارید نمی‌توان بلافاصله برای کاشت مو اقدام کرد...",
    psoriasis:        "اگر پسوریازیس شما فعال است—به‌خصوص اگر نواحی وسیعی از پوست سر درگیر باشد—ابتدا باید آن را کنترل/درمان کنید...",
    fungal_derm:      "قبل از در نظر گرفتن کاشت مو، باید درماتیت سبورئیک/عفونت قارچی کنترل شود...",
    folliculitis:     "اگر دچار فولیکولیت هستید، قبل از کاشت مو باید آن را درمان کنیم...",
    areata:           "کاشت مو در مرحلهٔ فعال ریزش سکه‌ای (آلوپسی آره‌آتا) امکان‌پذیر نیست...",
    scarring_alo:     "آلوپسی به همراه اسکار می‌تواند میزان موفقیت پیوند را تا حدود 70٪ کاهش دهد...",
    scar:             "جای زخم (اسکار) روی پوست سر می‌تواند موفقیت کاشت مو را تا حدود ۷۰٪ کاهش دهد..."
  };
  function heMapLabelToWarningKey(label){
    if(!label) return null;
    const t = String(label);
    if (t.includes('دیابت')) return 'diabetes';
    if (t.includes('انعقاد')) return 'coagulation';
    if (t.includes('قلب'))    return 'cardiac';
    if (t.includes('تیروئید'))return 'thyroid';
    if (t.match(/ایمنی|HIV|شیمی/)) return 'immunodef';
    if (t.match(/خودایمنی|لوپوس|آلوپسی/)) return 'autoimmune';
    return null;
  }
  function heMapScalpLabelToKey(label){
    if (!label) return null;
    var t = String(label);
    if (t.indexOf('عفونت فعال پوست سر') > -1)               return 'active_infection';
    if (t.indexOf('پسوریازیس') > -1)                         return 'psoriasis';
    if (t.indexOf('عفونت قارچی') > -1 || t.indexOf('سبورئیک') > -1) return 'fungal_derm';
    if (t.indexOf('فولیکولیت') > -1)                         return 'folliculitis';
    if (t.indexOf('ریزش سکه‌ای') > -1 || t.indexOf('آلوپسی آره‌آتا') > -1) return 'areata';
    if (t.indexOf('آلوپسی به همراه اسکار') > -1)            return 'scarring_alo';
    if (t.indexOf('جای زخم') > -1 || t.indexOf('اسکار') > -1) return 'scar';
    if (t.indexOf('هیچکدام') > -1)                           return null;
    return null;
  }
  function heNormalizeGender(g){
    var t = (g || '').toString().toLowerCase();
    if (t === 'female' || t === 'زن')  return 'female';
    return 'male';
  }
  function heRenderAllWarnings(opt){
    opt = opt || {};
    var systemicLabels = Array.isArray(opt.systemicLabels) ? opt.systemicLabels : [];
    var scalpLabels    = Array.isArray(opt.scalpLabels)    ? opt.scalpLabels    : [];
    var anchorSel      = opt.anchor || '#he-medical-warning-wrap';

    var host = document.querySelector(anchorSel);
    if (!host) return;
    host.innerHTML = '';

    var sysKeys = Array.from(new Set(systemicLabels.map(heMapLabelToWarningKey).filter(Boolean)));
    sysKeys.forEach(function(k){
      var div = document.createElement('div');
      div.className = 'he-warn-card';
      div.innerHTML = '<p>' + (HE_WARNINGS[k] || '') + '</p>';
      host.appendChild(div);
    });

    var scalpKeys = Array.from(new Set(scalpLabels.map(heMapScalpLabelToKey).filter(Boolean)));
    scalpKeys.forEach(function(k){
      var div = document.createElement('div');
      div.className = 'he-warn-card';
      div.innerHTML = '<p>' + (HE_SCALP_WARNINGS[k] || '') + '</p>';
      host.appendChild(div);
    });

    host.style.display = (host.children.length ? '' : 'none');
  }
  function heStageFromPatternValue(patternValue){
    if (!patternValue) return null;
    var m = String(patternValue).toLowerCase().match(/pattern[-_ ]?(\d+)/);
    if (!m || !m[1]) return null;
    var s = parseInt(m[1], 10);
    if (isNaN(s)) return null;
    if (s < 1) s = 1; if (s > 6) s = 6;
    return s;
  }
  function heGraftsFromStage(gender, stage){
    if (!stage) return null;
    var key = heNormalizeGender(gender);
    var tbl = HE_GRAFT_TABLE[key] || {};
    return tbl[stage] || null;
  }
  function heGetSelectedPatternFromDOM(){
    var el = document.querySelector('input[name="loss_pattern"]:checked');
    return el ? el.value : null;
  }

  /* =========================
   * UI helpers
   * ========================= */
  const UI = {
    setProgress(step){
      $('#step-current').text(step);
      $('#progress-bar').css('width', Math.floor((step/6)*100) + '%');
    },
    goToStep(step){
      $('#step2-loader').hide();
      $('.step').addClass('d-none').removeClass('active');
      $('#step-'+step).removeClass('d-none').addClass('active');
      UI.setProgress(step);
      LS.set('currentStep', step);

      if (step === 2) UI.loadStep2Images();
      if (step === 3) {
        const gender = LS.get('gender','male');
        UI.renderUploadBoxes(gender);
        UI.loadUploadedThumbs();
        UI.updateUploadHints(gender);
      }
    },
    loadStep2Images(){
      const gender = LS.get('gender','male');
      const $step2 = $('#step-2');
      $('#step2-loader').show();
      $step2.show().addClass('active');
      $step2.find('.pattern-option img').each((idx, img) => {
        const i = idx+1;
        img.src = (gender === 'female')
          ? `${shec_ajax.img_path}w${i}.png`
          : `${shec_ajax.img_path}ol${i}.png`;
        $(img).css('filter','grayscale(100%)');
      });
      const imgs = $step2.find('.pattern-option img').toArray();
      Promise.all(imgs.map(img => new Promise(resolve => img.complete ? resolve() : img.onload = resolve)))
        .then(()=> $('#step2-loader').hide());
    },
    renderUploadBoxes(gender){
      const uploadPositions = {
        male:   ['روبرو','پشت سر','فرق سر','کنار سر'],
        female: ['روبرو','بالای سر','فرق سر']
      };
      const container = $('#upload-zones').empty();
      uploadPositions[gender].forEach((label, index) => {
        const isLastFemale = (gender === 'female' && index === uploadPositions[gender].length - 1);
        const colClass = isLastFemale ? 'col-12' : 'col-12 col-lg-6';
        const isFront = (label === 'روبرو');
        container.append(`
          <div class="${colClass}">
            <div class="upload-wrap${isFront ? ' shec-upload-front' : ''}" ${isFront ? 'data-pos="front"' : ''}>
              <label class="upload-box" data-index="${index}" data-position="${label}">
                <span class="d-block fw-bold mb-2">${label}</span>
                <input type="file" name="pic${index+1}" accept="image/*">
                <div class="progress d-none"><div class="progress-bar" style="width:0%;"></div></div>
                <img src="" class="thumbnail d-none">
              </label>
              <div class="upload-overlay">
                <button type="button" class="remove-btn" aria-label="حذف تصویر">🗑</button>
              </div>
            </div>
          </div>
        `);
      });
    },
    loadUploadedThumbs(){
      const uploads = JSON.parse(LS.get('uploadedPics','{}'));
      for (const name in uploads) {
        const url = uploads[name];
        const $box  = $(`.upload-box input[name="${name}"]`).closest('.upload-box');
        const $wrap = $box.closest('.upload-wrap');
        if ($wrap.length) {
          $wrap.addClass('upload-success');
          $box.find('.thumbnail').attr('src', url).removeClass('d-none');

          if ($wrap.is('[data-pos="front"]')) {
            try { localStorage.setItem('shec_front', url); } catch(e){}
          }
        }
      }
    },
    updateUploadHints(gender){
      const maleImgs = [
        'https://fakhraei.clinic/wp-content/uploads/2025/07/New-Project-80.webp',
        'https://fakhraei.clinic/wp-content/uploads/2025/07/2-pic-1.webp',
        'https://fakhraei.clinic/wp-content/uploads/2025/07/3-pic-1.webp',
        'https://fakhraei.clinic/wp-content/uploads/2025/07/1-pic-1.webp'
      ];
      const femaleImgs = [
        'https://fakhraei.clinic/wp-content/uploads/2025/07/top_f.webp',
        'https://fakhraei.clinic/wp-content/uploads/2025/07/back_f.webp',
        'https://fakhraei.clinic/wp-content/uploads/2025/07/front_f.webp'
      ];
      const imgs = (gender==='female') ? femaleImgs : maleImgs;
      const $angleImages = $('.angles .angle img');
      $angleImages.each(function (i) {
        if (imgs[i]) { $(this).attr('src', imgs[i]).parent().show(); }
        else { $(this).parent().hide(); }
      });
      $('.angles').css({ display:'flex', justifyContent: (gender==='female') ? 'center' : 'space-between', gap:'12px' });
    },
    ensureStep5Containers(){
      if (!$('#step5-content').length) return;
      if (!$('#ai-questions-box').length){
        $('#step5-content').prepend(`
          <div id="ai-questions-box" class="mb-4">
            <p class="d-block mb-2 fw-bold">لطفاً به چند سؤال کوتاه پاسخ دهید:</p>
            <div id="ai-questions-list"></div>
          </div>
        `);
      }
    },

    /* ---------- Loader v2 (مرحله ۵) ---------- */
    __aiTimers: [],
    step5ShowLoader() {
      const $loader  = $('#step5-loader');
      const $content = $('#step5-content');
      let   $text    = $('#ai-loader-text');
      $('#form-step-5').addClass('ohide').removeClass('oshow');
      $('#ai-questions-box').addClass('ohide').removeClass('oshow');
      $content.hide();
      $loader.show();

      if (!$text.length) {
        $text = $('<div id="ai-loader-text" class="ai-loader-text"></div>');
        $loader.append($text);
      }

      const messages = [
        'در حال بررسی جنسیت و نوع ریزش مو',
        'در حال بررسی دغدغه و نگرانی شما',
        'در حال بررسی بیماری ها و داروهای نوشته شده',
        'در حال ایجاد 4 سوال اختصاصی برای شما توسط هوش مصنوعی فخرایی',
        'در حال آماده سازی نهایی ...'
      ];

      (UI.__aiTimers || []).forEach(clearTimeout);
      UI.__aiTimers = [];

      $text.stop(true, true).css('opacity', 1).text(messages[0]);

      function schedule(msg, delay){
        const id = setTimeout(function(){
          $text.fadeOut(250, function(){
            $text.text(msg).fadeIn(250);
          });
        }, delay);
        UI.__aiTimers.push(id);
      }
      schedule(messages[1], 2000);
      schedule(messages[2], 4000);
      schedule(messages[3], 6000);
      schedule(messages[4], 8000);
    },
    step5HideLoader(){
      (UI.__aiTimers || []).forEach(clearTimeout);
      UI.__aiTimers = [];
      $('#step5-loader').fadeOut(300);
      $('#step5-content').fadeIn(200);
      $('#form-step-5').addClass('oshow').removeClass('ohide');
      $('#ai-questions-box').addClass('oshow').removeClass('ohide');
    },
    waitForAiOrTimeout(promise, minMs = 10000){
      const dfd = jQuery.Deferred();
      const t0 = Date.now();
      const p = promise && typeof promise.then === 'function'
        ? promise
        : jQuery.Deferred().resolve().promise();

      p.always(function(){
        const spent  = Date.now() - t0;
        const remain = Math.max(0, minMs - spent);
        const id = setTimeout(function(){ dfd.resolve(); }, remain);
        UI.__aiTimers.push(id);
      });
      return dfd.promise();
    },

    // ===== Final-step loader =====
    finalTimers: [],
    ensureLottieLoaded(){
      if (window.customElements && customElements.get('dotlottie-wc')) return;
      if (document.getElementById('dotlottie-wc-loader')) return;
      const s = document.createElement('script');
      s.type = 'module';
      s.id = 'dotlottie-wc-loader';
      s.src = 'https://unpkg.com/@lottiefiles/dotlottie-wc@latest/dist/dotlottie-wc.js';
      document.head.appendChild(s);
    },
    ensureFinalLoaderDom(){
      if (!$('#final-step-loader').length) {
        UI.ensureLottieLoaded();
        const tpl = `
          <div id="final-step-loader" class="ai-loader-overlay bgl" style="display:none;">
            <div class="ai-loader-box">
              <dotlottie-wc
                src="https://lottie.host/f6ee527c-625e-421f-b114-b95e703a33c5/UHdu4rKs9b.lottie"
                speed="1"
                autoplay
                loop
              ></dotlottie-wc>
              <div id="final-loader-text" class="ai-loader-text" style="text-align:center;justify-content:center;"></div>
            </div>
          </div>`;
        const $host = $('#step-5').length ? $('#step-5') : $('body');
        $host.append(tpl);
      }
    },
    finalStepShowLoader(){
      UI.ensureFinalLoaderDom();
      const $overlay = $('#final-step-loader');
      const $text    = $('#final-loader-text');
      $('#form-step-5').addClass('ohide').removeClass('oshow');
      $('#ai-questions-box').addClass('ohide').removeClass('oshow');
      UI.finalTimers.forEach(id => clearTimeout(id));
      UI.finalTimers = [];

      const msgs = [
        'در حال بررسی پاسخ سوالات شما ...',
        'در حال بررسی نوع ریزش موی شما ...',
        'در حال یافتن بهترین روش کاشت موی مناسب شما ...',
        'در حال محاسبه تعداد تقریبی تار موی لازم برای کاشت  ...',
        'در حال آماده‌سازی نهایی ...'
      ];

      $text.stop(true, true).css('opacity', 1).text(msgs[0]);
      $overlay.fadeIn(150);

      function schedule(msg, delay){
        const id = setTimeout(function(){
          $text.fadeOut(200, function(){
            $text.text(msg).fadeIn(200);
          });
        }, delay);
        UI.finalTimers.push(id);
      }
      schedule(msgs[1], 2000);
      schedule(msgs[2], 4000);
      schedule(msgs[3], 6000);
      schedule(msgs[4], 8000);
    },
    finalStepHideLoader(){
      UI.finalTimers.forEach(id => clearTimeout(id));
      UI.finalTimers = [];
      $('#final-step-loader').fadeOut(250);
    },

    renderQuestions(qs){
      const $list = $('#ai-questions-list').empty();
      (qs||[]).forEach((q,i)=>{
        const idx = i+1;
        $list.append(`
          <div class="followup-item mb-3" data-idx="${idx}">
            <div class="d-block mb-2 fw-bold">${q}</div>
            <div class="toggle-group">
              <label class="toggle-option">
                <input type="radio" name="followup_${idx}" value="yes" hidden>
                <span>بله</span>
              </label>
              <label class="toggle-option">
                <input type="radio" name="followup_${idx}" value="no" hidden>
                <span>خیر</span>
              </label>
            </div>
          </div>
        `);
      });
    },
    renderQuestionsFallback(){
      UI.renderQuestions([
        'آیا در خانواده‌تان سابقهٔ ریزش مو وجود دارد؟',
        'آیا طی ۱۲ ماه گذشته شدت ریزش موی شما بیشتر شده است؟',
        'آیا در حال حاضر سیگار یا قلیان مصرف می‌کنید؟',
        'آیا خواب و استرس شما در ماه‌های اخیر بدتر شده است؟'
      ]);
    },
  };

  /* =========================
   * AI loader (Step 5)
   * ========================= */
  let __aiQOnce = false;

  function loadAiQuestions(force=false){
    const uid = parseInt(LS.get('userId') || 0, 10);

    UI.ensureStep5Containers();
    $('#ai-questions-box').removeClass('d-none').show();
    $('#ai-questions-list').empty();

    if (!uid) {
      UI.renderQuestionsFallback();
      return jQuery.Deferred().resolve().promise();
    }
    if (!force && __aiQOnce) {
      return jQuery.Deferred().resolve().promise();
    }

    const req = API.aiQuestions(uid);

    const pendingTimer = setTimeout(function(){
      try{
        if (req && typeof req.state === 'function' && req.state() === 'pending'){
          toastr.info('نتیجه AI دیرتر میاد، لطفاً کمی صبر کنید...');
        }
      }catch(e){}
    }, 7000);

    return req
      .done(function(res){
        try {
          if (!res || res.success !== true) {
            console.warn('[AIQ] success=false یا payload نامعتبر:', res);
            UI.renderQuestionsFallback();
            toastr.info('سؤالات هوشمند موقتاً موجود نیست؛ سؤالات عمومی نمایش داده شد.');
            return;
          }
          const payload = res.data || {};
          if (payload.debug) console.log('[AIQ] debug:', payload.debug);

          const qs = Array.isArray(payload.questions) ? payload.questions.filter(Boolean) : [];
          if (qs.length === 4) {
            UI.renderQuestions(qs);
          } else {
            console.warn('[AIQ] شکل questions درست نیست:', payload);
            UI.renderQuestionsFallback();
            toastr.info('خروجی سرویس نامعتبر بود؛ سؤالات عمومی نمایش داده شد.');
          }
        } catch(e){
          console.error('[AIQ] done() exception:', e);
          UI.renderQuestionsFallback();
          toastr.info('مشکل در پردازش پاسخ؛ سؤالات عمومی نمایش داده شد.');
        }
      })
      .fail(function(jqXHR, textStatus, errorThrown){
        const snippet = (jqXHR && jqXHR.responseText) ? jqXHR.responseText.slice(0, 300) : '';
        console.error('[AIQ] AJAX FAIL', { status: jqXHR.status, textStatus, errorThrown, responseSnippet: snippet });

        if (jqXHR.status === 403 && /nonce/i.test(snippet)) {
          toastr.error('نشست امنیتی منقضی شد. صفحه را نوسازی کنید.');
        } else if (jqXHR.status === 400) {
          toastr.warning('درخواست نامعتبر بود (400). لطفاً دوباره امتحان کنید.');
        } else {
          toastr.warning('اتصال به سرویس سؤالات برقرار نشد؛ سؤالات عمومی نمایش داده شد.');
        }
        UI.renderQuestionsFallback();
      })
      .always(function(){
        clearTimeout(pendingTimer);
        __aiQOnce = true;
      });
  }

  /* =========================
   * RENDER FINAL (shared for step6 & token page)
   * ========================= */
// اجازه‌ی پرش به استپ ۶ فقط اگر لازم بود

window.SHEC_renderFinal = function(fin){
  // 1) نرمال‌سازی ورودی
  var payload = (fin && fin.user) ? fin : ((window.Utils && Utils.wpUnwrap) ? Utils.wpUnwrap(fin) : fin);
  if (!payload || !payload.user) {
    jQuery('#ai-result-box').html('<div style="padding:24px">دادهٔ نتیجه پیدا نشد.</div>');
    return;
  }

  // ---------- Helpers (فال‌بک‌های سبک اگر در این صفحه تعریف نشده باشند) ----------
  function heNormalizeGender(g){ g=(g||'').toString().toLowerCase(); return (g==='female' || g==='زن')?'female':'male'; }
  function heStageFromPatternValueLocal(v){
    if (!v) return null;
    var m = String(v).toLowerCase().match(/pattern[-_ ]?(\d+)/);
    if (!m || !m[1]) return null;
    var s = parseInt(m[1],10); if (isNaN(s)) return null;
    if (s<1) s=1; if (s>6) s=6; return s;
  }
  var HE_GRAFT_TABLE = {
    male:   {1:8000, 2:10000, 3:12000, 4:14000, 5:16000, 6:18000},
    female: {1:4000, 2: 8000, 3:10000, 4:12000, 5:14000, 6:16000}
  };
  function heGraftsFromStageLocal(gender, stage){
    if (!stage) return null;
    var key = heNormalizeGender(gender);
    var tbl = HE_GRAFT_TABLE[key] || {};
    return tbl[stage] || null;
  }

  // ---------- Parse user ----------
  var d = payload;
  var u       = d.user || {};
  var first   = (u.contact && u.contact.first_name ? u.contact.first_name : '').trim();
  var last    = (u.contact && u.contact.last_name  ? u.contact.last_name  : '').trim();
  var full    = (first || last) ? (first + (first&&last?' ':'') + last) : '—';
  var ageVal  = u.age || (u.contact ? u.contact.age : '') || '—';
  var pattern = u.loss_pattern || u.pattern || (window.heGetSelectedPatternFromDOM ? heGetSelectedPatternFromDOM() : null);
  var gender  = (u.gender || (u.contact ? u.contact.gender : '') || 'male').toLowerCase();
  var concern = (u.medical && u.medical.concern) ? u.medical.concern : '—';

  // Stage & graft (با گارد)
  var stage   = (window.heStageFromPatternValue ? heStageFromPatternValue(pattern) : heStageFromPatternValueLocal(pattern));
  var graftN  = (window.heGraftsFromStage ? heGraftsFromStage(gender, stage) : heGraftsFromStageLocal(gender, stage));
  var graftByTable = graftN ? Number(graftN).toLocaleString('fa-IR') : '—';

  // Medical
  var med = u.medical || {};
  var splitFa = function(str){ return (!str||typeof str!=='string')? [] : str.split(/[,،;\n]/g).map(function(s){return s.trim();}).filter(Boolean); };
  var joinFa  = function(arr){ return (Array.isArray(arr)&&arr.length)? arr.join('، ') : '—'; };
  var drugsLabels = (med.has_meds === 'yes')
    ? (splitFa(med.meds_list).length ? splitFa(med.meds_list) : ['مصرف دارو'])
    : ['عدم مصرف دارو'];
  var dermLabels  = splitFa(med.scalp_conditions);
  var sysLabels   = splitFa(med.other_conditions);
  var showMedical = (drugsLabels.length || dermLabels.length || sysLabels.length) > 0;
  var warnHostId  = 'he-medical-warning-wrap-' + Date.now();

  // ---------- AI payload ----------
  var ai = {};
  try {
    ai = (typeof d.ai_result === 'string') ? JSON.parse(d.ai_result || '{}') : (d.ai_result || {});
  } catch(_){ ai = {}; }

  // ثابت‌های نمایشی
  var methodTxt = 'FIT';
  var duration  = 'دو جلسه هشت ساعته';
  var logoUrl   = 'https://fakhraei.clinic/wp-content/uploads/2024/02/Group-1560-300x300.png.webp';

  // توضیح الگو (Norwood/Ludwig)
  function mapFemaleLudwig(st){ if(!st) return null; if (st<=2) return 'Ludwig I'; if (st<=4) return 'Ludwig II'; return 'Ludwig III'; }
  var fallbackPatternExplain = (function(){
    if (!stage) return {label:'—', what_it_is:'', why_happens:'', note:'', fit_ok:true};
    if (gender==='female') {
      var label = mapFemaleLudwig(stage) || 'Ludwig';
      var what  = 'الگوی کم‌پشتی منتشر در ناحیه مرکزی سر که با پیشرفت، وسعت بیشتری می‌گیرد.';
      var why   = 'اغلب مرتبط با عوامل هورمونی و ژنتیکی؛ استرس و سبک زندگی هم اثر دارند.';
      var note  = (label==='Ludwig I') ? 'در این مرحله معمولاً کاشت لازم نیست و درمان نگه‌دارنده پیشنهاد می‌شود.' : '';
      return {label:label, what_it_is:what, why_happens:why, note:note, fit_ok:true};
    } else {
      var labelM = 'Norwood ' + stage;
      var whatM  = (stage>=5)
        ? 'درگیری جلوی سر و ورتکس با باریک‌شدن پل میانی؛ برای بازگردانی خط مو و تراکم، برداشت گسترده لازم می‌شود.'
        : 'عقب‌نشینی خط جلویی یا کم‌پشتی موضعی که با پیشرفت، نواحی بیشتری را درگیر می‌کند.';
      var whyM   = 'معمولاً ژنتیکی و مرتبط با حساسیت فولیکول‌ها به آندروژن‌ها؛ استرس و سبک زندگی می‌تواند شدت را تغییر دهد.';
      var noteM  = (stage===1) ? 'در این مرحله معمولاً کاشت لازم نیست و درمان نگه‌دارنده پیشنهاد می‌شود.' : '';
      return {label:labelM, what_it_is:whatM, why_happens:whyM, note:noteM, fit_ok:true};
    }
  })();
  var patExplain = Object.assign({}, fallbackPatternExplain, (ai.pattern_explain||{}));

  // دغدغه‌ی همدلانه (ترجیح با AI؛ در نبود → fallback)
  var concernBox = (typeof ai.concern_box==='string' && ai.concern_box.trim())
    ? ai.concern_box.trim()
    : (function(){
        var c = concern.toString();
        if (/هزینه|قیمت/.test(c)) return 'می‌دانیم هزینه برایتان مهم است. در کلینیک فخرائی برآورد شفاف و منطقی ارائه می‌شود و کیفیت، اولویت ماست. می‌توانید پیش از تصمیم، مشاوره مالی دریافت کنید تا با خیال راحت برنامه‌ریزی کنید.';
        if (/درد/.test(c))       return 'نگران درد نباشید؛ در طول عمل از بی‌حسی موضعی استفاده می‌شود و وضعیت شما مدام پایش می‌گردد تا تجربه‌ای بسیار قابل‌تحمل داشته باشید.';
        if (/نقاهت/.test(c))    return 'دوران نقاهت کوتاه و قابل‌مدیریت است. با راهنمای مرحله‌به‌مرحله و مراقبت‌های ساده، به‌خوبی از این مرحله عبور می‌کنید.';
        if (/طول|زمان/.test(c)) return 'رشد مو مرحله‌ای است؛ از ماه‌های اول تغییرات آغاز می‌شود و در ادامه تراکم طبیعی‌تر می‌شود. مسیر روشن است و کنار شما هستیم.';
        return 'نگرانی شما کاملاً قابل درک است. با پاسخ شفاف و مسیر درمان مشخص، همراه شما هستیم تا با آرامش تصمیم بگیرید.';
      })();

  // سؤالات پیگیری
  var followupsData = (((u.ai||{}).followups)||{});
  var answersArr = Array.isArray(followupsData.answers) ? followupsData.answers : [];
  var qaItems = Array.isArray(followupsData.qa) && followupsData.qa.length
    ? followupsData.qa
    : (Array.isArray(followupsData.questions)
        ? followupsData.questions.map(function(q,i){ return {q:q, a:(answersArr[i]||'')}; })
        : []);
  var faYesNo = function(v){ return (/^(yes|true|بله)$/i.test(String(v))) ? 'بله' : ((/^(no|false|خیر)$/i.test(String(v))) ? 'خیر' : (String(v||'').trim()||'—')); };

  var aiFollowups = Array.isArray(ai.followups) ? ai.followups : [];
  var norm = function(s){ return String(s||'').replace(/\s+/g,'').replace(/[‌\u200c]/g,'').trim(); };
  function getTipFor(qText, idx){
    var byIndex = aiFollowups[idx] && aiFollowups[idx].tip || aiFollowups[idx] && aiFollowups[idx].ai_tip;
    if (byIndex && String(byIndex).trim()) return String(byIndex).trim();
    var t = norm(qText);
    var hit = aiFollowups.find(function(x){ return norm(x.q||'') === t; });
    var tip = hit && (hit.tip || hit.ai_tip);
    return (tip && String(tip).trim()) ? String(tip).trim() : '';
  }

  var followupSummary = (typeof ai.followup_summary==='string' && ai.followup_summary.trim())
    ? ai.followup_summary.trim()
    : (function(){
        var get = function(re){
          var it = qaItems.find(function(x){ return re.test(String(x.q||'')); });
          return it ? faYesNo(it.a) : '';
        };
        var smoking = get(/سیگار|قلیان/i);
        var stress  = get(/استرس/i);
        var sleep   = get(/خواب/i);
        var worse   = get(/شدت|بدتر|افزایش/i);
        var infect  = get(/عفونت|التهاب|پوست سر/i);

        var s = [];
        s.push('🤖 با توجه به پاسخ‌هایی که دادید، یک برنامه‌ی عملی برای آمادگی قبل از کاشت پیشنهاد می‌کنیم. ');
        if (smoking==='بله') s.push('بهتر است از ۱۰ روز پیش از کاشت تا یک هفته بعد، دخانیات را کنار بگذارید تا خون‌رسانی بهتر و بقای گرافت‌ها بیشتر شود. ');
        if (sleep==='خیر')  s.push('کم‌خوابی را با تنظیم ساعت خواب و کاهش کافئین عصر اصلاح کنید؛ خواب کافی التهاب را پایین می‌آورد و ترمیم را سریع‌تر می‌کند. ');
        if (stress==='بله') s.push('چند دقیقه تمرین تنفس یا پیاده‌روی روزانه اضافه کنید؛ همین عادت‌ها اثر ملموسی بر ریزش ناشی از استرس دارد. ');
        if (worse==='بله')  s.push('با توجه به افزایش ریزش، بهتر است تصمیم درمانی را به تعویق نیندازید و درمان نگه‌دارنده را هم‌زمان آغاز کنید. ');
        if (infect==='بله') s.push('در صورت التهاب/عفونت پوست سر، ابتدا درمان کامل انجام می‌شود تا شانس بقای گرافت‌ها بالا برود. ');
        s.push('در مجموع مسیر درمان شما روشن است و تیم فخرائی کنار شماست.');
        var txt = s.join('');
        return (txt.length < 220) ? (txt + ' رعایت بهداشت پوست سر و پیروی از دستورهای مراقبتی، کیفیت ناحیه گیرنده را بهتر می‌کند و روند ترمیم را سرعت می‌دهد.') : txt;
      })();

  var extraText = (ai.extra_analysis && ai.extra_analysis.trim())
    ? ai.extra_analysis.trim()
    : ((ai.analysis && ai.analysis.trim()) ? ai.analysis.trim() : '');

  var qaHtml = (function(){
    return `
      <div class="ai-section-title" style="margin-top:18px;">نتیجه سوالات ایجاد شده هوشمند</div>
      <ol class="ai-qa">
        ${qaItems.map(function(item, i){
          var tip = getTipFor(item.q, i);
          var ans = faYesNo(item.a);
          var ansClass = /بله/.test(ans) ? 'ans-yes' : (/خیر/.test(ans) ? 'ans-no' : '');
          return `
            <li class="ai-qa-item">
              <div class="ai-qa-head">
                <div class="num">${i+1}</div>
                <div class="ai-qa-q">❓ ${(item.q||'').trim()}</div>
              </div>
              <div class="ai-qa-a">
                <span class="label">پاسخ:</span>
                <span class="ans-pill ${ansClass}">${ans}</span>
              </div>
              ${tip ? `
                <div class="ai-qa-tip" style="display:flex;gap:6px;align-items:flex-start;text-align:justify">
                  <span class="bot">🤖</span>
                  <div class="text">${tip}</div>
                </div>` : ``}
            </li>`;
        }).join('')}
      </ol>
    `;
  })();

  // ---------- Render ----------
  jQuery('#ai-result-box').html(`
    <div class="ai-result-container">
      <div class="ai-hero">
        <div class="ai-logo">${logoUrl ? `<img src="${logoUrl}" alt="Fakhraei">` : ''}</div>
        <div class="ai-title">برنامه اختصاصی کاشت موی شما آماده است!</div>
        <div class="ai-check">✓</div>
        <div class="ai-sub">از اعتماد شما سپاسگزاریم</div>
      </div>

      <div class="ai-section-title">اطلاعات شخصی شما</div>
      <div class="ai-chip-row">
        <div class="ai-chip">
          <span class="ai-chip-label">نام و نام خانوادگی</span>
          <div class="ai-chip-value">${full}</div>
        </div>
        <div class="ai-chip">
          <span class="ai-chip-label">الگوی ریزش مو</span>
          <div class="ai-chip-value">${pattern ?? '—'}</div>
        </div>
        <div class="ai-chip">
          <span class="ai-chip-label">بازه سنی</span>
          <div class="ai-chip-value">${ageVal}</div>
        </div>
      </div>

      <div class="ai-note" style="text-align:justify">
        🤖 <strong>${patExplain.label || '—'}</strong> — ${patExplain.what_it_is || ''}<br>
        ${patExplain.why_happens ? (patExplain.why_happens + '<br>') : ''}
        ${patExplain.fit_ok ? 'این الگو با روش FIT/FUE در کلینیک فخرائی قابل درمان است.' : ''} ${patExplain.note ? ('<br>'+patExplain.note) : ''}
      </div>

      <div class="ai-section-title" style="margin-top:18px;">مهم‌ترین دغدغهٔ شما</div>
      <div class="ai-note" style="margin-bottom:8px">${concern}</div>
      <div class="ai-note" style="text-align:justify">🤖 ${concernBox}</div>

      <hr class="ai-divider"/>

      <div class="ai-stats">
        <div class="ai-stat">
          <div class="ai-stat-label">مدت زمان تقریبی</div>
          <div class="ai-stat-value">${duration}</div>
        </div>
        <div class="ai-stat ai-stat--accent">
          <div class="ai-stat-label">تکنیک پیشنهادی</div>
          <div class="ai-stat-value">${methodTxt}</div>
        </div>
        <div class="ai-stat">
          <div class="ai-stat-label">تعداد تار موی پیشنهادی</div>
          <div class="ai-stat-value">${graftByTable}</div>
        </div>
      </div>

      ${showMedical ? `
        <div class="ai-section-title" style="margin-top:22px;">وضعیت پزشکی ثبت‌شده</div>
        <div class="ai-stats">
          <div class="ai-stat">
            <div class="ai-stat-label">دارو مورد استفاده</div>
            <div class="ai-stat-value">${joinFa(drugsLabels)}</div>
          </div>
          <div class="ai-stat">
            <div class="ai-stat-label">بیماری پوستی</div>
            <div class="ai-stat-value">${joinFa(dermLabels)}</div>
          </div>
          <div class="ai-stat">
            <div class="ai-stat-label">بیماری زمینه‌ای</div>
            <div class="ai-stat-value">${joinFa(sysLabels)}</div>
          </div>
        </div>
        <div id="${warnHostId}"></div>
      ` : ''}

      ${qaHtml}

      <div class="ai-section-title" style="margin-top:18px;">جمع‌بندی پاسخ‌ها و توصیه‌های اختصاصی</div>
      <div class="ai-note" style="text-align:justify">🤖 ${followupSummary}</div>

      ${extraText ? `
        <div class="ai-section-title" style="margin-top:18px;">توضیحات اضافه</div>
        <div class="ai-note" style="text-align:justify">🤖 ${extraText}</div>
      ` : ''}
    </div>
  `);

  // هشدارهای پزشکی: فقط اگر تابع موجود بود
  if (typeof window.heRenderAllWarnings === 'function') {
    heRenderAllWarnings({
      systemicLabels: sysLabels,
      scalpLabels: dermLabels,
      anchor: '#' + warnHostId
    });
  }

  // هرگز خودکار به Step 6 نرو مگر اجازه داده باشی
  if (window.SHEC_ALLOW_STEP6 && typeof window.UI !== 'undefined' && UI.goToStep && document.querySelector('#step-6')) {
    UI.goToStep(6);
  }
};

  /* =========================
   * Steps bindings
   * ========================= */

  // شروع از مرحله ذخیره‌شده
  UI.goToStep(parseInt(LS.get('currentStep')) || 0);

  // Step 0 → 1
  $(document).on('click', '#agree-btn', function(e){
    e.preventDefault();
    e.stopPropagation();
    UI.goToStep(1);
  });
  // Step 1
  $('#form-step-1').on('submit', function(e){
    e.preventDefault();

    const gender = $('input[name="gender"]:checked').val();
    const age    = $('input[name="age"]:checked').val();
    const confidence = $('select[name="confidence"]').val();
    let mobile   = Utils.normalizeMobile($('input[name="mobile"]').val());
    const validAges = ['18-23','24-29','30-35','36-43','44-56','+56'];

    if (!gender) return toastr.error('لطفاً جنسیت را انتخاب کنید');
    if (!age || !validAges.includes(age)) return toastr.error('لطفاً بازه سنی را درست انتخاب کنید');
    if (!confidence) return toastr.error('لطفاً میزان اعتماد به نفس را انتخاب کنید');
    if (!/^09\d{9}$/.test(mobile)) return toastr.error('شماره موبایل معتبر وارد کنید (مثلاً 09xxxxxxxxx)');

    const payload = { user_id: LS.get('userId') || 0, gender, age, mobile, confidence };
    const $btn = $(this).find('button[type="submit"]').prop('disabled', true);

    API.step1(payload).done(function(res){
      const d = Utils.wpUnwrap(res);
      if (res && res.success) {
        LS.set('userId', d.user_id);
        LS.set('gender', gender);
        LS.set('currentStep', 2);
        UI.goToStep(2);
      } else {
        toastr.error((d && d.message) || 'خطا در ارسال اطلاعات');
      }
    }).fail(()=> toastr.error('خطا در ارتباط با سرور'))
      .always(()=> $btn.prop('disabled', false));
  });

  // Step 2: loss pattern
  $('#form-step-2').on('click', '.pattern-option', function(){
    $('.pattern-option').removeClass('selected');
    $(this).addClass('selected');
    $('input[name="loss_pattern"]', this).prop('checked', true);
    $('#form-step-2 .pattern-option img').css('filter','grayscale(100%)');
    $(this).find('img').css('filter','none');
  });

  $('#form-step-2').on('submit', function(e){
    e.preventDefault();
    const loss_pattern = $('input[name="loss_pattern"]:checked').val();
    const uid = LS.get('userId');
    if (!loss_pattern) return toastr.error('لطفاً الگوی ریزش مو را انتخاب کنید');

    API.step2({user_id: uid, loss_pattern})
      .done(res=>{
        if (res.success) UI.goToStep(3);
        else toastr.error(res.message || 'خطا در مرحله ۲');
      })
      .fail(()=> toastr.error('خطای سرور در مرحله ۲'));
  });

  // Step 3 uploads
  const HE_MAX_UPLOAD_MB = (window.shec_ajax && Number(shec_ajax.max_upload_mb)) || 5;
  function heFormatBytes(bytes){
    if (!bytes || bytes <= 0) return '0 B';
    const units = ['B','KB','MB','GB'];
    const i = Math.floor(Math.log(bytes)/Math.log(1024));
    return (bytes/Math.pow(1024,i)).toFixed( (i===0)?0:1 ) + ' ' + units[i];
  }

  $('#form-step-3').on('submit', function(e){
    e.preventDefault();
    let frontUrl = null; try { frontUrl = localStorage.getItem('shec_front'); } catch(e){}
    if (!frontUrl) {
      if (window.toastr) toastr.error('لطفاً «تصویر روبه‌رو» را بارگذاری کنید.');
      const $front = $('.upload-wrap.shec-upload-front');
      $front.addClass('is-required-error');
      if (!$front.find('.err-msg').length) $front.find('.upload-box').append('<div class="err-msg">تصویر روبه‌رو الزامی است</div>');
      $('html,body').animate({scrollTop: $front.offset().top - 120}, 400);
      return;
    }
    UI.goToStep(4);
  });




  // Upload handler
  $(document).on('click', '.upload-overlay .remove-btn', function(e){
  e.preventDefault(); e.stopPropagation();
  const $wrap  = $(this).closest('.upload-wrap');
  const $box   = $wrap.find('.upload-box');
  const $img   = $box.find('.thumbnail');
  const $input = $box.find('input[type="file"]')[0];
  const name   = $input && $input.name;

  try { const up = JSON.parse(LS.get('uploadedPics','{}')); if (name && up[name]) { delete up[name]; LS.set('uploadedPics', JSON.stringify(up)); } } catch(_){}
  if ($wrap.is('[data-pos="front"]')) { try { localStorage.removeItem('shec_front'); } catch(_){} }

  $img.addClass('d-none').attr('src','');
  if ($input) $input.value = '';
  $wrap.removeClass('upload-success'); // پیام خطا اگر لازم شد بعداً در submit اضافه می‌شود
});




  $(document).on('change', '.upload-box input[type="file"]', function(){
    const fileInput = this;
    const file = fileInput.files && fileInput.files[0];
    if (!file) return;

    if (!file.type || !file.type.startsWith('image/')) {
      toastr.error('فقط فایل تصویری قابل آپلود است.');
      fileInput.value = '';
      return;
    }
    const maxBytes = HE_MAX_UPLOAD_MB * 1024 * 1024;
    if (file.size > maxBytes) {
      toastr.error(`حجم فایل ${heFormatBytes(file.size)} است. حداکثر مجاز ${HE_MAX_UPLOAD_MB} مگابایت می‌باشد.`);
      const $box = $(fileInput).closest('.upload-box');
      $box.removeClass('upload-success');
      $box.find('.thumbnail').addClass('d-none').attr('src','');
      $box.find('.progress').addClass('d-none').find('.progress-bar').css('width','0%');
      fileInput.value = '';
      return;
    }

    const $box = $(fileInput).closest('.upload-box');
    const $progress = $box.find('.progress');
    const $bar = $progress.find('.progress-bar');
    const $thumb = $box.find('.thumbnail');

    const fd = new FormData();
    fd.append('action', 'shec_step3');
    fd.append('_nonce', shec_ajax.nonce);
    fd.append('_wpnonce', shec_ajax.nonce);
    fd.append('user_id', LS.get('userId'));
    fd.append('position', $box.data('position'));
    fd.append(fileInput.name, file);

    $progress.removeClass('d-none');
    $bar.css('width','0%');

    API.step3Upload(fd).done(function(res){
      if (res && res.success) {
        const fileUrl = (res.data && (res.data.file || res.data)) || res.file || res.url || '';
        if (fileUrl) {
          const $wrap = $box.closest('.upload-wrap');
          $thumb.attr('src', fileUrl).removeClass('d-none');
          $wrap.addClass('upload-success');

          const uploads = JSON.parse(LS.get('uploadedPics','{}'));
          uploads[fileInput.name] = fileUrl;
          LS.set('uploadedPics', JSON.stringify(uploads));

          if ($wrap.is('[data-pos="front"]')) {
            try { localStorage.setItem('shec_front', fileUrl); } catch(e){}
            $wrap.removeClass('is-required-error');       // این خط مهم است
            $wrap.find('.err-msg').remove();
          }
        }
        $progress.addClass('d-none'); $bar.css('width','0%');
      } else {
        toastr.error((res && res.message) || 'خطا در آپلود');
        $progress.addClass('d-none'); $bar.css('width','0%');
        $thumb.addClass('d-none').attr('src','');
        $box.closest('.upload-wrap').removeClass('upload-success');
        fileInput.value = '';
      }
    }).fail(function(){
      toastr.error('خطا در ارتباط آپلود');
      $progress.addClass('d-none'); $bar.css('width','0%');
      $thumb.addClass('d-none').attr('src','');
      $box.closest('.upload-wrap').removeClass('upload-success');
      fileInput.value = '';
    });


  });

  // Step 4 medical
  $(document).on('change', 'input[name="has_medical"]', function(){
    $('#has-medical-group').removeClass('error shake');
    $('input[name="has_medical"]').parent().removeClass('active');
    if ($(this).is(':checked')) $(this).parent().addClass('active');
    const show = $(this).val()==='yes';
    $('#medical-fields').toggleClass('d-none', !show).removeClass('error shake');
    if (!show) {
      $('#medical-fields select[name="scalp_conditions"]').val('');
      $('#medical-fields select[name="other_conditions"]').val('');
    }
  });
  $(document).on('change', 'input[name="has_meds"]', function(){
    $('#has-meds-group').removeClass('error shake');
    $('input[name="has_meds"]').parent().removeClass('active');
    if ($(this).is(':checked')) $(this).parent().addClass('active');
    const show = $(this).val()==='yes';
    $('#meds-fields').toggleClass('d-none', !show).removeClass('error shake');
    if (!show) $('#meds-fields input[name="meds_list"]').val('');
  });

  $('#form-step-4').on('submit', function(e){
    e.preventDefault();

    const hasMedical = $('input[name="has_medical"]:checked').val() || '';
    const hasMeds    = $('input[name="has_meds"]:checked').val() || '';
    if (!hasMedical) return Utils.errorScroll('#has-medical-group','لطفاً به سؤال «آیا به بیماری خاصی مبتلا هستید؟» پاسخ دهید.');
    if (!hasMeds)    return Utils.errorScroll('#has-meds-group','لطفاً به سؤال «آیا در حال حاضر داروی خاصی مصرف می‌کنید؟» پاسخ دهید.');

    if (hasMedical === 'yes') {
      const scalp = ($('#medical-fields select[name="scalp_conditions"]').val()||'').trim();
      const other = ($('#medical-fields select[name="other_conditions"]').val()||'').trim();
      if (!scalp && !other) return Utils.errorScroll('#medical-fields','لطفاً یکی از گزینه‌های بیماری را انتخاب کنید (یا «هیچکدام» را بزنید).');
    }
    if (hasMeds === 'yes') {
      const meds = ($('#meds-fields input[name="meds_list"]').val()||'').trim();
      if (!meds) return Utils.errorScroll('#meds-fields','اگر دارو مصرف می‌کنید، نام دارو را وارد کنید.','#meds-fields input[name="meds_list"]');
    }

    const payload = Object.assign(
      $('#form-step-4').serializeArray().reduce((o,f)=> (o[f.name]=f.value,o),{}),
      { user_id: LS.get('userId') }
    );

    const $btn = $(this).find('button[type="submit"]').prop('disabled', true);

    API.step4(payload).done(function(res){
      if (res && res.success) {
        __aiQOnce = false;
        UI.goToStep(5);
        UI.step5ShowLoader();

        const p = loadAiQuestions(true);
        UI.waitForAiOrTimeout(p, 10000).always(function(){
          UI.step5HideLoader();
        });

      } else {
        toastr.error((res && res.message) || 'خطا در مرحله ۴');
      }
    }).fail(function(){
      toastr.error('خطا در سرور مرحله ۴');
    }).always(function(){
      $btn.prop('disabled', false);
    });
  });

  // Followup toggles
  $(document).on('change','input[name^="followup_"]', function(){
    const name = $(this).attr('name');
    $(`input[name="${name}"]`).parent().removeClass('active');
    if ($(this).is(':checked')) $(this).parent().addClass('active');
  });

  // Step 5: contact + finalize
  $(document).on('submit', '#form-step-5', function(e){
    e.preventDefault();

    const uid = parseInt(LS.get('userId') || 0, 10);
    if (!uid) { toastr.error('شناسه کاربر پیدا نشد. لطفاً فرم را از ابتدا شروع کنید.'); return; }

    // answers
    const answers = [];
    let missingIdx = 0;
    $('#ai-questions-list .followup-item').each(function(){
      const idx = $(this).data('idx');
      const val = $(`input[name="followup_${idx}"]:checked`).val() || '';
      if (!val && !missingIdx) missingIdx = idx;
      answers.push(val);
    });
    if (missingIdx) return Utils.errorScroll(`#ai-questions-list .followup-item[data-idx="${missingIdx}"]`, 'لطفاً به همهٔ سؤالات پاسخ دهید.');

    // contact
    const first_name = ($('input[name="first_name"]').val() || '').trim();
    const last_name  = ($('input[name="last_name"]').val()  || '').trim();
    const state      = ($('input[name="state"]').val()      || '').trim();
    const city       = ($('input[name="city"]').val()       || '').trim();
    const social     = $('input[name="social"]:checked').val() || '';

    if (!first_name || !last_name) return toastr.error('نام و نام خانوادگی را وارد کنید.');
    if (!state || !city)           return toastr.error('استان و شهر را وارد کنید.');
    if (!social)                   return toastr.error('روش تماس (تماس/واتس‌اپ) را انتخاب کنید.');

    const payloadContact = { user_id: uid, first_name, last_name, state, city, social };
    const $btn = $(this).find('button[type="submit"]').prop('disabled', true);

    // === پاک‌سازی استیت فرم (فقط کلیدهای مربوط به افزونه) ===
    window.SHEC_clearFormState = function () {
      try {
        for (var i = localStorage.length - 1; i >= 0; i--) {
          var k = localStorage.key(i);
          if (/^(shec_|SHEC_|hair_)/i.test(k)) {
            localStorage.removeItem(k);
          }
        }
        // اگر قبلاً چیزی برای ریدایرکت ذخیره کرده‌ایم
        localStorage.removeItem('shec_last_public');
        sessionStorage.removeItem('shec_force_reset');
      } catch (e) {}
    };

    UI.finalStepShowLoader();
    API.step5(payloadContact).done(function(res){
      if (!res || !res.success) {
        const d = Utils.wpUnwrap(res);
        toastr.error((d && d.message) || 'خطا در ذخیره اطلاعات تماس');
        $btn.prop('disabled', false);
        return;
      }

      // نمایش لودر نهایی (همون که داری)
      UI.finalStepShowLoader();

      // نهایی‌سازی با AI
      const req = API.finalize(uid, answers);

      // تضمین حداقل ۱۰ ثانیه نمایش لودر، بعدش عمل کن
      UI.waitForAiOrTimeout(req, 10000).done(function(){
        req.done(function(fin){
          const d = Utils.wpUnwrap(fin);
          if (!(fin && fin.success)) {
            toastr.error((d && d.message) || 'خطا در نهایی‌سازی هوش مصنوعی');
            UI.finalStepHideLoader();
            $btn.prop('disabled', false);
            return;
          }

          // URL عمومی توکن‌دار که بک‌اند تو shec_finalize برمی‌گردونه
          const publicUrl     = d.public_url || (d.data && d.data.public_url);

          // ✅ مستقیم برو به صفحه‌ی نتیجه‌ی توکن‌دار
          if (publicUrl) {
            LS.clear(); 
            try { UI.finalStepHideLoader(); } catch(_) {}
            // جلوگیری از رفتن به Step6
            window.SHEC_ALLOW_STEP6 = false;
            // ریدایرکت (در همان تب)
            window.location.replace(publicUrl);
            return;
          }

          // ⛑ اگر به هر دلیل URL برنگشت، فقط در این حالت fallback:
          console.warn('[SHEC] public_url نداشت؛ اجرای fallback render در همین صفحه.');
          try { UI.finalStepHideLoader(); } catch(_) {}
          if (typeof window.SHEC_renderFinal === 'function') {
            window.SHEC_renderFinal(d); // رندر محلی نتیجه (بدون رفتن به استپ ۶)
          } else {
            toastr.warning('نتیجه آماده است اما لینک عمومی در دسترس نیست.');
          }
          $btn.prop('disabled', false);

        }).fail(function(){
          UI.finalStepHideLoader();
          toastr.error('خطای ارتباط در نهایی‌سازی');
          $btn.prop('disabled', false);
        });
      }).fail(function(){
        UI.finalStepHideLoader();
        toastr.error('خطا در ارتباط با سرور (Final)');
        $btn.prop('disabled', false);
      });

    }).fail(function(){
      toastr.error('خطا در ارتباط با سرور');
      $btn.prop('disabled', false);
    });


  });

  (function($){
  // 1) مانیتور: آیا SHEC_renderFinal اصلاً وجود دارد؟
  (function hookRender(){
    var tried = 0;
    function wrap(){
      if (typeof window.SHEC_renderFinal === 'function' && !window.__SHEC_RF_WRAPPED__) {
        var orig = window.SHEC_renderFinal;
        window.SHEC_renderFinal = function(payload){
          console.log('[SHEC] renderFinal:payload =', payload);
          try {
            return orig.apply(this, arguments);
          } catch(e){
            console.error('[SHEC] renderFinal:ERROR', e);
            $('#ai-result-box').text('❌ خطای رندر نتیجه. Console را ببینید.');
          }
        };
        window.__SHEC_RF_WRAPPED__ = true;
        console.log('[SHEC] renderFinal:wrapped');
      } else if (tried++ < 20) {
        setTimeout(wrap, 150); // صبر کوتاه درصورت دیفر لود شدن
      } else {
        console.warn('[SHEC] renderFinal:missing after retries');
      }
    }
    wrap();
  })();

  // 2) لودر نتیجه با لاگ و سازگاری با هر دو شکل پاسخ (data/بدون data و ai_result رشته/آبجکت)
  function shecLoadResultByToken(){
    var t = new URLSearchParams(location.search).get('t');
    var $box = $('#ai-result-box');
    if (!t) { $box.text('❌ توکن یافت نشد.'); return; }
    console.log('[SHEC] fetching result_by_token t=', t);

    $.ajax({
      url: (window.shec_ajax && shec_ajax.url) || '/wp-admin/admin-ajax.php',
      method: 'POST',
      data: {
        action: 'shec_result_by_token',
        t: t,
        nonce: (window.shec_ajax && shec_ajax.nonce) || ''
      }
    })
    .done(function(resp){
      console.log('[SHEC] result_by_token:resp =', resp);
      // سازگاری: بعضی نسخه‌ها data داخل resp.data است
      var payload = (resp && (resp.data || (resp.success ? resp : null)));
      if (!resp || !resp.success || !payload) {
        $box.text('❌ داده‌ای یافت نشد.');
        return;
      }
      // ai_result اگر رشته بود، پارس کن
      if (typeof payload.ai_result === 'string') {
        try { payload.ai_result = JSON.parse(payload.ai_result); }
        catch(e){ console.warn('[SHEC] ai_result not JSON, keep as string'); }
      }
      // فراخوانی رندر
      if (typeof window.SHEC_renderFinal === 'function') {
        window.SHEC_renderFinal(payload);
      } else {
        $box.text('❌ تابع رندر یافت نشد (SHEC_renderFinal).');
      }
    })
    .fail(function(xhr){
      console.error('[SHEC] result_by_token:FAIL', xhr && xhr.responseText);
      $box.text('❌ خطا در دریافت نتیجه.');
    });
  }

  // 3) فقط روی صفحه نتیجه اجرا کن
  $(function(){
    if (location.pathname.indexOf('/hair-result') !== -1) {
      shecLoadResultByToken();
    }
  });

})(jQuery);

  /* =========================
   * PDF (optional)
   * ========================= */
  async function shecBuildPdfA4(selector) {
    const root = document.querySelector(selector);
    if (!root) { console.error('[PDF] root not found'); return; }

    const needAddFrameClass = !root.classList.contains('pdf-frame');
    const clone = root.cloneNode(true);
    if (needAddFrameClass) clone.classList.add('pdf-frame');
    clone.querySelectorAll('button, .btn, [data-no-pdf]').forEach(el => el.remove());
    clone.querySelectorAll('img').forEach(img => {
      try {
        const u = new URL(img.src, location.href);
        if (u.host !== location.host) {
          img.setAttribute('data-html2canvas-ignore','true');
          img.style.display = 'none';
        } else {
          img.setAttribute('crossorigin','anonymous');
        }
      } catch(e){}
    });

    const style = document.createElement('style');
    style.textContent = `
      @font-face {
        font-family: 'Shabnam';
        src: url('https://cdn.jsdelivr.net/gh/rastikerdar/shabnam-font@v5.0.1/dist/Shabnam.woff2') format('woff2'),
             url('https://cdn.jsdelivr.net/gh/rastikerdar/shabnam-font@v5.0.1/dist/Shabnam.woff') format('woff');
        font-weight: 400; font-style: normal; font-display: swap;
      }
      @font-face {
        font-family: 'Shabnam';
        src: url('https://cdn.jsdelivr.net/gh/rastikerdar/shabnam-font@v5.0.1/dist/Shabnam-Bold.woff2') format('woff2'),
             url('https://cdn.jsdelivr.net/gh/rastikerdar/shabnam-font@v5.0.1/dist/Shabnam-Bold.woff') format('woff');
        font-weight: 700; font-style: bold; font-display: swap;
      }
      :root, body, * { font-family: Shabnam, Vazirmatn, Tahoma, sans-serif !important; }
      #proposal-pdf-root, #proposal-pdf-root * {
        letter-spacing: normal !important; word-spacing: normal !important;
        unicode-bidi: isolate; text-rendering: optimizeLegibility;
      }
    `;
    clone.prepend(style);

    const stage = document.createElement('div');
    stage.style.cssText = 'position:fixed;left:-10000px;top:0;background:#fff;z-index:-1;';
    stage.style.width = '794px';
    document.body.appendChild(stage);
    stage.appendChild(clone);

    try { await document.fonts?.ready; } catch (_) {}

    let canvas;
    try {
      canvas = await html2canvas(clone, {
        backgroundColor: '#ffffff',
        scale: 2,
        useCORS: true,
        allowTaint: true,
        foreignObjectRendering: false,
        logging: false,
        removeContainer: true
      });
    } finally {
      document.body.removeChild(stage);
    }

    if (!canvas) return;

    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF('p', 'mm', 'a4');
    const pageW = pdf.internal.pageSize.getWidth();
    const pageH = pdf.internal.pageSize.getHeight();
    const margin = 14;

    const imgW = pageW - margin*2;
    const pxPerPt = canvas.width / imgW;

    const pageCanvas = document.createElement('canvas');
    const pageCtx    = pageCanvas.getContext('2d');

    const sliceHeightPx = Math.floor(((pageH - margin*2) * pxPerPt));
    let rendered = 0, first = true;

    while (rendered < canvas.height) {
      const slice = Math.min(sliceHeightPx, canvas.height - rendered);
      pageCanvas.width  = canvas.width;
      pageCanvas.height = slice;

      pageCtx.clearRect(0,0,pageCanvas.width,pageCanvas.height);
      pageCtx.drawImage(canvas, 0, rendered, canvas.width, slice, 0, 0, canvas.width, slice);

      const imgData = pageCanvas.toDataURL('image/png');
      if (!first) pdf.addPage();
      pdf.addImage(imgData, 'PNG', margin, margin, imgW, (slice / pxPerPt));

      first = false;
      rendered += slice;
    }
    pdf.save('fakhraei-result.pdf');
  }

  $(document).on('click', '#download-pdf', function(e){
    e.preventDefault();
    shecBuildPdfA4('#proposal-pdf-root');
  });

  // Prev & Reset
  $('.btn-prev').on('click', function(){
    const current = parseInt(LS.get('currentStep') || 1);
    UI.goToStep(Math.max(1, current-1));
  });
  $(document).on('click', '#reset-form', function(){
    if (confirm('آیا مطمئن هستید که می‌خواهید فرم را از ابتدا شروع کنید؟')) {
      LS.clear(); 

      // 1) اولویت با data-reset-href از مارکاپ
      var href =this.getAttribute('data-reset-href') ||(window.shec_ajax && shec_ajax.calc_url) ||'/hair-graft-calculator/';
      // 2) بعد calc_url که از سرور لوکالایز شده (هیچ وقت localhost اشتباه نمی‌دهد)
      if (!href && window.shec_ajax && shec_ajax.calc_url) href = shec_ajax.calc_url;
      // 3) فال‌بک امن
      if (!href) href = '/hair-graft-calculator/';
      
      window.location.assign(href);
    }
  });

  // initial
  (function preloadPatternImages(){
    for (let i=1;i<=6;i++){ new Image().src = `${shec_ajax.img_path}w${i}.png`; }
    const savedGender = LS.get('gender') || 'male';
    UI.updateUploadHints(savedGender);
  })();

    function animateSections(){
      $('.shec-section').each(function(){
        var top = this.getBoundingClientRect().top;
        if (top < window.innerHeight - 50) {
          $(this).addClass('visible');
        }
      });
    }
    $(window).on('scroll load', animateSections);
    animateSections();

});
