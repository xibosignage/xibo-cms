const path = require('path');
const UglifyJsPlugin = require('uglifyjs-webpack-plugin');
const CleanWebpackPlugin = require('clean-webpack-plugin');
const CopyWebpackPlugin = require('copy-webpack-plugin');

var config = {
    entry: {
        vendor: './ui/bundle_vendor.js',
        style: './ui/bundle_style.js',
        systemTools: './ui/bundle_tools.js',
        xibo: './ui/bundle_xibo.js',
        layoutDesigner: './ui/src/designer/main.js',
        playlistEditor: './ui/src/playlist-editor/main.js'
    },
    output: {
        path: path.resolve(__dirname, 'web/dist'),
        filename: '[name].bundle.min.js'
    },
    module: {
        rules: [
            {
                test: /datatables\.net.*/,
                use: [
                    'imports-loader?define=>false'
                ]
            },
            {
                test: /\.(css)$/,
                use: [
                    'style-loader',
                    'css-loader'
                ]
            },
            {
                test: /\.less$/,
                use: [
                    'style-loader',
                    'css-loader',
                    'less-loader'
                ]
            },
            {
                test: /\.(scss)$/,
                use: [{
                    loader: 'style-loader', // inject CSS to page
                }, {
                    loader: 'css-loader', // translates CSS into CommonJS modules
                }, {
                    loader: 'postcss-loader', // Run post css actions
                    options: {
                        plugins: function() { // post css plugins, can be exported to postcss.config.js
                            return [
                                require('precss'),
                                require('autoprefixer')
                            ];
                        }
                    }
                }, {
                    loader: 'sass-loader' // compiles Sass to CSS
                }]
            },
            {
                test: /\.(png|svg|jpg|gif|ttf|eot|woff|woff2)$/,
                use: [{
                    loader: 'file-loader',
                    options: {
                        name: '[name].[ext]'
                    }
                }]
            },
            {
                test: /\.(csv|tsv)$/,
                use: [
                    'csv-loader'
                ]
            },
            {
                test: /\.xml$/,
                use: [
                    'xml-loader'
                ]
            },
            {
                test: /\.hbs$/,
                use: [{
                    loader: 'handlebars-loader',
                    options: {
                        helperDirs: path.join(__dirname, 'ui/src/helpers/handlebars'),
                        precompileOptions: {
                            knownHelpersOnly: false,
                        }
                    }
                }]
            },
            {
                test: /\.js$/,
                exclude: /(node_modules|bower_components)/,
                use: {
                    loader: 'babel-loader',
                    options: {
                        presets: ['@babel/preset-env']
                    }
                }
            }
        ]
    },
    plugins: [
        new CleanWebpackPlugin(["web/dist"]),
        new UglifyJsPlugin({
            test: /\.js(\?.*)?$/i,
            sourceMap: true,
        }),
        new CopyWebpackPlugin([
            // Copy directory contents to {output}/
            {
                from: 'ui/src/core',
                to: 'core'
            },
            {
                from: 'ui/src/preview',
                to: 'preview'
            },
            {
                from: 'ui/src/assets',
                to: 'assets'
            },
            {
                from: 'ui/src/vendor',
                to: 'vendor'
            }
        ], {
                // By default, we only copy modified files during
                // a watch or webpack-dev-server build. Setting this
                // to `true` copies all files.
                copyUnmodified: true,
            }
        ),
    ],
};

module.exports = (env, argv) => {

    if(argv.mode === 'development') {
        config.devtool = 'source-map';
    }

    if(argv.mode === 'production') {
        config.devtool = false;
    }

    return config;
};
