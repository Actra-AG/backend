// Entry of dist/backend.min.js (npm run build:js): a classic script (no module, no bundler needed in the project)
// that runs initBackend() and exposes the functions as window.actraBackend, e.g. actraBackend.initTomSelect(TomSelect).
import { initBackend } from './backend.js';

export * from './backend.js';

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initBackend);
} else {
  initBackend();
}
