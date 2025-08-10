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
        if (!gender) {
            toastr.error('لطفاً جنسیت را انتخاب کنید');
            return;
        }

        const dataToSend = {
            action: 'shec_step1',
            _nonce: shec_ajax.nonce,
            gender: gender,
            age: $('input[name="age"]:checked').val(),
            confidence: $('select[name="confidence"]').val()
        };

        // لاگ کردن داده‌های ارسالی به کنسول
        console.log("Data being sent to server:", dataToSend);
        $.post(
            shec_ajax.url,
            {
                action: 'shec_step1',
                _nonce: shec_ajax.nonce,
                gender: gender,
                age: $('input[name="age"]:checked').val(),
                confidence: $('select[name="confidence"]').val()
            },
            function (response) {
                console.log(response);
                if (response.success) {
                    console.log('res',response); // لاگ گرفتن از user_id
                    userId = response.data.user_id;
                    localStorage.setItem('userId', userId);
                    localStorage.setItem('gender', gender);
                    initializeStep2PatternImages(gender);
                    goToStep(2);
                } else {
                    toastr.error(response.message || 'خطا در ارسال اطلاعات');
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
                    console.log('Response from server:', response);
                    const firstFile = Object.values(res.files)[0];
                    $thumb.attr('src', firstFile).removeClass('d-none');
                    $progress.addClass('d-none');
                    $bar.css('width', '0%');
                    $box.addClass('upload-success');

                    const uploads = JSON.parse(localStorage.getItem('uploadedPics') || '{}');
                    uploads[fileInput.name] = firstFile;
                    localStorage.setItem('uploadedPics', JSON.stringify(uploads));
                } else {
                    toastr.error(res.message || 'خطا در آپلود');
                }
            }
        });
    });


    // ======== مرحله ۴: سوالات پزشکی ========
    $(document).on('change', 'input[name="has_medical"]', function () {
        $('input[name="has_medical"]').parent().removeClass('active');
        if ($(this).is(':checked')) $(this).parent().addClass('active');
        $('#medical-fields').toggleClass('d-none', $(this).val() !== 'yes');
    });

    $(document).on('change', 'input[name="has_meds"]', function () {
        $('input[name="has_meds"]').parent().removeClass('active');
        if ($(this).is(':checked')) $(this).parent().addClass('active');
        $('#meds-fields').toggleClass('d-none', $(this).val() !== 'yes');
    });

    $('#form-step-4').submit(function (e) {
        e.preventDefault();
        $.post(
            shec_ajax.url,
            Object.assign(
                { action: 'shec_step4', _nonce: shec_ajax.nonce },
                $('#form-step-4').serializeArray().reduce((o, field) => (o[field.name] = field.value, o), {}),
                { user_id: userId }
            ),
            function (response) {
                console.log('Response from server5:', response);
                if (response.success) {
                    goToStep(5);
                } else {
                    toastr.error(response.message || 'خطا در مرحله ۴');
                }
            },
            'json'
        );
    });

    // ======== مرحله ۵: اطلاعات تماس ========
    $(document).on('submit', '#form-step-5', function (e) {
        e.preventDefault();
        if (!userId) {
            toastr.error('کاربر شناسایی نشد، لطفاً دوباره مراحل را شروع کنید');
            return;
        }

        $.post(
            shec_ajax.url,
            {
                action: 'shec_step5',
                _nonce: shec_ajax.nonce,
                user_id: userId,
                first_name: $('input[name="first_name"]').val(),
                last_name: $('input[name="last_name"]').val(),
                state: $('input[name="state"]').val(),
                city: $('input[name="city"]').val(),
                mobile: $('input[name="mobile"]').val(),
                social: $('input[name="social"]:checked').val()
            },
            function (response) {
                console.log('Response from server5:', response);
                if (response.success) {
                    let method = '';
                    let graftCount = '';
                    let analysis = '';

                    try {
                        const parsed = JSON.parse(response.ai_result);
                        method = parsed.method || 'FIT';
                        graftCount = parsed.graft_count || '';
                        analysis = parsed.analysis || '';
                    } catch (err) {
                        analysis = response.ai_result;
                        method = 'FIT';
                    }

                    $('#ai-result-box').html(`
                        <div class="ai-result-container">
                            <h4>روش پیشنهادی: <span class="method-text">${method}</span></h4>
                            <p class="analysis-text">${analysis}</p>
                            ${graftCount ? `
                                <div class="graft-count-box">
                                    <strong>تخمین تعداد گرافت:</strong> ${graftCount} گرافت
                                </div>` : ''}
                        </div>
                    `);

                    let summary = `
                        <li><strong>نام:</strong> ${response.data.user.contact.first_name} ${response.data.user.contact.last_name}</li>
                        <li><strong>جنسیت:</strong> ${response.data.user.contact.gender}</li>
                        <li><strong>سن:</strong> ${response.data.user.contact.age}</li>
                        <li><strong>شهر:</strong> ${response.data.user.contact.city}, ${response.data.user.contact.state}</li>
                    `;
                    $('#user-summary-list').html(summary);

                    goToStep(6);
                } else {
                    toastr.error(response.message || 'خطا در مرحله ۵');
                }
            },
            'json'
        );
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
    updateLossPatternImages(savedGender);
    updateUploadDescriptionImages(savedGender);
});
