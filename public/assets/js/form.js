var $ = jQuery.noConflict(); 
$(document).ready(function () {
    console.log("shec_ajax object:", shec_ajax);

    console.log("📌 form.js loaded!");

    // ======== متغیرهای اصلی ========
    let userId = localStorage.getItem('userId') || null;
    let currentStep = parseInt(localStorage.getItem('currentStep')) || 0;

    const uploadPositions = {
        male: ['روبرو', 'پشت سر', 'فرق سر', 'کنار سر'],
        female: ['روبرو', 'بالای سر', 'فرق سر']
    };

    function showLoader() {
        $('#step2-loader').show();  // لودینگ را نمایش می‌دهیم
        $('#form-step-2 .step-content').hide();  // مخفی کردن محتوای اصلی
    }

    function hideLoader() {
        $('#step2-loader').hide();  // لودینگ را مخفی می‌کنیم
        $('#form-step-2 .step-content').show();  // نمایش محتوای اصلی
    }
    function wpUnwrap(res){ return (res && res.data) ? res.data : res; }


    function markErrorAndScroll(selector, msg, focusSelector) {
        const $el = $(selector);
        $el.addClass('error shake');

        // اسکرول نرم (هر کدوم موجود بود)
        if ($el.length && $el.get(0) && $el.get(0).scrollIntoView) {
            $el.get(0).scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else if ($el.length) {
            $('html, body').animate({ scrollTop: $el.offset().top - 120 }, 300);
        }

        if (msg) toastr.error(msg);
        if (focusSelector) $(focusSelector).trigger('focus');

        setTimeout(() => $el.removeClass('shake'), 400);
    }

    // ==================== تغییر عکس‌های الگوی ریزش مو (Step 2) ====================
    // تابعی که فقط تصاویر رنگی را لود می‌کند
    function initializeStep2PatternImages(gender) {
        const isF = gender === 'female';
        $('#form-step-2 .pattern-option img').each((idx, img) => {
            const i = idx + 1;
            const colored = isF ? `${shec_ajax.img_path}w${i}.png` : `${shec_ajax.img_path}ol${i}.png`;
            $(img).data('colored', colored)[0].src = colored;
            $(img).css('filter', 'grayscale(100%)');  // تصویر به صورت سیاه و سفید لود می‌شود
        });
    }

    // پیش‌بارگذاری تصاویر رنگی فقط
    const preloadImages = () => {
        const urls = [];
        for (let i = 1; i <= 6; i++) {
            urls.push(`${shec_ajax.img_path}w${i}.png`);
        }
        urls.forEach(u => (new Image().src = u));  // فقط تصاویر رنگی پیش‌بارگذاری می‌شود
    };
    preloadImages();

    $('#form-step-2').on('click', '.pattern-option', function () {
        $('.pattern-option').removeClass('selected');
        $(this).addClass('selected');
        $('input[name="loss_pattern"]', this).prop('checked', true);

        // تغییر تصویر انتخاب‌شده به رنگی
        const img = $(this).find('img');
        img.css('filter', 'none');  // تصویر انتخاب‌شده به حالت رنگی
        img.attr('src', img.data('colored'));  // تغییر تصویر به رنگی

        // تغییر باقی تصاویر به حالت سیاه و سفید
        $('#form-step-2 .pattern-option img').not(img).each(function() {
            $(this).css('filter', 'grayscale(100%)');  // سایر تصاویر سیاه و سفید می‌شوند
        });
    });

    // هنگام بارگذاری صفحه، ابتدا تصاویر رنگی بارگذاری می‌شوند
    $(document).ready(function() {
        const gender = localStorage.getItem('gender') || 'male'; // از جنسیت ذخیره شده استفاده می‌کنیم
        initializeStep2PatternImages(gender);
    });

    // ==================== تغییر عکس‌های توضیحی آپلود (Step 3) ====================
    function updateUploadDescriptionImages(gender) {
        showLoader();
        const images = {
            male: [
                'https://fakhraei.clinic/wp-content/uploads/2025/07/New-Project-80.webp',
                'https://fakhraei.clinic/wp-content/uploads/2025/07/2-pic-1.webp',
                'https://fakhraei.clinic/wp-content/uploads/2025/07/3-pic-1.webp',
                'https://fakhraei.clinic/wp-content/uploads/2025/07/1-pic-1.webp'
            ],
            female: [
                'https://fakhraei.clinic/wp-content/uploads/2025/07/top_f.webp',
                'https://fakhraei.clinic/wp-content/uploads/2025/07/back_f.webp',
                'https://fakhraei.clinic/wp-content/uploads/2025/07/front_f.webp'
            ]
        };

        const $angleImages = $('.angles .angle img');
        const imgList = images[gender];

        $angleImages.each(function (index) {
            if (imgList[index]) {
                $(this).attr('src', imgList[index]).parent().show();
            } else {
                $(this).parent().hide(); // مخفی کردن عکس چهارم
            }
        });

        // برای وسط چین کردن سه‌تایی
        $('.angles').css({
            'display': 'flex',
            'justify-content': gender === 'female' ? 'center' : 'space-between',
            'gap': '12px'
        });
        hideLoader();
    }

    // ==================== هدایت به مرحله مشخص ====================
    function goToStep(step) {
        showLoader();
        $('.step').addClass('d-none').removeClass('active');
        $('#step-' + step).removeClass('d-none').addClass('active');
        updateProgress(step);
        localStorage.setItem('currentStep', step);

        if (step === 3) {
            const gender = localStorage.getItem('gender') || 'male';
            if (gender) {
                renderUploadBoxes(gender);
                loadUploadedThumbnails();
                updateUploadDescriptionImages(gender);
            } else {
                toastr.error('جنسیت مشخص نیست. لطفاً مرحله ۱ را کامل کنید');
                goToStep(1);
            }
        }

        if (step === 2) {
            const gender = localStorage.getItem('gender') || 'male';
            const $step2    = $('#step-2');
            showLoader();

            // ۱) نشان دادن کانتینر Step2 و لودر، پنهان کردن محتوای داخل
            $step2.show().addClass('active');
            

            // ۲) یک‌بار ست کردن src تصاویر رنگی
            $step2.find('.pattern-option img').each((idx, img) => {
                const i = idx + 1;
                img.src = (gender === 'female')
                    ? `${shec_ajax.img_path}w${i}.png`
                    : `${shec_ajax.img_path}ol${i}.png`;
            });

            // ۳) صبر برای لود همه تصاویر
            const imgs = $step2.find('.pattern-option img').toArray();
            Promise.all(imgs.map(img => new Promise(resolve => {
                if (img.complete) resolve();
                else img.onload = resolve;
            }))).then(() => {
                // ۴) وقتی آماده شد: مخفی کردن لودر، نمایش محتوا
                hideLoader();
                updateProgress(2);
                localStorage.setItem('currentStep', 2);
            });

            return;
        }

        if (step === 6) {
            localStorage.removeItem('currentStep');
        }
        hideLoader();
    }

    // ======== آپدیت نوار پیشرفت ========
    function updateProgress(step) {
        $('#step-current').text(step);
        const percent = Math.floor((step / 6) * 100);
        $('#progress-bar').css('width', percent + '%');
    }

    // ======== ساخت باکس‌های آپلود ========
    function renderUploadBoxes(gender = 'male') {
        const container = $('#upload-zones');
        container.empty();

        uploadPositions[gender].forEach((label, index) => {
            const box = `
                <div class="col-12 col-lg-6">
                    <label class="upload-box" data-index="${index}" data-position="${label}">
                        <span class="d-block fw-bold mb-2">${label}</span>
                        <input type="file" name="pic${index + 1}" accept="image/*">
                        <div class="progress d-none">
                            <div class="progress-bar" style="width: 0%;"></div>
                        </div>
                        <img src="" class="thumbnail d-none">
                    </label>
                </div>
            `;
            container.append(box);
        });
    }

    // ======== بارگذاری thumbnail های ذخیره‌شده ========
    function loadUploadedThumbnails() {
        const uploads = JSON.parse(localStorage.getItem('uploadedPics') || '{}');
        for (const name in uploads) {
            const url = uploads[name];
            const $box = $(`.upload-box input[name="${name}"]`).closest('.upload-box');
            if ($box.length) {
                $box.addClass('upload-success');
                $box.find('.thumbnail').attr('src', url).removeClass('d-none');
            }
        }
    }

    // ======== شروع کار از مرحله ذخیره‌شده ========
    goToStep(currentStep);

    // ======== رویداد شروع فرم ========
    $('#agree-btn').click(function () {
        goToStep(1);
    });

    // ======== مرحله ۱: اطلاعات اولیه ========
    $('#form-step-1').on('submit', function (e) {
    e.preventDefault();

    const gender = $('input[name="gender"]:checked').val();
    const age = $('input[name="age"]:checked').val();
    let mobile = $('input[name="mobile"]').val();

    // نرمال‌سازی ساده موبایل
    mobile = (''+mobile).trim()
        .replace(/\s+/g, '')
        .replace(/^\+98/, '0')
        .replace(/^0098/, '0')
        .replace(/^98/, '0')
        .replace(/\D+/g, '');
    if (/^9\d{9}$/.test(mobile)) mobile = '0' + mobile;

    // ولیدیشن
    if (!gender) return toastr.error('لطفاً جنسیت را انتخاب کنید');
    if (!age || !/^(\+56|44-56|36-43|30-35|24-29|18-23)$/.test(age)) {
        return toastr.error('لطفاً بازه سنی را درست انتخاب کنید');
    }
    if (!/^09\d{9}$/.test(mobile)) {
        return toastr.error('لطفاً شماره موبایل معتبر وارد کنید (مثلاً 09xxxxxxxxx)');
    }

    const dataToSend = {
        action: 'shec_step1',
        _nonce: shec_ajax.nonce,
        gender,
        age,
        confidence: $('select[name="confidence"]').val(),
        mobile
    };

    console.log('[STEP1] sending:', dataToSend);

    const $btn = $(this).find('button[type="submit"]').prop('disabled', true);

    $.post(
        shec_ajax.url,
        dataToSend,
        function (response) {
        $btn.prop('disabled', false);
        console.log('[STEP1] response:', response);

        if (response && response.success) {
            userId = response.data.user_id;
            localStorage.setItem('userId', userId);
            localStorage.setItem('gender', gender);
            localStorage.setItem('currentStep', 2);
            initializeStep2PatternImages(gender);
            goToStep(2);
        } else {
            toastr.error((response && response.message) || 'خطا در ارسال اطلاعات');
        }
        },
        'json'
    );
    });

    // ======== مرحله ۲: الگوی ریزش ========
    $('#form-step-2').submit(function (e) {
        e.preventDefault();
        const lossPattern = $('input[name="loss_pattern"]:checked').val();
        const userId = localStorage.getItem('userId'); // دریافت user_id از localStorage

        console.log('User ID being sent to server:', userId); // لاگ گرفتن از userId

        if (!lossPattern) {
            toastr.error('لطفاً الگوی ریزش مو را انتخاب کنید');
            return;
        }

        $.post(
            shec_ajax.url,
            {
                action: 'shec_step2',
                _nonce: shec_ajax.nonce,
                user_id: userId, // ارسال user_id به سرور
                loss_pattern: lossPattern
            },
            function (response) {
                console.log('Response from server:', response); // بررسی پاسخ سرور
                if (response.success) {
                    goToStep(3);
                } else {
                    toastr.error(response.message || 'خطا در مرحله ۲');
                }
            },
            'json'
        );
    });

    // ======== مرحله ۳: آپلود ========
    $('#form-step-3').submit(function (e) {
        e.preventDefault();
        goToStep(4);
    });
    
    $(document).on('change', '.upload-box input[type="file"]', function () {
        const fileInput = this;
        const file = fileInput.files[0];
        if (!file) return;

        const $box = $(this).closest('.upload-box');
        const $progress = $box.find('.progress');
        const $bar = $progress.find('.progress-bar');
        const $thumb = $box.find('.thumbnail');

        const formData = new FormData();
        formData.append('action', 'shec_step3');
        formData.append('_nonce', shec_ajax.nonce);
        formData.append('user_id', localStorage.getItem('userId'));
        formData.append('position', $box.data('position'));
        formData.append(fileInput.name, file);

        $progress.removeClass('d-none');
        $bar.css('width', '0%');

        $.ajax({
            url: shec_ajax.url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (res) {
                if (res.success) {
                    console.log('Response from server:', res); // ✅
                    const fileUrl = res.data?.file || res.file || res?.data || res?.file; // پوشش امن
                    $thumb.attr('src', fileUrl).removeClass('d-none');
                    $progress.addClass('d-none');
                    $bar.css('width', '0%');
                    $box.addClass('upload-success');

                    const uploads = JSON.parse(localStorage.getItem('uploadedPics') || '{}');
                    uploads[fileInput.name] = fileUrl;
                    localStorage.setItem('uploadedPics', JSON.stringify(uploads));
                } else {
                    toastr.error(res.message || 'خطا در آپلود');
                }
            }
        });
    });


    // ======== مرحله ۴: سوالات پزشکی ========
    $(document).on('change', 'input[name="has_medical"]', function () {
    $('#has-medical-group').removeClass('error shake');
    $('input[name="has_medical"]').parent().removeClass('active');
    if ($(this).is(':checked')) $(this).parent().addClass('active');

    const show = $(this).val() === 'yes';
    $('#medical-fields').toggleClass('d-none', !show)
                        .removeClass('error shake');

    // اگر نه گفت، ورودی‌های وابسته پاک شوند
    if (!show) {
        $('#medical-fields select[name="scalp_conditions"]').val('');
        $('#medical-fields select[name="other_conditions"]').val('');
    }
    });

    // فعال/غیرفعال کردن گروه "دارو"
    $(document).on('change', 'input[name="has_meds"]', function () {
    $('#has-meds-group').removeClass('error shake');
    $('input[name="has_meds"]').parent().removeClass('active');
    if ($(this).is(':checked')) $(this).parent().addClass('active');

    const show = $(this).val() === 'yes';
    $('#meds-fields').toggleClass('d-none', !show)
                    .removeClass('error shake');

    if (!show) {
        $('#meds-fields input[name="meds_list"]').val('');
    }
    });

    $('#form-step-4').submit(function (e) {
        e.preventDefault();

        const hasMedical = $('input[name="has_medical"]:checked').val() || '';
        const hasMeds    = $('input[name="has_meds"]:checked').val() || '';

        // باید یکی از بله/خیر انتخاب شود
        if (!hasMedical) {
            markErrorAndScroll('#has-medical-group', 'لطفاً به سؤال «آیا به بیماری خاصی مبتلا هستید؟» پاسخ دهید.');
            return;
        }
        if (!hasMeds) {
            markErrorAndScroll('#has-meds-group', 'لطفاً به سؤال «آیا در حال حاضر داروی خاصی مصرف می‌کنید؟» پاسخ دهید.');
            return;
        }

        // اگر بیماری = بله → حداقل یکی از دو لیست انتخاب شود (یا «هیچکدام»)
        if (hasMedical === 'yes') {
            const scalp = ($('#medical-fields select[name="scalp_conditions"]').val() || '').trim();
            const other = ($('#medical-fields select[name="other_conditions"]').val() || '').trim();
            if (!scalp && !other) {
            markErrorAndScroll('#medical-fields', 'لطفاً یکی از گزینه‌های بیماری را انتخاب کنید (یا «هیچکدام» را بزنید).');
            return;
            }
        }

        // اگر دارو = بله → نام دارو الزامی
        if (hasMeds === 'yes') {
            const meds = ($('#meds-fields input[name="meds_list"]').val() || '').trim();
            if (!meds) {
            markErrorAndScroll('#meds-fields', 'اگر دارو مصرف می‌کنید، نام دارو را وارد کنید.', '#meds-fields input[name="meds_list"]');
            return;
            }
        }

        const payload = Object.assign(
            { action: 'shec_step4', _nonce: shec_ajax.nonce },
            $('#form-step-4').serializeArray().reduce((o, f) => (o[f.name] = f.value, o), {}),
            { user_id: userId }
        );

        const $btn = $(this).find('button[type="submit"]').prop('disabled', true);

        $.post(
            shec_ajax.url,
            payload,
            function (response) {
            $btn.prop('disabled', false);
            if (response && response.success) {
                goToStep(5);
                step5ShowLoader();
                loadAiQuestions().always(step5HideLoader);

            } else {
                toastr.error((response && response.message) || 'خطا در مرحله ۴');
            }
            },
            'json'
        );
    });

    function ensureStep5Containers(){
        if (!$('#step5-content').length) return;
        if (!$('#ai-questions-box').length) {
            $('#step5-content').prepend(`
            <div id="ai-questions-box" class="mb-4">
                <p class="d-block mb-2 fw-bold">لطفاً به چند سؤال کوتاه پاسخ دهید:</p>
                <div id="ai-questions-list"></div>
            </div>
            `);
        }
    }


    function step5ShowLoader(){ $('#step5-loader').show(); $('#step5-content').hide(); }
    function step5HideLoader(){ $('#step5-loader').hide(); $('#step5-content').show(); }

    function loadAiQuestions() {
        const uid = localStorage.getItem('userId');
        ensureStep5Containers();

        $('#ai-questions-box').removeClass('d-none').show();
        $('#ai-questions-list').empty();

        if (!uid) {
            console.warn('[AI] no userId; showing fallback');
            renderAiQuestionsFallback();
            return $.Deferred().resolve().promise();
        }

        step5ShowLoader();

        return $.post(shec_ajax.url, {
            action: 'shec_ai_questions',
            _nonce: shec_ajax.nonce,
            user_id: uid
        }, function(res){
            const payload = wpUnwrap(res);            // ⬅️ مهم
            console.log('[AI] questions debug:', payload?.debug || '(no debug)');

            let qs = (payload && Array.isArray(payload.questions) && payload.questions.length === 3)
                    ? payload.questions
                    : null;

            if (!qs) {
            console.warn('[AI] using fallback questions');
            renderAiQuestionsFallback();
            } else {
            renderAiQuestions(qs);
            }
        }, 'json').fail(function(xhr){
            console.error('[AI] questions ajax fail', xhr?.status, xhr?.responseText);
            renderAiQuestionsFallback();
        }).always(function(){
            step5HideLoader();
        });
    }


// رندر سؤال‌ها (کمپوننت واحد)
function renderAiQuestions(qs){
  const $list = $('#ai-questions-list').empty();
  (qs || []).forEach((q, i) => {
    const idx = i + 1;
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
}

// fallback قطعی
function renderAiQuestionsFallback(){
  renderAiQuestions([
    'آیا در خانواده‌تان سابقهٔ ریزش مو وجود دارد؟',
    'آیا طی ۱۲ ماه گذشته شدت ریزش موی شما بیشتر شده است؟',
    'آیا در حال حاضر سیگار یا قلیان مصرف می‌کنید؟'
  ]);
}



    // فعال/غیرفعال کردن استایل toggle
    $(document).on('change', 'input[name^="followup_"]', function(){
    const name = $(this).attr('name');
    $(`input[name="${name}"]`).parent().removeClass('active');
    if ($(this).is(':checked')) $(this).parent().addClass('active');
    });


    // active کردن استایل toggle برای سؤالات AI
    $(document).on('change', 'input[name^="followup_"]', function(){
    const name = $(this).attr('name');
    $(`input[name="${name}"]`).parent().removeClass('active');
    if ($(this).is(':checked')) $(this).parent().addClass('active');
    });


    // ======== مرحله ۵: اطلاعات تماس ========
    $(document).on('submit', '#form-step-5', function (e) {
    e.preventDefault();
    if (!userId) {
        toastr.error('کاربر شناسایی نشد، لطفاً دوباره مراحل را شروع کنید');
        return;
    }

    // اعتبارسنجی سؤالات (اگر بودند)
    const answers = [];
    let missingIdx = 0;
    $('#ai-questions-list .followup-item').each(function(){
        const idx = $(this).data('idx');
        const val = $(`input[name="followup_${idx}"]:checked`).val() || '';
        if (!val && !missingIdx) missingIdx = idx;
        answers.push(val || '');
    });
    if (missingIdx) {
        markErrorAndScroll(`#ai-questions-list .followup-item[data-idx="${missingIdx}"]`, 'لطفاً به همهٔ سؤالات پاسخ دهید.');
        return;
    }

    const payloadContact = {
        action: 'shec_step5',
        _nonce: shec_ajax.nonce,
        user_id: userId,
        first_name: $('input[name="first_name"]').val(),
        last_name: $('input[name="last_name"]').val(),
        state: $('input[name="state"]').val(),
        city: $('input[name="city"]').val(),
        social: $('input[name="social"]:checked').val()
    };

    const $btn = $(this).find('button[type="submit"]').prop('disabled', true);

    // 1) اول تماس را ذخیره کن
    $.post(shec_ajax.url, payloadContact, function (response) {
        if (!response || !response.success) {
        $btn.prop('disabled', false);
        toastr.error((response && response.message) || 'خطا در ذخیره اطلاعات تماس');
        return;
        }

        // 2) سپس finalize با پاسخ‌ها
        $.post(shec_ajax.url, {
        action: 'shec_finalize',
        _nonce: shec_ajax.nonce,
        user_id: userId,
        answers: answers
        }, function (fin) {
        $btn.prop('disabled', false);
        const d = wpUnwrap(fin);   
        if (fin && fin.success) {
            let method = 'FIT', graftCount = '', analysis = '';
        try {
        const parsed = JSON.parse(d.ai_result);  // ⬅️ از d بخوان
        method = parsed.method || method;
        graftCount = parsed.graft_count || '';
        analysis = parsed.analysis || '';
        } catch(e) {
        analysis = d.ai_result;                // ⬅️ از d بخوان
        }

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

            goToStep(6);
        } else {
            toastr.error((d && d.message) || 'خطا در نهایی‌سازی هوش مصنوعی');
        }
        }, 'json');

    }, 'json');
    });


    // ======== دانلود PDF ========
    $('#download-pdf').click(function () {
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

    // ======== دکمه برگشت به مرحله قبل ========
    $('.btn-prev').click(function () {
        const current = parseInt(localStorage.getItem('currentStep') || 1);
        const prev = Math.max(1, current - 1);
        goToStep(prev);
    });

    $(document).on('click', '#reset-form', function () {
        if (confirm('آیا مطمئن هستید که می‌خواهید فرم را از ابتدا شروع کنید؟')) {
            localStorage.clear();
            window.location.reload();
        }
    });

    // ==================== اجرا در زمان لود صفحه ====================
    const savedGender = localStorage.getItem('gender') || 'male';
     updateUploadDescriptionImages(savedGender);
});

function markErrorAndScroll(selector, msg, focusSelector) {
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
}