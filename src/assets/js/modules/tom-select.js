// Searchable selects: every select.chosen (yuf renderAsChosenEnhancedField) becomes a Tom Select with a search field.
// Opt-in: the project installs the npm package tom-select and passes its constructor, e.g.
// import TomSelect from 'tom-select/dist/js/tom-select.popular.js'; initTomSelect(TomSelect);
export function initTomSelect(TomSelect) {
  const selects = document.querySelectorAll('select.chosen');

  if (!selects.length || typeof TomSelect !== 'function') {
    return;
  }

  const noResults = document.documentElement.lang === 'en' ? 'No results' : 'Keine Treffer';

  selects.forEach(select => {
    new TomSelect(select, {
      plugins: ['dropdown_input'],
      allowEmptyOption: true,
      maxOptions: null,
      render: {
        no_results: () => '<div class="no-results">' + noResults + '</div>',
      },
    });
  });
}
