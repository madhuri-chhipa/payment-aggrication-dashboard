document.addEventListener('DOMContentLoaded', function () {
  const stepperEl = document.querySelector('#wizard-icons');
  // const stepper = new Stepper(stepperEl);
  const stepper = new Stepper(stepperEl, {
    linear: true, // 🔥 THIS forces step-by-step validation
    animation: true
  });
  const nextBtns = document.querySelectorAll('.btn-next');
  const prevBtns = document.querySelectorAll('.btn-prev');
  const form = document.getElementById('registerForm');
  const rules = {
    company_name: {
      required: true,
      maxlength: 255
    },
    email: {
      required: true,
      email: true
    },
    mobile_number: {
      required: true,
      digits: true,
      minlength: 10,
      maxlength: 10
    },
    password: {
      required: true,
      minlength: 8
    },
    password_confirmation: {
      required: true,
      equalTo: 'password'
    },

    company_type: {
      required: true
    },
    gst_no: {
      maxlength: 15
    },
    cin: {
      maxlength: 21
    },
    pan: {
      maxlength: 10
    },
    udhyam_number: {
      maxlength: 20
    },
    address: {
      maxlength: 500
    },

    director_name: {
      required: true,
      maxlength: 255
    },
    director_email: {
      required: true,
      email: true
    },
    director_mobile: {
      required: true,
      digits: true,
      minlength: 10,
      maxlength: 10
    },
    director_aadhar_no: {
      required: true,
      digits: true,
      minlength: 12,
      maxlength: 12
    },
    director_pan_no: {
      required: true,
      maxlength: 10
    },

    // payout_status: {},
    // payin_status: {},

    // minimum_transaction: {
    //   number: true,
    //   min: 1
    // },
    // maximum_transaction: {
    //   number: true,
    //   greaterThan: 'minimum_transaction'
    // },

    // payin_minimum_transaction: {
    //   number: true,
    //   min: 1
    // },
    // payin_maximum_transaction: {
    //   number: true,
    //   greaterThan: 'payin_minimum_transaction'
    // },

    // virtal_charges: {
    //   number: true,
    //   min: 0
    // },
    // pslab_1000: {
    //   number: true,
    //   min: 0
    // },
    // pslab_25000: {
    //   number: true,
    //   min: 0
    // },
    // pslab_200000: {
    //   number: true,
    //   min: 0
    // },
    // pslab_percentage: {
    //   number: true,
    //   min: 0
    // },
    // payin_charges: {
    //   number: true,
    //   min: 0
    // },

    // client_key: {
    //   maxlength: 255
    // },
    // client_secret: {
    //   maxlength: 255
    // },
    // payin_webhooks: {
    //   url: true
    // },
    // payout_webhooks: {
    //   url: true
    // }
  };

  const fileRules = {
    gst_image: {},
    cin_image: {},
    pan_image: {},
    udhyam_image: {},
    moa_image: {},
    br_image: {},
    director_aadhar_image: {
      required: true
    },
    director_pan_image: {
      required: true
    }
  };

  const allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'webp', 'svg', 'gif'];
  const maxFileSize = 5120 * 1024; // 5MB

  function showError(input, message) {
    removeError(input); // remove old message first

    input.classList.add('is-invalid');

    const error = document.createElement('div');
    error.className = 'error-text';
    error.innerText = message;

    input.closest('.col-md-6, .col-12')?.appendChild(error);
  }

  function removeError(input) {
    input.classList.remove('is-invalid');
    const parent = input.closest('.col-md-6, .col-12');
    const oldError = parent?.querySelector('.error-text');
    if (oldError) oldError.remove();
  }

  function validateStep(stepContent) {
    let inputs = stepContent.querySelectorAll('input, select, textarea');
    let isValid = true;
    let firstInvalid = null;

    inputs.forEach(input => {
      removeError(input);
      const name = input.name;
      const value = input.value.trim();
      const rule = rules[name];
      const fileRule = fileRules[name];

      function invalidate(message = 'This field is invalid') {
        isValid = false;
        showError(input, message);
        if (!firstInvalid) firstInvalid = input;
      }
      // ---------------- TEXT RULES ----------------
      if (rule) {
        if (rule.required && !value) invalidate('This field is required');

        if (rule.email && value) {
          let pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
          if (!pattern.test(value)) invalidate('Enter a valid email address');
        }

        if (rule.digits && value && !/^\d+$/.test(value)) invalidate('Only digits allowed');

        if (rule.minlength && value.length < rule.minlength)
          invalidate(`Minimum ${rule.minlength} characters required`);
        if (rule.maxlength && value.length > rule.maxlength) invalidate(`Maximum ${rule.maxlength} characters allowed`);

        if (rule.number && value && isNaN(value)) invalidate('Only numbers allowed');

        if (rule.min && Number(value) < rule.min) invalidate(`Value must be at least ${rule.min}`);

        if (rule.equalTo) {
          let other = form.querySelector(`[name="${rule.equalTo}"]`);
          if (other && value !== other.value) invalidate('Passwords do not match');
        }

        if (rule.greaterThan) {
          let other = form.querySelector(`[name="${rule.greaterThan}"]`);
          if (other && Number(value) <= Number(other.value)) invalidate('Must be greater than minimum value');
        }

        if (rule.url && value) {
          try {
            new URL(value);
          } catch {
            invalidate('Enter a valid URL');
          }
        }
      }

      // ---------------- FILE RULES ----------------
      if (fileRule && input.type === 'file') {
        let file = input.files[0];

        if (fileRule.required && !file) invalidate('This file is required');

        if (file) {
          let ext = file.name.split('.').pop().toLowerCase();

          if (!allowedExtensions.includes(ext)) invalidate('Invalid file type');

          if (file.size > maxFileSize) invalidate('File size must be under 5MB');
        }
      }
    });

    if (firstInvalid) firstInvalid.focus();
    return isValid;
  }
  nextBtns.forEach(btn => {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();

      let currentStep = btn.closest('.content');

      // If this button is submit, let form handler deal with it
      if (btn.type === 'submit') return;

      // Validate only current step
      let valid = validateStep(currentStep);

      if (valid) {
        stepper.next(); // move forward ONLY if valid
      }
      // ❌ DO NOTHING if invalid — stay on same step
    });
  });

  prevBtns.forEach(btn => {
    btn.addEventListener('click', function () {
      stepper.previous();
    });
  });

  // Final submit validation
  form.addEventListener('submit', function (e) {
    let allSteps = form.querySelectorAll('.content');
    let isValid = true;

    allSteps.forEach(step => {
      if (!validateStep(step)) {
        isValid = false;
      }
    });

    if (!isValid) {
      e.preventDefault();
    }
  });
});
