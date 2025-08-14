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
    step5ShowLoader(){ $('#step5-loader').show(); $('#step5-content').hide(); },
    step5HideLoader(){ $('#step5-loader').hide(); $('#step5-content').show(); },
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
  let __aiQInflight = false;

  function loadAiQuestions(force=false){
    const uid = LS.get('userId');
    if (!force && (__aiQOnce || __aiQInflight)) return $.Deferred().resolve().promise();

    __aiQInflight = true;
    UI.step5ShowLoader();
    UI.ensureStep5Containers();
    $('#ai-questions-box').removeClass('d-none').show();
    $('#ai-questions-list').empty();

    if (!uid) {
      UI.renderQuestionsFallback();
      __aiQInflight = false; __aiQOnce = true; UI.step5HideLoader();
      return $.Deferred().resolve().promise();
    }

    return API.aiQuestions(uid)
      .done(function(res){
        const payload = Utils.wpUnwrap(res);
        console.log('[AI] payload:', payload);
        console.log('[AI] debug:', payload?.debug || '(no debug)');
        const qs = (payload && Array.isArray(payload.questions) && payload.questions.length === 4) ? payload.questions : null;
        if (!qs) {
          UI.renderQuestionsFallback();
          if (payload?.debug && payload.debug.source !== 'openai') {
            toastr.info('سؤالات هوشمند موقتاً در دسترس نیست؛ سؤالات عمومی نمایش داده شد.');
          }
        } else {
          UI.renderQuestions(qs);
        }
      })
      .fail(function(xhr){
        console.error('[AI] questions ajax fail', xhr?.status, xhr?.responseText);
        UI.renderQuestionsFallback();
        toastr.warning('اتصال به سرویس سؤالات برقرار نشد؛ سؤالات عمومی نمایش داده شد.');
      })
      .always(function(){
        __aiQInflight = false; __aiQOnce = true; UI.step5HideLoader();
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
        __aiQOnce = false; // چون خلاصه تغییر کرد
        UI.goToStep(5);
        UI.step5ShowLoader();
        loadAiQuestions().always(()=> UI.step5HideLoader());
      } else {
        toastr.error((res && res.message) || 'خطا در مرحله ۴');
      }
    }).fail(()=> toastr.error('خطا در سرور مرحله ۴'))
      .always(()=> $btn.prop('disabled', false));
  });

  // Toggle active style for followup
  $(document).on('change','input[name^="followup_"]', function(){
    const name = $(this).attr('name');
    $(`input[name="${name}"]`).parent().removeClass('active');
    if ($(this).is(':checked')) $(this).parent().addClass('active');
  });

  // Step 5: contact + finalize
  // Step 5: contact + finalize (fixed)
  $(document).on('submit', '#form-step-5', function(e){
    e.preventDefault();

    const uid = parseInt(LS.get('userId') || 0, 10);
    if (!uid) { toastr.error('شناسه کاربر پیدا نشد. لطفاً فرم را از ابتدا شروع کنید.'); return; }

    // 1) جمع‌کردن پاسخ‌های 4 سوال و الزام پاسخ‌گویی
    const answers = [];
    let missingIdx = 0;
    $('#ai-questions-list .followup-item').each(function(){
      const idx = $(this).data('idx');
      const val = $(`input[name="followup_${idx}"]:checked`).val() || '';
      if (!val && !missingIdx) missingIdx = idx;
      answers.push(val);
    });
    if (missingIdx) {
      return Utils.errorScroll(`#ai-questions-list .followup-item[data-idx="${missingIdx}"]`, 'لطفاً به همهٔ سؤالات پاسخ دهید.');
    }

    // 2) اعتبارسنجی نام/محل/روش تماس در فرانت قبل از ارسال
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

    // 3) اول ذخیرهٔ اطلاعات تماس
    API.step5(payloadContact).done(function(res){
      // ✅ پیام درست از res.data خوانده می‌شود
      if (!res || !res.success) {
        const d = Utils.wpUnwrap(res);
        toastr.error((d && d.message) || 'خطا در ذخیره اطلاعات تماس');
        $btn.prop('disabled', false);
        return;
      }

      // 4) سپس نهایی‌سازی AI
      API.finalize(uid, answers).done(function(fin){
        const d = Utils.wpUnwrap(fin);
        if (fin && fin.success) {
          let method='FIT', graftCount='', analysis='';
          try {
            const parsed = JSON.parse(d.ai_result);
            method = parsed.method || method;
            graftCount = parsed.graft_count || '';
            analysis = parsed.analysis || '';
          } catch(e){ analysis = d.ai_result; }

          $('#ai-result-box').html(`
            <div class="ai-result-container">
              <h4>روش پیشنهادی: <span class="method-text">${method}</span></h4>
              <p class="analysis-text">${analysis}</p>
              ${graftCount ? `<div class="graft-count-box"><strong>تخمین تعداد گرافت:</strong> ${graftCount} گرافت</div>` : ''}
            </div>
          `);

          const u = d.user || {};
          $('#user-summary-list').html(`
            <li><strong>نام:</strong> ${(u.contact?.first_name||'')} ${(u.contact?.last_name||'')}</li>
            <li><strong>جنسیت:</strong> ${u.gender||''}</li>
            <li><strong>سن:</strong> ${u.age||''}</li>
            <li><strong>شهر:</strong> ${(u.contact?.city||'')}, ${(u.contact?.state||'')}</li>
          `);

          UI.goToStep(6);
        } else {
          toastr.error((d && d.message) || 'خطا در نهایی‌سازی هوش مصنوعی');
        }
      }).fail(function(){
        toastr.error('خطای ارتباط در نهایی‌سازی');
      }).always(function(){
        $btn.prop('disabled', false);
      });

    }).fail(function(){
      toastr.error('خطا در ارتباط با سرور');
      $btn.prop('disabled', false);
    });
  });


  // PDF
  $('#download-pdf').on('click', function(){
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    const resultText = $('#ai-result-box').text();
    const userSummary = $('#user-summary-list').text();
    doc.setFont("Helvetica");
    doc.text("نتیجه مشاوره کاشت مو", 10, 10);
    doc.text(resultText, 10, 20);
    doc.text("خلاصه اطلاعات:", 10, 50);
    doc.text(userSummary, 10, 60);
    doc.save("diagnosis.pdf");
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
