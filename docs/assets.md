# Assets

The package ships default assets in `src/assets`.

Projects using this library should include these assets in their own build or asset publishing process. Depending on the
project setup, this can mean importing them into a npm, Grunt, or other asset pipeline, bundling and minifying them
together with project-specific assets, or publishing them directly as static files.

The main entrypoints are:

- `src/assets/css/backend.css`
- `src/assets/js/backend.js`

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
call it: install the npm package (`npm i -D tom-select@2.6.2`, Apache-2.0) and pass its constructor:

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

After changing the version of `actra/backend`, rebuild (or republish) the CSS and JavaScript bundles of the project.

After the assets are available through the application's public asset URLs, reference them when initializing the
backend:

```php
$actraBackend = ActraBackend::create(/* see "Usage" in README.md */);
```
