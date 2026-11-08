# Frontend: coding standard

For the frontend developer: please check and decide (ActiveCollab task #11 of "Composer: actra/backend"). The assets
are in `src/assets/`; the backend builds them into `dist/` itself (`npm run build`, see `docs/assets.md`).

1. [x] JavaScript: settled. esbuild bundles the ES modules into `dist/backend.min.js` (allowed for ES modules from
   libraries since coding standard v1.18.0), PostCSS builds `dist/backend.min.css`.
2. [ ] `js/modules/responsive-table.js` sets `style.display`: use `hidden` or a class instead.
3. [ ] Lint: add `stylelint.config.js` and `prettier.config.js` from `vendor/actra/coding-standard/templates/` (with a
   `package.json` for `npm run lint` and `format` only, no build), then fix the findings?
4. [ ] Typo `css/blocks/_authenticaction.css`: rename to `_authentication.css` (projects that import it need the new
   name)?
