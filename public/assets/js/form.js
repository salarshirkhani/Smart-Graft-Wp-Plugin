/* public/assets/js/form.js
 * Fakhraei Hair Graft Calculator – Clean build
 * - ساختاردهی ماژولی
 * - حذف کدهای تکراری/بلااستفاده
 * - کامنت‌گذاری منسجم
 */

/* ========= Bootstrap ========= */
var $ = jQuery.noConflict();
$(function () {
  console.log("📌 form.js loaded!", window.shec_ajax);
  if (!window.shec_ajax || !shec_ajax.nonce) {
    console.error("[SHEC] shec_ajax.nonce is missing! Nonce check may fail.");
  }

  /* ========= LocalStorage utils ========= */
  const LS = {
    get(key, fallback = null) {
      try { const v = localStorage.getItem(key); return v === null ? fallback : v; }
      catch { return fallback; }
    },
    set(key, val) { try { localStorage.setItem(key, val); } catch {} },
    del(key)      { try { localStorage.removeItem(key); } catch {} },
    clear()       { try { localStorage.clear(); } catch {} },
  };

  /* ========= General utilities ========= */
  const Utils = {
    wpUnwrap(res) { return (res && res.data) ? res.data : res; },
    normalizeMobile(m) {
      m = ('' + m).trim()
        .replace(/\s+/g, '')
        .replace(/^\+98|^0098|^98/, '0')
        .replace(/\D+/g, '');
      if (/^9\d{9}$/.test(m)) m = '0' + m;
      return m;
    },
    errorScroll(selector, msg, focusSelector) {
      const $el = $(selector);
      $el.addClass('error shake');
      if ($el.length && $el.get(0)?.scrollIntoView) {
        $el.get(0).scrollIntoView({ behavior: 'smooth', block: 'center' });
      } else if ($el.length) {
        $('html, body').animate({ scrollTop: $el.offset().top - 120 }, 300);
      }
      if (msg) toastr.error(msg);
      if (focusSelector) $(focusSelector).trigger('focus');
      setTimeout(() => $el.removeClass('shake'), 400);
    },
  };
  window.Utils = Utils;

  /* ========= AJAX API ========= */
  const API = {
    post(action, data, dataType = 'json') {
      return $.post(
        shec_ajax.url,
        Object.assign({ _nonce: shec_ajax.nonce, _wpnonce: shec_ajax.nonce, action }, data || {}),
        null,
        dataType
      );
    },
    step1(p) { return this.post('shec_step1', p); },
    step2(p) { return this.post('shec_step2', p); },
    step3Upload(formData) {
      return $.ajax({ url: shec_ajax.url, type: 'POST', data: formData, processData: false, contentType: false, dataType: 'json' });
    },
    step4(p) { return this.post('shec_step4', p); },
    step5(p) { return this.post('shec_step5', p); },
    aiQuestions(user_id) {
      return $.ajax({
        url: shec_ajax.url, type: 'POST', dataType: 'json', timeout: 25000,
        data: { action: 'shec_ai_questions', user_id, _nonce: shec_ajax.nonce, _wpnonce: shec_ajax.nonce }
      });
    },
    finalize(user_id, answers) { return this.post('shec_finalize', { user_id, answers }); },
  };

  /* ========= Domain helpers (Hair Estimator) ========= */
  const HE_GRAFT_TABLE = {
    male:   { 1: 8000, 2: 10000, 3: 12000, 4: 14000, 5: 16000, 6: 18000 },
    female: { 1: 4000, 2:  8000, 3: 10000, 4: 12000, 5: 14000, 6: 16000 },
  };
  function heNormalizeGender(g) { g = (g || '').toString().toLowerCase(); return (g === 'female' || g === 'زن') ? 'female' : 'male'; }
  function heStageFromPatternValue(v) {
    if (!v) return null;
    const m = String(v).toLowerCase().match(/pattern[-_ ]?(\d+)/);
    const s = m && parseInt(m[1], 10);
    if (!s) return null;
    return Math.min(6, Math.max(1, s));
  }
  function heGraftsFromStage(gender, stage) {
    if (!stage) return null;
    const tbl = HE_GRAFT_TABLE[heNormalizeGender(gender)] || {};
    return tbl[stage] || null;
  }

  /* ========= Medical warnings (self-contained; no dependency) ========= */
  const HE_WARNINGS = {
    diabetes:    "اگر دچار دیابت هستید، کاشت مو تنها در صورتی ممکن است که بیماری تحت کنترل کامل باشد. دیابت می‌تواند بر روند بهبودی تأثیر بگذارد و خطر عفونت پس از جراحی را افزایش دهد. قبل از کاشت، تأییدیه کتبی پزشک معالج لازم است.",
    coagulation: "کاشت مو در بیماران مبتلا به اختلالات انعقاد خون ممکن است دشوار باشد و خونریزی را در طول عمل افزایش دهد و بر بقای گرافت‌ها تأثیر بگذارد. تأییدیه کتبی پزشک معالج لازم است.",
    cardiac:     "کاشت مو با وجود بیماری‌های قلبی/عروقی تنها در صورتی ممکن است که بیماری تحت کنترل کامل باشد و ممکن است ریسک داروی بی‌حسی و نقاهت بالاتر برود. تأییدیه کتبی پزشک لازم است.",
    thyroid:     "کاشت مو برای اختلالات تیروئید در صورت متعادل بودن سطح هورمون‌ها امکان‌پذیر است؛ حالت کنترل‌نشده می‌تواند بر رشد مو و بقای گرافت‌ها اثر بگذارد. تأییدیه کتبی پزشک لازم است.",
    immunodef:   "برای نقص سیستم ایمنی (مانند برخی موارد HIV یا شیمی‌درمانی) معمولاً کاشت توصیه نمی‌شود؛ بهبودی کندتر و عوارض بیشتر است. تصمیم نهایی با ارزیابی تخصصی و تأیید پزشک است.",
    autoimmune:  "در بیماری‌های خودایمنی، بسته به نوع و فعالیت بیماری، کاشت ممکن است دشوار یا غیرقابل انجام باشد و روی پذیرش گرافت‌ها اثر بگذارد. ارزیابی تخصصی و تأیید پزشک لازم است."
  };
  const HE_SCALP_WARNINGS = {
    active_infection: "اگر عفونت فعال پوست سر دارید نمی‌توان بلافاصله برای کاشت مو اقدام کرد؛ ابتدا باید کامل درمان شود تا ریسک عارضه و کاهش بقای گرافت پایین بیاید.",
    psoriasis:        "اگر پسوریازیس فعال است—خصوصاً با درگیری وسیع—ابتدا کنترل/درمان لازم است؛ سپس دربارهٔ کاشت تصمیم می‌گیریم.",
    fungal_derm:      "قبل از در نظر گرفتن کاشت مو، درماتیت سبورئیک/عفونت قارچی باید کنترل شود؛ التهاب فعال شانس موفقیت را کم می‌کند.",
    folliculitis:     "در صورت فولیکولیت، ابتدا درمان عفونت/التهاب انجام می‌شود؛ سپس می‌توان برای کاشت اقدام کرد.",
    areata:           "کاشت مو در فاز فعال آلوپسی آره‌آتا (ریزش سکه‌ای) توصیه نمی‌شود؛ ابتدا باید بیماری غیرفعال شود.",
    scarring_alo:     "آلوپسی اسکارینگ می‌تواند موفقیت پیوند را کم کند؛ تصمیم‌گیری پس از ارزیابی تخصصی و پایدار بودن ضایعات انجام می‌شود.",
    scar:             "وجود اسکار روی پوست سر ممکن است درصد موفقیت را کاهش دهد؛ ارزیابی تراکم خون‌رسانی محل ضروری است."
  };
  function heMapLabelToWarningKey(label) {
    if (!label) return null;
    const t = String(label).trim().toLowerCase();
    if (/(^|[^a-z])diab|دیابت/.test(t)) return 'diabetes';
    if (/coag|انعقاد/.test(t))          return 'coagulation';
    if (/card|قلب|عروقی/.test(t))       return 'cardiac';
    if (/thyroid|تیروئید/.test(t))      return 'thyroid';
    if (/immuno|hiv|chemo|ایمنی|شیمی|ایدز/.test(t)) return 'immunodef';
    if (/autoim|lupus|alopecia|خودایمنی|لوپوس|آلوپسی/.test(t)) return 'autoimmune';
    if (/none|هیچ|ندارم/.test(t)) return null;
    return null;
  }
  function heMapScalpLabelToKey(label) {
    if (!label) return null;
    const t = String(label).trim().toLowerCase();
    if (/active[_\-\s]*infection|عفونت\s*فعال/.test(t))             return 'active_infection';
    if (/psoriasis|پسوریازیس/.test(t))                               return 'psoriasis';
    if (/fung|derm|seborr|قارچی|سبورئیک/.test(t))                    return 'fungal_derm';
    if (/folliculit|فولیکولیت/.test(t))                              return 'folliculitis';
    if (/areata|alopecia\s*areata|ریزش\s*سکه‌ای|آلوپسی\s*آره‌آتا/.test(t)) return 'areata';
    if (/scarring[_\-\s]*alo|آلوپسی\s*به\s*همراه\s*اسکار/.test(t))  return 'scarring_alo';
    if (/scar|اسکار|جای\s*زخم/.test(t))                              return 'scar';
    if (/none|هیچ|ندارم/.test(t)) return null;
    return null;
  }
  function heRenderAllWarnings(opt) {
    opt = opt || {};
    const systemicLabels = Array.isArray(opt.systemicLabels) ? opt.systemicLabels : [];
    const scalpLabels    = Array.isArray(opt.scalpLabels)    ? opt.scalpLabels    : [];
    const anchorSel      = opt.anchor || '#he-medical-warning-wrap';
    const host = document.querySelector(anchorSel);
    if (!host) return;

    // Inject base style once
    if (!document.getElementById('shec-warn-style')) {
      const st = document.createElement('style');
      st.id = 'shec-warn-style';
      st.textContent = `
        .he-warn-card{background:#fff5f5;border:1px solid #f0b8b8;color:#b61414;padding:12px 14px;border-radius:8px;margin:8px 0;line-height:1.7}
        .he-warn-card p{margin:0}
        .he-warn-title{font-weight:700;margin:12px 0 6px 0;color:#b61414}
      `;
      document.head.appendChild(st);
    }

    host.innerHTML = '';

    const sysKeys   = [...new Set(systemicLabels.map(heMapLabelToWarningKey).filter(Boolean))];
    const scalpKeys = [...new Set(scalpLabels.map(heMapScalpLabelToKey).filter(Boolean))];

    if (!sysKeys.length && !scalpKeys.length) { host.style.display = 'none'; return; }

    const title = document.createElement('div');
    title.className = 'he-warn-title';
    title.textContent = 'نکات مهم پیش از کاشت (براساس وضعیت پزشکی):';
    host.appendChild(title);

    sysKeys.forEach(k => {
      const div = document.createElement('div');
      div.className = 'he-warn-card';
      div.innerHTML = '<p>' + (HE_WARNINGS[k] || '') + '</p>';
      host.appendChild(div);
    });
    scalpKeys.forEach(k => {
      const div = document.createElement('div');
      div.className = 'he-warn-card';
      div.innerHTML = '<p>' + (HE_SCALP_WARNINGS[k] || '') + '</p>';
      host.appendChild(div);
    });

    host.style.display = '';
  }

  /* ========= UI helpers ========= */
  const UI = {
    setProgress(step) {
      $('#step-current').text(step);
      $('#progress-bar').css('width', Math.floor((step / 6) * 100) + '%');
    },
    goToStep(step) {
      $('#step2-loader').hide();
      $('.step').addClass('d-none').removeClass('active');
      $('#step-' + step).removeClass('d-none').addClass('active');
      UI.setProgress(step);
      LS.set('currentStep', step);
      if (step === 2) UI.loadStep2Images();
      if (step === 3) {
        const gender = LS.get('gender', 'male');
        UI.renderUploadBoxes(gender);
        UI.loadUploadedThumbs();
        UI.updateUploadHints(gender);
      }
    },
    loadStep2Images() {
      const gender = LS.get('gender', 'male');
      const $step2 = $('#step-2');
      $('#step2-loader').show();
      $step2.show().addClass('active');
      $step2.find('.pattern-option img').each((idx, img) => {
        const i = idx + 1;
        img.src = (gender === 'female') ? `${shec_ajax.img_path}w${i}.png` : `${shec_ajax.img_path}ol${i}.png`;
        $(img).css('filter', 'grayscale(100%)');
      });
      const imgs = $step2.find('.pattern-option img').toArray();
      Promise.all(imgs.map(img => new Promise(res => img.complete ? res() : (img.onload = res))))
        .then(() => $('#step2-loader').hide());
    },
    renderUploadBoxes(gender) {
      const positions = { male: ['روبرو', 'پشت سر', 'فرق سر', 'کنار سر'], female: ['روبرو', 'بالای سر', 'فرق سر'] };
      const container = $('#upload-zones').empty();
      positions[gender].forEach((label, index) => {
        const isLastFemale = (gender === 'female' && index === positions[gender].length - 1);
        const colClass = isLastFemale ? 'col-12' : 'col-12 col-lg-6';
        const isFront = (label === 'روبرو');
        container.append(`
          <div class="${colClass}">
            <div class="upload-wrap${isFront ? ' shec-upload-front' : ''}" ${isFront ? 'data-pos="front"' : ''}>
              <label class="upload-box" data-index="${index}" data-position="${label}">
                <span class="d-block fw-bold mb-2">${label}</span>
                <input type="file" name="pic${index + 1}" accept="image/*">
                <div class="progress d-none"><div class="progress-bar" style="width:0%;"></div></div>
                <img src="" class="thumbnail d-none">
              </label>
              <div class="upload-overlay"><button type="button" class="remove-btn" aria-label="حذف تصویر">🗑</button></div>
            </div>
          </div>
        `);
      });
    },
    loadUploadedThumbs() {
      const uploads = JSON.parse(LS.get('uploadedPics', '{}'));
      for (const name in uploads) {
        const url = uploads[name];
        const $box = $(`.upload-box input[name="${name}"]`).closest('.upload-box');
        const $wrap = $box.closest('.upload-wrap');
        if ($wrap.length) {
          $wrap.addClass('upload-success');
          $box.find('.thumbnail').attr('src', url).removeClass('d-none');
          if ($wrap.is('[data-pos="front"]')) { try { localStorage.setItem('shec_front', url); } catch {} }
        }
      }
    },
    updateUploadHints(gender) {
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
      const imgs = (gender === 'female') ? femaleImgs : maleImgs;
      const $angleImages = $('.angles .angle img');
      $angleImages.each(function (i) { imgs[i] ? $(this).attr('src', imgs[i]).parent().show() : $(this).parent().hide(); });
      $('.angles').css({ display: 'flex', justifyContent: (gender === 'female') ? 'center' : 'space-between', gap: '12px' });
    },
    ensureStep5Containers() {
      if (!$('#step5-content').length) return;
      if (!$('#ai-questions-box').length) {
        $('#step5-content').prepend(`
          <div id="ai-questions-box" class="mb-4">
            <p class="d-block mb-2 fw-bold">لطفاً به چند سؤال کوتاه پاسخ دهید:</p>
            <div id="ai-questions-list"></div>
          </div>
        `);
      }
    },

    /* ---- Step5 loaders ---- */
    __aiTimers: [],
    step5ShowLoader() {
      const $loader = $('#step5-loader');
      const $content = $('#step5-content');
      $('#form-step-5, #ai-questions-box').addClass('ohide').removeClass('oshow');
      $content.hide(); $loader.show();

      let $text = $('#ai-loader-text');
      if (!$text.length) { $text = $('<div id="ai-loader-text" class="ai-loader-text"></div>'); $loader.append($text); }

      const messages = [
        'در حال بررسی جنسیت و نوع ریزش مو',
        'در حال بررسی دغدغه و نگرانی شما',
        'در حال بررسی بیماری ها و داروهای نوشته شده',
        'در حال ایجاد 4 سوال اختصاصی برای شما توسط هوش مصنوعی فخرایی',
        'در حال آماده سازی نهایی ...'
      ];

      this.__aiTimers.forEach(clearTimeout); this.__aiTimers = [];
      $text.stop(true, true).css('opacity', 1).text(messages[0]);

      const schedule = (msg, delay) => {
        const id = setTimeout(() => { $text.fadeOut(250, () => $text.text(msg).fadeIn(250)); }, delay);
        this.__aiTimers.push(id);
      };
      schedule(messages[1], 2000); schedule(messages[2], 4000); schedule(messages[3], 6000); schedule(messages[4], 8000);
    },
    step5HideLoader() {
      this.__aiTimers.forEach(clearTimeout); this.__aiTimers = [];
      $('#step5-loader').fadeOut(300);
      $('#step5-content').fadeIn(200);
      $('#form-step-5, #ai-questions-box').addClass('oshow').removeClass('ohide');
    },
    waitForAiOrTimeout(promise, minMs = 10000) {
      const dfd = jQuery.Deferred(); const t0 = Date.now();
      const p = (promise && typeof promise.then === 'function') ? promise : jQuery.Deferred().resolve().promise();
      p.always(() => setTimeout(() => dfd.resolve(), Math.max(0, minMs - (Date.now() - t0))));
      return dfd.promise();
    },

    /* ---- Final step loader ---- */
    finalTimers: [],
    ensureLottieLoaded() {
      if (window.customElements?.get('dotlottie-wc')) return;
      if (document.getElementById('dotlottie-wc-loader')) return;
      const s = document.createElement('script');
      s.type = 'module'; s.id = 'dotlottie-wc-loader';
      s.src = 'https://unpkg.com/@lottiefiles/dotlottie-wc@latest/dist/dotlottie-wc.js';
      document.head.appendChild(s);
    },
    ensureFinalLoaderDom() {
      if ($('#final-step-loader').length) return;
      this.ensureLottieLoaded();
      const tpl = `
        <div id="final-step-loader" class="ai-loader-overlay bgl" style="display:none;">
          <div class="ai-loader-box">
            <dotlottie-wc src="https://lottie.host/f6ee527c-625e-421f-b114-b95e703a33c5/UHdu4rKs9b.lottie" speed="1" autoplay loop></dotlottie-wc>
            <div id="final-loader-text" class="ai-loader-text" style="text-align:center;justify-content:center;"></div>
          </div>
        </div>`;
      const $host = $('#step-5').length ? $('#step-5') : $('body');
      $host.append(tpl);
    },
    finalStepShowLoader() {
      this.ensureFinalLoaderDom();
      const $overlay = $('#final-step-loader'); const $text = $('#final-loader-text');
      $('#form-step-5, #ai-questions-box').addClass('ohide').removeClass('oshow');
      this.finalTimers.forEach(clearTimeout); this.finalTimers = [];
      const msgs = [
        'در حال بررسی پاسخ سوالات شما ...',
        'در حال بررسی نوع ریزش موی شما ...',
        'در حال یافتن بهترین روش کاشت موی مناسب شما ...',
        'در حال محاسبه تعداد تقریبی تار موی لازم برای کاشت  ...',
        'در حال آماده‌سازی نهایی ...'
      ];
      $text.stop(true, true).css('opacity', 1).text(msgs[0]); $overlay.fadeIn(150);
      const schedule = (msg, delay) => {
        const id = setTimeout(() => { $text.fadeOut(200, () => $text.text(msg).fadeIn(200)); }, delay);
        this.finalTimers.push(id);
      };
      schedule(msgs[1], 2000); schedule(msgs[2], 4000); schedule(msgs[3], 6000); schedule(msgs[4], 8000);
    },
    finalStepHideLoader() { this.finalTimers.forEach(clearTimeout); this.finalTimers = []; $('#final-step-loader').fadeOut(250); },

    /* ---- AI Questions UI ---- */
    renderQuestions(qs) {
      const $list = $('#ai-questions-list').empty();
      (qs || []).forEach((q, i) => {
        const idx = i + 1;
        $list.append(`
          <div class="followup-item mb-3" data-idx="${idx}">
            <div class="d-block mb-2 fw-bold">${q}</div>
            <div class="toggle-group">
              <label class="toggle-option"><input type="radio" name="followup_${idx}" value="yes" hidden><span>بله</span></label>
              <label class="toggle-option"><input type="radio" name="followup_${idx}" value="no" hidden><span>خیر</span></label>
            </div>
          </div>
        `);
      });
    },
    renderQuestionsFallback() {
      this.renderQuestions([
        'آیا در خانواده‌تان سابقهٔ ریزش مو وجود دارد؟',
        'آیا طی ۱۲ ماه گذشته شدت ریزش موی شما بیشتر شده است؟',
        'آیا در حال حاضر سیگار یا قلیان مصرف می‌کنید؟',
        'آیا خواب و استرس شما در ماه‌های اخیر بدتر شده است؟'
      ]);
    },
  };

  /* ========= AI loader (Step 5) ========= */
  let __aiQOnce = false;

  function loadAiQuestions(force = false) {
    const uid = parseInt(LS.get('userId') || 0, 10);
    UI.ensureStep5Containers();
    $('#ai-questions-box').removeClass('d-none').show();
    $('#ai-questions-list').empty();

    if (!uid) { UI.renderQuestionsFallback(); return $.Deferred().resolve().promise(); }
    if (!force && __aiQOnce) return $.Deferred().resolve().promise();

    const req = API.aiQuestions(uid);
    const pendingTimer = setTimeout(() => {
      try { if (req?.state?.() === 'pending') toastr.info('نتیجه AI دیرتر میاد، لطفاً کمی صبر کنید...'); } catch {}
    }, 7000);

    return req
      .done(res => {
        if (!res || res.success !== true) {
          console.warn('[AIQ] invalid payload:', res);
          UI.renderQuestionsFallback();
          toastr.info('سؤالات هوشمند موقتاً موجود نیست؛ سؤالات عمومی نمایش داده شد.');
          return;
        }
        const qs = Array.isArray(res.data?.questions) ? res.data.questions.filter(Boolean) : [];
        (qs.length === 4) ? UI.renderQuestions(qs) : (UI.renderQuestionsFallback(), toastr.info('خروجی سرویس نامعتبر بود؛ سؤالات عمومی نمایش داده شد.'));
      })
      .fail(jq => {
        const snippet = (jq && jq.responseText) ? jq.responseText.slice(0, 300) : '';
        console.error('[AIQ] AJAX FAIL', { status: jq.status, responseSnippet: snippet });
        if (jq.status === 403 && /nonce/i.test(snippet)) toastr.error('نشست امنیتی منقضی شد. صفحه را نوسازی کنید.');
        else toastr.warning('اتصال به سرویس سؤالات برقرار نشد؛ سؤالات عمومی نمایش داده شد.');
        UI.renderQuestionsFallback();
      })
      .always(() => { clearTimeout(pendingTimer); __aiQOnce = true; });
  }

  

  /* ========= Final renderer (shared: step6 + token page) ========= */
  window.SHEC_renderFinal = function (fin) {
    // --- Parse payload ---
    const payload = (fin && fin.user) ? fin : Utils.wpUnwrap(fin);
    if (!payload || !payload.user) return $('#ai-result-box').html('<div style="padding:24px">دادهٔ نتیجه پیدا نشد.</div>');

    const d = payload, u = d.user || {};
    const first = (u.contact?.first_name || '').trim();
    const last  = (u.contact?.last_name  || '').trim();
    const full  = (first || last) ? (first + (first && last ? ' ' : '') + last) : '—';
    const ageVal  = u.age || u.contact?.age || '—';
    const pattern = u.loss_pattern || u.pattern || null;
    const gender  = (u.gender || u.contact?.gender || 'male').toLowerCase();
    const concern = (u.medical?.concern) ? u.medical.concern : '—';

    const stage   = heStageFromPatternValue(pattern);
    const graftN  = heGraftsFromStage(gender, stage);
    const graftByTable = graftN ? Number(graftN).toLocaleString('fa-IR') : '—';

    // --- Medical (clean join/split to avoid "??" mess) ---
    const splitFa = (str) => {
      if (!str || typeof str !== 'string') return [];
      str = str.replace(/[\u200c\u200e\u200f]/g, '')
               .replace(/\s*[,،;]\s*|\r?\n+/g, '، ')
               .replace(/\s{2,}/g, ' ')
               .replace(/^(،\s*)+|(،\s*)+$/g, '');
      return str.split(/،\s*/g).map(s => s.trim()).filter(Boolean);
    };
    const joinFa = (arr) => (Array.isArray(arr) && arr.length) ? arr.join('، ') : '—';

    const med         = u.medical || {};
    const drugsLabels = (med.has_meds === 'yes') ? (splitFa(med.meds_list).length ? splitFa(med.meds_list) : ['مصرف دارو']) : ['عدم مصرف دارو'];
    const dermLabels  = splitFa(med.scalp_conditions);
    const sysLabels   = splitFa(med.other_conditions);
    const showMedical = (drugsLabels.length || dermLabels.length || sysLabels.length) > 0;
    const warnHostId  = 'he-medical-warning-wrap-' + Date.now();

    // --- AI blob ---
    let ai = {};
    try { ai = (typeof d.ai_result === 'string') ? JSON.parse(d.ai_result || '{}') : (d.ai_result || {}); } catch { ai = {}; }

    // --- Display constants ---
    const methodTxt = 'FIT';
    const duration  = 'دو جلسه هشت ساعته';
    const logoUrl   = 'https://fakhraei.clinic/wp-content/uploads/2024/02/Group-1560-300x300.png.webp';

    // --- Pattern explain fallback ---
    function mapFemaleLudwig(st) { if (!st) return null; if (st <= 2) return 'Ludwig I'; if (st <= 4) return 'Ludwig II'; return 'Ludwig III'; }
    const fallbackPatternExplain = (() => {
      if (!stage) return { label: '—', what_it_is: '', why_happens: '', note: '', fit_ok: true };
      if (gender === 'female') {
        const label = mapFemaleLudwig(stage) || 'Ludwig';
        const what  = 'الگوی کم‌پشتی منتشر در ناحیه مرکزی سر که با پیشرفت، وسعت بیشتری می‌گیرد.';
        const why   = 'اغلب مرتبط با عوامل هورمونی و ژنتیکی؛ استرس و سبک زندگی هم اثر دارند.';
        const note  = (label === 'Ludwig I') ? 'در این مرحله معمولاً کاشت لازم نیست و درمان نگه‌دارنده پیشنهاد می‌شود.' : '';
        return { label, what_it_is: what, why_happens: why, note, fit_ok: true };
      } else {
        const label = 'Norwood ' + stage;
        const what  = (stage >= 5)
          ? 'درگیری جلوی سر و ورتکس با باریک‌شدن پل میانی؛ برای بازگردانی خط مو و تراکم، برداشت گسترده لازم می‌شود.'
          : 'عقب‌نشینی خط جلویی یا کم‌پشتی موضعی که با پیشرفت، نواحی بیشتری را درگیر می‌کند.';
        const why   = 'معمولاً ژنتیکی و مرتبط با حساسیت فولیکول‌ها به آندروژن‌ها؛ استرس و سبک زندگی می‌تواند شدت را تغییر دهد.';
        const note  = (stage === 1) ? 'در این مرحله معمولاً کاشت لازم نیست و درمان نگه‌دارنده پیشنهاد می‌شود.' : '';
        return { label, what_it_is: what, why_happens: why, note, fit_ok: true };
      }
    })();
    const patExplain = Object.assign({}, fallbackPatternExplain, (ai.pattern_explain || {}));

    // --- Concern helper ---
    const concernBox = (ai.concern_box && ai.concern_box.trim())
      ? ai.concern_box.trim()
      : (() => {
          const c = concern.toString();
          if (/هزینه|قیمت/.test(c)) return 'می‌دانیم هزینه برایتان مهم است... برآورد شفاف و منطقی ارائه می‌شود.';
          if (/درد/.test(c))       return 'نگران درد نباشید؛ بی‌حسی موضعی و پایش مداوم داریم تا تجربه قابل‌تحمل باشد.';
          if (/نقاهت/.test(c))    return 'دوران نقاهت کوتاه و قابل‌مدیریت است و راهنمای مرحله‌به‌مرحله می‌دهیم.';
          if (/طول|زمان/.test(c)) return 'رشد مو مرحله‌ای است؛ تغییرات از ماه‌های اول شروع می‌شود.';
          return 'نگرانی شما قابل درک است؛ مسیر درمان روشن است و کنار شما هستیم.';
        })();

    // --- Followups & tips ---
    const followupsData = ((u.ai || {}).followups) || {};
    const answersArr    = Array.isArray(followupsData.answers) ? followupsData.answers : [];
    const qaItems       = Array.isArray(followupsData.qa) && followupsData.qa.length
      ? followupsData.qa
      : (Array.isArray(followupsData.questions) ? followupsData.questions.map((q, i) => ({ q, a: (answersArr[i] || '') })) : []);
    const faYesNo = v => (/^(yes|true|بله)$/i.test(String(v))) ? 'بله' : (/^(no|false|خیر)$/i.test(String(v)) ? 'خیر' : (String(v || '').trim() || '—'));

    const aiFollowups = Array.isArray(ai.followups) ? ai.followups : [];
    const norm = s => String(s || '').replace(/\s+/g, '').replace(/[‌\u200c]/g, '').trim();
    function getTipFor(qText, idx) {
      const byIndex = aiFollowups[idx] && (aiFollowups[idx].tip || aiFollowups[idx].ai_tip);
      if (byIndex && String(byIndex).trim()) return String(byIndex).trim();
      const hit = aiFollowups.find(x => norm(x.q || '') === norm(qText));
      const tip = hit && (hit.tip || hit.ai_tip);
      return (tip && String(tip).trim()) ? String(tip).trim() : '';
    }

    const followupSummary = (ai.followup_summary && ai.followup_summary.trim())
      ? ai.followup_summary.trim()
      : (() => {
          const pick = re => (qaItems.find(x => re.test(String(x.q || ''))) ? faYesNo(qaItems.find(x => re.test(String(x.q || ''))).a) : '');
          const smoking = pick(/سیگار|قلیان/), stress = pick(/استرس/), sleep = pick(/خواب/), worse = pick(/شدت|بدتر|افزایش/), infect = pick(/عفونت|التهاب|پوست سر/);
          const s = [];
          s.push('🤖 با توجه به پاسخ‌ها، برنامهٔ آمادگی قبل از کاشت: ');
          if (smoking === 'بله') s.push('۱۰ روز قبل تا یک هفته بعد از کاشت دخانیات قطع شود. ');
          if (sleep === 'خیر')  s.push('خواب را با تنظیم ساعت و کاهش کافئین عصر بهبود دهید. ');
          if (stress === 'بله') s.push('تنفس عمیق/پیاده‌روی روزانه برای مدیریت استرس. ');
          if (worse === 'بله')  s.push('تصمیم درمانی را عقب نیندازید و درمان نگه‌دارنده شروع شود. ');
          if (infect === 'بله') s.push('ابتدا التهاب/عفونت پوست سر را درمان کنید. ');
          s.push('رعایت بهداشت پوست سر و دستورات مراقبتی، کیفیت گیرنده را بهتر می‌کند.');
          return s.join('');
        })();

    const extraText = (ai.extra_analysis?.trim()) || (ai.analysis?.trim()) || '';

    const qaHtml = `
      <div class="ai-section-title" style="margin-top:18px;">نتیجه سوالات ایجاد شده هوشمند</div>
      <ol class="ai-qa">
        ${qaItems.map((item, i) => {
          const tip = getTipFor(item.q, i);
          const ans = faYesNo(item.a);
          const ansClass = /بله/.test(ans) ? 'ans-yes' : (/خیر/.test(ans) ? 'ans-no' : '');
          return `
            <li class="ai-qa-item">
              <div class="ai-qa-head"><div class="num">${i + 1}</div><div class="ai-qa-q">❓ ${(item.q || '').trim()}</div></div>
              <div class="ai-qa-a"><span class="label">پاسخ:</span><span class="ans-pill ${ansClass}">${ans}</span></div>
              ${tip ? `<div class="ai-qa-tip" style="display:flex;gap:6px;align-items:flex-start;text-align:justify"><span class="bot">🤖</span><div class="text">${tip}</div></div>` : ``}
            </li>`;
        }).join('')}
      </ol>
    `;

    // --- Render ---
    $('#ai-result-box').html(`
      <div class="ai-result-container">
        <div class="ai-hero">
          <div class="ai-logo">${logoUrl ? `<img src="${logoUrl}" alt="Fakhraei">` : ''}</div>
          <div class="ai-title">برنامه اختصاصی کاشت موی شما آماده است!</div>
          <div class="ai-check">✓</div>
          <div class="ai-sub">از اعتماد شما سپاسگزاریم</div>
        </div>

        <div class="ai-section-title">اطلاعات شخصی شما</div>
        <div class="ai-chip-row">
          <div class="ai-chip"><span class="ai-chip-label">نام و نام خانوادگی</span><div class="ai-chip-value">${full}</div></div>
          <div class="ai-chip"><span class="ai-chip-label">الگوی ریزش مو</span><div class="ai-chip-value">${pattern ?? '—'}</div></div>
          <div class="ai-chip"><span class="ai-chip-label">بازه سنی</span><div class="ai-chip-value">${ageVal}</div></div>
        </div>

        <div class="ai-note" style="text-align:justify">
          🤖 <strong>${patExplain.label || '—'}</strong> — ${patExplain.what_it_is || ''}<br>
          ${patExplain.why_happens ? (patExplain.why_happens + '<br>') : ''}
          ${patExplain.fit_ok ? 'این الگو با روش FIT/FUE در کلینیک فخرائی قابل درمان است.' : ''} ${patExplain.note ? ('<br>'+patExplain.note) : ''}
        </div>

        <div class="ai-section-title" style="margin-top:18px;">مهم‌ترین دغدغهٔ شما</div>
        <div class="ai-content" style="margin-bottom:8px">${concern}</div>
        <div class="ai-note" style="text-align:justify">🤖 ${concernBox}</div>

        <hr class="ai-divider"/>

        <div class="ai-stats">
          <div class="ai-stat"><div class="ai-stat-label">مدت زمان تقریبی</div><div class="ai-stat-value">${duration}</div></div>
          <div class="ai-stat ai-stat--accent"><div class="ai-stat-label">تکنیک پیشنهادی</div><div class="ai-stat-value">${methodTxt}</div></div>
          <div class="ai-stat"><div class="ai-stat-label">تعداد تار موی پیشنهادی</div><div class="ai-stat-value">${graftByTable}</div></div>
        </div>

        ${showMedical ? `
          <div class="ai-section-title" style="margin-top:22px;">وضعیت پزشکی ثبت‌شده</div>
          <div class="ai-stats">
            <div class="ai-stat"><div class="ai-stat-label">دارو مورد استفاده</div><div class="ai-stat-value">${joinFa(drugsLabels)}</div></div>
            <div class="ai-stat"><div class="ai-stat-label">بیماری پوستی</div><div class="ai-stat-value">${joinFa(dermLabels)}</div></div>
            <div class="ai-stat"><div class="ai-stat-label">بیماری زمینه‌ای</div><div class="ai-stat-value">${joinFa(sysLabels)}</div></div>
          </div>
          <div id="${warnHostId}"></div>
        ` : ''}

        ${qaHtml}

        <div class="ai-section-title" style="margin-top:18px;">جمع‌بندی پاسخ‌ها و توصیه‌های اختصاصی</div>
        <div class="ai-note" style="text-align:justify">${followupSummary}</div>

        ${extraText ? `
          <div class="ai-section-title" style="margin-top:18px;">توضیحات اضافه</div>
          <div class="ai-note" style="text-align:justify">🤖 ${extraText}</div>
        ` : ''}
      </div>
    `);

    // Medical warnings (red cards)
    heRenderAllWarnings({ systemicLabels: sysLabels, scalpLabels: dermLabels, anchor: '#' + warnHostId });

    // prevent auto-step6 switch on result view
    if (window.SHEC_ALLOW_STEP6 && UI.goToStep && document.querySelector('#step-6')) {
      UI.goToStep(6);
    }
  };

  /* ========= Step Bindings ========= */

  // Init step from LS
  UI.goToStep(parseInt(LS.get('currentStep')) || 0);

  // Step 0 → 1
  $(document).on('click', '#agree-btn', e => { e.preventDefault(); UI.goToStep(1); });

  // Step 1
  $('#form-step-1').on('submit', function (e) {
    e.preventDefault();
    const gender = $('input[name="gender"]:checked').val();
    const age = $('input[name="age"]:checked').val();
    const confidence = $('select[name="confidence"]').val();
    let mobile = Utils.normalizeMobile($('input[name="mobile"]').val());
    const validAges = ['18-23', '24-29', '30-35', '36-43', '44-56', '+56'];

    if (!gender) return toastr.error('لطفاً جنسیت را انتخاب کنید');
    if (!age || !validAges.includes(age)) return toastr.error('لطفاً بازه سنی را درست انتخاب کنید');
    if (!confidence) return toastr.error('لطفاً میزان اعتماد به نفس را انتخاب کنید');
    if (!/^09\d{9}$/.test(mobile)) return toastr.error('شماره موبایل معتبر وارد کنید (مثلاً 09xxxxxxxxx)');

    const payload = { user_id: LS.get('userId') || 0, gender, age, mobile, confidence };
    const $btn = $(this).find('button[type="submit"]').prop('disabled', true);

    API.step1(payload)
      .done(res => {
        const d = Utils.wpUnwrap(res);
        if (res?.success) {
          LS.set('userId', d.user_id); LS.set('gender', gender); LS.set('currentStep', 2); UI.goToStep(2);
        } else toastr.error(d?.message || 'خطا در ارسال اطلاعات');
      })
      .fail(() => toastr.error('خطا در ارتباط با سرور'))
      .always(() => $btn.prop('disabled', false));
  });

  // Step 2: pattern
  $('#form-step-2').on('click', '.pattern-option', function () {
    $('.pattern-option').removeClass('selected');
    $(this).addClass('selected');
    $('input[name="loss_pattern"]', this).prop('checked', true);
    $('#form-step-2 .pattern-option img').css('filter', 'grayscale(100%)');
    $(this).find('img').css('filter', 'none');
  });
  $('#form-step-2').on('submit', function (e) {
    e.preventDefault();
    const loss_pattern = $('input[name="loss_pattern"]:checked').val();
    const uid = LS.get('userId');
    if (!loss_pattern) return toastr.error('لطفاً الگوی ریزش مو را انتخاب کنید');
    API.step2({ user_id: uid, loss_pattern })
      .done(res => res.success ? UI.goToStep(3) : toastr.error(res.message || 'خطا در مرحله ۲'))
      .fail(() => toastr.error('خطای سرور در مرحله ۲'));
  });

  // Step 3: uploads
  const HE_MAX_UPLOAD_MB = Number(shec_ajax?.max_upload_mb) || 5;
  function heFormatBytes(bytes) {
    if (!bytes || bytes <= 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return (bytes / Math.pow(1024, i)).toFixed((i === 0) ? 0 : 1) + ' ' + units[i];
  }

  // Remove uploaded image
  $(document).on('click', '.upload-overlay .remove-btn', function (e) {
    e.preventDefault();
    const $wrap = $(this).closest('.upload-wrap');
    const $box  = $wrap.find('.upload-box');
    const $img  = $box.find('.thumbnail');
    const $in   = $box.find('input[type="file"]')[0];
    const name  = $in && $in.name;

    try {
      const up = JSON.parse(LS.get('uploadedPics', '{}'));
      if (name && up[name]) { delete up[name]; LS.set('uploadedPics', JSON.stringify(up)); }
    } catch {}
    if ($wrap.is('[data-pos="front"]')) { try { localStorage.removeItem('shec_front'); } catch {} }

    $img.addClass('d-none').attr('src', '');
    if ($in) $in.value = '';
    $wrap.removeClass('upload-success');
  });

  // Upload change handler
  $(document).on('change', '.upload-box input[type="file"]', function () {
    const fileInput = this;
    const file = fileInput.files && fileInput.files[0];
    if (!file) return;

    if (!file.type?.startsWith('image/')) { toastr.error('فقط فایل تصویری قابل آپلود است.'); fileInput.value = ''; return; }

    const maxBytes = HE_MAX_UPLOAD_MB * 1024 * 1024;
    if (file.size > maxBytes) {
      toastr.error(`حجم فایل ${heFormatBytes(file.size)} است. حداکثر مجاز ${HE_MAX_UPLOAD_MB} مگابایت می‌باشد.`);
      const $box = $(fileInput).closest('.upload-box');
      $box.removeClass('upload-success'); $box.find('.thumbnail').addClass('d-none').attr('src', '');
      $box.find('.progress').addClass('d-none').find('.progress-bar').css('width', '0%');
      fileInput.value = ''; return;
    }

    const $box = $(fileInput).closest('.upload-box');
    const $progress = $box.find('.progress'); const $bar = $progress.find('.progress-bar'); const $thumb = $box.find('.thumbnail');
    const fd = new FormData();
    fd.append('action', 'shec_step3'); fd.append('_nonce', shec_ajax.nonce); fd.append('_wpnonce', shec_ajax.nonce);
    fd.append('user_id', LS.get('userId')); fd.append('position', $box.data('position')); fd.append(fileInput.name, file);

    $progress.removeClass('d-none'); $bar.css('width', '0%');

    API.step3Upload(fd)
      .done(res => {
        if (res?.success) {
          const fileUrl = (res.data && (res.data.file || res.data)) || res.file || res.url || '';
          if (fileUrl) {
            const $wrap = $box.closest('.upload-wrap');
            $thumb.attr('src', fileUrl).removeClass('d-none'); $wrap.addClass('upload-success');

            const uploads = JSON.parse(LS.get('uploadedPics', '{}')); uploads[fileInput.name] = fileUrl; LS.set('uploadedPics', JSON.stringify(uploads));
            if ($wrap.is('[data-pos="front"]')) { try { localStorage.setItem('shec_front', fileUrl); } catch {} $wrap.removeClass('is-required-error'); $wrap.find('.err-msg').remove(); }
          }
          $progress.addClass('d-none'); $bar.css('width', '0%');
        } else {
          toastr.error(res?.message || 'خطا در آپلود');
          $progress.addClass('d-none'); $bar.css('width', '0%'); $thumb.addClass('d-none').attr('src', ''); $box.closest('.upload-wrap').removeClass('upload-success'); fileInput.value = '';
        }
      })
      .fail(() => {
        toastr.error('خطا در ارتباط آپلود');
        $progress.addClass('d-none'); $bar.css('width', '0%'); $thumb.addClass('d-none').attr('src', ''); $box.closest('.upload-wrap').removeClass('upload-success'); fileInput.value = '';
      });
  });

  // Step 3 submit → Step 4
  $('#form-step-3').on('submit', function (e) {
    e.preventDefault();
    let frontUrl = null; try { frontUrl = localStorage.getItem('shec_front'); } catch {}
    if (!frontUrl) {
      toastr.error('لطفاً «تصویر روبه‌رو» را بارگذاری کنید.');
      const $front = $('.upload-wrap.shec-upload-front').addClass('is-required-error');
      if (!$front.find('.err-msg').length) $front.find('.upload-box').append('<div class="err-msg">تصویر روبه‌رو الزامی است</div>');
      $('html,body').animate({ scrollTop: $front.offset().top - 120 }, 400);
      return;
    }
    UI.goToStep(4);
  });

  // Step 4 medical
  $(document).on('change', 'input[name="has_medical"]', function () {
    $('#has-medical-group').removeClass('error shake');
    $('input[name="has_medical"]').parent().removeClass('active');
    if ($(this).is(':checked')) $(this).parent().addClass('active');
    const show = $(this).val() === 'yes';
    $('#medical-fields').toggleClass('d-none', !show).removeClass('error shake');
    if (!show) { $('#medical-fields select[name="scalp_conditions"]').val(''); $('#medical-fields select[name="other_conditions"]').val(''); }
  });
  $(document).on('change', 'input[name="has_meds"]', function () {
    $('#has-meds-group').removeClass('error shake');
    $('input[name="has_meds"]').parent().removeClass('active');
    if ($(this).is(':checked')) $(this).parent().addClass('active');
    const show = $(this).val() === 'yes';
    $('#meds-fields').toggleClass('d-none', !show).removeClass('error shake');
    if (!show) $('#meds-fields input[name="meds_list"]').val('');
  });

  $('#form-step-4').on('submit', function (e) {
    e.preventDefault();

    const hasMedical = $('input[name="has_medical"]:checked').val() || '';
    const hasMeds    = $('input[name="has_meds"]:checked').val() || '';
    if (!hasMedical) return Utils.errorScroll('#has-medical-group', 'لطفاً به سؤال «آیا به بیماری خاصی مبتلا هستید؟» پاسخ دهید.');
    if (!hasMeds)    return Utils.errorScroll('#has-meds-group', 'لطفاً به سؤال «آیا در حال حاضر داروی خاصی مصرف می‌کنید؟» پاسخ دهید.');

    if (hasMedical === 'yes') {
      const scalp = ($('#medical-fields select[name="scalp_conditions"]').val() || '').trim();
      const other = ($('#medical-fields select[name="other_conditions"]').val() || '').trim();
      if (!scalp && !other) return Utils.errorScroll('#medical-fields', 'لطفاً یکی از گزینه‌های بیماری را انتخاب کنید (یا «هیچکدام» را بزنید).');
    }
    if (hasMeds === 'yes') {
      const meds = ($('#meds-fields input[name="meds_list"]').val() || '').trim();
      if (!meds) return Utils.errorScroll('#meds-fields', 'اگر دارو مصرف می‌کنید، نام دارو را وارد کنید.', '#meds-fields input[name="meds_list"]');
    }

    const payload = Object.assign(
      $('#form-step-4').serializeArray().reduce((o, f) => (o[f.name] = f.value, o), {}),
      { user_id: LS.get('userId') }
    );
    const $btn = $(this).find('button[type="submit"]').prop('disabled', true);

    API.step4(payload).done(res => {
      if (res?.success) {
        __aiQOnce = false;
        UI.goToStep(5);
        UI.step5ShowLoader();
        const p = loadAiQuestions(true);
        UI.waitForAiOrTimeout(p, 10000).always(() => UI.step5HideLoader());
      } else toastr.error(res?.message || 'خطا در مرحله ۴');
    }).fail(() => toastr.error('خطا در سرور مرحله ۴'))
      .always(() => $btn.prop('disabled', false));
  });

  // Step 5 toggles
  $(document).on('change', 'input[name^="followup_"]', function () {
    const name = $(this).attr('name');
    $(`input[name="${name}"]`).parent().removeClass('active');
    if ($(this).is(':checked')) $(this).parent().addClass('active');
  });

  // Step 5 → finalize
  $(document).on('submit', '#form-step-5', function (e) {
    e.preventDefault();

    const uid = parseInt(LS.get('userId') || 0, 10);
    if (!uid) return toastr.error('شناسه کاربر پیدا نشد. لطفاً فرم را از ابتدا شروع کنید.');

    // Answers
    const answers = [];
    let missingIdx = 0;
    $('#ai-questions-list .followup-item').each(function () {
      const idx = $(this).data('idx');
      const val = $(`input[name="followup_${idx}"]:checked`).val() || '';
      if (!val && !missingIdx) missingIdx = idx;
      answers.push(val);
    });
    if (missingIdx) return Utils.errorScroll(`#ai-questions-list .followup-item[data-idx="${missingIdx}"]`, 'لطفاً به همهٔ سؤالات پاسخ دهید.');

    // Contact
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

    // cleanup helper
    window.SHEC_clearFormState = function () {
      try {
        for (let i = localStorage.length - 1; i >= 0; i--) {
          const k = localStorage.key(i);
          if (/^(shec_|SHEC_|hair_)/i.test(k)) localStorage.removeItem(k);
        }
        localStorage.removeItem('shec_last_public');
        sessionStorage.removeItem('shec_force_reset');
      } catch {}
    };

    UI.finalStepShowLoader();

    API.step5(payloadContact).done(res => {
      if (!res?.success) {
        toastr.error(Utils.wpUnwrap(res)?.message || 'خطا در ذخیره اطلاعات تماس');
        $btn.prop('disabled', false);
        return;
      }

      UI.finalStepShowLoader(); // keep animation up to finalize

      const req = API.finalize(uid, answers);
      UI.waitForAiOrTimeout(req, 10000).done(function () {
        req.done(fin => {
          const d = Utils.wpUnwrap(fin);
          if (!fin?.success) {
            toastr.error(d?.message || 'خطا در نهایی‌سازی هوش مصنوعی');
            UI.finalStepHideLoader();
            $btn.prop('disabled', false);
            return;
          }

          const publicUrl = d.public_url || d.data?.public_url;
          if (publicUrl) {
            LS.clear();
            UI.finalStepHideLoader();
            window.SHEC_ALLOW_STEP6 = false;
            window.location.replace(publicUrl);
            return;
          }

          console.warn('[SHEC] public_url missing; fallback to local render.');
          UI.finalStepHideLoader();
          window.SHEC_renderFinal?.(d);
          $btn.prop('disabled', false);

        }).fail(() => {
          UI.finalStepHideLoader();
          toastr.error('خطای ارتباط در نهایی‌سازی');
          $btn.prop('disabled', false);
        });
      }).fail(() => {
        UI.finalStepHideLoader();
        toastr.error('خطا در ارتباط با سرور (Final)');
        $btn.prop('disabled', false);
      });

    }).fail(() => {
      toastr.error('خطا در ارتباط با سرور');
      $btn.prop('disabled', false);
    });
  });

  /* ========= Token result page loader ========= */
  (function shecLoadResultByTokenOnce($) {
    function shecLoadResultByToken() {
      const t = new URLSearchParams(location.search).get('t');
      const $box = $('#ai-result-box');
      if (!t) { $box.text('❌ توکن یافت نشد.'); return; }

      $.ajax({
        url: shec_ajax?.url || '/wp-admin/admin-ajax.php',
        method: 'POST',
        data: { action: 'shec_result_by_token', t, nonce: shec_ajax?.nonce || '' }
      })
      .done(resp => {
        const payload = (resp && (resp.data || (resp.success ? resp : null)));
        if (!resp?.success || !payload) { $box.text('❌ داده‌ای یافت نشد.'); return; }
        if (typeof payload.ai_result === 'string') { try { payload.ai_result = JSON.parse(payload.ai_result); } catch {} }
        window.SHEC_renderFinal ? window.SHEC_renderFinal(payload) : $box.text('❌ تابع رندر یافت نشد (SHEC_renderFinal).');
      })
      .fail(xhr => {
        console.error('[SHEC] result_by_token:FAIL', xhr?.responseText);
        $box.text('❌ خطا در دریافت نتیجه.');
      });
    }

    $(function () {
      if (location.pathname.indexOf('/hair-result') !== -1) shecLoadResultByToken();
    });
  })(jQuery);

  /* ========= PDF (optional) ========= */
  async function shecBuildPdfA4(selector) {
    const root = document.querySelector(selector);
    if (!root) { console.error('[PDF] root not found'); return; }

    const clone = root.cloneNode(true);
    if (!clone.classList.contains('pdf-frame')) clone.classList.add('pdf-frame');
    clone.querySelectorAll('button, .btn, [data-no-pdf]').forEach(el => el.remove());
    clone.querySelectorAll('img').forEach(img => {
      try {
        const u = new URL(img.src, location.href);
        if (u.host !== location.host) { img.setAttribute('data-html2canvas-ignore', 'true'); img.style.display = 'none'; }
        else { img.setAttribute('crossorigin', 'anonymous'); }
      } catch {}
    });

    const style = document.createElement('style');
    style.textContent = `
      @font-face { font-family: 'Shabnam'; src: url('https://cdn.jsdelivr.net/gh/rastikerdar/shabnam-font@v5.0.1/dist/Shabnam.woff2') format('woff2'), url('https://cdn.jsdelivr.net/gh/rastikerdar/shabnam-font@v5.0.1/dist/Shabnam.woff') format('woff'); font-weight: 400; font-display: swap; }
      @font-face { font-family: 'Shabnam'; src: url('https://cdn.jsdelivr.net/gh/rastikerdar/shabnam-font@v5.0.1/dist/Shabnam-Bold.woff2') format('woff2'), url('https://cdn.jsdelivr.net/gh/rastikerdar/shabnam-font@v5.0.1/dist/Shabnam-Bold.woff') format('woff'); font-weight: 700; font-display: swap; }
      :root, body, * { font-family: Shabnam, Vazirmatn, Tahoma, sans-serif !important; }
      #proposal-pdf-root, #proposal-pdf-root * { letter-spacing: normal !important; word-spacing: normal !important; unicode-bidi: isolate; text-rendering: optimizeLegibility; }
    `;
    clone.prepend(style);

    const stage = document.createElement('div');
    stage.style.cssText = 'position:fixed;left:-10000px;top:0;background:#fff;z-index:-1;width:794px;';
    document.body.appendChild(stage);
    stage.appendChild(clone);

    try { await document.fonts?.ready; } catch {}

    let canvas;
    try {
      canvas = await html2canvas(clone, { backgroundColor: '#ffffff', scale: 2, useCORS: true, allowTaint: true, logging: false, removeContainer: true });
    } finally { document.body.removeChild(stage); }

    if (!canvas) return;

    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF('p', 'mm', 'a4');
    const pageW = pdf.internal.pageSize.getWidth();
    const pageH = pdf.internal.pageSize.getHeight();
    const margin = 14;

    const imgW = pageW - margin * 2;
    const pxPerPt = canvas.width / imgW;

    const pageCanvas = document.createElement('canvas');
    const pageCtx = pageCanvas.getContext('2d');

    const sliceHeightPx = Math.floor(((pageH - margin * 2) * pxPerPt));
    let rendered = 0, first = true;

    while (rendered < canvas.height) {
      const slice = Math.min(sliceHeightPx, canvas.height - rendered);
      pageCanvas.width = canvas.width; pageCanvas.height = slice;
      pageCtx.clearRect(0, 0, pageCanvas.width, pageCanvas.height);
      pageCtx.drawImage(canvas, 0, rendered, canvas.width, slice, 0, 0, canvas.width, slice);

      const imgData = pageCanvas.toDataURL('image/png');
      if (!first) pdf.addPage();
      pdf.addImage(imgData, 'PNG', margin, margin, imgW, (slice / pxPerPt));

      first = false; rendered += slice;
    }
    pdf.save('fakhraei-result.pdf');
  }
  $(document).on('click', '#download-pdf', e => { e.preventDefault(); shecBuildPdfA4('#proposal-pdf-root'); });

  /* ========= Prev & Reset ========= */
  $('.btn-prev').on('click', function () {
    const current = parseInt(LS.get('currentStep') || 1);
    UI.goToStep(Math.max(1, current - 1));
  });
  $(document).on('click', '#reset-form', function () {
    if (!confirm('آیا مطمئن هستید که می‌خواهید فرم را از ابتدا شروع کنید؟')) return;
    LS.clear();
    let href = this.getAttribute('data-reset-href') || shec_ajax?.calc_url || '/hair-graft-calculator/';
    window.location.assign(href || '/hair-graft-calculator/');
  });

  /* ========= Initial boot ========= */
  (function preloadPatternImages() {
    for (let i = 1; i <= 6; i++) new Image().src = `${shec_ajax.img_path}w${i}.png`;
    const savedGender = LS.get('gender') || 'male';
    UI.updateUploadHints(savedGender);
  })();

  function animateSections() {
    $('.shec-section').each(function () {
      if (this.getBoundingClientRect().top < window.innerHeight - 50) $(this).addClass('visible');
    });
  }
  $(window).on('scroll load', animateSections);
  animateSections();
});
