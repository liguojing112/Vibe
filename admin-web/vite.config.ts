import path from 'node:path'
import process from 'node:process'
import dayjs from 'dayjs'
import { defineConfig, loadEnv } from 'vite'
import pkg from './package.json'
import createVitePlugins from './vite'
import { exclude, include } from './vite/optimize'

// https://cn.vite.dev/config/
export default async ({ mode, command }) => {
  const env = loadEnv(mode, process.cwd())
  function isProduction(): boolean {
    return mode === 'production'
  }

  // 全局 scss 资源
  // const scssFiles: string[] = []
  // fs.readdirSync('src/assets/styles/resources').forEach((dirname) => {
  //   if (fs.statSync(`src/assets/styles/resources/${dirname}`).isFile()) {
  //     scssFiles.push(`@use "src/assets/styles/resources/${dirname}" as *;`)
  //   }
  // })

  const proxyPrefix = env.VITE_PROXY_PREFIX

  /**
   * 发版版本号：`应用版本-构建时间戳`。
   *
   * 后台是 SPA：菜单由服务端下发，页面组件却打包在 JS 里。
   * 浏览器还跑着旧包时，服务端新加的菜单点开就是白屏（旧包里没有这个组件）。
   * 所以把版本号拼到 index.html 里所有 JS/CSS 的地址上 ——
   * 每次发版 URL 都变，旧缓存挡不住新代码。
   */
  const buildVersion = `${pkg.version}-${Date.now()}`

  return defineConfig({
    base: env.VITE_APP_ROOT_BASE,
    // 开发服务器选项 https://cn.vite.dev/config/#server-options
    server: {
      open: true,
      port: Number(env.VITE_APP_PORT ?? process.env.port),
      proxy: {
        [proxyPrefix]: {
          target: env.VITE_APP_API_BASEURL,
          changeOrigin: command === 'serve' && env.VITE_OPEN_PROXY === 'true',
          rewrite: path => path.replace(new RegExp(`^${proxyPrefix}`), ''),
        },
      },
    },
    esbuild: {
      drop: isProduction() ? ['console', 'debugger'] : [],
    },
    // 构建选项 https://cn.vite.dev/config/#server-fsserve-root
    build: {
      outDir: isProduction ? 'dist' : `dist-${mode}`,
      sourcemap: env.VITE_BUILD_SOURCEMAP === 'true',
      minify: 'esbuild',
      cssMinify: 'esbuild',
      rolldownOptions: {
        output: {
          codeSplitting: true,
          chunkFileNames: 'static/js/[name]-[hash].js',
          entryFileNames: 'static/js/[name]-[hash].js',
          assetFileNames: 'static/[ext]/[name]-[hash].[ext]',
          manualChunks: {
            echarts: ['echarts'], // 将 echarts 单独打包
          },
        },
      },
    },
    define: {
      __MINE_SYSTEM_INFO__: JSON.stringify({
        pkg: {
          version: pkg.version,
          dependencies: pkg.dependencies,
          devDependencies: pkg.devDependencies,
        },
        lastBuildTime: dayjs().format('YYYY-MM-DD HH:mm:ss'),
      }),
    },
    plugins: [
      ...createVitePlugins(env, command === 'build'),
      {
        // 给 index.html 里的 JS/CSS 地址统一加上 `?v=版本号`
        name: 'cl-version-query',
        apply: 'build',
        // post：等其他插件（polyfills、modulepreload）都注入完再改写，否则会漏掉它们
        transformIndexHtml: {
          order: 'post',
          handler(html: string) {
            return html.replace(
              /(src|href)="(\/static\/[^"?]+\.(?:js|css))"/g,
              `$1="$2?v=${buildVersion}"`,
            )
          },
        },
      },
    ],
    resolve: {
      alias: {
        '@': path.resolve(__dirname, 'src'),
        '#': path.resolve(__dirname, 'types'),
        '$': path.resolve(__dirname, 'src/plugins'),
        '~': path.resolve(__dirname, 'src/modules'),
      },
    },
    css: {
      preprocessorOptions: {
        scss: {
          api: 'modern-compiler',
          // additionalData: scssFiles.join(''),
          javascriptEnabled: true,
        },
      },
    },
    optimizeDeps: { include, exclude },
  })
}
