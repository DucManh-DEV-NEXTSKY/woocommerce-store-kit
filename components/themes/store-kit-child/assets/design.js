/* Presets fill a draft only. The core settings form owns nonce, validation and saving. */
(function () {
  'use strict';
  var button = document.getElementById('sk-apply-design');
  var select = document.getElementById('sk-design-preset');
  if (!button || !select) return;
  button.addEventListener('click', function () {
    var presets = window.storeKitDesignPresets || {};
    var preset = presets[select.value];
    if (!preset) return;
    Object.keys(preset).forEach(function (key) {
      var input = document.getElementById('sk-design-' + key);
      if (!input) return;
      input.value = preset[key];
      input.dispatchEvent(new Event('change', { bubbles: true }));
    });
    document.getElementById('sk-design-primary').focus();
  });
}());
