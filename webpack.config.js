const path = require('path');
const UglifyJSPlugin = require('uglifyjs-webpack-plugin');
const CleanWebpackPlugin = require('clean-webpack-plugin');
const CopyWebpackPlugin = require('copy-webpack-plugin');

module.exports = {
    entry: {
        vendor: './ui/bundle_vendor.js',
        style: './ui/bundle_style.js',
        layoutDesigner: './ui/src/designer/main.js'
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
                copyUnmodified: true
            })
    ],
    output: {
        path: path.resolve(__dirname, 'web/dist'),
        filename: '[name].bundle.min.js',
        publicPath: '/dist/'
    },
    module: {
        rules: [{
            test: /datatables\.net.*/,
            use: [
                'imports-loader?define=>false'
            ]
        },
        {
            test: /\.(css|scss)$/,
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
            test: /\.(png|svg|jpg|gif)$/,
            use: [{
                loader: 'file-loader',
                options: {
                    name: '[hash].[ext]',
                    useRelativePath: true
                }
            }]
        },
        {
            test: /\.(ttf|eot|svg|woff|woff2)(\?v=[0-9]\.[0-9]\.[0-9])?$/,
            use: [{
                loader: 'file-loader',
                options: {
                    name: '[hash].[ext]',
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
        },
        {
            test: /\.hbs$/,
            use: [{
                loader: 'handlebars-loader',
                options: {
                    helperDirs: path.join(__dirname, 'ui/src/helpers'),
                    precompileOptions: {
                        knownHelpersOnly: false,
                    }
                }
            }]
        }]
    }
};
