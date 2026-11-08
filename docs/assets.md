# Assets

The package ships its CSS and JavaScript built in `dist/`, so a project only copies files after an update:

- `dist/backend.min.css`: all CSS of `src/assets/css/backend.css` (imports inlined, custom media and nesting resolved,
  prefixed for the browsers of `browserslist` "defaults", minified).
- `dist/backend.min.js`: all JavaScript of `src/assets/js/backend.js` as one classic script (no module, no bundler):
  it runs `initBackend()` when the page is loaded and exposes the functions as `window.actraBackend`.
- `dist/tom-select.min.css`: the optional look of Tom Select ([below](#searchable-selects-tom-select-optional)).

Copy them to the public directory after `composer update` (e.g. as a Composer script or in the deployment) and pass
the paths to `ActraBackendSettings` (`$stylesPaths`, `$javaScriptPaths`):

```json
"scripts": {
  "post-update-cmd": [
    "cp vendor/actra/backend/dist/backend.min.css public/css/backend.min.css",
    "cp vendor/actra/backend/dist/backend.min.js public/js/backend.min.js"
  ]
}
```

Projects that build their own bundle (e.g. with project CSS) import the sources instead: `src/assets/css/backend.css`
and the ES module `src/assets/js/backend.js` (`initBackend()` and the single functions).

Development of the backend: after a change in `src/assets/`, run `npm ci` once and `npm run build`, and commit
`dist/` with the change. The workflow `assets` fails if `dist/` is not up to date.

The default CSS expects the bundled backend fonts to be available below the public font path:

- `/fonts/backend/`

For example, when publishing the package assets directly, publish the backend font files so that
`/fonts/backend/inter-v18-latin-regular.woff2`, `/fonts/backend/inter-v18-latin-italic.woff2`, and the used bold weights
are reachable by the browser.

Messages use the `msg` block (`src/assets/css/blocks/_msg.css`) with one variant: `msg-success`, `msg-note`,
`msg-warning` or `msg-error`. Use `role="status"` for success and notes, `role="alert"` for errors that need
attention:

```html
<p class="msg msg-error" role="alert"><strong>Error:</strong> This event has subscriptions and cannot be deleted.</p>
<p class="msg msg-warning" role="status"><strong>Warning:</strong> The event is fully booked.</p>
```

## Searchable selects (Tom Select, optional)

`initTomSelect()` turns every `select.chosen` into a [Tom Select](https://tom-select.js.org/) with a search field. yuf
renders this class with `SelectOptionsField(renderAsChosenEnhancedField: true)`. It is opt-in, `initBackend()` does not
call it. With the built files, include Tom Select's own script and CSS (npm package `tom-select@2.6.2`, Apache-2.0,
`dist/js/tom-select.popular.min.js` and `dist/css/tom-select.css`) and `dist/tom-select.min.css` after
`backend.min.css`, then call it after both scripts:

```html
<script src="/js/tom-select.popular.min.js" defer></script>
<script src="/js/backend.min.js" defer></script>
<script src="/js/project.js" defer></script> <!-- actraBackend.initTomSelect(TomSelect); -->
```

In an own bundle, pass the constructor of the npm package:

```js
import TomSelect from 'tom-select/dist/js/tom-select.popular.js';
import { initBackend, initTomSelect } from '../../vendor/actra/backend/src/assets/js/backend.js';

initBackend();
initTomSelect(TomSelect);
```

```css
@import url("../../vendor/actra/backend/src/assets/css/backend.css");
@import url("tom-select/dist/css/tom-select.css");
@import url("../../vendor/actra/backend/src/assets/css/tom-select.css");
```

After changing the version of `actra/backend`, copy the built files again (or rebuild the own bundle).
