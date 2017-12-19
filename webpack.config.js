const path = require('path');
const UglifyJSPlugin = require('uglifyjs-webpack-plugin');
const CleanWebpackPlugin = require('clean-webpack-plugin');
const CopyWebpackPlugin = require('copy-webpack-plugin');

module.exports = {
        entry: {
            vendor: './ui/bundle_vendor.js',
            style: './ui/bundle_style.js'
        },
        devtool: 'source-map',
        plugins: [
                new UglifyJSPlugin({
                        sourceMap: true
                }),
                new CleanWebpackPlugin(['web/dist']),
                new CopyWebpackPlugin([
                        // Copy directory contents to {output}/
                        {
                                from: 'ui/src/js'
                        }
                ], {
                        // By default, we only copy modified files during
                        // a watch or webpack-dev-server build. Setting this
                        // to `true` copies all files.
                        copyUnmodified: true
                })
        ],
        output: {
                path: path.resolve(__dirname, 'web/dist'),
                filename: '[name].bundle.min.js'
        },
        module: {
                rules: [{
                                test: /\.css$/,
                                use: [
                                        'style-loader',
                                        'css-loader'
                                ]
                        },
                        {
                                test: /\.(png|svg|jpg|gif)$/,
                                use: [{
                                        loader: 'file-loader',
                                        options: {
                                                name: '[hash].[ext]',
                                                outputPath: '../',
                                                useRelativePath: true
                                        }
                                }]
                        },
                        {
                                test: /\.(woff|woff2|eot|ttf|otf|html)$/,
                                use: [{
                                        loader: 'file-loader',
                                        options: {
                                                name: '[name].[ext]',
                                                outputPath: '../',
                                                useRelativePath: true
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
                                // Load and run script
                                test: /\.exec\.js$/,
                                use: [
                                        'script-loader'
                                ]
                        }
                ]
        }
};
