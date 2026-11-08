// Builds dist/*.min.css (npm run build:css): imports inlined, custom media and nesting resolved, minified
module.exports = {
  plugins: [
    require("postcss-import")(),
    require("postcss-preset-env")({
      stage: 2,
      features: {
        "is-pseudo-class": false
      }
    }),
    require("autoprefixer")(),
    require("cssnano")()
  ]
};
