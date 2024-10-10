/*
 * Copyright (C) 2023 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

const path = require('path');
const webpack = require('webpack');
const {CleanWebpackPlugin} = require('clean-webpack-plugin');
const CopyWebpackPlugin = require('copy-webpack-plugin');
const MonacoWebpackPlugin = require('monaco-editor-webpack-plugin');
// const BundleAnalyzerPlugin =
// require('webpack-bundle-analyzer').BundleAnalyzerPlugin;

const config = {
  // Add common Configurations
  module: {},
};

const mainConfig = Object.assign({}, config, {
  entry: {
    vendor: './ui/bundle_vendor.js',
    style: './ui/bundle_style.js',
    systemTools: './ui/bundle_tools.js',
    templates: './ui/bundle_templates.js',
    xibo: './ui/bundle_xibo.js',
    layoutEditor: './ui/src/layout-editor/main.js',
    playlistEditor: './ui/src/playlist-editor/main.js',
    campaignBuilder: './ui/src/campaign-builder/main.js',
    editorCommon: './ui/bundle_editor_common.js',
    preview: './ui/bundle_preview.js',
    legacyPreview: './ui/bundle_legacy_preview.js',
    datatables: './ui/bundle_datatables.js',
    monacoEditor: './ui/bundle_monaco_editor.js',
    leaflet: './ui/bundle_leaflet.js',
  },
  output: {
    path: path.resolve(__dirname, 'web/dist'),
    filename: '[name].bundle.min.js',
  },
  target: ['web', 'es5'],
  module: {
    rules: [
      {
        test: /datatables\.net.*/,
        use: [{
          loader: 'imports-loader',
          options: {
            additionalCode: 'var define = false;',
          },
        }],
      },
      {
        test: /\.(css)$/,
        use: [
          'style-loader',
          'css-loader',
        ],
      },
      {
        test: /\.less$/,
        use: [
          'style-loader',
          'css-loader',
          'less-loader',
        ],
      },
      {
        test: /\.s[ac]ss$/i,
        use: [{
          loader: 'style-loader', // inject CSS to page
        }, {
          loader: 'css-loader', // translates CSS into CommonJS modules
        }, {
          loader: 'postcss-loader', // Run post css actions
          options: {
            postcssOptions: {
              plugins: [
                'autoprefixer',
              ],
            },
          },
        }, {
          loader: 'sass-loader', // compiles Sass to CSS
        }],
      },
      {
        test: /\.(woff(2)?|ttf|eot|svg|jpg|gif|png)$/,
        type: 'asset/resource',
        generator: {
          filename: 'assets/[hash][ext][query]',
        },
      },
      {
        test: /\.(csv|tsv)$/,
        use: [
          'csv-loader',
        ],
      },
      {
        test: /\.xml$/,
        use: [
          'xml-loader',
        ],
      },
      {
        test: /\.hbs$/,
        use: [{
          loader: 'handlebars-loader',
          options: {
            helperDirs: path.join(__dirname, 'ui/src/helpers/handlebars'),
            precompileOptions: {
              knownHelpersOnly: false,
            },
          },
        }],
      },
      {
        test: /\.js$/,
        exclude: /(node_modules|bower_components)/,
        use: {
          loader: 'babel-loader',
          options: {
            presets: [
              ['@babel/preset-env', {targets: 'defaults'}],
            ],
          },
        },
      },
    ],
  },
  plugins: [
    // new BundleAnalyzerPlugin(),
    new CleanWebpackPlugin({
      // To make sure it runs only on the first config
      cleanOnceBeforeBuildPatterns: ['**/*', '!pages/**'],
    }),
    new CopyWebpackPlugin({
      patterns: [
        // Copy directory contents to {output}/
        {
          from: 'ui/src/core',
          to: 'core',
          globOptions: {
            ignore: [
              'forms.js',
              'help-pane.js',
              'xibo-cms.js',
            ],
          },
        },
        {
          from: 'ui/src/preview',
          to: 'preview',
        },
        {
          from: 'ui/src/assets',
          to: 'assets',
        },
        {
          from: 'ui/src/vendor',
          to: 'vendor',
        },
      ],
    }),
    new MonacoWebpackPlugin({
      languages: ['typescript', 'javascript', 'css', 'html'],
    }),
  ],
});

const pageConfig = Object.assign({}, config, {
  entry: {
    'display-page': './ui/src/pages/display/display-page.js',
    'schedule-page': './ui/src/pages/schedule/schedule-page.js',
    'campaign-page': './ui/src/pages/campaign/campaing-page.js',
  },
  output: {
    path: path.resolve(__dirname, 'web/dist/pages'),
    filename: '[name].bundle.min.js',
    libraryTarget: 'window', // Expose the library to the window object
  },
  target: ['web', 'es5'],
  module: {
    rules: [
      {
        test: /datatables\.net.*/,
        use: [{
          loader: 'imports-loader',
          options: {
            additionalCode: 'var define = false;',
          },
        }],
      },
      {
        test: /\.(css)$/,
        use: [
          'style-loader',
          'css-loader',
        ],
      },
      {
        test: /\.less$/,
        use: [
          'style-loader',
          'css-loader',
          'less-loader',
        ],
      },
      {
        test: /\.s[ac]ss$/i,
        use: [{
          loader: 'style-loader', // inject CSS to page
        }, {
          loader: 'css-loader', // translates CSS into CommonJS modules
        }, {
          loader: 'postcss-loader', // Run post css actions
          options: {
            postcssOptions: {
              plugins: [
                'autoprefixer',
              ],
            },
          },
        }, {
          loader: 'sass-loader', // compiles Sass to CSS
        }],
      },
      {
        test: /\.(woff(2)?|ttf|eot|svg|jpg|gif|png)$/,
        type: 'asset/resource',
        generator: {
          filename: 'assets/[hash][ext][query]',
        },
      },
      {
        test: /\.(csv|tsv)$/,
        use: [
          'csv-loader',
        ],
      },
      {
        test: /\.xml$/,
        use: [
          'xml-loader',
        ],
      },
      {
        test: /\.hbs$/,
        use: [{
          loader: 'handlebars-loader',
          options: {
            helperDirs: path.join(__dirname, 'ui/src/helpers/handlebars'),
            precompileOptions: {
              knownHelpersOnly: false,
            },
          },
        }],
      },
      {
        test: /\.js$/,
        exclude: /(node_modules|bower_components)/,
        use: {
          loader: 'babel-loader',
          options: {
            presets: [
              ['@babel/preset-env', {targets: 'defaults'}],
            ],
          },
        },
      },
    ],
  },
  plugins: [
    new CleanWebpackPlugin(),
  ],
});

const moduleConfig = Object.assign({}, config, {
  entry: {
    player: './modules/src/player_bundle.js',
  },
  output: {
    path: path.resolve(__dirname, 'modules'),
    filename: '[name].bundle.min.js',
  },
  target: ['web', 'es5'],
  module: {
    rules: [
      {
        test: /\.m?js$/,
        exclude: /node_modules/,
        use: {
          loader: 'babel-loader',
          options: {
            sourceType: 'unambiguous',
            presets: [
              [
                '@babel/preset-env',
                {
                  corejs: '3.32',
                  useBuiltIns: 'usage',
                  targets: 'defaults',
                },
              ],
            ],
            plugins: [
              [
                'polyfill-corejs3',
                {
                  method: 'usage-global',
                  version: '3.32',
                },
              ],
              '@babel/plugin-transform-nullish-coalescing-operator',
              '@babel/plugin-bugfix-v8-spread-parameters-in-optional-chaining',
              '@babel/plugin-transform-optional-chaining',
              '@babel/plugin-transform-arrow-functions',
              '@babel/plugin-transform-object-rest-spread',
              '@babel/plugin-transform-spread',
              '@babel/plugin-transform-parameters',
              '@babel/plugin-transform-destructuring',
              '@babel/plugin-transform-block-scoping',
              '@babel/plugin-transform-template-literals',
            ],
          },
        },
      },
      {
        test: /\.(css)$/,
        use: [
          'style-loader',
          'css-loader',
        ],
      },
    ],
  },
  plugins: [
    new webpack.ProvidePlugin({
      $: 'jquery',
      jQuery: 'jquery',
    }),
    new CleanWebpackPlugin({
      cleanOnceBeforeBuildPatterns: ['modules/*.min.js'],
    }),
    new CopyWebpackPlugin({
      patterns: [
        // Copy directory contents to {output}/
        {
          from: 'node_modules/pdfjs-dist/es5/build/pdf.worker.js',
          to: 'assets/pdfjs/pdf.worker.js',
        },
      ],
    }),
  ],
});

module.exports = (env, argv) => {
  if (argv.mode === 'development') {
    mainConfig.devtool = 'source-map';
    moduleConfig.devtool = 'source-map';
    pageConfig.devtool = 'source-map';
  }

  if (argv.mode === 'production') {
    mainConfig.devtool = false;
    moduleConfig.devtool = false;
    pageConfig.devtool = false;
  }

  return [mainConfig, pageConfig, moduleConfig];
};
