<?php $img_path = plugins_url('../public/assets/img/', __FILE__); ?>
<script src="https://unpkg.com/@lottiefiles/dotlottie-wc@0.6.2/dist/dotlottie-wc.js" type="module"></script>

<div class="container mt-5 flexy">
  <div id="progress-wrapper" class="position-sticky top-0 start-0 bg-white z-3">
    <div class="progress" style="height: 8px; border-radius:5px;">
      <div id="progress-bar" class="progress-bar bg-or" role="progressbar" style="width: 0%; transition: width 0.4s ease;"></div>
    </div>
  </div>

  <div id="step-container">
    <div class="container">

      <!-- Step 0: Rules -->
      <div id="step-0" class="step active">
        <div class="header-box">&nbsp;</div>
        <h3><?php echo esc_html__( 'Smart Hair Transplant Graft Estimator', 'shec' ); ?></h3>
        <div class="description">
          <div class="form-question">
            <?php echo esc_html__( 'Want to know how many grafts you may need for a hair transplant?', 'shec' ); ?>
          </div>
          <p>
            <?php echo esc_html__( 'Fakhraei Clinic’s smart tool, powered by our in-house AI, gives you a personalized estimate based on a few quick answers (and optional photos): expected graft count, best-fit technique, procedure duration, and recovery — all in a few clicks.', 'shec' ); ?>
            <br><strong><?php echo esc_html__( 'Note:', 'shec' ); ?></strong>
            <?php echo esc_html__( 'results are approximate and not a substitute for a specialist consultation. For a precise treatment plan, book a free in-person consultation at Fakhraei Clinic.', 'shec' ); ?>
          </p>
        </div>
        <div class="centrilized">
          <button class="btn form-button" id="agree-btn">
            <?php echo esc_html__( 'Agree & Start', 'shec' ); ?>
          </button>
        </div>
      </div>

      <!-- Step 1 -->
      <div id="step-1" class="step d-none">
        <form id="form-step-1" class="text-center">
          <!-- Gender Selection -->
          <div class="mb-4 shec-section">
            <label class="d-block mb-2 fw-bold">
              <?php echo esc_html__( 'Select your gender', 'shec' ); ?> <span class="text-danger">*</span>
            </label>
            <div class="g-box d-flex gap-3">
              <label class="gender-option">
                <input type="radio" name="gender" value="female" class="hidden-radio">
                <div class="option-box">
                  <span><?php echo esc_html__( 'Female', 'shec' ); ?></span>
                  <img src="<?php echo esc_url( $img_path . 'female.png' ); ?>" alt="<?php echo esc_attr__( 'Female', 'shec' ); ?>">
                </div>
              </label>

              <label class="gender-option">
                <input type="radio" name="gender" value="male" class="hidden-radio">
                <div class="option-box">
                  <span><?php echo esc_html__( 'Male', 'shec' ); ?></span>
                  <img src="<?php echo esc_url( $img_path . 'male.png' ); ?>" alt="<?php echo esc_attr__( 'Male', 'shec' ); ?>">
                </div>
              </label>
            </div>
          </div>

          <!-- Age -->
          <div class="mb-4 shec-section">
            <label class="d-block mb-2 fw-bold">
              <?php echo esc_html__( 'Select your age range', 'shec' ); ?> <span class="text-danger">*</span>
            </label>
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

            <label class="d-block mb-2 fw-bold" style=" margin-bottom:5px;">
              <?php echo esc_html__( 'Enter your mobile number', 'shec' ); ?>
            </label>
            <div class="mt-1 d-flex gap-1 justify-content-between" style="margin-bottom:30px;">
              <input type="text" class="d-block mb-2 fw-bold col-12" style="padding: 10px;" name="mobile" placeholder="<?php echo esc_attr__( 'Mobile number', 'shec' ); ?>">
            </div>
          </div>

          <!-- Confidence -->
          <div class="mb-4 shec-section">
            <label class="d-block mb-2 fw-bold">
              <?php echo esc_html__( 'How certain are you about getting a hair transplant?', 'shec' ); ?>
            </label>
            <select name="confidence" class="form-select mx-auto" required style="padding: 10px;border: 1px solid #ff6600;">
              <option value=""><?php echo esc_html__( 'Select…', 'shec' ); ?></option>
              <option value="<?php echo esc_attr__( 'I have hair loss and I’m not sure if now is the right time.', 'shec' ); ?>">
                <?php echo esc_html__( 'I have hair loss and I’m not sure if now is the right time.', 'shec' ); ?>
              </option>
              <option value="<?php echo esc_attr__( 'I want a transplant but I’m still researching.', 'shec' ); ?>">
                <?php echo esc_html__( 'I want a transplant but I’m still researching.', 'shec' ); ?>
              </option>
              <option value="<?php echo esc_attr__( 'I’ve decided and I’m looking for a good clinic.', 'shec' ); ?>">
                <?php echo esc_html__( 'I’ve decided and I’m looking for a good clinic.', 'shec' ); ?>
              </option>
              <option value="<?php echo esc_attr__( 'I’ve had a transplant before and I’m seeking repair/second session.', 'shec' ); ?>">
                <?php echo esc_html__( 'I’ve had a transplant before and I’m seeking repair/second session.', 'shec' ); ?>
              </option>
            </select>
          </div>

          <div class="mt-4 d-flex justify-content-between">
            <button type="button" class="btn btn-secondary btn-prev"><?php echo esc_html__( 'Previous', 'shec' ); ?></button>
            <button type="submit" class="btn btn-primary"><?php echo esc_html__( 'Next', 'shec' ); ?></button>
          </div>
        </form>
      </div>

    </div>

    <!-- Step 2: loss pattern -->
    <div id="step-2" class="step d-none">
      <div id="step2-loader" class="loader-overlay"><div class="spinner"></div></div>

      <label class="d-block mb-2 fw-bold">
        <?php echo esc_html__( 'Select your hair loss pattern', 'shec' ); ?>
      </label>
      <form id="form-step-2">
        <div class="row g-4">
          <?php for ( $i = 1; $i <= 6; $i++ ) { ?>
            <div class="col-6 col-md-6">
              <label class="pattern-option w-100 text-center">
                <input type="radio" name="loss_pattern" value="pattern-<?php echo esc_attr( $i ); ?>" hidden>
                <img src="#" data-colored="" data-gray="" class="pattern-img img-fluid" alt="<?php echo esc_attr__( 'Hair loss pattern image', 'shec' ); ?>">
                <div class="mt-2 fw-bold">
                  <?php echo esc_html__( 'Pattern', 'shec' ) . ' ' . intval( $i ); ?>
                </div>
              </label>
            </div>
          <?php } ?>
        </div>

        <div class="mt-4 d-flex justify-content-between">
          <button type="button" class="btn btn-secondary btn-prev"><?php echo esc_html__( 'Previous', 'shec' ); ?></button>
          <button type="submit" class="btn btn-primary"><?php echo esc_html__( 'Next', 'shec' ); ?></button>
        </div>
      </form>
    </div>

    <!-- Step 3: Upload -->
    <div id="step-3" class="step d-none">
      <div class="container-image shec-section">
        <label class="d-block mb-2 fw-bold">
          <?php echo esc_html__( 'Please upload photos of your scalp from the angles shown. (Front view is required!)', 'shec' ); ?>
        </label>
        <div class="subheading0-image">
          <?php echo esc_html__( 'Why do we need these images?', 'shec' ); ?>
        </div>
        <div class="description-image" style="text-align: left;line-height: 1.8">
          <p>
            <?php echo esc_html__( 'Photos of your donor area and thinning regions help our consultants provide better guidance and build a personalized plan for you.', 'shec' ); ?>
          </p>
          <div class="privacy-note" style="margin-top: 1.5em;background: #ff5a0014;padding: 1em;border-radius: 8px;font-size: 0.95rem;color: #333">
            <?php echo esc_html__( 'Your privacy matters. Your photos will be kept confidential and will not be used for any other purpose.', 'shec' ); ?>
          </div>
        </div>
        <div class="angles">
          <div class="angle"><img decoding="async" src="https://fakhraei.clinic/wp-content/uploads/2025/07/New-Project-80.webp" alt="<?php echo esc_attr__( 'Top view', 'shec' ); ?>"></div>
          <div class="angle"><img decoding="async" src="https://fakhraei.clinic/wp-content/uploads/2025/07/2-pic-1.webp" alt="<?php echo esc_attr__( 'Left side view', 'shec' ); ?>"></div>
          <div class="angle"><img decoding="async" src="https://fakhraei.clinic/wp-content/uploads/2025/07/3-pic-1.webp" alt="<?php echo esc_attr__( 'Back view', 'shec' ); ?>"></div>
          <div class="angle"><img decoding="async" src="https://fakhraei.clinic/wp-content/uploads/2025/07/1-pic-1.webp" alt="<?php echo esc_attr__( 'Front view (required)', 'shec' ); ?>"></div>
        </div>
      </div>

      <form id="form-step-3" enctype="multipart/form-data">
        <div class="row g-3" id="upload-zones"></div>
        <div class="mt-4 d-flex justify-content-between">
          <button type="button" class="btn btn-secondary btn-prev"><?php echo esc_html__( 'Previous', 'shec' ); ?></button>
          <button type="submit" class="btn btn-primary"><?php echo esc_html__( 'Next', 'shec' ); ?></button>
        </div>
      </form>
    </div>

    <!-- Step 4: concerns & medical -->
    <div id="step-4" class="step d-none">
      <form id="form-step-4">
        <div class="mb-4 shec-section">
          <label class="d-block mb-2 fw-bold">
            <?php echo esc_html__( 'What is your main concern about getting a hair transplant?', 'shec' ); ?>
          </label>
          <select name="concern" class="form-select mx-auto" required style="padding: 10px;border: 1px solid #ff6600;">
            <option value=""><?php echo esc_html__( 'Select…', 'shec' ); ?></option>
            <option value="<?php echo esc_attr__( 'I’m not sure the result will look good.', 'shec' ); ?>"><?php echo esc_html__( 'I’m not sure the result will look good.', 'shec' ); ?></option>
            <option value="<?php echo esc_attr__( 'I’m worried the final result takes too long.', 'shec' ); ?>"><?php echo esc_html__( 'I’m worried the final result takes too long.', 'shec' ); ?></option>
            <option value="<?php echo esc_attr__( 'I’m worried the recovery will be difficult.', 'shec' ); ?>"><?php echo esc_html__( 'I’m worried the recovery will be difficult.', 'shec' ); ?></option>
            <option value="<?php echo esc_attr__( 'I’m worried it will be very painful.', 'shec' ); ?>"><?php echo esc_html__( 'I’m worried it will be very painful.', 'shec' ); ?></option>
            <option value="<?php echo esc_attr__( 'Cost is very important to me.', 'shec' ); ?>"><?php echo esc_html__( 'Cost is very important to me.', 'shec' ); ?></option>
          </select>
        </div>

        <label class="d-block mb-2 shec-section">
          <?php echo esc_html__( 'Do you have any medical conditions?', 'shec' ); ?>
        </label>
        <div class="toggle-group">
          <label class="toggle-option">
            <input type="radio" name="has_medical" value="yes" hidden>
            <span><?php echo esc_html__( 'Yes', 'shec' ); ?></span>
          </label>
          <label class="toggle-option">
            <input type="radio" name="has_medical" value="no" hidden>
            <span><?php echo esc_html__( 'No', 'shec' ); ?></span>
          </label>
        </div>

        <div id="medical-fields" class="d-none">
          <label class="d-block mb-2 shec-section">
            <?php echo esc_html__( 'Scalp conditions', 'shec' ); ?>
          </label>
          <select name="scalp_conditions" class="form-select mx-auto" style="padding: 10px;border: 1px solid #ff6600;">
            <option value=""><?php echo esc_html__( 'Select…', 'shec' ); ?></option>
            <option value="<?php echo esc_attr__( 'Active scalp infection', 'shec' ); ?>"><?php echo esc_html__( 'Active scalp infection', 'shec' ); ?></option>
            <option value="<?php echo esc_attr__( 'Psoriasis', 'shec' ); ?>"><?php echo esc_html__( 'Psoriasis', 'shec' ); ?></option>
            <option value="<?php echo esc_attr__( 'Fungal infection / seborrheic dermatitis', 'shec' ); ?>"><?php echo esc_html__( 'Fungal infection / seborrheic dermatitis', 'shec' ); ?></option>
            <option value="<?php echo esc_attr__( 'Folliculitis', 'shec' ); ?>"><?php echo esc_html__( 'Folliculitis', 'shec' ); ?></option>
            <option value="<?php echo esc_attr__( 'Alopecia areata (patchy hair loss)', 'shec' ); ?>"><?php echo esc_html__( 'Alopecia areata (patchy hair loss)', 'shec' ); ?></option>
            <option value="<?php echo esc_attr__( 'Scarring alopecia', 'shec' ); ?>"><?php echo esc_html__( 'Scarring alopecia', 'shec' ); ?></option>
            <option value="<?php echo esc_attr__( 'Scar (cicatrix)', 'shec' ); ?>"><?php echo esc_html__( 'Scar (cicatrix)', 'shec' ); ?></option>
          </select>

          <label class="d-block mb-2 shec-section" style="margin:15px 0px;">
            <?php echo esc_html__( 'Other conditions', 'shec' ); ?>
          </label>
          <select name="other_conditions" class="form-select mx-auto" style="padding: 10px;border: 1px solid #ff6600;">
            <option value=""><?php echo esc_html__( 'Select…', 'shec' ); ?></option>
            <option value="<?php echo esc_attr__( 'Diabetes', 'shec' ); ?>"><?php echo esc_html__( 'Diabetes', 'shec' ); ?></option>
            <option value="<?php echo esc_attr__( 'Coagulation disorders', 'shec' ); ?>"><?php echo esc_html__( 'Coagulation disorders', 'shec' ); ?></option>
            <option value="<?php echo esc_attr__( 'Cardiovascular disease', 'shec' ); ?>"><?php echo esc_html__( 'Cardiovascular disease', 'shec' ); ?></option>
            <option value="<?php echo esc_attr__( 'Thyroid disorders', 'shec' ); ?>"><?php echo esc_html__( 'Thyroid disorders', 'shec' ); ?></option>
            <option value="<?php echo esc_attr__( 'Immunodeficiency', 'shec' ); ?>"><?php echo esc_html__( 'Immunodeficiency', 'shec' ); ?></option>
            <option value="<?php echo esc_attr__( 'Autoimmune disease', 'shec' ); ?>"><?php echo esc_html__( 'Autoimmune disease', 'shec' ); ?></option>
          </select>
        </div>

        <label class="d-block mb-2 shec-section">
          <?php echo esc_html__( 'Are you currently taking any medications?', 'shec' ); ?>
        </label>
        <div class="toggle-group">
          <label class="toggle-option">
            <input type="radio" name="has_meds" value="yes" hidden>
            <span><?php echo esc_html__( 'Yes', 'shec' ); ?></span>
          </label>
          <label class="toggle-option">
            <input type="radio" name="has_meds" value="no" hidden>
            <span><?php echo esc_html__( 'No', 'shec' ); ?></span>
          </label>
        </div>

        <div id="meds-fields" class="d-none">
          <input type="text" class="form-input mx-auto" style="padding: 10px;border: 1px solid #ff6600;" name="meds_list" placeholder="<?php echo esc_attr__( 'Enter the medication name', 'shec' ); ?>">
        </div>

        <div class="mt-4 d-flex justify-content-between">
          <button type="button" class="btn btn-secondary btn-prev"><?php echo esc_html__( 'Previous', 'shec' ); ?></button>
          <button type="submit" class="btn btn-primary"><?php echo esc_html__( 'Next', 'shec' ); ?></button>
        </div>
      </form>
    </div>

    <!-- Step 5: Final -->
    <div id="step-5" class="step d-none">
      <div id="step5-loader" class="ai-loader" style="display:none">
        <div class="ai-loader-card" style="padding:10px;background:linear-gradient(135deg, rgba(247,247,247,0.88) 10%, rgba(255,255,255,0.45) 90%);border:1.5px solid rgba(255,255,255,0.24);backdrop-filter: blur(18px) saturate(160%);-webkit-backdrop-filter: blur(18px) saturate(160%);outline:none!important;width:700px;">
          <div class="container-image shec-section visible">
            <label class="d-block mb-2 fw-bold" style="font-size:17px; font-weight:800;">
              <?php echo esc_html__( 'Fakhraei AI is preparing your personalized treatment path…', 'shec' ); ?>
            </label>
            <div class="description-image" style="text-align: left;line-height: 1.8">
              <p>
                <?php echo esc_html__( 'Every question you see is tailored just for you. They are generated from your concerns, medical context, and lifestyle to make your hair-transplant journey simpler and safer.', 'shec' ); ?>
              </p>
            </div>
            <div class="ai-ring-wrap">
              <svg class="ai-ring" viewBox="0 0 120 120" aria-hidden="true">
                <circle class="ring-bg" cx="60" cy="60" r="54"></circle>
                <circle class="ring-fg" cx="60" cy="60" r="54"></circle>
              </svg>
              <div class="ai-ring-label"><span id="ai-pct-step5">0</span>%</div>
            </div>
            <div id="ai-loader-text" class="ai-loader-text">
              <?php echo esc_html__( 'Analyzing gender and hair loss pattern…', 'shec' ); ?>
            </div>
          </div>

          <div class="why-padra-wrapper" style="margin-top:5px;">
            <p style="font-size:17px; font-weight:bold; ">
              <?php echo esc_html__( 'Why choose Fakhraei Clinic?', 'shec' ); ?>
            </p>

            <div class="why-padra-item">
              <img class="why-padra-logo" src="https://fakhraei.clinic/wp-content/uploads/2025/06/Black-White-Yellow-Simple-Initial-Name-Logo-22-1.png" alt="">
              <div class="why-padra-info">
                <span class="why-padra-info-title"><?php echo esc_html__( 'Experienced professional team', 'shec' ); ?></span>
                <p class="why-padra-info-description">
                  <?php echo esc_html__( 'Transplants are performed by trained technicians under the supervision of a specialist physician.', 'shec' ); ?>
                </p>
              </div>
            </div>

            <div class="why-padra-item">
              <img class="why-padra-logo" src="https://fakhraei.clinic/wp-content/uploads/2025/06/Group-1000003350.png" alt="">
              <div class="why-padra-info">
                <span class="why-padra-info-title"><?php echo esc_html__( 'Thousands of successful procedures', 'shec' ); ?></span>
                <p class="why-padra-info-description">
                  <?php echo esc_html__( 'With 20+ years of experience, we know how to deliver natural, lasting results.', 'shec' ); ?>
                </p>
              </div>
            </div>

            <div class="why-padra-item">
              <img class="why-padra-logo" src="https://fakhraei.clinic/wp-content/uploads/2025/06/Group-1000003557.png" alt="">
              <div class="why-padra-info">
                <span class="why-padra-info-title"><?php echo esc_html__( 'Fair pricing without quality compromise', 'shec' ); ?></span>
                <p class="why-padra-info-description">
                  <?php echo esc_html__( 'We aim to offer top technology and expertise at reasonable costs.', 'shec' ); ?>
                </p>
              </div>
            </div>

            <div class="why-padra-item">
              <img class="why-padra-logo" src="https://fakhraei.clinic/wp-content/uploads/2025/06/Group-1000003353.png" alt="">
              <div class="why-padra-info">
                <span class="why-padra-info-title"><?php echo esc_html__( 'Comfortable, fully equipped environment', 'shec' ); ?></span>
                <p class="why-padra-info-description">
                  <?php echo esc_html__( 'A calm, hygienic, and well-equipped space for a confident experience.', 'shec' ); ?>
                </p>
              </div>
            </div>

            <div class="why-padra-item">
              <img class="why-padra-logo" src="https://fakhraei.clinic/wp-content/uploads/2025/06/Group-1000003563.png" alt="">
              <div class="why-padra-info">
                <span class="why-padra-info-title"><?php echo esc_html__( 'Complimentary lodging for out-of-town clients', 'shec' ); ?></span>
                <p class="why-padra-info-description">
                  <?php echo esc_html__( 'We provide free accommodation for clients traveling from other cities.', 'shec' ); ?>
                </p>
              </div>
            </div>

            <div class="why-padra-item">
              <img class="why-padra-logo" src="https://fakhraei.clinic/wp-content/uploads/2025/06/bihesi.png" alt="">
              <div class="why-padra-info">
                <span class="why-padra-info-title"><?php echo esc_html__( 'Low-pain, comfort-focused experience', 'shec' ); ?></span>
                <p class="why-padra-info-description">
                  <?php echo esc_html__( 'Local anesthesia and modern techniques help ensure a comfortable procedure.', 'shec' ); ?>
                </p>
              </div>
            </div>

            <div class="why-padra-item">
              <img class="why-padra-logo" src="https://fakhraei.clinic/wp-content/uploads/2025/06/Group-1000003351.png" alt="">
              <div class="why-padra-info">
                <span class="why-padra-info-title"><?php echo esc_html__( 'Real support, before and after surgery', 'shec' ); ?></span>
                <p class="why-padra-info-description">
                  <?php echo esc_html__( 'From consultation to post-op care, we’re here to support you.', 'shec' ); ?>
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div id="ai-questions-box" class="mb-4 oshow" style="display:none">
        <div id="ai-questions-list"></div>
      </div>

      <form id="form-step-5" class="oshow">
        <div class="container">
          <p class="d-block mb-2 shec-section fw-bold">
            <?php echo esc_html__( 'Enter your first and last name', 'shec' ); ?>
          </p>
          <div class="mt-1 d-flex gap-1 justify-content-between" style="padding:5px; margin-bottom:30px; border-bottom: 1px solid #CDCFCE;">
            <input type="text" class="col-sm-12 col-md-6" name="first_name" placeholder="<?php echo esc_attr__( 'First name', 'shec' ); ?>">
            <input type="text" class="col-sm-12 col-md-6" name="last_name" placeholder="<?php echo esc_attr__( 'Last name', 'shec' ); ?>">
          </div>

          <p class="d-block mb-2 shec-section fw-bold">
            <?php echo esc_html__( 'Enter your location', 'shec' ); ?>
          </p>
          <div class="mt-1 d-flex gap-1 justify-content-between" style="padding:5px; margin-bottom:30px;">
            <input type="text" class="col-sm-12 col-md-6" name="state" placeholder="<?php echo esc_attr__( 'Province/State', 'shec' ); ?>">
            <input type="text" class="col-sm-12 col-md-6" name="city" placeholder="<?php echo esc_attr__( 'City', 'shec' ); ?>">
          </div>

          <p class="d-block mb-2 shec-section fw-bold">
            <?php echo esc_html__( 'How would you like to receive your specialist consultation?', 'shec' ); ?>
          </p>
          <div class="mt-1 d-flex gap-1 justify-content-between" style="padding:5px; margin-bottom:30px;">
            <div class="toggle-group">
              <label class="toggle-option">
                <input type="radio" name="social" value="call" hidden>
                <span><?php echo esc_html__( 'Phone call', 'shec' ); ?></span>
              </label>
              <label class="toggle-option">
                <input type="radio" name="social" value="whatsapp" hidden>
                <span><?php echo esc_html__( 'WhatsApp', 'shec' ); ?></span>
              </label>
            </div>
          </div>
        </div>

        <div class="mt-4 d-flex justify-content-between">
          <button type="button" class="btn btn-secondary btn-prev"><?php echo esc_html__( 'Previous', 'shec' ); ?></button>
          <button type="submit" class="btn btn-primary"><?php echo esc_html__( 'Next', 'shec' ); ?></button>
        </div>
      </form>
    </div>

    <!-- Step 6: Result -->
    <div id="step-6" class="step d-none">
      <div id="final-loader" class="loader-overlay bgl" style="display:none;">
        <div class="ai-loader">
          <div class="ai-spinner-img ai-scale-img">
            <img src="<?php echo esc_url( $img_path . 'spinner.webp' ); ?>" alt="<?php echo esc_attr__( 'Loading', 'shec' ); ?>" />
          </div>
          <div id="final-loader-text" class="ai-loader-text"></div>
        </div>
      </div>

      <!-- PDF root -->
      <div id="proposal-pdf-root" class="proposal-container">
        <h3><?php echo esc_html__( 'Consultation Result', 'shec' ); ?></h3>

        <!-- AI output -->
        <div id="ai-result-box" class="result-box"></div>

        <!-- Sample/info -->
        <div class="sample-info-wrapper">
          <p style="font-size:20px; font-weight:bold; text-align:center;">
            <?php echo esc_html__( 'You can transform your look too!', 'shec' ); ?>
          </p>
          <img class="sample-image" src="https://fakhraei.clinic/wp-content/uploads/2025/06/BEFORE_Miss.webp" style="width: 100%;border-radius: 5px;" alt="<?php echo esc_attr__( 'Before/After sample', 'shec' ); ?>">
        </div>

        <div class="hair-trans-wrapper">
          <img src="https://fakhraei.clinic/wp-content/uploads/2025/06/FIT1-1-scaled-1.png" style="width: 100%;border-radius: 5px;" alt="<?php echo esc_attr__( 'Hair transplant', 'shec' ); ?>">
        </div>

        <div class="fit-timeline-wrapper">
          <p style="font-size:20px; font-weight:bold; text-align:center;">
            <?php echo esc_html__( 'Expected Timeline of FIT Hair Transplant Results', 'shec' ); ?>
          </p>
          <table class="fit-timeline-table">
            <thead>
              <tr>
                <th><?php echo esc_html__( 'Timeframe', 'shec' ); ?></th>
                <th><?php echo esc_html__( 'What to expect', 'shec' ); ?></th>
              </tr>
            </thead>
            <tbody>
              <tr><td><?php echo esc_html__( 'Days 1–7', 'shec' ); ?></td><td><?php echo esc_html__( 'Redness and mild swelling are normal; they gradually subside.', 'shec' ); ?></td></tr>
              <tr><td><?php echo esc_html__( 'Weeks 2–3', 'shec' ); ?></td><td><?php echo esc_html__( 'Temporary shedding of transplanted hairs (shock loss); completely normal.', 'shec' ); ?></td></tr>
              <tr><td><?php echo esc_html__( 'Months 1–2', 'shec' ); ?></td><td><?php echo esc_html__( 'Scalp returns to baseline; new hairs are typically not visible yet.', 'shec' ); ?></td></tr>
              <tr><td><?php echo esc_html__( 'Months 3–4', 'shec' ); ?></td><td><?php echo esc_html__( 'New hair growth begins; initially thin and soft.', 'shec' ); ?></td></tr>
              <tr><td><?php echo esc_html__( 'Months 5–6', 'shec' ); ?></td><td><?php echo esc_html__( 'Shafts strengthen and density improves.', 'shec' ); ?></td></tr>
              <tr><td><?php echo esc_html__( 'Months 7–9', 'shec' ); ?></td><td><?php echo esc_html__( 'Thicker, denser, more natural look; differences become obvious.', 'shec' ); ?></td></tr>
              <tr><td><?php echo esc_html__( 'Months 10–12', 'shec' ); ?></td><td><?php echo esc_html__( 'Around 80–90% of the final result is visible.', 'shec' ); ?></td></tr>
              <tr><td><?php echo esc_html__( 'After 12 months', 'shec' ); ?></td><td><?php echo esc_html__( 'Full stabilization; natural and long-lasting result.', 'shec' ); ?></td></tr>
            </tbody>
          </table>
        </div>

        <div class="why-padra-wrapper">
          <p style="font-size:20px; font-weight:bold; text-align:center;margin-top: 50px;">
            <?php echo esc_html__( 'Why choose Fakhraei Clinic?', 'shec' ); ?>
          </p>

          <div class="why-padra-item">
            <img class="why-padra-logo" src="https://fakhraei.clinic/wp-content/uploads/2025/06/Black-White-Yellow-Simple-Initial-Name-Logo-22-1.png" alt="">
            <div class="why-padra-info">
              <span class="why-padra-info-title"><?php echo esc_html__( 'Experienced professional team', 'shec' ); ?></span>
              <p class="why-padra-info-description">
                <?php echo esc_html__( 'Procedures are performed by trained technicians under specialist supervision.', 'shec' ); ?>
              </p>
            </div>
          </div>

          <div class="why-padra-item">
            <img class="why-padra-logo" src="https://fakhraei.clinic/wp-content/uploads/2025/06/Group-1000003350.png" alt="">
            <div class="why-padra-info">
              <span class="why-padra-info-title"><?php echo esc_html__( 'Thousands of successful procedures', 'shec' ); ?></span>
              <p class="why-padra-info-description">
                <?php echo esc_html__( 'With 20+ years of experience, we deliver natural, lasting results.', 'shec' ); ?>
              </p>
            </div>
          </div>

          <div class="why-padra-item">
            <img class="why-padra-logo" src="https://fakhraei.clinic/wp-content/uploads/2025/06/Group-1000003557.png" alt="">
            <div class="why-padra-info">
              <span class="why-padra-info-title"><?php echo esc_html__( 'Fair pricing without quality compromise', 'shec' ); ?></span>
              <p class="why-padra-info-description">
                <?php echo esc_html__( 'Top technology and expertise at reasonable costs.', 'shec' ); ?>
              </p>
            </div>
          </div>

          <div class="why-padra-item">
            <img class="why-padra-logo" src="https://fakhraei.clinic/wp-content/uploads/2025/06/Group-1000003353.png" alt="">
            <div class="why-padra-info">
              <span class="why-padra-info-title"><?php echo esc_html__( 'Comfortable, fully equipped environment', 'shec' ); ?></span>
              <p class="why-padra-info-description">
                <?php echo esc_html__( 'A calm, hygienic, and fully equipped space.', 'shec' ); ?>
              </p>
            </div>
          </div>

          <div class="why-padra-item">
            <img class="why-padra-logo" src="https://fakhraei.clinic/wp-content/uploads/2025/06/Group-1000003563.png" alt="">
            <div class="why-padra-info">
              <span class="why-padra-info-title"><?php echo esc_html__( 'Complimentary lodging for out-of-town clients', 'shec' ); ?></span>
              <p class="why-padra-info-description">
                <?php echo esc_html__( 'Free accommodation for clients from other cities.', 'shec' ); ?>
              </p>
            </div>
          </div>

          <div class="why-padra-item">
            <img class="why-padra-logo" src="https://fakhraei.clinic/wp-content/uploads/2025/06/bihesi.png" alt="">
            <div class="why-padra-info">
              <span class="why-padra-info-title"><?php echo esc_html__( 'Low-pain, comfort-focused experience', 'shec' ); ?></span>
              <p class="why-padra-info-description">
                <?php echo esc_html__( 'Local anesthesia and modern techniques for a comfortable experience.', 'shec' ); ?>
              </p>
            </div>
          </div>

          <div class="why-padra-item">
            <img class="why-padra-logo" src="https://fakhraei.clinic/wp-content/uploads/2025/06/Group-1000003351.png" alt="">
            <div class="why-padra-info">
              <span class="why-padra-info-title"><?php echo esc_html__( 'We’re with you before and after surgery', 'shec' ); ?></span>
              <p class="why-padra-info-description">
                <?php echo esc_html__( 'From consultation to post-op care, we’re here to help.', 'shec' ); ?>
              </p>
            </div>
          </div>
        </div>

        <div class="actions mt-3">
          <button id="reset-form" class="btn btn-danger">
            <?php echo esc_html__( 'Start Over', 'shec' ); ?>
          </button>
          <button id="download-pdf" class="btn btn-primary">
            <?php echo esc_html__( 'Download PDF', 'shec' ); ?>
          </button>
        </div>
      </div>
    </div>

  </div>
</div>

<script>console.log('Form template loaded');</script>
