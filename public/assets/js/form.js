/* public/assets/js/form.js
 * Fakhraei Hair Graft Calculator – Clean build
 * - ساختاردهی ماژولی
 * - حذف کدهای تکراری/بلااستفاده
 * - کامنت‌گذاری منسجم
 */

/* ========= Bootstrap ========= */
var $ = jQuery.noConflict();
/* ==== i18n bootstrap (must be first) ==== */
(function (w) {
  if (w.__ && w.sprintf) return; // already set

  var wpI18n = w.wp && w.wp.i18n;
  var TD = (w.shec_ajax && w.shec_ajax.textdomain) || 'shec';

  function fallbackSprintf(str) {
    var args = Array.prototype.slice.call(arguments, 1), i = 0;
    return String(str).replace(/%((\d+)\$)?s/g, function(){ return String(args[i++]); });
  }

  w.SHEC_I18N = w.SHEC_I18N || {};
  w.SHEC_I18N.TD = w.SHEC_I18N.TD || TD;
  w.SHEC_I18N.__ = w.SHEC_I18N.__ ||
    (wpI18n && typeof wpI18n.__ === 'function' ? function (s) { return wpI18n.__(s, TD); } : function (s) { return s; });
  w.SHEC_I18N.sprintf = w.SHEC_I18N.sprintf ||
    (wpI18n && typeof wpI18n.sprintf === 'function' ? wpI18n.sprintf : fallbackSprintf);

  // expose legacy globals so code like __('…') works everywhere
  w.__ = w.SHEC_I18N.__;
  w.sprintf = w.SHEC_I18N.sprintf;
})(window);

$(function () {
  
  console.log("📌 form.js loaded!", window.shec_ajax);
  if (!window.shec_ajax || !shec_ajax.nonce) {
    console.error("[SHEC] shec_ajax.nonce is missing! Nonce check may fail.");
  }
  /* ========= LocalStorage utils ========= */
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
    diabetes:
      'If you have diabetes, hair transplantation is only possible when the disease is fully controlled. Diabetes may affect healing and increase the risk of post-operative infection. A written approval from your treating physician is required.',
    coagulation:
      'Hair transplantation in patients with coagulation disorders can be challenging. It may increase bleeding during the procedure and affect graft survival. A written approval from your physician is required.',
    cardiac:
      'With cardiovascular disease, hair transplantation is only possible when the condition is fully controlled. Local anesthesia and recovery may carry higher risk. A physician’s written approval is required.',
    thyroid:
      'With thyroid disorders, hair transplantation is possible if hormone levels are balanced; uncontrolled status can affect hair growth and graft survival. A physician’s written approval is required.',
    immunodef:
      'In immunodeficiency (e.g., some HIV cases or during chemotherapy), hair transplantation is usually not recommended; healing is slower and complications are more likely. Final decision depends on specialist evaluation and physician approval.',
    autoimmune:
      'In autoimmune diseases, depending on type and activity, transplantation may be difficult or inadvisable and can affect graft acceptance. Specialist evaluation and physician approval are required.',
  };

  const HE_SCALP_WARNINGS = {
    active_infection:
      'If you have an active scalp infection, transplantation cannot be performed immediately; it must be fully treated first to lower complication risk and improve graft survival.',
    psoriasis:
      'If psoriasis is active—especially if extensive—control/treatment is required first, then a decision about transplantation can be made.',
    fungal_derm:
      'Before considering transplantation, seborrheic dermatitis/fungal infection should be controlled; active inflammation lowers the chance of success.',
    folliculitis:
      'With folliculitis, infection/inflammation should be treated first; then transplantation can be considered.',
    areata:
      'Transplantation is not recommended during the active phase of alopecia areata; the disease should be inactive first.',
    scarring_alo:
      'Scarring alopecia can reduce transplant success; decisions are made after specialist evaluation and ensuring lesion stability.',
    scar:
      'Existing scalp scars may reduce the success rate; vascularity and the area must be evaluated first.',
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
    if (/none|هیچ|נדارم/.test(t)) return null;
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
    title.textContent = 'Important notes before hair transplant (based on your medical status):';
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
      // Keep internal labels (fa) for logic, show English to users
      const POS_LABELS_EN = {
        'روبرو': 'Front',
        'پشت سر': 'Back',
        'فرق سر': 'Crown',
        'کنار سر': 'Side',
        'بالای سر': 'Top',
      };
      const positions = {
        male:   ['روبرو', 'پشت سر', 'فرق سر', 'کنار سر'],
        female: ['روبرو', 'بالای سر', 'فرق سر']
      };
      const container = $('#upload-zones').empty();
      positions[gender].forEach((label, index) => {
        const isLastFemale = (gender === 'female' && index === positions[gender].length - 1);
        const colClass = isLastFemale ? 'col-12' : 'col-12 col-lg-6';
        const isFront = (label === 'روبرو');
        const displayLabel = POS_LABELS_EN[label] || label;
        container.append(`
          <div class="${colClass}">
            <div class="upload-wrap${isFront ? ' shec-upload-front' : ''}" ${isFront ? 'data-pos="front"' : ''}>
              <label class="upload-box" data-index="${index}" data-position="${label}">
                <span class="d-block fw-bold mb-2">${displayLabel}</span>
                <input type="file" name="pic${index + 1}" accept="image/*">
                <div class="progress d-none"><div class="progress-bar" style="width:0%;"></div></div>
                <img src="" class="thumbnail d-none">
              </label>
              <div class="upload-overlay"><button type="button" class="remove-btn" aria-label="Remove image">🗑</button></div>
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
            <p class="d-block mb-2 fw-bold">Please answer a few short questions:</p>
            <div id="ai-questions-list"></div>
          </div>
        `);
      }
    },

    /* ---- Step5 loaders ---- */
    __aiTimers: [],

    step5ShowLoader() {
      const $loader  = $('#step5-loader');
      const $content = $('#step5-content');

      $('body').addClass('shec-lock');
      $('#form-step-5, #ai-questions-box').addClass('ohide').removeClass('oshow');
      $content.hide();
      $loader.css({ display: 'flex', opacity: 1 });

      SHEC_Circle.start('#step5-loader', 10000);

      let $text = $('#ai-loader-text');
      if (!$text.length) {
        $text = $('<div id="ai-loader-text" class="ai-loader-text"></div>');
        $loader.append($text);
      }

      const messages = [
        'Analyzing gender and hair-loss pattern',
        'Reviewing your concerns',
        'Reviewing declared conditions and medications',
        'Generating 4 personalized questions with Fakhraei AI',
        'Finalizing...'
      ];

      this.__aiTimers.forEach(clearTimeout);
      this.__aiTimers = [];
      $text.stop(true, true).css('opacity', 1).text(messages[0]);

      const schedule = (msg, delay) => {
        const id = setTimeout(() => {
          $text.fadeOut(250, () => $text.text(msg).fadeIn(250));
        }, delay);
        this.__aiTimers.push(id);
      };
      schedule(messages[1], 2000);
      schedule(messages[2], 4000);
      schedule(messages[3], 6000);
      schedule(messages[4], 8000);
    },

    step5HideLoader() {
      this.__aiTimers.forEach(clearTimeout);
      this.__aiTimers = [];

      SHEC_Circle.to100('#step5-loader');

      $('#step5-loader').animate({ opacity: 0 }, 200, function () {
        $(this).css({ display: 'none', opacity: 1 });
        $('body').removeClass('shec-lock');
      });

      $('#step5-content').fadeIn(200);
      $('#form-step-5, #ai-questions-box').addClass('oshow').removeClass('ohide');
    },

    waitForAiOrTimeout(promise, minMs = 10000) {
      const dfd = jQuery.Deferred();
      const t0  = Date.now();
      const p   = (promise && typeof promise.then === 'function') ? promise : jQuery.Deferred().resolve().promise();
      p.always(() =>
        setTimeout(() => dfd.resolve(), Math.max(0, minMs - (Date.now() - t0)))
      );
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
        'Reviewing your answers...',
        'Analyzing your hair-loss type...',
        'Finding the best transplant method for you...',
        'Estimating the approximate number of needed hairs...',
        'Preparing the final result...'
      ];
      $text.stop(true, true).css('opacity', 1).text(msgs[0]); $overlay.fadeIn(150);
      SHEC_Circle.start('#final-step-loader', 10000);

      const schedule = (msg, delay) => {
        const id = setTimeout(() => { $text.fadeOut(200, () => $text.text(msg).fadeIn(200)); }, delay);
        this.finalTimers.push(id);
      };
      schedule(msgs[1], 2000); schedule(msgs[2], 4000); schedule(msgs[3], 6000); schedule(msgs[4], 8000);
    },
    finalStepHideLoader() { this.finalTimers.forEach(clearTimeout); this.finalTimers = []; SHEC_Circle.to100('#final-step-loader'); $('#final-step-loader').fadeOut(250); },

    /* ---- AI Questions UI ---- */
    renderQuestions(qs) {
      const $list = $('#ai-questions-list').empty();
      (qs || []).forEach((q, i) => {
        const idx = i + 1;
        $list.append(`
          <div class="followup-item mb-3" data-idx="${idx}">
            <div class="d-block mb-2 fw-bold">${q}</div>
            <div class="toggle-group">
              <label class="toggle-option"><input type="radio" name="followup_${idx}" value="yes" hidden><span>Yes</span></label>
              <label class="toggle-option"><input type="radio" name="followup_${idx}" value="no" hidden><span>No</span></label>
            </div>
          </div>
        `);
      });
    },
    renderQuestionsFallback() {
      this.renderQuestions([
        'Is there a family history of hair loss?',
        'Has your hair loss worsened over the last 12 months?',
        'Do you currently smoke (cigarettes or hookah)?',
        'Have your sleep or stress levels worsened in recent months?'
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
      try { if (req?.state?.() === 'pending') toastr.info('The AI result is taking a bit longer. Please wait...'); } catch {}
    }, 7000);

    return req
      .done(res => {
        if (!res || res.success !== true) {
          console.warn('[AIQ] invalid payload:', res);
          UI.renderQuestionsFallback();
          toastr.info('Smart questions are temporarily unavailable; general questions are shown instead.');
          return;
        }
        const qs = Array.isArray(res.data?.questions) ? res.data.questions.filter(Boolean) : [];
        (qs.length === 4)
          ? UI.renderQuestions(qs)
          : (UI.renderQuestionsFallback(), toastr.info('Service output was invalid; general questions are shown instead.'));
      })
      .fail(jq => {
        const snippet = (jq && jq.responseText) ? jq.responseText.slice(0, 300) : '';
        console.error('[AIQ] AJAX FAIL', { status: jq.status, responseSnippet: snippet });
        if (jq.status === 403 && /nonce/i.test(snippet)) toastr.error('Security session expired. Please refresh the page.');
        else toastr.warning('Could not connect to the questions service; general questions are shown instead.');
        UI.renderQuestionsFallback();
      })
      .always(() => { clearTimeout(pendingTimer); __aiQOnce = true; });
  }

/* ========= Final renderer (shared: step6 + token page) ========= */
  window.SHEC_renderFinal = function (fin) {
    // --- Parse payload ---
    const payload = (fin && fin.user) ? fin : Utils.wpUnwrap(fin);
    if (!payload || !payload.user) {
      return $('#ai-result-box').html('<div style="padding:24px">No result data found.</div>');
    }

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
    const graftByTable = graftN ? Number(graftN).toLocaleString('en-US') : '—';

    // --- Medical (clean join/split) ---
    const splitEn = (str) => {
      if (!str || typeof str !== 'string') return [];
      // normalize zero-widths and collapse punctuation to comma-space
      str = str.replace(/[\u200c\u200e\u200f]/g, '')
              .replace(/\s*[,،;]\s*|\r?\n+/g, ', ')
              .replace(/\s{2,}/g, ' ')
              .replace(/^(\s*,\s*)+|(\s*,\s*)+$/g, '');
      return str.split(/\s*,\s*/g).map(s => s.trim()).filter(Boolean);
    };
    const joinEn = (arr) => (Array.isArray(arr) && arr.length) ? arr.join(', ') : '—';

    const med         = u.medical || {};
    const drugsLabels = (med.has_meds === 'yes')
      ? (splitEn(med.meds_list).length ? splitEn(med.meds_list) : ['Medication in use'])
      : ['No medication in use'];
    const dermLabels  = splitEn(med.scalp_conditions);
    const sysLabels   = splitEn(med.other_conditions);
    const showMedical = (drugsLabels.length || dermLabels.length || sysLabels.length) > 0;
    const warnHostId  = 'he-medical-warning-wrap-' + Date.now();

    // --- AI blob ---
    let ai = {};
    try { ai = (typeof d.ai_result === 'string') ? JSON.parse(d.ai_result || '{}') : (d.ai_result || {}); } catch { ai = {}; }

    // --- Display constants ---
    const methodTxt = 'FIT';
    const duration  = 'Two sessions of eight hours';
    const logoUrl   = 'https://fakhraei.clinic/wp-content/uploads/2024/02/Group-1560-300x300.png.webp';

    // --- Pattern explain fallback ---
    function mapFemaleLudwig(st) { if (!st) return null; if (st <= 2) return 'Ludwig I'; if (st <= 4) return 'Ludwig II'; return 'Ludwig III'; }
    const fallbackPatternExplain = (() => {
      if (!stage) return { label: '—', what_it_is: '', why_happens: '', note: '', fit_ok: true };
      if (gender === 'female') {
        const label = mapFemaleLudwig(stage) || 'Ludwig';
        const what  = 'A diffuse thinning pattern across the central scalp that expands as it progresses.';
        const why   = 'Often related to hormonal and genetic factors; stress and lifestyle can contribute.';
        const note  = (label === 'Ludwig I') ? 'Transplant is usually not required at this stage; maintenance therapy is recommended.' : '';
        return { label, what_it_is: what, why_happens: why, note, fit_ok: true };
      } else {
        const label = 'Norwood ' + stage;
        const what  = (stage >= 5)
          ? 'Frontal and vertex involvement with a thinning bridge; broader harvesting is needed to restore the hairline and density.'
          : 'Recession of the frontal hairline or localized thinning that extends with progression.';
        const why   = 'Typically genetic with follicular sensitivity to androgens; stress and lifestyle may modulate severity.';
        const note  = (stage === 1) ? 'Transplant is usually not required at this stage; maintenance therapy is recommended.' : '';
        return { label, what_it_is: what, why_happens: why, note, fit_ok: true };
      }
    })();
    const patExplain = Object.assign({}, fallbackPatternExplain, (ai.pattern_explain || {}));

    // --- Concern helper ---
    const concernBox = (ai.concern_box && ai.concern_box.trim())
      ? ai.concern_box.trim()
      : (() => {
          const c = concern.toString();
          // Try to detect common concerns in both EN/FA to be robust
          if (/(cost|price|هزینه|قیمت)/i.test(c)) return 'We understand cost matters to you. We’ll provide a transparent, reasonable estimate.';
          if (/(pain|درد)/i.test(c))               return 'Don’t worry about pain; we use local anesthesia and continuous monitoring for a tolerable experience.';
          if (/(downtime|recovery|نقاهت)/i.test(c))return 'Recovery is short and manageable; we’ll give you step-by-step guidance.';
          if (/(duration|time|طول|زمان)/i.test(c)) return 'Hair growth is gradual; visible changes begin in the first months.';
          return 'Your concern is valid. The treatment path is clear, and we’re with you throughout.';
        })();

    // --- Followups & tips ---
    const followupsData = ((u.ai || {}).followups) || {};
    const answersArr    = Array.isArray(followupsData.answers) ? followupsData.answers : [];
    const qaItems       = Array.isArray(followupsData.qa) && followupsData.qa.length
      ? followupsData.qa
      : (Array.isArray(followupsData.questions) ? followupsData.questions.map((q, i) => ({ q, a: (answersArr[i] || '') })) : []);

    // Normalize yes/no to English label while keeping CSS classes (ans-yes/ans-no)
    const yesNoLabel = v => (/^(yes|true|بله)$/i.test(String(v))) ? 'Yes'
                          : (/^(no|false|خیر)$/i.test(String(v)) ? 'No' : (String(v || '').trim() || '—'));

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
          const pick = re => {
            const item = qaItems.find(x => re.test(String(x.q || '')));
            return item ? yesNoLabel(item.a) : '';
          };
          const smoking = pick(/smok|سیگار|قلیان/i);
          const stress  = pick(/stress|استرس/i);
          const sleep   = pick(/sleep|خواب/i);
          const worse   = pick(/worse|worsen|increase|شدت|بدتر|افزایش/i);
          const infect  = pick(/infection|inflamm|scalp|عفونت|التهاب|پوست\s*سر/i);

          const s = [];
          s.push('🤖 Based on your answers, here is your pre-transplant preparation plan: ');
          if (smoking === 'Yes') s.push('Stop smoking 10 days before until one week after the procedure. ');
          if (sleep   === 'No')  s.push('Improve sleep hygiene (consistent schedule, reduce evening caffeine). ');
          if (stress  === 'Yes') s.push('Daily deep-breathing/walking to manage stress. ');
          if (worse   === 'Yes') s.push('Don’t postpone treatment decisions; start maintenance therapy. ');
          if (infect  === 'Yes') s.push('Treat scalp inflammation/infection first. ');
          s.push('Follow scalp hygiene and care instructions to optimize recipient quality.');
          return s.join('');
        })();

    const extraText = (ai.extra_analysis?.trim()) || (ai.analysis?.trim()) || '';

    const qaHtml = `
      <div class="ai-section-title" style="margin-top:18px;">Result of AI-generated questions</div>
      <ol class="ai-qa">
        ${qaItems.map((item, i) => {
          const tip = getTipFor(item.q, i);
          const ans = yesNoLabel(item.a);
          const ansClass = /^(Yes)$/i.test(ans) ? 'ans-yes' : (/^(No)$/i.test(ans) ? 'ans-no' : '');
          return `
            <li class="ai-qa-item">
              <div class="ai-qa-head"><div class="num">${i + 1}</div><div class="ai-qa-q">❓ ${(item.q || '').trim()}</div></div>
              <div class="ai-qa-a"><span class="label">Answer:</span><span class="ans-pill ${ansClass}">${ans}</span></div>
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
          <div class="ai-title">Your personalized hair transplant plan is ready!</div>
          <div class="ai-check">✓</div>
          <div class="ai-sub">Thank you for your trust</div>
        </div>

        <div class="ai-section-title">Your personal information</div>
        <div class="ai-chip-row">
          <div class="ai-chip"><span class="ai-chip-label">Full name</span><div class="ai-chip-value">${full}</div></div>
          <div class="ai-chip"><span class="ai-chip-label">Hair loss pattern</span><div class="ai-chip-value">${pattern ?? '—'}</div></div>
          <div class="ai-chip"><span class="ai-chip-label">Age range</span><div class="ai-chip-value">${ageVal}</div></div>
        </div>

        <div class="ai-note" style="text-align:justify">
          🤖 <strong>${patExplain.label || '—'}</strong> — ${patExplain.what_it_is || ''}<br>
          ${patExplain.why_happens ? (patExplain.why_happens + '<br>') : ''}
          ${patExplain.fit_ok ? 'This pattern is treatable with FIT/FUE at Fakhraei Clinic.' : ''} ${patExplain.note ? ('<br>'+patExplain.note) : ''}
        </div>

        <div class="ai-section-title" style="margin-top:18px;">Your main concern</div>
        <div class="ai-content" style="margin-bottom:8px">${concern}</div>
        <div class="ai-note" style="text-align:justify">🤖 ${concernBox}</div>

        <hr class="ai-divider"/>

        <div class="ai-stats">
          <div class="ai-stat"><div class="ai-stat-label">Estimated duration</div><div class="ai-stat-value">${duration}</div></div>
          <div class="ai-stat ai-stat--accent"><div class="ai-stat-label">Recommended technique</div><div class="ai-stat-value">${methodTxt}</div></div>
          <div class="ai-stat"><div class="ai-stat-label">Estimated hair count</div><div class="ai-stat-value">${graftByTable}</div></div>
        </div>

        ${showMedical ? `
          <div class="ai-section-title" style="margin-top:22px;">Recorded medical status</div>
          <div class="ai-stats">
            <div class="ai-stat"><div class="ai-stat-label">Medication in use</div><div class="ai-stat-value">${joinEn(drugsLabels)}</div></div>
            <div class="ai-stat"><div class="ai-stat-label">Scalp condition(s)</div><div class="ai-stat-value">${joinEn(dermLabels)}</div></div>
            <div class="ai-stat"><div class="ai-stat-label">Systemic condition(s)</div><div class="ai-stat-value">${joinEn(sysLabels)}</div></div>
          </div>
          <div id="${warnHostId}"></div>
        ` : ''}

        ${qaHtml}

        <div class="ai-section-title" style="margin-top:18px;">Summary of answers & personalized tips</div>
        <div class="ai-note" style="text-align:justify">${followupSummary}</div>

        ${extraText ? `
          <div class="ai-section-title" style="margin-top:18px;">Additional notes</div>
          <div class="ai-note" style="text-align:justify">🤖 ${extraText}</div>
        ` : ''}
      </div>
    `);

    // Medical warnings (red cards) – uses the English strings defined in HE_WARNINGS/HE_SCALP_WARNINGS
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

    if (!gender) return toastr.error('Please select your gender.');
    if (!age || !validAges.includes(age)) return toastr.error('Please choose a valid age range.');
    if (!confidence) return toastr.error('Please select your confidence level.');
    if (!/^09\d{9}$/.test(mobile)) return toastr.error('Enter a valid mobile number (e.g., 09xxxxxxxxx).');

    const payload = { user_id: LS.get('userId') || 0, gender, age, mobile, confidence };
    const $btn = $(this).find('button[type="submit"]').prop('disabled', true);

    API.step1(payload)
      .done(res => {
        const d = Utils.wpUnwrap(res);
        if (res?.success) {
          LS.set('userId', d.user_id); LS.set('gender', gender); LS.set('currentStep', 2); UI.goToStep(2);
        } else toastr.error(d?.message || 'Error submitting information.');
      })
      .fail(() => toastr.error('Server communication error.'))
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
    if (!loss_pattern) return toastr.error('Please select a hair loss pattern.');
    API.step2({ user_id: uid, loss_pattern })
      .done(res => res.success ? UI.goToStep(3) : toastr.error(res.message || 'Error in step 2.'))
      .fail(() => toastr.error('Server error in step 2.'));
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

    if (!file.type?.startsWith('image/')) { toastr.error('Only image files can be uploaded.'); fileInput.value = ''; return; }

    const maxBytes = HE_MAX_UPLOAD_MB * 1024 * 1024;
    if (file.size > maxBytes) {
      toastr.error(`File size is ${heFormatBytes(file.size)}. The maximum allowed is ${HE_MAX_UPLOAD_MB} MB.`);
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
          toastr.error(res?.message || 'Upload error.');
          $progress.addClass('d-none'); $bar.css('width', '0%'); $thumb.addClass('d-none').attr('src', ''); $box.closest('.upload-wrap').removeClass('upload-success'); fileInput.value = '';
        }
      })
      .fail(() => {
        toastr.error('Upload connection error.');
        $progress.addClass('d-none'); $bar.css('width', '0%'); $thumb.addClass('d-none').attr('src', ''); $box.closest('.upload-wrap').removeClass('upload-success'); fileInput.value = '';
      });
  });

  // Step 3 submit → Step 4
  $('#form-step-3').on('submit', function (e) {
    e.preventDefault();
    let frontUrl = null; try { frontUrl = localStorage.getItem('shec_front'); } catch {}
    if (!frontUrl) {
      toastr.error('Please upload the "front" photo.');
      const $front = $('.upload-wrap.shec-upload-front').addClass('is-required-error');
      if (!$front.find('.err-msg').length) $front.find('.upload-box').append('<div class="err-msg">Front photo is required</div>');
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
    if (!hasMedical) return Utils.errorScroll('#has-medical-group', 'Please answer: "Do you have any medical conditions?"');
    if (!hasMeds)    return Utils.errorScroll('#has-meds-group', 'Please answer: "Are you currently taking any medications?"');

    if (hasMedical === 'yes') {
      const scalp = ($('#medical-fields select[name="scalp_conditions"]').val() || '').trim();
      const other = ($('#medical-fields select[name="other_conditions"]').val() || '').trim();
      if (!scalp && !other) return Utils.errorScroll('#medical-fields', 'Please select at least one condition (or choose "None").');
    }
    if (hasMeds === 'yes') {
      const meds = ($('#meds-fields input[name="meds_list"]').val() || '').trim();
      if (!meds) return Utils.errorScroll('#meds-fields', 'If you take medication, please enter the name.', '#meds-fields input[name="meds_list"]');
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
      } else toastr.error(res?.message || 'Error in step 4.');
    }).fail(() => toastr.error('Server error in step 4.'))
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
    if (!uid) return toastr.error('User ID not found. Please start the form from the beginning.');

    // Answers
    const answers = [];
    let missingIdx = 0;
    $('#ai-questions-list .followup-item').each(function () {
      const idx = $(this).data('idx');
      const val = $(`input[name="followup_${idx}"]:checked`).val() || '';
      if (!val && !missingIdx) missingIdx = idx;
      answers.push(val);
    });
    if (missingIdx) return Utils.errorScroll(`#ai-questions-list .followup-item[data-idx="${missingIdx}"]`, 'Please answer all questions.');

    // Contact
    const first_name = ($('input[name="first_name"]').val() || '').trim();
    const last_name  = ($('input[name="last_name"]').val()  || '').trim();
    const state      = ($('input[name="state"]').val()      || '').trim();
    const city       = ($('input[name="city"]').val()       || '').trim();
    const social     = $('input[name="social"]:checked').val() || '';
    if (!first_name || !last_name) return toastr.error('Please enter your first and last name.');
    if (!state || !city)           return toastr.error('Please enter your state and city.');
    if (!social)                   return toastr.error('Please choose a contact method (Call/WhatsApp).');

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
        toastr.error(Utils.wpUnwrap(res)?.message || 'Error saving contact information.');
        $btn.prop('disabled', false);
        return;
      }

      UI.finalStepShowLoader(); // keep animation up to finalize

      const req = API.finalize(uid, answers);
      UI.waitForAiOrTimeout(req, 10000).done(function () {
        req.done(fin => {
          const d = Utils.wpUnwrap(fin);
          if (!fin?.success) {
            toastr.error(d?.message || 'Error in AI finalization.');
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
          toastr.error('Connection error during finalization.');
          $btn.prop('disabled', false);
        });
      }).fail(() => {
        UI.finalStepHideLoader();
        toastr.error('Server communication error (final).');
        $btn.prop('disabled', false);
      });

    }).fail(() => {
      toastr.error('Server communication error.');
      $btn.prop('disabled', false);
    });
  });

  // ========= Token result page loader =========
  (function shecLoadResultByTokenOnce($) {
    function shecLoadResultByToken() {
      const t = new URLSearchParams(location.search).get('t');
      const $box = $('#ai-result-box');
      if (!t) { $box.text('❌ Token not found.'); return; }

      $.ajax({
        url: shec_ajax?.url || '/wp-admin/admin-ajax.php',
        method: 'POST',
        data: { action: 'shec_result_by_token', t, nonce: shec_ajax?.nonce || '' }
      })
      .done(resp => {
        const payload = (resp && (resp.data || (resp.success ? resp : null)));
        if (!resp?.success || !payload) { $box.text('❌ No data found.'); return; }
        if (typeof payload.ai_result === 'string') { try { payload.ai_result = JSON.parse(payload.ai_result); } catch {} }
        window.SHEC_renderFinal ? window.SHEC_renderFinal(payload) : $box.text('❌ Render function not found (SHEC_renderFinal).');
      })
      .fail(xhr => {
        console.error('[SHEC] result_by_token:FAIL', xhr?.responseText);
        $box.text('❌ Error fetching result.');
      });
    }

    $(function () {
      if (location.pathname.indexOf('/hair-result') !== -1) shecLoadResultByToken();
    });
  })(jQuery);

  // ========= PDF (optional) =========
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

  // ========= Prev & Reset =========
  $('.btn-prev').on('click', function () {
    const current = parseInt(LS.get('currentStep') || 1);
    UI.goToStep(Math.max(1, current - 1));
  });
  $(document).on('click', '#reset-form', function () {
    if (!confirm('Are you sure you want to restart the form?')) return;
    LS.clear();
    let href = this.getAttribute('data-reset-href') || shec_ajax?.calc_url || '/hair-graft-calculator/';
    window.location.assign(href || '/hair-graft-calculator/');
  });

  // ========= Initial boot =========
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

  /* ========= Progress Ring (guarded define) ========= */
  (function ensureCircle(){
    if (window.SHEC_Circle) return;
    const CIRC = 339.292; // 2πr for r=54
    const reg = new Map(); // dom -> state

    function query(dom){
      return {
        root:  document.querySelector(dom),
        fg:    document.querySelector(dom+' .ring-fg'),
        pctEl: document.querySelector(dom+' .ai-ring-label span'),
      };
    }
    function start(dom, durationMs){
      const els = query(dom);
      if (!els.root) return;
      stop(dom);

      const t0 = performance.now();
      function tick(now){
        const p = Math.min(1, (now - t0) / durationMs);
        const pct = Math.round(p * 100);
        if (els.fg)    els.fg.style.strokeDashoffset = (CIRC * (1 - p)).toFixed(2);
        if (els.pctEl) els.pctEl.textContent = pct;
        if (p < 1 && reg.get(dom)) reg.get(dom).raf = requestAnimationFrame(tick);
      }
      const st = { raf: requestAnimationFrame(tick) };
      reg.set(dom, st);
    }
    function stop(dom){
      const st = reg.get(dom);
      if (!st) return;
      cancelAnimationFrame(st.raf);
      reg.delete(dom);
    }
    function to100(dom){ // smooth finish to 100%
      const els = query(dom);
      if (!els.root) return;
      stop(dom);
      if (els.fg)    els.fg.style.strokeDashoffset = 0;
      if (els.pctEl) els.pctEl.textContent = 100;
    }

    window.SHEC_Circle = { start, stop, to100 };
  })();


});
    