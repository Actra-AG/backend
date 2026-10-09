# Frontend: coding standard

For the frontend developer: please check and decide. Nothing is changed yet. The assets are in `src/assets/` and are
bundled by the projects (e.g. `../drogeriehaas.ch`, esbuild).

1. [ ] JavaScript: the standard wants plain files, each in a `{ … }` block, concatenated with uglify-js (no
   `import`/`export`). `backend.js` uses ES modules and exports `initBackend()`. Convert (projects then drop esbuild
   and the `initBackend()` call), or keep ES modules as documented deviation for this library or update the standards?
2. [ ] `js/modules/responsive-table.js` sets `style.display`: use `hidden` or a class instead.
3. [ ] Lint: add `stylelint.config.js` and `prettier.config.js` from `vendor/actra/coding-standard/templates/` (with a
   `package.json` for `npm run lint` and `format` only, no build), then fix the findings?
4. [ ] Typo `css/blocks/_authenticaction.css`: rename to `_authentication.css` (projects that import it need the new
   name)?
