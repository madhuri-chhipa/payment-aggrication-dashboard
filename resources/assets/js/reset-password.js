'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const form = document.querySelector('#formAuthentication');
  if (!form || typeof FormValidation === 'undefined') return;

  FormValidation.formValidation(form, {
    fields: {
      otp: {
        validators: {
          notEmpty: { message: 'OTP is required' },
          stringLength: { min: 6, max: 6 }
        }
      },
      password: {
        validators: {
          notEmpty: { message: 'Password is required' },
          stringLength: { min: 8 }
        }
      },
      password_confirmation: {
        validators: {
          notEmpty: { message: 'Confirm password is required' },
          identical: {
            compare: () => form.querySelector('[name="password"]').value,
            message: 'Passwords do not match'
          }
        }
      }
    },
    plugins: {
      trigger: new FormValidation.plugins.Trigger(),
      bootstrap5: new FormValidation.plugins.Bootstrap5({
        rowSelector: '.fv-row'
      }),
      submitButton: new FormValidation.plugins.SubmitButton(),
      defaultSubmit: new FormValidation.plugins.DefaultSubmit()
    }
  });
});
