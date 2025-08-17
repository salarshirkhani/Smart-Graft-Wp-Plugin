/* public/assets/js/form.js */
var $ = jQuery.noConflict();
$(function () {
  console.log("📌 form.js loaded!", shec_ajax);

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

  /* =========================
   * API
   * ========================= */
  const API = {
    post(action, data, dataType='json'){
      return $.post(shec_ajax.url, Object.assign({_nonce:shec_ajax.nonce, action}, data||{}), null, dataType);
    },
    step1(payload){ return this.post('shec_step1', payload); },
    step2(payload){ return this.post('shec_step2', payload); },
    step3Upload(formData){
      return $.ajax({ url:shec_ajax.url, type:'POST', data:formData, processData:false, contentType:false, dataType:'json' });
    },
    step4(payload){ return this.post('shec_step4', payload); },
    step5(payload){ return this.post('shec_step5', payload); },
    aiQuestions(user_id){ return this.post('shec_ai_questions', {user_id}); },
    finalize(user_id, answers){ return this.post('shec_finalize', {user_id, answers}); },
  };

  /* =========================
   * UI helpers
   * ========================= */
const UI = {
  setProgress(step){
    $('#step-current').text(step);
    $('#progress-bar').css('width', Math.floor((step/6)*100) + '%');
  },

  goToStep(step){
    $('#step2-loader').hide(); // مطمئن: لودر step2 خاموش
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
    if (step === 6) LS.del('currentStep');
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
      container.append(`
        <div class="col-12 col-lg-6">
          <label class="upload-box" data-index="${index}" data-position="${label}">
            <span class="d-block fw-bold mb-2">${label}</span>
            <input type="file" name="pic${index+1}" accept="image/*">
            <div class="progress d-none"><div class="progress-bar" style="width:0%;"></div></div>
            <img src="" class="thumbnail d-none">
          </label>
        </div>
      `);
    });
  },

  loadUploadedThumbs(){
    const uploads = JSON.parse(LS.get('uploadedPics','{}'));
    for (const name in uploads) {
      const url = uploads[name];
      const $box = $(`.upload-box input[name="${name}"]`).closest('.upload-box');
      if ($box.length) {
        $box.addClass('upload-success');
        $box.find('.thumbnail').attr('src', url).removeClass('d-none');
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
    $('.angles').css({
      display:'flex',
      justifyContent: (gender==='female') ? 'center' : 'space-between',
      gap:'12px'
    });
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

    $content.hide();
    $loader.show();

    // اگر عنصر متن وجود نداشت، بساز
    if (!$text.length) {
      $text = $('<div id="ai-loader-text" class="ai-loader-text"></div>');
      $loader.append($text);
    }

    const messages = [
      'در حال بررسی جنسیت و نوع ریزش مو',
      'در حال بررسی دغدغه و نگرانی شما',
      'در حال بررسی بیماری ها و داروهای نوشته شده',
      'در حال ایجاد 4 سوال اختصاصی برای شما توسط هوش مصنوعی فخرایی',
      'در حال آماده سازی نهایی ...' ,
    ];

    // هر بار که لودر شروع می‌شود، تایمرهای قبلی را پاک کن
    (UI.__aiTimers || []).forEach(clearTimeout);
    UI.__aiTimers = [];

    // پیام اول بلافاصله نمایش داده شود
    $text.stop(true, true).css('opacity', 1).text(messages[0]);

    // زمان‌بندی تغییر پیام‌ها: 2s, 4s, 6s
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
    // پاکسازی تمام تایمرها
    (UI.__aiTimers || []).forEach(clearTimeout);
    UI.__aiTimers = [];
    $('#step5-loader').fadeOut(300);
    $('#step5-content').fadeIn(200);
  },

  /**
   * تضمین می‌کند لودر «حداقل» minMs بماند
   * و بعد از اتمام Promise (مثل loadAiQuestions) جمع شود.
   * اگر promise سریع بود → صبر تا minMs.
   * اگر promise دیرتر بود → بلافاصله بعد از اتمام آن.
   */
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

// ===== Final step loader (Step 5 -> Step 6) =====
finalTimers: [],

ensureFinalLoaderDom(){
  // اگر نبود، داینامیک بساز (محض اطمینان)
  if (!$('#final-step-loader').length) {
    const tpl = `
      <div id="final-step-loader" class="ai-loader-overlay" style="display:none;">
        <div class="ai-loader-box">
        <div class="ai-spinner-img ai-scale-img">
          <img src="${shec_ajax.img_path}spinner.webp" alt="loading" />
        </div>  
        <div id="final-loader-text" class="ai-loader-text"></div>
        </div>
      </div>`;
    // سعی می‌کنیم داخل step-5 قرار دهیم؛ اگر نبود body
    const $host = $('#step-5').length ? $('#step-5') : $('body');
    $host.append(tpl);
  }
},

finalStepShowLoader(){
  UI.ensureFinalLoaderDom();
  const $overlay = $('#final-step-loader');
  const $text    = $('#final-loader-text');

  // پاک‌سازی تایمرهای قبلی
  UI.finalTimers.forEach(id => clearTimeout(id));
  UI.finalTimers = [];

  // پیام‌ها (۵ پیام × ۲ ثانیه = ۱۰ ثانیه)
  const msgs = [
    'در حال بررسی پاسخ سوالات شما ...',
    'در حال بررسی نوع ریزش موی شما ...',
    'در حال یافتن بهترین روش کاشت موی مناسب شما ...',
    'در حال محاسبه تعداد گرافت مورد نیاز شما ...',
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
  // تایمرها قطع
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

function HE_notifyIfPending(jq, ms, opts){
  opts = opts || {};
  setTimeout(function(){
    try{
      if (jq && typeof jq.state === 'function' && jq.state() === 'pending'){
        if (opts.toast)  { toastr.info(opts.toast); }
        if (opts.loader) { 
          var $box = $(opts.loader);
          if ($box.length) {
            $box.show();
            if (opts.textSel) { $(opts.textSel).text(opts.loaderText || opts.toast || 'در حال پردازش...'); }
          }
        }
      }
    }catch(e){}
  }, ms);
}

  /* =========================
   * AI loader (Step 5)
   * ========================= */
  let __aiQOnce = false;
  let __aiQInflight = false;

function loadAiQuestions(force=false){
  const uid = parseInt(LS.get('userId') || 0, 10);

  UI.ensureStep5Containers();
  $('#ai-questions-box').removeClass('d-none').show();
  $('#ai-questions-list').empty();

  // اگر کاربر آیدی ندارد → رندر fallback و resolve فوری
  if (!uid) {
    UI.renderQuestionsFallback();
    return jQuery.Deferred().resolve().promise();
  }

  // اگر قبلاً لود شده و force=false → همینجا resolve
  if (!force && __aiQOnce) {
    return jQuery.Deferred().resolve().promise();
  }

  __aiQInflight = true;

  // حتماً return کن که jqXHR (پرومیس) برگردد
  return API.aiQuestions(uid)
    .done(function(res){
      const payload = Utils.wpUnwrap(res);
      const qs = (payload && Array.isArray(payload.questions) && payload.questions.length === 4)
        ? payload.questions
        : null;

      if (qs) {
        UI.renderQuestions(qs);
      } else {
        UI.renderQuestionsFallback();
        if (payload?.debug && payload.debug.source !== 'openai') {
          toastr.info('سؤالات هوشمند موقتاً در دسترس نیست؛ سؤالات عمومی نمایش داده شد.');
        }
      }
    })
    .fail(function(){
      UI.renderQuestionsFallback();
      toastr.warning('اتصال به سرویس سؤالات برقرار نشد؛ سؤالات عمومی نمایش داده شد.');
    })
    .always(function(){
      __aiQInflight = false;
      __aiQOnce = true;
    });
}


  /* =========================
   * Steps bindings
   * ========================= */

  // شروع از مرحله ذخیره‌شده
  UI.goToStep(parseInt(LS.get('currentStep')) || 0);

  // Step 0 → 1
  $('#agree-btn').on('click', ()=> UI.goToStep(1));

  // Step 1: gender + age + mobile + confidence
  $('#form-step-1').on('submit', function(e){
    e.preventDefault();

    const gender = $('input[name="gender"]:checked').val();
    const age    = $('input[name="age"]:checked').val();
    let mobile   = Utils.normalizeMobile($('input[name="mobile"]').val());
    const validAges = ['18-23','24-29','30-35','36-43','44-56','+56'];

    if (!gender) return toastr.error('لطفاً جنسیت را انتخاب کنید');
    if (!age || !validAges.includes(age)) return toastr.error('لطفاً بازه سنی را درست انتخاب کنید');
    if (!/^09\d{9}$/.test(mobile)) return toastr.error('شماره موبایل معتبر وارد کنید (مثلاً 09xxxxxxxxx)');

    const payload = {
      user_id: LS.get('userId') || 0,   // ✅ اگر قبلاً رکورد داریم برو آپدیت
      gender, age, mobile,
      confidence: $('select[name="confidence"]').val()
    };


    const $btn = $(this).find('button[type="submit"]').prop('disabled', true);
    API.step1(payload).done(function(res){
      const d = Utils.wpUnwrap(res);
      if (res && res.success) {
        LS.set('userId', d.user_id); LS.set('gender', gender); LS.set('currentStep', 2);
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

  // Step 3: uploads
  $('#form-step-3').on('submit', function(e){ e.preventDefault(); UI.goToStep(4); });

  $(document).on('change', '.upload-box input[type="file"]', function(){
    const fileInput = this;
    const file = fileInput.files[0];
    if (!file) return;

    const $box = $(this).closest('.upload-box');
    const $progress = $box.find('.progress');
    const $bar = $progress.find('.progress-bar');
    const $thumb = $box.find('.thumbnail');

    const fd = new FormData();
    fd.append('action', 'shec_step3');
    fd.append('_nonce', shec_ajax.nonce);
    fd.append('user_id', LS.get('userId'));
    fd.append('position', $box.data('position'));
    fd.append(fileInput.name, file);

    $progress.removeClass('d-none'); $bar.css('width','0%');
    API.step3Upload(fd).done(function(res){
      if (res.success) {
        const fileUrl = res.data?.file || res.file || res?.data || res?.file;
        $thumb.attr('src', fileUrl).removeClass('d-none');
        $progress.addClass('d-none'); $bar.css('width','0%'); $box.addClass('upload-success');
        const uploads = JSON.parse(LS.get('uploadedPics','{}')); uploads[fileInput.name] = fileUrl;
        LS.set('uploadedPics', JSON.stringify(uploads));
      } else {
        toastr.error(res.message || 'خطا در آپلود');
      }
    }).fail(()=> toastr.error('خطا در ارتباط آپلود'));
  });

  // Step 4: medical + meds
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

      // پرامیسِ لود سوالات + حداقل 10s نمایش لودر
      const p = loadAiQuestions(true); // حتماً Promise برگرداند
      HE_notifyIfPending(req, 7000, {
        toast: 'در حال ارسال/دریافت اطلاعات… لطفاً کمی صبر کنید.',

      });
      UI.waitForAiOrTimeout(p, 9000).always(function(){
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


  // Toggle active style for followup
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

  // 1) پاسخ‌های 4 سوال
  const answers = [];
  let missingIdx = 0;
  $('#ai-questions-list .followup-item').each(function(){
    const idx = $(this).data('idx');
    const val = $(`input[name="followup_${idx}"]:checked`).val() || '';
    if (!val && !missingIdx) missingIdx = idx;
    answers.push(val);
  });
  if (missingIdx) return Utils.errorScroll(`#ai-questions-list .followup-item[data-idx="${missingIdx}"]`, 'لطفاً به همهٔ سؤالات پاسخ دهید.');

  // 2) اطلاعات تماس
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


/* ---- ثابت‌ها ---- */

// جدول تار مو بر اساس الگو و جنس (اگر از اسم عکس stage کشیدی و خواستی #he-grafts را پر کنی)
const HE_GRAFT_TABLE = {
  male:   {1:8000, 2:10000, 3:12000, 4:14000, 5:16000, 6:18000},
  female: {1:4000, 2: 8000, 3:10000, 4:12000, 5:14000, 6:16000}
};

// هشدارهای پزشکی
const HE_WARNINGS = {
  diabetes:    "اگر دچار دیابت هستید، کاشت مو تنها در صورتی ممکن است که بیماری تحت کنترل کامل باشد. دیابت می‌تواند بر روند بهبودی تأثیر بگذارد و خطر عفونت پس از جراحی را افزایش دهد. قبل از کاشت، تأییدیه کتبی پزشک معالج لازم است.",
  coagulation: "کاشت مو در بیماران مبتلا به اختلالات انعقاد خون ممکن است دشوار باشد و خونریزی را در طول عمل افزایش دهد و بر بقای گرافت‌ها تأثیر بگذارد. تأییدیه کتبی پزشک معالج لازم است.",
  cardiac:     "کاشت مو با وجود بیماری‌های قلبی/عروقی تنها در صورتی ممکن است که بیماری تحت کنترل کامل باشد و ممکن است ریسک داروی بی‌حسی و نقاهت بالاتر برود. تأییدیه کتبی پزشک لازم است.",
  thyroid:     "کاشت مو برای اختلالات تیروئید در صورت متعادل بودن سطح هورمون‌ها امکان‌پذیر است؛ حالت کنترل‌نشده می‌تواند بر رشد مو و بقای گرافت‌ها اثر بگذارد. تأییدیه کتبی پزشک لازم است.",
  immunodef:   "برای نقص سیستم ایمنی (مانند برخی موارد HIV یا شیمی‌درمانی) معمولاً کاشت توصیه نمی‌شود؛ بهبودی کندتر و عوارض بیشتر است. تصمیم نهایی با ارزیابی تخصصی و تأیید پزشک است.",
  autoimmune:  "در بیماری‌های خودایمنی، بسته به نوع و فعالیت بیماری، کاشت ممکن است دشوار یا غیرقابل انجام باشد و روی پذیرش گرافت‌ها اثر بگذارد. ارزیابی تخصصی و تأیید پزشک لازم است."
};

/* ==== هشدارهای پوستی (scalp_conditions) – جدید ==== */
const HE_SCALP_WARNINGS = {
  active_infection: "اگر عفونت فعال پوست سر دارید نمی‌توان بلافاصله برای کاشت مو اقدام کرد. عفونت می‌تواند زنده‌ماندن فولیکول‌ها را به خطر بیندازد و خطر عوارض پس از کاشت را افزایش دهد. متخصص پوست در کلینیک‌های فخرائی ابتدا عفونت را درمان می‌کند تا مطمئن شود پوست سر شما سالم و آماده یک عمل ایمن و موفق است.",
  psoriasis:        "اگر پسوریازیس شما فعال است—به‌خصوص اگر نواحی وسیعی از پوست سر درگیر باشد—ابتدا باید آن را کنترل/درمان کنید. پسوریازیس فعال می‌تواند به سد پوستی آسیب بزند و در شانس بقای فولیکول‌های کاشته شده اختلال ایجاد کند. در برخی موارد ممکن است حتی کاشت مو توصیه نشود. مناسب‌بودن شرایط در جلسه تخصصی ارزیابی می‌شود.",
  fungal_derm:      "قبل از در نظر گرفتن کاشت مو، باید درماتیت سبورئیک/عفونت قارچی کنترل شود. التهاب فعال می‌تواند روند بهبود را مختل و شانس بقای فولیکول‌های کاشته‌شده را کاهش دهد. متخصص پوست کلینیک فخرائی شما را در طول درمان همراهی می‌کند تا پوست سر برای کاشت آماده شود.",
  folliculitis:     "اگر دچار فولیکولیت هستید، قبل از کاشت مو باید آن را درمان کنیم. فولیکولیتِ درمان‌نشده می‌تواند به عفونت و ازبین‌رفتن فولیکول‌ها منجر شود. تیم پوست کلینیک فخرائی شما را تا آماده‌شدن پوست سر برای کاشت همراهی می‌کند.",
  areata:           "کاشت مو در مرحلهٔ فعال ریزش سکه‌ای (آلوپسی آره‌آتا) امکان‌پذیر نیست. این بیماری ممکن است باعث حملهٔ سیستم ایمنی به فولیکول‌های تازه کاشته‌شده شود. اگر بیماری درمان و برای مدت طولانی غیرفعال باشد، ممکن است بتوان کاشت را انجام داد؛ تصمیم نهایی در مشاوره تخصصی گرفته می‌شود.",
  scarring_alo:     "آلوپسی به همراه اسکار می‌تواند میزان موفقیت پیوند را تا حدود 70٪ کاهش دهد. بافت اسکار جریان خون کمتری دارد و رشد و بقای گرافت‌ها را محدود می‌کند. ارزیابی تخصصی برای سنجش امکان/محدودیت‌ها ضروری است.",
  scar:             "جای زخم (اسکار) روی پوست سر می‌تواند موفقیت کاشت مو را تا حدود ۷۰٪ کاهش دهد. برخی اسکارها عروق خونی ضعیفی دارند که باعث می‌شود گرافت‌ها پس از کاشت زنده نمانند. برای راهکار دقیق، ناحیه در مشاوره حضوری بررسی می‌شود."
};

// نگاشت لیبل فارسی → کلید هشدار
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

// نرمال‌سازی جنسیت به کلید جدول
function heNormalizeGender(g){
  var t = (g || '').toString().toLowerCase();
  if (t === 'female' || t === 'زن')  return 'female';
  return 'male'; // پیش‌فرض
}

// رندر کارت‌های هشدار
function heRenderAllWarnings(opt){
  opt = opt || {};
  var systemicLabels = Array.isArray(opt.systemicLabels) ? opt.systemicLabels : [];
  var scalpLabels    = Array.isArray(opt.scalpLabels)    ? opt.scalpLabels    : [];
  var anchorSel      = opt.anchor || '#he-medical-warning-wrap';

  var host = document.querySelector(anchorSel);
  if (!host) return;
  host.innerHTML = '';

  // سیستمیک
  var sysKeys = Array.from(new Set(systemicLabels.map(heMapLabelToWarningKey).filter(Boolean)));
  sysKeys.forEach(function(k){
    var div = document.createElement('div');
    div.className = 'he-warn-card';
    div.innerHTML = '<p>' + (HE_WARNINGS[k] || '') + '</p>';
    host.appendChild(div);
  });

  // پوستی
  var scalpKeys = Array.from(new Set(scalpLabels.map(heMapScalpLabelToKey).filter(Boolean)));
  scalpKeys.forEach(function(k){
    var div = document.createElement('div');
    div.className = 'he-warn-card';
    div.innerHTML = '<p>' + (HE_SCALP_WARNINGS[k] || '') + '</p>';
    host.appendChild(div);
  });

  host.style.display = (host.children.length ? '' : 'none');
}


// استخراج stage از value مثل "pattern-3"
function heStageFromPatternValue(patternValue){
  if (!patternValue) return null;
  var m = String(patternValue).toLowerCase().match(/pattern[-_ ]?(\d+)/);
  if (!m || !m[1]) return null;
  var s = parseInt(m[1], 10);
  if (isNaN(s)) return null;
  if (s < 1) s = 1; if (s > 6) s = 6;
  return s;
}

// محاسبه‌ی گرافت از جنسیت و stage
function heGraftsFromStage(gender, stage){
  if (!stage) return null;
  var key = heNormalizeGender(gender);
  var tbl = HE_GRAFT_TABLE[key] || {};
  return tbl[stage] || null;
}

// خواندن value انتخاب‌شده از DOM (fallback)
function heGetSelectedPatternFromDOM(){
  var el = document.querySelector('input[name="loss_pattern"]:checked');
  return el ? el.value : null; // مثل: "pattern-3"
}

// ستون «پیل‌ها» به‌صورت HTML
const pillsCol = (title, items=[]) => `
  <div class="he-pill-group">
    <div class="he-pill-title">${title}</div>
    <div class="he-pill-wrap">
      ${items.map(i=>`<div class="he-pill">${i}</div>`).join('')}
    </div>
  </div>
`;

// === Step 6 Handler (replace old one) ===
API.step5(payloadContact).done(function(res){
  if (!res || !res.success) {
    const d = Utils.wpUnwrap(res);
    toastr.error((d && d.message) || 'خطا در ذخیره اطلاعات تماس');
    $btn.prop('disabled', false);
    return;
  }

  UI.finalStepShowLoader();

  const req = API.finalize(uid, answers);

  UI.waitForAiOrTimeout(req, 10000).done(function(){
    req.done(function(fin){
      const d = Utils.wpUnwrap(fin);
      if (!(fin && fin.success)) {
        toastr.error((d && d.message) || 'خطا در نهایی‌سازی هوش مصنوعی');
        return;
      }

     // --- user fields ---
      const u       = d.user || {};
      const first   = (u.contact && u.contact.first_name ? u.contact.first_name : '').trim();
      const last    = (u.contact && u.contact.last_name  ? u.contact.last_name  : '').trim();
      const full    = (first || last) ? (first + (first&&last?' ':'') + last) : '—';
      const ageVal  = u.age || (u.contact ? u.contact.age : '') || '—';
      var pattern = u.loss_pattern || u.pattern || null;  // از سرور
      if (!pattern) { // اگر از سرور نبود، از DOM بخوان
        pattern = heGetSelectedPatternFromDOM();
      }
      const gender  = u.gender || (u.contact ? u.contact.gender : '') || 'male';
      const concern = (u.medical && u.medical.concern) ? u.medical.concern : '—';
      const images  = u.images || u.uploads || (answers && answers.images) || [];

      // ---- محاسبه گرافت از جدول شما ----
      var stage   = heStageFromPatternValue(pattern);           // 1..6 یا null
      var graftN  = heGraftsFromStage(gender, stage);           // عدد یا null
      var graftByTable = graftN ? Number(graftN).toLocaleString('fa-IR') : null;


      // ---------- Stage از عکس ----------
      var stageFromFile = null;
      if (Array.isArray(images)) {
        for (var i = 0; i < images.length; i++) {
          var nm = ((images[i] && (images[i].filename || images[i].name)) || '').toLowerCase();
          var mm = nm.match(/(?:^|[\/\\])(ol|w)[-_ ]?(\d+)/);
          if (mm && mm[2]) { stageFromFile = parseInt(mm[2], 10); break; }
        }
      }
      if (stageFromFile) {
        if (stageFromFile < 1) stageFromFile = 1;
        if (stageFromFile > 6) stageFromFile = 6;
      }

      // ---------- Stage از pattern ----------
      var stageFromPattern = null;
      if (pattern) {
        var mp = String(pattern).toLowerCase().match(/pattern[-_ ]?(\d+)/);
        if (mp && mp[1]) {
          stageFromPattern = parseInt(mp[1], 10);
          if (isNaN(stageFromPattern)) stageFromPattern = null;
        }
      }


      // ---- اگر نشانِ بالایی (#he-grafts) داری، پرش کن
      var elBadge = document.getElementById('he-grafts');
      if (elBadge && graftByTable) elBadge.textContent = graftByTable;
      // ---------- مپ پزشکی طبق JSON واقعی شما ----------
      var med = u.medical || {};
      function splitFa(str){
        if (!str || typeof str !== 'string') return [];
        return str.split(/[,،;\n]/g).map(function(s){return s.trim();}).filter(Boolean);
      }
      function joinFa(arr){ return (Array.isArray(arr) && arr.length) ? arr.join('، ') : '—'; }

      // دارو
      var drugsLabels = (med.has_meds === 'yes')
        ? (splitFa(med.meds_list).length ? splitFa(med.meds_list) : ['مصرف دارو'])
        : ['عدم مصرف دارو'];

      // بیماری‌ها
      var dermLabels = splitFa(med.scalp_conditions);   // مثل: "پسوریازیس"
      var sysLabels  = splitFa(med.other_conditions);    // مثل: "دیابت"

      // هشدارها
      // --- لیبل‌ها از JSON شما ---
      function splitFa(str){
        if(!str || typeof str !== 'string') return [];
        return str.split(/[,،;\n]/g).map(s=>s.trim()).filter(Boolean);
      }

      var med        = u.medical || {};
      var drugsLabels = (med.has_meds === 'yes')
        ? (splitFa(med.meds_list).length ? splitFa(med.meds_list) : ['مصرف دارو'])
        : ['عدم مصرف دارو'];

      // سری دوم: بیماری پوستی
      var dermLabels  = splitFa(med.scalp_conditions);   // مثل: "پسوریازیس"
      // سری اول: بیماری زمینه‌ای
      var sysLabels   = splitFa(med.other_conditions);    // مثل: "دیابت"

      // به‌جای warnKeys/warnsHtml از فانکشن جدید استفاده می‌کنیم
      // showMedical فقط برای نمایش سکشن بالا (چیپ‌ها) استفاده می‌شود
      var showMedical = (drugsLabels.length || dermLabels.length || sysLabels.length) > 0;

      // یک ID یکتا برای محل درج کارت‌های هشدار بسازیم
      var warnHostId = 'he-medical-warning-wrap-' + Date.now();

      // ---------- خروجی AI ----------
      let method='FIT', graftCount='', analysis='';
      try {
        const parsed = JSON.parse(d.ai_result);
        method     = parsed.method      || method;
        graftCount = parsed.graft_count || '';
        analysis   = parsed.analysis    || '';
      } catch(e){ analysis = d.ai_result; }

      const graft     = graftCount || '—';
      const methodTxt = method || '—';
      const duration  = 'دو جلسه هشت ساعته';
      const logoUrl   = 'https://fakhraei.clinic/wp-content/uploads/2024/02/Group-1560-300x300.png.webp';



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

          <div class="ai-section-title" style="margin-top:18px;">مهم‌ترین دغدغهٔ شما</div>
          <div class="ai-note">${concern}</div>

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
              <div class="ai-stat-label">تعداد گرافت پیشنهادی (AI)</div>
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

          ${analysis ? `
            <div class="ai-section-title" style="margin-top:18px;">توضیح هوش مصنوعی</div>
            <div class="ai-note" style="text-align:justify">${analysis}</div>
          ` : ''}

        </div>
      `);
        heRenderAllWarnings({
          systemicLabels: sysLabels,   // other_conditions
          scalpLabels: dermLabels,     // scalp_conditions
          anchor: '#' + warnHostId
        });

      UI.goToStep(6);
    }).fail(function(){
      toastr.error('خطای ارتباط در نهایی‌سازی');
    }).always(function(){
      UI.finalStepHideLoader();
      $btn.prop('disabled', false);
    });
  });

}).fail(function(){
  toastr.error('خطا در ارتباط با سرور');
  $btn.prop('disabled', false);
});

});



  // PDF
// نیاز است html2canvas و jsPDF را لود کرده باشی (که قبلاً انجام دادی)

async function shecBuildPdfA4(selector) {
  const root = document.querySelector(selector);
  if (!root) { console.error('[PDF] root not found'); return; }

  // اگر فریم در صفحه نباشد، روی کلون اعمال می‌کنیم
  const needAddFrameClass = !root.classList.contains('pdf-frame');

  // --- کلون امن ---
  const clone = root.cloneNode(true);
  if (needAddFrameClass) clone.classList.add('pdf-frame');

  // دکمه‌ها را حذف کن
  clone.querySelectorAll('button, .btn, [data-no-pdf]').forEach(el => el.remove());

  // تصاویر کراس‌دامین را نادیده بگیر (CORS)
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

  // --- استایل و فونت داخل کلون ---
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

  // --- استیج نامرئی برای رندر ---
  const stage = document.createElement('div');
  stage.style.cssText = 'position:fixed;left:-10000px;top:0;background:#fff;z-index:-1;';
  // عرض ثابت نزدیک به A4 برای شارپ بودن (حدود 794px در 96dpi)
  stage.style.width = '794px';
  document.body.appendChild(stage);
  stage.appendChild(clone);

  try { await document.fonts?.ready; } catch (_) {}

  // --- رندر به canvas ---
  let canvas;
  try {
    canvas = await html2canvas(clone, {
      backgroundColor: '#ffffff',
      scale: 2,                    // کیفیت بهتر
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

  // --- ساخت PDF A4 چند صفحه‌ای ---
  const { jsPDF } = window.jspdf;
  const pdf = new jsPDF('p', 'mm', 'a4');
 const pageW = pdf.internal.pageSize.getWidth();
      const pageH = pdf.internal.pageSize.getHeight();
      const margin = 14;

      const imgW = pageW - margin*2;
      const imgH = (canvas.height * imgW) / canvas.width;
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

// کلیک دانلود
jQuery(function($){
  $(document).on('click', '#download-pdf', function(e){
    e.preventDefault();
    shecBuildPdfA4('#proposal-pdf-root');
  });
});


  // Prev button
  $('.btn-prev').on('click', function(){
    const current = parseInt(LS.get('currentStep') || 1);
    UI.goToStep(Math.max(1, current-1));
  });

  // Reset
  $(document).on('click', '#reset-form', function(){
    if (confirm('آیا مطمئن هستید که می‌خواهید فرم را از ابتدا شروع کنید؟')) {
      LS.clear(); window.location.reload();
    }
  });

  // هنگام لود
  (function preloadPatternImages(){
    for (let i=1;i<=6;i++){ new Image().src = `${shec_ajax.img_path}w${i}.png`; }
    const savedGender = LS.get('gender') || 'male';
    UI.updateUploadHints(savedGender);
  })();

});
