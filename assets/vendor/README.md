# Bundled third-party libraries

Loaded on public pages, so they are served from the plugin itself (no request to a third-party CDN from your visitors' browsers).

| Library | Version | License | Source | File |
| --- | --- | --- | --- | --- |
| Chart.js | 4.5.1 | MIT | npm `chart.js@4.5.1`, `dist/chart.umd.min.js` (unchanged) | `chartjs/chart.umd.min.js` |
| canvas-confetti | 1.9.3 | ISC | npm `canvas-confetti@1.9.3`, `dist/confetti.browser.js` minified with terser | `canvas-confetti/confetti.browser.min.js` |

To update: download the new version from npm, replace the file, then update the version in `FQI3_Statistics::CHART_VERSION` (Chart.js) or in `FormQuizzFQI3::registerFrontendAssets()` (confetti).
