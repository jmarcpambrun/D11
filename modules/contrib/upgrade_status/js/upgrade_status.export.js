/**
 * @file
 * Copies the paths of files in the HTML exports of Upgrade Status.
 *
 * The exports work on their own, without the scripts of Drupal. The code of
 * this file is put in the export, and the Content Security Policy of the
 * export only allows this code, by its hash.
 */

(() => {
  // The buttons stay hidden when the browser cannot copy.
  if (!navigator.clipboard) {
    return;
  }
  const status = document.querySelector('[data-us-copy-status]');
  document.querySelectorAll('button[data-us-copy]').forEach((button) => {
    let timer;
    const reset = () => {
      button.classList.remove('is-copied');
      status.textContent = '';
    };
    button.hidden = false;
    button.addEventListener('click', () => {
      navigator.clipboard.writeText(button.dataset.usCopy).then(() => {
        // Show the confirmation icon for a short time, and tell assistive
        // technology.
        button.classList.add('is-copied');
        status.textContent = status.dataset.usCopyStatus;
        clearTimeout(timer);
        timer = setTimeout(reset, 2000);
      });
    });
  });
})();
