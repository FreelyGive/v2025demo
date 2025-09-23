(async () => {
  const serverOrigin = (window.drupalSettings?.canvas_vite?.serverOrigin) || 'http://localhost:5173';

  const { default: RefreshRuntime } = await import(`${serverOrigin}/@react-refresh`);
  RefreshRuntime.injectIntoGlobalHook(window);

  window.$RefreshReg$ = () => {};
  window.$RefreshSig$ = () => (type) => type;
  window.__vite_plugin_react_preamble_installed__ = true;
})();
