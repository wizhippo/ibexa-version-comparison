const path = require('path');
module.exports = (Encore) => {
  Encore.addStyleEntry('wizhippo-ibexa-version-comparison-css', path.resolve(__dirname, '../../assets/scss/version-comparison.scss'));
};
