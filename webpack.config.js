const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

module.exports = {
    ...defaultConfig,
    entry: {
        blocks: path.resolve(__dirname, 'resources/js/frontend/blocks.js')
    }
}; 