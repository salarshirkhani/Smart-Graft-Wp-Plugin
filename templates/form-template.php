<?php $img_path = plugins_url('../public/assets/img/', __FILE__); ?>
<div id="progress-wrapper" class="position-sticky top-0 start-0 w-100 bg-white z-3" >
    <div class="progress" style="height: 8px; border-radius:0px;">
         <div id="progress-bar" class="progress-bar bg-or" role="progressbar" style="width: 0%; transition: width 0.4s ease;"></div>
    </div>
</div>
<div class="container mt-5">
    <div id="step-container">

        <div class="container">
        <!-- Step 0: Rules -->
        <div id="step-0" class="step active">
            <div class="header-box">&nbsp;</div>
            <h3>ابزار هوشمند محاسبه تعداد تار مو برای کاشت</h3>
            <div class="description">
                <div class="form-question">می&zwnj;خواهید بدانید به چند گرافت برای کاشت مو نیاز دارید؟</div>
                <p>ابزار هوشمند کلینیک فخرائی با تکیه بر هوش مصنوعی اختصاصی و بر اساس پاسخ‌های کوتاه شما (و در صورت تمایل، تصاویر ارسالی) یک برآورد شخصی‌سازی‌شده ارائه می‌دهد: تعداد گرافت موردنیاز، روش مناسب کاشت، مدت‌زمان انجام و دوره نقاهت—همه فقط در چند کلیک.
توجه: این نتایج تقریبی‌اند و جایگزین مشاوره تخصصی نیستند. برای برنامه درمانی دقیق، از مشاوره حضوری رایگان کلینیک فخرائی استفاده کنید.</p>
            </div>
            <div class="centrilized">
                <button class="btn form-button" id="agree-btn">تایید و شروع</button>
            </div>
        </div>

        <div id="step-1" class="step d-none">
        <form id="form-step-1" class="text-center">

            <!-- Gender Selection -->
            <div class="mb-4">
                <label class="d-block mb-2 fw-bold ">جنسیت خود را انتخاب کنید <span class="text-danger">*</span></label>
                <div class="g-box d-flex justify-content-center gap-3">
                    <label class="gender-option">
                    <input type="radio" name="gender" value="female" class="hidden-radio">
                    <div class="option-box">
                        <span>زن</span>
                        <img src="<?php echo $img_path . 'women.png'; ?>" alt="زن">
                    </div>
                    </label>

                    <label class="gender-option">
                    <input type="radio" name="gender" value="male" class="hidden-radio">
                    <div class="option-box">
                        <span>مرد</span>
                        <img src="<?php echo $img_path . 'men.png'; ?>" alt="مرد">
                    </div>
                    </label>
                </div>
            </div>

            <!-- Age -->
            <div class="mb-4">
                <label class="d-block mb-2 fw-bold ">بازه سنی خود را انتخاب کنید 
                <span class="text-danger">*</span></label>
                <div class="a-box d-flex justify-content-center gap-1">
                    <label class="age-option">
                        <input type="radio" name="age" value="18-23" class="hidden-radio">
                        <div class="option-box"><span>18-23</span></div>
                    </label>
                    <label class="age-option">
                        <input type="radio" name="age" value="24-29" class="hidden-radio">
                        <div class="option-box"><span>24-29</span></div>
                    </label>
                    <label class="age-option">
                        <input type="radio" name="age" value="30-35" class="hidden-radio">
                        <div class="option-box"><span>30-35</span></div>
                    </label>
                    <label class="age-option">
                        <input type="radio" name="age" value="36-43" class="hidden-radio">
                        <div class="option-box"><span>36-43</span></div>
                    </label>
                    <label class="age-option">
                        <input type="radio" name="age" value="44-56" class="hidden-radio">
                        <div class="option-box"><span>44-56</span></div>
                    </label>
                    <label class="age-option">
                        <input type="radio" name="age" value="+56" class="hidden-radio">
                        <div class="option-box"><span>+56</span></div>
                    </label>
                </div>

                <p class="d-block mb-2 fw-bold" style="text-align: right; margin-bottom:5px;">شماره تلفن همراه خود را وارد کنید</p>
                    <div class="mt-1 d-flex gap-1 justify-content-between" style="margin-bottom:30px; ">
                    <input type="text" class="d-block mb-2 fw-bold col-12" style="padding: 10px;" name="mobile" placeholder="شماره همراه">
                </div>

            </div>

            <div class="mb-4">
                <label class="d-block mb-2 fw-bold">برای کاشت مو چقدر قطعیت دارید؟</label>
                <select name="confidence" class="form-select mx-auto" style="padding: 10px;border: 1px solid #ff6600;">
                    <option value="ریزش مو دارم و نمیدونم الان باید بکارم یا نه!">ریزش مو دارم و نمیدونم الان باید بکارم یا نه!</option>
                    <option value="می‌خوام مو بکارم اما دارم در این مورد تحقیق می‌کنم.">می‌خوام مو بکارم اما دارم در این مورد تحقیق می‌کنم.</option>
                    <option value="تصمیمم رو گرفتم و دنبال یه کلینیک خوب می‌گردم.">تصمیمم رو گرفتم و دنبال یه کلینیک خوب می‌گردم.</option>
                    <option value="قبلا مو کاشتم و دنبال کاشت مجدد و ترمیم هستم.">قبلا مو کاشتم و دنبال کاشت مجدد و ترمیم هستم.</option>
                </select>
            </div>

            <div class="mt-4 d-flex justify-content-between">
                <button type="button" class="btn btn-secondary btn-prev">مرحله قبل</button>
                <button type="submit" class="btn btn-primary">مرحله بعد</button>
            </div>
        </form>
        </div>
    </div>
        <!-- Step 2: extent of hair loss -->
        <div id="step-2" class="step d-none">

            <div id="step2-loader" class="loader-overlay">
                <div class="spinner"></div>
            </div>
            
            <label class="d-block mb-2 fw-bold ">الگوی ریزش موی خود را انتخاب کنید</label>
            <form id="form-step-2">
                <div class="row g-4">

                    <?php for( $i=1 ; $i<=6 ; $i++){ ?>
                        
                    <div class="col-12 col-md-6">
                        <label class="pattern-option w-100 text-center">
                        <input type="radio" name="loss_pattern" value="pattern-<?php echo $i; ?>" hidden>
                        <img src="#" data-colored="" data-gray="" class="pattern-img img-fluid">
                        <div class="mt-2 fw-bold">الگوی <?php echo $i; ?></div>
                        </label>
                    </div>

                    <?php  }  ?>

                </div>

                <div class="mt-4 d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary btn-prev">مرحله قبل</button>
                    <button type="submit" class="btn btn-primary">مرحله بعد</button>
                </div>
            </form>
        </div>

        <!-- Step 3: Upload-->
        <div id="step-3" class="step d-none">
            <div class="container-image">
                <label class="d-block mb-2 fw-bold ">لطفاً عکس‌هایی از موی سر خود، از زوایای مشخص‌شده بارگذاری کنید. (اختیاری)</label>
                <div class="subheading0-image">چرا به این تصاویر نیاز داریم؟</div>
                <div class="description-image" dir="rtl" style="text-align: right;line-height: 1.8">
                    <p>تصاویر بانک مو و مناطقی که دچار ریزش مو شده&zwnj;اند، به مشاوران ما کمک می&zwnj;کنند تا راهنمایی بهتری ارائه کرده و یک برنامه درمانی شخصی&zwnj;سازی&zwnj;شده برای شما تنظیم کنند.</p>
                    <div class="privacy-note" style="margin-top: 1.5em;background: #ff5a0014;padding: 1em;border-radius: 8px;font-size: 0.95rem;color: #333">حریم خصوصی شما برای ما اهمیت زیادی دارد. تصاویر شما محرمانه نگهداری خواهند شد و هیچ&zwnj;گونه استفاده&zwnj;ای از آن&zwnj;ها نخواهد شد.</div>
                </div>
                <div class="angles">
                    <div class="angle"><img decoding="async" src="https://fakhraei.clinic/wp-content/uploads/2025/07/New-Project-80.webp" alt="نمای بالا"></div>
                    <div class="angle"><img decoding="async" src="https://fakhraei.clinic/wp-content/uploads/2025/07/2-pic-1.webp" alt="نمای سمت چپ"></div>
                    <div class="angle"><img decoding="async" src="https://fakhraei.clinic/wp-content/uploads/2025/07/3-pic-1.webp" alt="نمای پشت"></div>
                    <div class="angle"><img decoding="async" src="https://fakhraei.clinic/wp-content/uploads/2025/07/1-pic-1.webp" alt="نمای روبرو"></div>
                </div>
            </div>
            <form id="form-step-3" enctype="multipart/form-data">
                <div class="row g-3" id="upload-zones">
                </div>
                <div class="mt-4 d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary btn-prev">مرحله قبل</button>
                    <button type="submit" class="btn btn-primary">مرحله بعد</button>
                </div>
            </form>
        </div>

        <!-- Step 4: concerns & medical -->
        <div id="step-4" class="step d-none">
            <form id="form-step-4">
                <div class="mb-4">
                    <label class="d-block mb-2 fw-bold">نگرانی و دغدغه اصلی شما برای انجام کاشت مو کدام است؟</label>
                    <select name="concern" class="form-select mx-auto" style="padding: 10px;border: 1px solid #ff6600;">
                        <option value="مطمئن نیستم نتیجه کاشت خوب بشه یا نه.">مطمئن نیستم نتیجه کاشت خوب بشه یا نه.</option>
                        <option value="نگرانم نتیجه نهایی خیلی طول بکشه.">نگرانم نتیجه نهایی خیلی طول بکشه.</option>
                        <option value="نگرانم دوران نقاهت سختی داشته باشه.">نگرانم دوران نقاهت سختی داشته باشه.</option>
                        <option value="نگرانم خیلی درد داشته باشه.">نگرانم خیلی درد داشته باشه.</option>
                        <option value="هزینه برام خیلی مهمه.">هزینه برام خیلی مهمه.</option>
                        <option value="نگرانی دیگه ای دارم">نگرانی دیگه ای دارم</option>
                    </select>
                </div>
                <label class="d-block mb-2 ">آیا به بیماری خاصی مبتلا هستید؟</label>
                <div class="toggle-group">
                    <label class="toggle-option">
                    <input type="radio" name="has_medical" value="yes" hidden>
                    <span>بله</span>
                    </label>
                    <label class="toggle-option">
                    <input type="radio" name="has_medical" value="no" hidden>
                    <span>خیر</span>
                    </label>
                </div>

                <div id="medical-fields" class="d-none">
                    <label class="d-block mb-2 ">بیماری‌های پوستی</label>
                    <select name="scalp_conditions" class="form-select mx-auto" style="padding: 10px;border: 1px solid #ff6600;">
                        <option value="">انتخاب کنید</option>    
                        <option value="عفونت فعال پوست سر">عفونت فعال پوست سر</option>
                        <option value="پسوریازیس">پسوریازیس</option>
                        <option value="عفونت قارچی">عفونت قارچی</option>
                        <option value="فولیکولیت">فولیکولیت</option>
                        <option value="ریزش سکه‌ای (آلوپسی آره‌آتا)">ریزش سکه‌ای (آلوپسی آره‌آتا)</option>
                        <option value="آلوپسی به همراه اسکار">آلوپسی به همراه اسکار</option>
                        <option value="جای زخم (اسکار)">جای زخم (اسکار)</option>
                        <option value="هیچکدام">هیچکدام</option>
                    </select>
                    <label class="d-block mb-2 " style="margin:15px 0px;">سایر بیماری ها</label>
                    <select name="other_conditions" class="form-select mx-auto" style="padding: 10px;border: 1px solid #ff6600;">
                        <option value="">انتخاب کنید</option>    
                        <option value="دیابت">دیابت</option>
                        <option value="اختلالات انعقاد خون">اختلالات انعقاد خون</option>
                        <option value="بیماری قلبی">بیماری قلبی</option>
                        <option value="اختلالات تیروئید">اختلالات تیروئید</option>
                        <option value="ضعف سیستم ایمنی">ضعف سیستم ایمنی</option>
                        <option value="بیماری‌های خودایمنی">بیماری‌های خودایمنی</option>
                        <option value="هیچکدام">هیچکدام</option>
                    </select>
                </div>

                <label class="d-block mb-2 ">آیا در حال حاضر داروی خاصی مصرف می‌کنید؟</label>
                <div class="toggle-group">
                    <label class="toggle-option">
                    <input type="radio" name="has_meds" value="yes" hidden>
                    <span>بله</span>
                    </label>
                    <label class="toggle-option">
                    <input type="radio" name="has_meds" value="no" hidden>
                    <span>خیر</span>
                    </label>
                </div>

                <div id="meds-fields" class="d-none">
                    <input type="text" class="form-input mx-auto" style="padding: 10px;border: 1px solid #ff6600;" name="meds_list" placeholder="نام دارو را وارد کنید">
                </div>

                <div class="mt-4 d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary btn-prev">مرحله قبل</button>
                    <button type="submit" class="btn btn-primary">مرحله بعد</button>
                </div>
            </form>
        </div>

        <!-- Step 5: fINAL-->
        <div id="step-5" class="step d-none">
            <div id="step5-loader" class="loader-overlay" style="display:none;">
            <div class="ai-loader">
                <div class="ai-spinner-img">
                    <img src="<?php echo $img_path . 'spinner.webp'; ?>" alt="loading" />
                </div>
                <div id="ai-loader-text" class="ai-loader-text"></div>
            </div>
            </div>
            <div id="ai-questions-box" class="mb-4" style="display:none">
                <div id="ai-questions-list"></div>
            </div>
            <form id="form-step-5">
                <div class="container">
                    <p class="d-block mb-2 fw-bold">نام و نام خانوادگی خود را وارد کنید</p>
                    <div class="mt-1 d-flex gap-1 justify-content-between" style="padding:5px; margin-bottom:30px; border-bottom: 1px solid #CDCFCE;">
                        <input type="text" class="col-sm-12 col-md-6" name="first_name" placeholder="نام">
                        <input type="text" class="col-sm-12 col-md-6" name="last_name" placeholder="نام خانوادگی">
                    </div>
                    <p class="d-block mb-2 fw-bold"> محل سکونت خود را انتخاب کنید</p>
                    <div class="mt-1 d-flex gap-1 justify-content-between" style="padding:5px; margin-bottom:30px; ">
                        <input type="text" class="col-sm-12 col-md-6" name="state" placeholder="استان">
                        <input type="text" class="col-sm-12 col-md-6" name="city" placeholder="شهر">
                    </div>

                    <p class="d-block mb-2 fw-bold">از چه طریق تمایل به دریافت مشاوره تخصصی دارید؟</p>
                    <div class="mt-1 d-flex gap-1 justify-content-between" style="padding:5px; margin-bottom:30px; ">
                        <div class="toggle-group">
                            <label class="toggle-option">
                                <input type="radio" name="social" value="call" hidden>
                                <span>تماس</span>
                            </label>
                            <label class="toggle-option">
                                <input type="radio" name="social" value="whatsapp" hidden>
                                <span>واتس اپ</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mt-4 d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary btn-prev">مرحله قبل</button>
                    <button type="submit" class="btn btn-primary">مرحله بعد</button>
                </div>
            </form>
        </div>

<!-- Step 6: Result -->
<div id="step-6" class="step d-none">
    <div id="final-loader" class="loader-overlay" style="display:none;">
        <div class="ai-loader">
            <div class="ai-spinner-img ai-scale-img">
            <img src="<?php echo $img_path . 'spinner.webp'; ?>" alt="loading" />
            </div>
            <div id="final-loader-text" class="ai-loader-text"></div>
        </div>
    </div>


  <!-- 👇 همین قسمت PDF می‌شود -->
  <div id="proposal-pdf-root" class="proposal-container">
    <h3>نتیجه مشاوره</h3>

    <!-- خروجی هوش مصنوعی (همان قبلی) -->
    <div id="ai-result-box" class="result-box"></div>

    <!-- خلاصه اطلاعات کاربر (همان قبلی) -->
    <div class="user-summary mt-4">
      <h5>خلاصه اطلاعات شما</h5>
      <ul id="user-summary-list"></ul>
    </div>
  </div>

  <div class="actions mt-3">
    <button id="reset-form" class="btn btn-danger">شروع مجدد</button>
    <button id="download-pdf" class="btn btn-primary">دانلود PDF</button>
  </div>
</div>




    </div>
</div>

<script>console.log('Form template loaded');</script>
