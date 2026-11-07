import { initNavMainToggle } from './modules/nav-main-toggle.js';
import { initNavMainSubToggle } from './modules/nav-main-sub-toggle.js';
import { initDialog } from './modules/dialog.js';
import { initDropdown } from './modules/dropdown.js';
import { initResponsiveTable } from './modules/responsive-table.js';
import { initTomSelect } from './modules/tom-select.js';

export {
  initNavMainToggle,
  initNavMainSubToggle,
  initDialog,
  initDropdown,
  initResponsiveTable,
  initTomSelect,
};

export function initBackend() {
  initNavMainToggle();
  initNavMainSubToggle();
  initDialog();
  initDropdown();
  initResponsiveTable();
}
