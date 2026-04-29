import './bootstrap';
import $ from 'jquery';
window.$ = window.jQuery = $;

import 'jquery-validation';
import 'jquery-validation/dist/additional-methods';

/*
  Add custom scripts here
*/
import.meta.glob([
  '../assets/img/**',
  // '../assets/json/**',
  '../assets/vendor/fonts/**'
]);
