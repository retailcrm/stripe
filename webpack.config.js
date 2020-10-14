var Encore = require('@symfony/webpack-encore');
if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    .setOutputPath('public/build/')
    .setPublicPath('/build')
    .addEntry('app', './assets/vue/index.js')
    .addEntry('pay', './assets/js/pay.js')
    .copyFiles({
        from: './assets/images',
        to: 'images/[path][name].[ext]',
        pattern: /\.(png|jpg|jpeg|ico|svg)$/
    })
    .splitEntryChunks()
    .disableSingleRuntimeChunk()
    .cleanupOutputBeforeBuild()
    .enableBuildNotifications()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())

    .configureBabel((babelConfig) => {
        babelConfig.plugins.push('@babel/plugin-transform-runtime');
    }, {
        useBuiltIns: 'usage',
        corejs: 3
    })
    .enableVueLoader()
    .enableIntegrityHashes()
    .enableLessLoader()
    .addLoader({
        enforce: 'pre',
        test: /\.(js|vue)$/,
        loader: 'eslint-loader',
        exclude: /(node_modules|vendor)/,
        options: {
            fix: true,
            emitError: true,
            emitWarning: true,
        },
    })
;

module.exports = Encore.getWebpackConfig();
