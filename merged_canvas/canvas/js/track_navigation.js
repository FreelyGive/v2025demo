(function () {
  const STORAGE_KEY = 'CanvasPreviousURL';
  const currentUrl = window.location.href;
  const inIframe = window.self !== window.top || currentUrl === 'about:srcdoc';

  // Check if this is being run in an iframe and don't update the previous URL.
  if (inIframe) {
    return;
  }

  sessionStorage.setItem(STORAGE_KEY, currentUrl);
})();
