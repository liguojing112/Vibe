# PROGRESS（进度记忆）

> 这是你的记忆。**每次开工先读，每个任务做完必须更新。**
> 只追加和修改状态，不要删除历史记录。

## 当前状态

- 当前阶段：**P2 安卓基础**（P1 已完成）
- 当前任务：T2.6 已完成 → 下一步 T2.7 导航与 App 骨架
- 任务状态：待开始 <!-- 未开始 / 进行中 / 已完成 -->
- 上次停在哪：T2.6 网络层与本地存储完成（OkHttp+Retrofit 装配、`ApiResponse`/`ApiResult`、`AuthInterceptor`、`AuthApi`、`ClDataStore`、Room 本地库骨架），`check-android.sh` 与 `check-server.sh` 都通过。顺带把**服务端业务错误的 `data` 从 `[]` 改成 `null`**（客户端按 DTO 解析 data，空数组会解析失败）。下一步按 `docs/08` 做 T2.7（`Routes.kt` 全部页面路由、`ClNavHost`、底部导航受 `call_enabled` 控制、启动拉 `/app/config`、未实现页面占位）。

## 任务进度

<!-- 做完一个，把 [ ] 改成 [x]，并在下方「任务记录」里写自检记录 -->

- P0：[x] T0.6 [x] T0.1 [x] T0.2 [x] T0.3 [x] T0.4 [x] T0.5
- P1：[x] T1.1 [x] T1.2 [x] T1.3 [x] T1.4 [x] T1.5 [x] T1.6 [x] T1.7
- P2：[x] T2.1 [x] T2.2 [x] T2.3 [x] T2.4 [x] T2.5 [x] T2.6 [ ] T2.7 [ ] T2.8 [ ] T2.9 [ ] T2.10 [ ] T2.11
- P3：[ ] T3.1 [ ] T3.2 [ ] T3.3 [ ] T3.4 [ ] T3.5 [ ] T3.6 [ ] T3.7 [ ] T3.8 [ ] T3.9 [ ] T3.10
- P4：[ ] T4.1 [ ] T4.2 [ ] T4.3 [ ] T4.4 [ ] T4.5 [ ] T4.6 [ ] T4.7 [ ] T4.8
- P5：[ ] T5.1 [ ] T5.2 [ ] T5.3 [ ] T5.4 [ ] T5.5 [ ] T5.6 [ ] T5.7 [ ] T5.8 [ ] T5.9
- P6：[ ] T6.1 [ ] T6.2 [ ] T6.3 [ ] T6.4 [ ] T6.5 [ ] T6.6 [ ] T6.7
- P7：[ ] T7.1 [ ] T7.2 [ ] T7.3 [ ] T7.4 [ ] T7.5 [ ] T7.6 [ ] T7.7 [ ] T7.8 [ ] T7.9
- P8：[ ] T8.1 [ ] T8.2 [ ] T8.3
- P9：[ ] T9.1 [ ] T9.2 [ ] T9.3

## 环境信息（T0 阶段填写）

> 2026-10-11 探测本机（Windows 11）结果：

- MineAdmin 版本 / commit：**v3.2.0**（git clone --branch v3.2.0，已删 server/.git）
- MineAdmin-Vue 版本 / commit：未安装（T0.3）
- PHP / Swoole / MySQL / Redis 版本：**PHP 8.3.6（WSL Ubuntu 24.04）；Swoole 5.1.8（GitHub 源码编译）；MySQL 8.0.44（Windows 宿主，WSL 经 mirrored localhost 连）；Redis 7.0.15（WSL）**
- Android Gradle Plugin / Kotlin / Compose BOM 版本：**AGP 9.4.1 / Gradle 9.8.1 / Kotlin 2.4.21（经 buildscript 升级；AGP 9 内置为 2.2.10） / Compose BOM 2026.09.00**；compileSdk = targetSdk = 37、minSdk = 26；构建用 **JDK 17**（`C:\Program Files\Eclipse Adoptium\jdk-17.0.19.10-hotspot`，本机 JAVA_HOME 指向 JDK 24）
- 本地启动命令：WSL 内 `cd /mnt/c/Workspace/android/vibe/changliao/server && setsid nohup php bin/hyperf.php start > /root/build/server.log 2>&1 &`（Hyperf 无 `-d`，不能用 `-d`）
- 测试账号：后台 `admin` / `123456`（MineAdmin 默认）；App 男号 / 女号（待补）

**本机已具备**：git 2.53、Node v24.14.1、pnpm 10.33、npm 11.11、JDK 17.0.19、MySQL 8.0.44（MySQL80 服务运行中，root 密码 `root123`）、Docker 29.4.0（未启动）、WSL2 Ubuntu 24.04。

**关键环境处理（重要，勿删）**：
1. **WSL 网络**：本机装了 Clash，WSL 的 NAT 模式用不了 localhost 代理，外网域名被解析成 127.0.0.1。新建了 `C:\Users\34252\.wslconfig`（`networkingMode=mirrored` + `autoProxy=true` + `dnsTunneling=true`）并 `wsl --shutdown` 后，WSL 才能访问 github / getcomposer。
2. **apt 源**已换成阿里云镜像；`/etc/apt/sources.list.d/ubuntu.sources`。
3. **Swoole**：pecl 频道异常，改为 GitHub 源码编译（`/root/build/swoole-src`，`./configure --enable-openssl --enable-sockets`，需 `libbrotli-dev`）；并设 `swoole.use_shortname=Off`（否则 Hyperf 拒绝启动）。
4. **测试库隔离**：`composer test` 脚本已改为 `DB_DATABASE=cl_test REDIS_DB=15 co-phpunit ...`，测试走 `cl_test`，不再 truncate 开发库。

**缺失（阻塞对应任务）**：
- 安卓 → 需 Android SDK（`ANDROID_HOME` 未设置，无 `adb`）：**阻塞 T0.4**，等你装 Android Studio
- 后台前端 → Node/pnpm 就绪：**T0.3 可做**

## 新增依赖记录

| 端 | 名称 | 版本 | 用途 | 任务 |
|---|---|---|---|---|
| 后台 | postcss-html | 2.0.0 | stylelint 解析 `.vue` 的 `<style>` 所需；它是上游的隐式 peer 依赖，pnpm 严格布局下未被提升到根 node_modules，必须显式声明才能跑 `lint:stylelint` | T0.3 |
| 安卓 | androidx.compose.material:material-icons-extended | 1.7.8（Compose BOM 2026.09.00 钉的版本） | `ClIcons` 要用 Material Icons Rounded 全套。**注意**：Google 已弃用这个 artifact（建议自带矢量图），且它会明显增大包体 —— release 构建应打开 `isMinifyEnabled` 让 R8 裁掉没用到的图标，或以后把用到的图标改成自带 ImageVector | T2.2 |
| 后台 | postcss-html | 2.0.0 | stylelint 解析 `.vue` 的 `<style>` 所需；它是上游的隐式 peer 依赖，pnpm 严格布局下未被提升到根 node_modules，必须显式声明才能跑 `lint:stylelint` | T0.3 |

## 待确认问题

<!-- 文档没写清、或文档之间矛盾的地方。写清楚：问题、你暂时怎么做的、为什么 -->

| 编号 | 问题 | 暂时做法 | 涉及任务 | 状态 |
|---|---|---|---|---|
| Q0-1 | 「广场」指什么？是发现页（P01）的另一种说法，还是新增的动态/社区功能？ | 客户 2026-10-11 确认：**= 发现页 P01**，不新增页面/接口/表 | 需求澄清 | ✅ 已解决 |
| Q0-2 | 「服务端只做转发和监管」，消息在服务端保留多久？（监管、举报取证需要） | 客户确认：保留 N 个月后清理；新增 `chat.message_retention_months`（默认 6，后台可配），保留 `sensitive_hit=1` 与被举报关联的消息 | T4.9 | ✅ 已解决 |
| Q0-3 | 聊天本地化需要引入 Room，但 `02` 文档 4.2 依赖清单里没有 | 已在 `02` 4.2 加入 Room（room-runtime/room-ktx/room-compiler + KSP，见 T0.6） | T0.4、T2.6 | ✅ 已解决 |
| Q0-4 | `01` 文档 P06 写的是「历史消息上拉加载（每页 30 条）」，与本地化方案矛盾 | 已改 `01` P06：历史消息以本地库为准，接口只做 `/chat/sync` 增量补拉（见 T0.6） | P06 | ✅ 已解决 |
| Q0-5 | `02` 文档 4.3 安卓包结构里没有本地数据库目录 | 已在 `02` 4.3 新增 `core/data/local/db/` 与 `LocalCache.kt`（见 T0.6） | T0.4、T2.6 | ✅ 已解决 |
| Q0-6 | token 存 DataStore 默认不加密，是否需要加密存储 | 客户确认：本期明文 DataStore 即可 | T2.6 | ✅ 已解决 |
| Q0-7 | **本机（Windows）无 PHP/Composer/Redis，Hyperf+Swoole 不能原生运行** | 已解决：改用 **WSL2 Ubuntu 24.04** 跑后端（PHP 8.3 + 源码编译 Swoole + Redis）；并新建 `.wslconfig` 开 mirrored 网络解决 Clash 代理不通 | T0.2 | ✅ 已解决 |
| Q0-8 | **本机无 Android SDK**（`ANDROID_HOME` 未设、无 `adb`），T0.4 建不了安卓工程 | 你已选择「装 Android Studio」，等你装好后我设 `ANDROID_HOME` | T0.4 | ⏳ 待你装 Android Studio |
| Q0-9 | **`composer test` 有 7 个 MineAdmin 自带测试失败**（期望 422、实得 401）。根因：后台控制器 `AccessTokenMiddleware` 用 `#[Middleware(priority: 100)]`，优先级高于全局 `ValidationMiddleware`，先鉴权返回 401 | 你决定：**排除这 7 个用例**。已改 `server/composer.json` 的 test 脚本，用 phpunit 的 filter 反向排除 `Menu/Role/UserControllerTest::{testCreate,testSave,testUpdateInfo}`；原因写在 `server/phpunit.xml.dist` 注释里。**未改任何 MineAdmin 源码**；401 先返回本就是正确行为。排除后 44 测试全过 | T0.2、T0.4、T1.2 | ✅ 已解决（排除） |
| Q0-12 | **`composer analyse`（phpstan）在 MineAdmin 自带代码上报 5 个错**（`IRepository` 泛型模板返回值 3 处、`RoleService` 调 `Model::menus()` 1 处、`User::getPolicy()` 1 处） | 已用 PHPStan 官方 **baseline** 机制精确豁免这 5 条（`server/phpstan-baseline.neon` + `phpstan.neon.dist` 里 includes），以后新增的问题仍会让 analyse 失败。未改 MineAdmin 源码 | T0.4 | ✅ 已解决 |
| Q0-13 | `docs/05` 第 4.1 节的 `GET /app/config` 要返回 `version`，但 06 的 key 清单与其它文档都没定义它是什么（客户端版本？配置版本？） | 按铁律 7「可调项从配置读」新增配置项 **`app.version`**（默认 `1.0.0`，group=feature，后台可改），当「启动配置版本号」用。若客户另有定义再改 | T1.4、T7.7 | ⏳ 待确认语义 |
| Q0-14 | `ClSoftButton` 的三种底色（docs/03 第 5.2 节只说 Yellow/Peach/Gray，对应语音/视频/私信）在原型 CSS 里找不到确切的三组值（原型有几十种相近暖色底） | 先映射到最接近的颜色 token：Yellow = primarySoft/primaryText、Peach = tagGoldBg/tagGoldText、Gray = bgInput/inkSecondary。等做 P01 卡片时对着设计图定哪种按钮用哪种 | T2.3、T3.7 | ⏳ 待对着设计图确认 |
| Q0-10 | MineAdmin 自带测试默认连 `.env` 里的开发库，会 `truncate` 种子表 | 已解决：`composer test` 脚本前置 `DB_DATABASE=cl_test REDIS_DB=15`（Hyperf dotenv 是 immutable，shell 变量优先；phpunit 的 `<env>` 因测试引导先建容器而无效） | T0.2 | ✅ 已解决 |
| Q0-11 | **MineAdmin-Vue 3.2.1 自带代码有大量 lint 错误**（eslint 13 error：未使用变量/`prefer-promise-reject-errors`；stylelint 328 error：单行声明数、重复选择器、空 style 块等），导致 `pnpm lint` 直接失败 | 你已决定：**在配置里给上游目录放宽**。已改 `admin-web/eslint.config.js`（`src/**` 放宽两条规则，`src/modules/cl/**` 重新收紧）与 `admin-web/stylelint.config.js`（对 `src/{components,layouts,plugins,assets,modules/base}/**` 用 overrides 关掉相关规则；`@apply` 加白名单；`.md` 加入 ignores）。**未改任何 MineAdmin 源码** | T0.3、T7.x | ✅ 已解决 |

## 任务记录

<!-- 按 09 文档第 4 节模板，每个任务一段，最新的写在最下面 -->

### T0.6 文档同步 —— ✅ 完成（2026-10-11）
- 完成内容：按开工规划（客户 2026-10-11 确认的四条需求）同步更新需求文档，消除「聊天本地化」与旧写法的架构级冲突。**本次更新由项目负责人（你）明确授权，属铁律 15 的例外**（否则后续开发会照旧文档走偏）。
- 改动文件与要点：
  - `01-产品需求与页面清单.md`：① 第 6 节新增「静默刷新 + 本地持久化」适用范围；② P06 「历史消息上拉加载」→「历史消息从本地库读取 + `/chat/sync` 增量补拉 + 乐观 UI」；③ 第 5 节备注「广场 = 发现页 P01」；④ P01、P03 补本地持久化说明。
  - `02-技术架构与目录结构.md`：① 4.2 依赖新增 Room（room-runtime/room-ktx/room-compiler + KSP）；② 4.3 包结构新增 `core/data/local/db/`、`LocalCache.kt`、`ClDataStore.kt`；③ 4.4 新增约定：本地库为主数据源、乐观 UI 与消息状态机、stale-while-revalidate、Coil 图片缓存。
  - `04-数据库设计.md`：`cl_message` 补「监管留存」定位与保留策略；`cl_config` 注明新增 `chat.message_retention_months`。
  - `05-接口规范.md`：`/chat/conversations`、`/chat/messages`、`/chat/sync` 补本地化口径，新增聊天本地化说明块。
  - `06-计费与资金规则.md`：2.1 表格新增 `chat.message_retention_months`（默认 6）。**（超出原规划的一处小补充，原因是配置种子必须来自 06 的默认值表，否则新增 key 不会被种子化，反而制造新的不一致。）**
- 验收对照：
  1. `docs/` 与源文档一致 —— ✅（T0.1 复制后逐份 diff，6 份改动文档全部一致）
  2. Q0-3 / Q0-4 / Q0-5 标为已解决 —— ✅（PROGRESS「待确认问题」表）
- 自检命令：不涉及代码，未跑检查脚本
- 对照设计图：不涉及
- 遗留/疑问：消息保留期 N 的具体月数（默认 6）待客户最终确认（R-1）

### T0.1 建仓库和文档 —— ✅ 完成（2026-10-11）
- 完成内容：创建 `changliao/` 目录；把「开发规则」复制为 `changliao/docs/`；复制 HTML 原型、`页面设计图/`（31 张）、png 总览（四大主页面/收费聊天核心页面/页面总览）、`产品参考与计费假设.md` 进 `docs/`；把 `docs/PROGRESS.md` 复制到根目录；写 `.gitignore`；`git init`（分支 main）并提交首个 commit `8a8eab1`（52 个文件）。
- 验收对照：
  1. 目录结构与 `02` 第 1 节一致 —— ✅（`changliao/` 下为 `PROGRESS.md` + `docs/` + `.gitignore`）
  2. `git log` 有第一个提交 —— ⏳ **未提交**（按全局规则不自动 commit，等确认）
- 自检命令：不涉及代码；`git ls-files | wc -l` = 52
- 遗留/疑问：首次提交待确认；后端/安卓运行环境缺失（见 Q0-7、Q0-8）

### T0.2 后端安装 —— ✅ 完成（2026-10-11，含 7 个上游已知失败）
- 完成内容：在 **WSL2 Ubuntu 24.04** 里搭好后端环境并跑通 MineAdmin 3.2.0。
  - 环境：PHP 8.3.6（apt）+ Swoole 5.1.8（GitHub 源码编译，非 pecl）+ Redis 7.0.15 + Composer + libbrotli-dev/build-essential。
  - WSL 网络：新建 `C:\Users\34252\.wslconfig`（mirrored + autoProxy）解决 Clash 导致的外网 DNS 被劫持；apt 源换阿里云。
  - 后端：`git clone --branch v3.2.0` 到 `server/`，删 `.git`，`composer install`（138 包），`mine:install -n`（迁移 + 种子），`composer require hyperf/websocket-server:3.2.*`。
  - 数据库：建 `changliao`、`cl_test`（utf8mb4）；`.env` 指向 127.0.0.1:3306 root/root123，补 `CL_ID_CARD_KEY`/`CL_APP_JWT_SECRET`/`CL_WS_PORT`；`.env.example` 同步补这三个变量。
  - 运行：Swoole 需 `swoole.use_shortname=Off`（已写 `/etc/php/8.3/cli/conf.d/20-swoole.ini`）；`setsid nohup php bin/hyperf.php start` 启动（Hyperf 无 `-d`）。
- 验收对照：
  1. `composer test` 跑通 —— ⚠️ **51 测试 / 996 断言 / 7 失败**（都是 401 vs 422，见 Q0-9）
  2. `.env.example` 已补 `CL_ID_CARD_KEY`、`CL_APP_JWT_SECRET`、`CL_WS_PORT` —— ✅
  3. 能启动并用默认管理员登录拿 token —— ✅（`POST /admin/passport/login`，admin/123456，返回 access_token）
  4. 安装 `hyperf/websocket-server` —— ✅（v3.2.1）
- 自检命令：`composer test`（已改为连 cl_test，不再污染开发库）；`curl POST /admin/passport/login` → 200
- 对照设计图：不涉及
- 遗留/疑问：① 7 个上游测试失败（Q0-9）；② WSL 里跑着后端，**每次 `wsl --shutdown` 或空闲回收后需重新启动**；③ Redis 在 WSL 里以 daemonize 启动

### T0.3 后台前端安装 —— ✅ 完成（2026-10-11，含对上游 lint 的放宽）
- 完成内容：`git clone --branch v3.2.1` MineAdmin-Vue 到 `admin-web/`，删 `.git`，`pnpm install`（3m24s）。接口地址沿用上游默认值即为本机后端（`.env.development` 的 `VITE_APP_API_BASEURL=http://127.0.0.1:9501`），**无需改动**。
- 为让检查通过所做的改动（均为配置文件，未改 MineAdmin 源码）：
  - 新增 devDependency `postcss-html@2.0.0`（stylelint 解析 `.vue` `<style>` 所需）。
  - `eslint.config.js`：`**/*.md` 加入 ignores；`src/**` 放宽 `unused-imports/no-unused-vars` 与 `prefer-promise-reject-errors`；紧随其后对 `src/modules/cl/**` 重新收紧为 error。
  - `stylelint.config.js`：新增 `overrides`，对 `src/components|layouts|plugins|assets|modules/base/**` 关掉 `declaration-block-single-line-max-declarations`、`no-duplicate-selectors`、`scss/operator-no-unspaced`、`keyframes-name-pattern`、`selector-type-no-unknown`、`scss/at-rule-no-unknown`；全局关 `no-empty-source`；`scss/at-rule-no-unknown` 加 `@apply/@screen/...` 白名单。
- 验收对照：
  1. 接口地址指向本地后端 —— ✅（上游默认就是这个值）
  2. `pnpm build` 成功 —— ✅（生成 `dist/`）
  3. `pnpm lint`（= tsc + eslint + stylelint）通过 —— ✅（0 error / 19 warning）
  4. 开发服务器能起并服务页面 —— ✅（`VITE ready in 29.4s`，`http://[::1]:2888/` 返回 200；**注意绑定在 IPv6，用 `localhost:2888` 而非 `127.0.0.1`**）
- 自检命令：`pnpm build`、`pnpm lint`、`pnpm dev` + `curl http://[::1]:2888/`
- 对照设计图：不涉及
- 遗留/疑问：① **未能完成「浏览器登录后台看到菜单」的人工验证**（本会话无浏览器可用）；dev 日志里出现过一次客户端 Vue 运行时错误 `setAttribute ... Cannot convert object to primitive value`，需在 P0 阶段验收时用浏览器确认。② 上游 lint 放宽见 Q0-11。

### T0.4 安卓工程与检查脚本 —— ✅ 完成（2026-10-11）
- 完成内容：新建 Compose 工程 `android/`（applicationId `com.qichuang.changliao`，App 名「畅撩交友」，minSdk 26、compileSdk/targetSdk 37，单模块 `app`）。全部依赖写死在 `gradle/libs.versions.toml`：Compose BOM/Navigation/Lifecycle/Hilt/Room/DataStore/Retrofit+OkHttp+kotlinx.serialization/Coil3；测试 JUnit4/coroutines-test/MockK/Turbine/MockWebServer。含 `ClApp`（@HiltAndroidApp）、`MainActivity`（空白页）、空的 `DesignSystemGalleryScreen`、`local.properties`（sdk.dir）、`network_security_config.xml`（debug 允许明文指向 10.0.2.2）。
- 按 AGP 9「内置 Kotlin」做的三处适配（这是本次最花时间的地方）：
  1. **删掉 `org.jetbrains.kotlin.android` 插件**（AGP 9 自带 Kotlin 编译，不再需要）。
  2. **在根 `build.gradle.kts` 的 `buildscript` 里把 KGP 升级到 2.4.21**：AGP 9.4.1 内置 KGP 2.2.10，读不了 Compose BOM 2026.09 的 Kotlin 2.4 metadata（报 `binary version of its metadata is 2.4.0, expected 2.2.0`）。
  3. **kapt 换成 AGP 的 `com.android.legacy-kapt`**：内置 Kotlin 与 `org.jetbrains.kotlin.kapt` 不兼容；KSP 也与内置 Kotlin 的 sourceSets 冲突（已按官方提示加 `android.disallowKotlinSourceSets=false`）。Hilt/Room 的注解处理走 legacy-kapt。
- 网络：本机 `dl.google.com` / `maven.google.com` 不可达，`settings.gradle.kts` 与根 `buildscript` 均改用**阿里云 Maven 镜像**；Gradle 发行版从 `services.gradle.org` 下载。
- 检查脚本（`scripts/`，均已 chmod +x）：`check-all.sh`、`check-server.sh`、`check-admin.sh`、`check-android.sh`、`check-money.sh`、`check-ui.sh`。**`check-server.sh` 在 Windows 上会自动转发进 WSL 执行**（本机无原生 PHP），`check-ui.sh` 对尚未出现的 `feature/` 目录做了判空。
- 验收对照：
  1. `./gradlew assembleDebug` 成功 —— ✅（产出 `app/build/outputs/apk/debug/app-debug.apk`，约 14.5MB）
  2. `scripts/check-all.sh` 完整跑完并通过 —— ✅（`✅✅✅ 全部检查通过`，rc=0）
  3. 6 个脚本齐全且可执行 —— ✅
- 自检命令：`bash scripts/check-all.sh` → 通过（server 44 测试 / admin-web lint+build / android testDebugUnitTest+lintDebug+assembleDebug+check-ui）
- 对照设计图：不涉及
- 遗留/疑问：① 构建需 `JAVA_HOME` 指向 JDK 17（本机默认 JDK 24 未验证是否可行）；② `composer test` 排除了 7 个上游用例（Q0-9）、phpstan 有 5 条 upstream baseline（Q0-12）。

### T0.5 README —— ✅ 完成（2026-10-11）
- 完成内容：写根目录 `README.md`，包含三端环境要求、数据库说明、三端启动命令、检查命令；只写本机已实际验证过的命令。
- 验收对照：按 README 的步骤能启动三端 —— ✅
  1. 后端：WSL 里 `cd server && php bin/hyperf.php start` → `HTTP Server listening at 0.0.0.0:9501`，`/admin/passport/login` 返回 200 ✅
  2. 后台：`cd admin-web && pnpm dev` → `http://localhost:2888/` 返回 200（注意是 IPv6）✅
  3. 安卓：`cd android && JAVA_HOME=<JDK17> ./gradlew assembleDebug` → BUILD SUCCESSFUL ✅
- 自检命令：不涉及代码改动
- 对照设计图：不涉及
- 遗留/疑问：无

### T1.1 配置中心 —— ✅ 完成（2026-10-11）
- 完成内容：
  - 迁移 `2026_10_11_000001_create_cl_config_tables.php`：建 `cl_config`（key 唯一、value/value_type/group/label/remark）与 `cl_level_config`（5 行等级配置）。
  - 模型 `App\Model\Cl\Config`、`App\Model\Cl\LevelConfig`；仓储 `App\Repository\Cl\ConfigRepository`（新增 `findByKey`）、`LevelConfigRepository`（`allOrdered`/`findByLevel`）。
  - 服务 `App\Service\Cl\Config\AppConfigService`（**全项目唯一读配置入口**）：`get(key, default)` / `getGroup(group)` / `set(key, value)` / `forget()`；整表缓存在 Redis 的 `cl:config`（key => {value, group}），按 `value_type` 自动转 int/bool/json/string；`set()` 会写库并清缓存，且**只允许改已存在的 key**（不允许自创），key 不存在返回 false。
  - 种子 `cl_config_seeder_20261011.php`（docs/06 第 2 节全部 **27 个 key** + 协议文本 `agreement.user`/`agreement.privacy`）、`cl_level_config_seeder_20261011.php`（Lv1~Lv5：门槛 0/20000/100000/300000/1000000，单价 5/8/10/15/20，分成 3000/3500/4000/4500/5000，语音 60~200，视频 120~400）。
- 验收对照：
  1. int/bool/json/string 读取 —— ✅ `AppConfigServiceTest` 的 testGetInt/testGetBool/testGetJson/testGetString
  2. 缓存失效 —— ✅ testSetClearsCache（set 后立刻读到新值，不是缓存旧值）
  3. 种子 key 与 06 第 2 节逐项一致 —— ✅ testSeedKeysMatchDoc（把 27 个 key 排序后断言与种子完全一致）
  4. 额外：`set` 未定义 key 返回 false —— ✅ testSetUnknownKeyReturnsFalse
- 自检命令：`bash scripts/check-server.sh` → 通过（**Tests: 54, Assertions: 1018**；phpstan `[OK] No errors`；money 检查通过）
- 对照设计图：不涉及
- 遗留/疑问：`cl_config.value` 用 text 存，json 类型统一 `json_encode`（`JSON_UNESCAPED_UNICODE`）；布尔统一存 `'1'/'0'`。

### T1.2 统一响应与错误码 —— ✅ 完成（2026-10-11）
- 完成内容：
  - `App\Http\Api\ApiResult`：App 接口统一响应 `{code, message, data}`（结构与 MineAdmin 的 `Result` 一致，但 code 允许业务码整数）；带 `success()` / `error()` / **分页工具** `page()`（`{list,total,page,page_size,has_more}`）与游标分页 `cursor()`（`{list,has_more}`，聊天消息用）。
  - `App\Model\Enums\Cl\BizCode`：docs/05 第 3 节**全部 18 个业务码**（10001~50001）+ `message()` 中文文案。
  - `App\Exception\ClBusinessException` + `App\Exception\Handler\ClBusinessExceptionHandler`：业务异常输出 **HTTP 200** + body code = 业务码（注册进 `config/autoload/exceptions.php`）。
  - 测试 `tests/Unit/Cl/Http/ApiResultTest.php`、`tests/Feature/Cl/Api/ExceptionResponseTest.php`。
- 验收对照：
  1. 抛业务异常返回 `{code:20001,...}` 且 HTTP 200 —— ✅（`ClBusinessExceptionHandler` 断言 `getStatusCode()===200` 且 body code=20001、data 带 balance/needed）
  2. 参数校验失败返回 422 —— ✅（`ValidationExceptionHandler` + `ValidationException::withMessages()`，断言 body code=422、message 为第一条错误）
- 自检命令：`bash scripts/check-server.sh` → 通过（**Tests: 63, Assertions: 1037**；phpstan `[OK] No errors`；money 通过）
- 踩到的两个坑（都已处理）：
  1. **注解扫描缓存**：新增带 `#[Message]` 的枚举后 `ConstantsCollector` 读不到，导致文案为空。已在 `composer.json` 的 `test` 脚本前置 `rm -rf runtime/container`（否则以后每加一个注解类都会莫名失败）。同理，**改了异常处理器等配置后要重启后端**（已重启）。
  2. **phpunit 容器里翻译器取不到 key**（直接 `php` 跑能取到），同一份翻译两种上下文行为不一致。考虑到这些是固定的中文用户文案、不做多语言，`BizCode` 改为**把文案直接写在枚举里**，删除了 `storage/languages/zh_CN/cl.php`。以后若真要 i18n 再迁。
- 对照设计图：不涉及
- 遗留/疑问：无

### T1.3 App 用户表与认证 —— ✅ 完成（2026-10-11）
- 完成内容：
  - 迁移 `2026_10_11_000002_create_cl_user_tables.php`：`cl_user`（04 第 1 节全部字段 + 3 个索引）、`cl_coin_wallet`、`cl_point_wallet`（含 MySQL 8 的 `CHECK (balance >= 0)` / `CHECK (frozen >= 0)`）。
  - 模型 `App\Model\Cl\{User,CoinWallet,PointWallet}`；仓储 `Cl\UserRepository`（账号/手机号/账号或手机号查询）、`Cl\CoinWalletRepository`、`Cl\PointWalletRepository`。
  - 服务 `App\Service\Cl\Auth\AppAuthService`（注册/登录/刷新/退出/改密码、封号检查、密码错误 5 次锁 15 分钟、登录按 IP 限流 10 次/分钟）；`App\Service\Cl\Wallet\{CoinWalletService,PointWalletService}`（T1.3 先只做「开户」，T1.6 补增减流水）。
  - `App\Http\Api\Middleware\AppAuthMiddleware`：Bearer token（scene `api`）+ 黑名单 + 用户存在/未注销，封禁抛 10004；未登录/token 失效 → `ClUnauthorizedException` + `ClUnauthorizedExceptionHandler` → **HTTP 401**。
  - `App\Http\Api\Controller\V1\AuthController` + 4 个 Request；`config/autoload/jwt.php` 新增 `api` 场景（密钥 = `CL_APP_JWT_SECRET`，access 7 天 / refresh 30 天，黑名单前缀 `cl_jwt_blacklist`）。
- 验收对照（9 项 + 补充）：
  1. 注册成功（且密码 bcrypt、男号自动建金币钱包、女号建积分钱包）—— ✅
  2. 手机号重复 10002 —— ✅
  3. 密码规则（无数字 / 太短 / 手机号格式错 → 422）—— ✅
  4. 登录成功 —— ✅
  5. 密码错误 10001 —— ✅
  6. 连续错 5 次后锁定 10003（第 6 次即使密码正确也被锁）—— ✅
  7. 封号登录 10004（data 带 reason、until）—— ✅
  8. **后台 token 不能访问 App 接口** —— ✅（401）
  9. **App token 不能访问后台接口** —— ✅（401）
  10. 补充：改密码（旧密码错 10006）、退出后 token 进黑名单、刷新 token、无 token 401 —— ✅
- 自检命令：`bash scripts/check-server.sh` → 通过（**Tests: 80, Assertions: 1078**；phpstan `[OK] No errors`；money 通过）；开发库已 `migrate`，重启后端后 curl 冒烟：注册 200 / 登录 200 / 错密码 10001。
- 踩到的坑（都已处理）：
  1. `Hyperf\HttpServer\Request` **没有 `ip()`**（MineAdmin 的 `ClientIpRequestTrait` 只提供 `getClientIps()`）→ 控制器改用 `getClientIps()[0]`。原来用 `$request->ip()` 会 500。
  2. `scripts/check-money.sh` **先抓到注册时直接 `CoinWallet::create`**，随后又抓到「在 Service 里注入钱包仓储」→ 按 docs/02 的架构改成注入 `CoinWalletService`/`PointWalletService`（服务类名不在扫描正则里）。**这条检查很有用，别绕过。**
  3. phpstan：`Hyperf\Database\Exception\UniqueConstraintViolationException`（不是 `DbConnection\...\QueryException`）；模型日期字段 docblock 要写 `Carbon\Carbon`；PSR-7 请求用 `withAttribute()` 而不是 Swoke 的 `setAttribute()`。
  4. swagger 注解注册路由：新增控制器后要清 `runtime/container`（`composer test` 已自带）。
- 对照设计图：不涉及
- 遗留/疑问：注册请求接受一个**可选**的 `confirm_password`（01 P00 表单有「确认密码」，但 05 的接口表只列了 phone/password/gender/nickname，故做成可选 + `same:password`）。

### T1.4 启动配置与协议接口 —— ✅ 完成（2026-10-11）
- 完成内容：
  - 迁移 `2026_10_11_000003_create_cl_tag_city_tables.php`：`cl_tag`（兴趣标签库）、`cl_city`（热门城市）。
  - 模型 `App\Model\Cl\{Tag,City}`；仓储 `Cl\{TagRepository,CityRepository}`（都带 `enabledList()`，按 sort 排序、只取启用的）。
  - 种子 `cl_tag_city_seeder_20261011.php`：**10 个标签**（旅行/音乐/电影/美食/健身/读书/摄影/游戏/咖啡/宠物）、**10 个城市**（北京/上海/广州/深圳/杭州/成都/重庆/武汉/西安/南京）。
  - `App\Http\Api\Controller\V1\AppConfigController`：`GET /api/v1/app/config`、`GET /api/v1/app/agreement/{type}`（都免鉴权）。
  - 新增配置项 `app.version`（见 Q0-13），并同步更新 T1.1 的 key 清单测试（现 **28** 个 key）。
- 接口返回（docs/05 第 4.1 节）：
  - `/app/config`：`call_enabled`(bool)、`compliance_disclosure`(string)、`service{wechat,qrcode_url,phone}`、`chat_max_length`(int)、`image_max_mb`(int)、`cities`(城市名字符串数组)、`tags`(`[{id,name}]`，因为 `cl_user.tags` 存的是标签 ID)、`version`。
  - `/app/agreement/{type}`：`{type, content}`；type 只接受 `user`/`privacy`，其它返回 422。
- 验收对照：
  1. 返回字段与 05 一致 —— ✅（testConfigReturnsAllFieldsFromDoc 逐字段断言 8 个字段都在）
  2. `compliance_disclosure` 永远非空 —— ✅（testComplianceDisclosureIsNeverEmpty，并断言含「付费聊天」）
  3. 补充：10 标签 / 10 城市、协议正文非空、非法 type 422、免鉴权可访问 —— ✅
- 自检命令：`bash scripts/check-server.sh` → 通过（**Tests: 87, Assertions: 1112**；phpstan `[OK] No errors`；money 通过）；开发库 `migrate` + `db:seed`，重启后端后 curl 冒烟：config 200（字段齐全）、agreement/user 200、非法 type 422。
- 对照设计图：不涉及
- 遗留/疑问：`version` 的语义待客户确认（Q0-13）。

### T1.5 图片上传 —— ✅ 完成（2026-10-11）
- 完成内容：
  - `App\Service\Cl\Storage\StorageInterface`（`save()` / `url()`）+ `LocalStorage`：存到 `server/storage/uploads/cl/{yyyymm}/{32位随机}.{ext}`；**用 finfo 读真实 MIME**（只允许 jpg/png/webp，改后缀无效）；大小上限读配置 `chat.image_max_mb`（默认 5MB）。绑定在 `config/autoload/dependencies.php`。
  - `App\Http\Api\Controller\V1\UploadController`：`POST /api/v1/upload/image`（**要登录**，`scene` 只接受 avatar/photo/chat/report/real_name，否则 422）；返回 `{url}`；图片不合法/超限用业务码 **30004**（05 第 3 节：30004 = 消息内容不合法，含「图片不合法」）。
  - `config/routes.php` 增加 `GET /uploads/{path:.+}` 读盘路由：只读 `storage/uploads` 一个目录、`realpath` + 前缀校验防目录穿越 —— 这样 `StorageInterface::url()` 给出的地址真的能访问（生产建议改由 Nginx 托管）。
  - `config/autoload/server.php`：`PACKAGE_MAX_LENGTH` 从 `4MB` 提到 `12MB`。**原来它比图片上限 5MB 还小**，超过 4MB 的请求会在 Swoole 协议层被拒，根本走不到业务层的「图片不能超过 X MB」。
- 验收对照：
  1. jpg 成功 —— ✅（返回 `.../uploads/cl/YYYYMM/xxxx.jpg`）
  2. 伪装成 jpg 的文本文件被拒 —— ✅（30004「只支持 jpg / png / webp 图片」）
  3. 超过 5MB 被拒 —— ✅（30004「图片不能超过 5MB」）
  4. 未登录 401 —— ✅
  5. 补充：scene 非法 422、返回的 URL 可静态访问（200 / 707 字节 / Cache-Control）—— ✅
- 自检命令：`bash scripts/check-server.sh` → 通过（**Tests: 92, Assertions: 1121**；phpstan `[OK] No errors`；money 通过）；重启后端后 curl 冒烟全部符合预期。
- 对照设计图：不涉及
- 遗留/疑问：返回的 URL 用的是 `APP_URL`（当前 `http://127.0.0.1:9501`）。安卓模拟器访问宿主要用 `10.0.2.2`，客户端做图片地址拼接时要注意（或将 `APP_URL` 配成对外可达地址）。

### T1.6 钱包服务 —— ✅ 完成（2026-10-11）
- 完成内容：
  - 迁移 `2026_10_11_000004_create_cl_wallet_log_tables.php`：`cl_coin_log`、`cl_point_log`、`cl_platform_income`；两张流水表都有**唯一索引 (type, ref_type, ref_id)** 用于防重复记账。
  - 枚举 `Cl\CoinLogType`（RECHARGE/MESSAGE/GIFT/VIP/TASK/ADMIN_DEDUCT）、`Cl\PointLogType`（7 种）、`Cl\PlatformIncomeSource`（MESSAGE/GIFT/VIP）；模型 `Cl\{CoinLog,PointLog,PlatformIncome}`。
  - 仓储 `Cl\{CoinLogRepository,PointLogRepository,PlatformIncomeRepository}`；两个钱包仓储补 `lockByUserId()`（`lockForUpdate`）。
  - 服务（**全项目唯一改余额入口**）：
    - `CoinWalletService::increase/decrease`：事务内锁行 → 改余额 → 写流水（含 `balance_after`）；余额不足抛 **20001**（data 带 `balance`、`needed`）并整单回滚；只有 RECHARGE 计入 `total_recharge`，只有 MESSAGE/GIFT/VIP 计入 `total_spent`。
    - `PointWalletService::increase/decrease/freeze/unfreeze/settleFrozen`：change/frozen_change 严格按 docs/04 第 4 节的表；不足抛 **20002**。
    - `PlatformIncomeService::record`：写 `cl_platform_income`（含 `income_date` 按天）。
- 验收对照：
  1. 加减正确、`balance_after` 正确 —— ✅
  2. 不能扣成负数 —— ✅（20001 + 回滚，失败不留流水）
  3. 重复 ref 不重复记账 —— ✅（同 (type,ref_type,ref_id) 第二次直接跳过）
  4. 并发扣减不出负数 —— ✅ **场景 8**：余额 10、并发扣 3×5 → 只成功 2 笔、余额 0（`parallel()` + `lockForUpdate`）
  5. 提现三步（冻结/驳回退回/已打款）的 change、frozen_change、total_withdrawn —— ✅
  6. **场景 34 对账** —— ✅ `ReconcileTest`：随机 100 次操作后校验「金币 balance = Σ金币流水 change」「积分 balance = Σ积分流水 change」「frozen = Σfrozen_change」
- 自检命令：`bash scripts/check-server.sh` → 通过（**Tests: 100, Assertions: 1160**；phpstan `[OK] No errors`；money 通过）；开发库已迁移，后端重启后登录 200。
- 对照设计图：不涉及
- 遗留/疑问：`total_recharge` / `total_spent` 的归属口径（哪些 type 计入）文档没明说，按名字取最合理的口径实现，已记在此处备查。

### T2.1 主题 —— ✅ 完成（2026-10-11）
- 完成内容（都在 `android/app/src/main/java/com/qichuang/changliao/core/designsystem/theme/`）：
  - `ClColors`（37 个颜色 token，含 mask 的 43% 透明度）、`ClTypography`（13 个字级）、`ClSpacing`（8 个）、`ClSize`（14 个）、`ClShapes`（9 个，气泡角序同 CSS border-radius）、`ClElevation`（卡片阴影 Y5/blur20）。
  - `ClGradients`：7 个渐变（数值取自 HTML 原型 CSS，见下）。`ClTheme`（`CompositionLocal` 提供，强制浅色）+ `Modifier.clCardShadow()`（全项目唯一卡片阴影）+ `Modifier.clTopWashBackground()` + `Modifier.clGradientBackground()`。
  - `MainActivity` 套上 `ClTheme`，占位页改用 `ClTheme.colors/typography`（不再用 MaterialTheme）。
- 一处与文档写法的偏差（结果一致）：03 文档说渐变「定义在 ClColors 里的 Brush」，但 Compose 的 `Brush` 与尺寸绑定、没法像 CSS 那样只写角度/百分比，**`gradientTopWash` 更是三层（两块 radial + 一块 linear）**，单个 Brush 表达不了。所以改成「颜色 + 角度」的数据 + `Modifier` 按实际尺寸绘制，数值严格来自原型 CSS（`.topwash` = `radial-gradient(circle at 8% 1%,#fff7cb 0,transparent 37%), radial-gradient(circle at 100% 45%,#fff0f0 0,transparent 38%), linear-gradient(#fffaf2,#fff 90%)`）。
- 验收对照：03 第 2~4 节的每个 token 都存在且数值一致 —— ✅ `ClThemeTokensTest` 逐项断言：37 个颜色、8 个间距、14 个尺寸、13 个字号、2 个阴影。
- 自检命令：`bash scripts/check-android.sh` → 通过（BUILD SUCCESSFUL；`testDebugUnitTest` 含上面的断言测试；`check-ui.sh` 通过）
- 对照设计图：本任务只做 token，无可视页面；颜色/字号/间距数值已与 03 文档逐项核对。
- 踩到的坑：Compose 里**没有** `Canvas.drawOutline()` / `Path.addOutline()`，把 `Shape` 转成 `Path` 要显式按 `Outline.Rounded / Rectangle / Generic` 分支构造。
- 遗留/疑问：无

### T2.2 图标 —— ✅ 完成（2026-10-11）
- 完成内容：`core/designsystem/icon/ClIcons.kt`，把 docs/03 第 6 节第 6 条列的 **26 个图标全部**提供出来（home/heart/message/user/search/filter/pin/chevron/back/bell/eye/star/wallet/shield/settings/calendar/edit/mic/phone/video/gift/check/more/spark/speaker/flip），全部映射到 **Material Icons Rounded**（线条风格）。页面里只准用 `ClIcons.xxx`，不准直接用 `Icons.Rounded.*`。
- 新增依赖：`material-icons-extended:1.7.8`（见「新增依赖记录」表，含包体/弃用提醒）。
- 验收对照：docs 列出的图标全部存在 —— ✅ `ClIconsTest` 逐项断言 26 个图标都有名字、有尺寸。
- 自检命令：`bash scripts/check-android.sh` → 通过（BUILD SUCCESSFUL；含上面的图标断言测试；`check-ui.sh` 通过）
- 对照设计图：本任务只做图标清单，无可视页面。
- 遗留/疑问：release 包体受 `material-icons-extended` 影响，见依赖记录表。

### T2.3 结构类与按钮类组件 —— ✅ 完成（2026-10-11）
- 完成内容（`core/designsystem/component/`，一个组件一个文件）：
  - **5.1 结构类**：`ClScaffold`（状态栏色 + 顶栏 + 内容 + 固定底部区 + 底栏 + Toast 宿主）、`ClTopBar`、`ClPageHeader`（带黄色短下划线）、`ClBottomBar`（选中项 primary 圆角底 + 未读角标 >99 显示 99+）、`ClSectionHeader`、`ClFixedBottomArea`。
  - **5.2 按钮类**：`ClPrimaryButton`（黄底胶囊，支持 subText/禁用/loading）、`ClSecondaryButton`、`ClSoftButton`（Yellow/Peach/Gray 三种底色）、`ClOutlineSmallButton`、`ClTextButton`、`ClIconButton`。
  - `ClToast` + `rememberClToast()`：ClScaffold 描述里要求「Toast 宿主」，所以把 5.6 的 Toast 一并做了（深色胶囊、2.2 秒消失）。
  - 组件预览页 `DesignSystemGalleryScreen` 重写：按 5.1/5.2 分组展示**每个组件的各种状态**（主按钮的 normal/subText/禁用/loading 等）。
  - `MainActivity`：debug 包直接进组件预览页（03 第 7 节要求它只在 debug 可达；正式入口「设置页连点版本号 5 次」在 T6.6 接）。
- 验收对照：
  1. 预览页能看到每个组件 —— ✅（`check-ui.sh` 会遍历 `component/*.kt`、逐个确认出现在预览页里，本次通过）
  2. `check-ui.sh` 通过 —— ✅
- 自检命令：`bash scripts/check-android.sh` → 通过（BUILD SUCCESSFUL；lint、单测、打包、`check-ui.sh` 全通过）
- 对照设计图：本阶段无页面；组件样式按 docs/03 第 5.1/5.2 节 + 原型 CSS（`.inner-head`/`.page-title`/`.bottom`/`.full-btn`/`.say-hi`/`.action`/`.section-head`）落地。
- 遗留/疑问：
  1. `ClSoftButton` 三种底色的映射见 Q0-14。
  2. 原型 `.page-title` 是 26px/800，而 docs/03 第 3 节写 25sp/Bold —— 按**文档**取 25sp（03 是规范）。
  3. `ClBottomBar` 里的未读角标是临时私有实现，等 T2.4 的 `ClBadgeDot` 出来后改为复用它。

### T2.4 展示类组件 —— ✅ 完成（2026-10-12）
- 完成内容（`core/designsystem/component/`，19 个）：`ClAvatar`（Coil 加载、在线点右下 / VIP 角标右上）、`ClTag`（Green/Gold/Pink）、`ClLevelBadge`（紫色渐变）、`ClVipBadge`、`ClCoinPill`、`ClCard`（统一卡片阴影）、`ClNoticeCard`（可高亮）、`ClGradientHero`、`ClBalanceCard`、`ClPriceGrid`、`ClBillRow`+`ClBillGroup`、`ClRecordRow`（金额 +绿/-黑）、`ClMenuRow`+`ClMenuGroup`（55dp 高）、`ClSettingRow`+`ClSwitch`（开关用主题色）、`ClStatCell`+`ClStatRow`、`ClBadgeDot`（>99 显示 99+）、`ClFeeStrip`、`ClProgressBar`、`ClDemoLabel`。
- 顺带改动：`ClBottomBar` 的未读角标改为**复用 `ClBadgeDot`**（消除 T2.3 的临时实现）；`ClTextButton` 增加可选 `color` 参数（`ClFeeStrip` 要用收费条的文字色）。
- 验收对照：
  1. 预览页能看到每个组件 —— ✅（`check-ui.sh` 逐个确认 `component/*.kt` 都出现在预览页，本次通过）
  2. `check-ui.sh` 通过 —— ✅
- 自检命令：`bash scripts/check-android.sh` → 通过（BUILD SUCCESSFUL；lint、单测、打包、`check-ui.sh` 全通过）
- 对照设计图：本阶段无页面；样式按 docs/03 第 5.3 节 + 原型 CSS（`.notice-card`/`.balance-hero`/`.bill-grid`/`.record-line`/`.menu-row`/`.demo-label` 等）落地。
- 踩到的坑：`ClSettingRow(label, desc, trailing, onClick)` 这种「可组合参数后面还有普通参数」的签名，**调用时尾随 lambda 会绑到最后一个参数（onClick）**而不是 `trailing` —— 必须写成 `trailing = { ... }`。
- 遗留/疑问：两个原型色不在 docs/03 的颜色 token 表里，作为**组件内私有常量**并注明出处：头像占位色 `#E8D8C7`（03 第 6 节第 7 条）、次按钮底色 `#F4EEE3`（03 第 5.2 节）、卡片阴影色 `#F5F1EB`（03 第 4 节）。

### T2.5 输入类与反馈类组件 —— ✅ 完成（2026-10-12）
- 完成内容：
  - **5.5 输入类**（6 个）：`ClTextField`（标签在上、`input` 圆角、`line` 描边、错误红字、`maxLength` 截断）、`ClPasswordField`（显示/隐藏切换）、`ClSelectField`（点击弹选择，另附 `ClSelectOption`）、`ClChipGroup`（单选/多选，选中用 primaryChip + primaryChipBorder）、`ClRangeSlider`（年龄 18~60）、`ClCheckboxAgreement`（协议勾选，两份协议可点）。
  - **5.6 反馈类**（7 个，`ClToast` 已在 T2.3 做过）：`ClBottomSheet`、`ClConfirmDialog`（可危险色）、`ClLoading`、`ClEmpty`、`ClErrorRetry`、`ClPullRefreshList`（下拉刷新 + 滚到底自动加载更多 + 底部「加载中 / 加载失败点击重试 / 没有更多了」）、`ClImagePreview`（全屏、多图可左右翻、显示 1/N）。
  - `core/common/UiState.kt`：`sealed interface UiState<out T> { Loading; Empty; Error(message); Content(data) }`（docs/02 第 4.4 节）。
  - 组件预览页补上这两个分组，每个组件都带状态示例。
- 验收对照：
  1. 预览页能看到每个组件 —— ✅（`check-ui.sh` 逐个确认 `component/*.kt` 都出现在预览页）
  2. `check-ui.sh` 通过 —— ✅
- 自检命令：`bash scripts/check-android.sh` → 通过（BUILD SUCCESSFUL；lint、单测、打包、`check-ui.sh` 全通过）
- 对照设计图：本阶段无页面；样式按 docs/03 第 5.5/5.6 节 + 原型 CSS（`.sheet`、`.empty` 等）落地。
- 遗留/疑问：
  1. `ClPullRefreshList` 内部是 `LazyColumn`，**必须给它一个确定高度**（不能直接放进 `verticalScroll` 的 Column，会因无限高度约束崩溃）——预览页里就是用固定高度包的。
  2. `ClChipGroup` 把选中集合整体回传（`onSelectionChange(Set<String>)`），由调用方持有状态；`multi=false` 时点谁选谁。

### T2.6 网络层与本地存储 —— ✅ 完成（2026-10-12）
- 完成内容（`core/network/`、`core/data/local/`）：
  - `ApiResponse<T>`（服务端响应外壳，含 200/401/10004 常量）、`ApiResult`（`Success` / `BizError(code,message,data)` / `NetworkError`）+ **`safeApiCall<T>`（reified）**：code=200 时按调用方要的类型解析 `data`，其余走业务错误/网络错误。
  - `TokenStore`（接口）+ `AuthInterceptor`：给**本服务**的请求加 `Authorization`；收到 401 用 refresh_token **自动刷新一次**并重放；刷新失败清登录态；跨主机（以后图片走 CDN）**不带 token**；刷新请求自身标 `X-No-Auth` 防止递归。
  - `AuthApi`（注册/登录/刷新/退出/改密码）+ `dto/AuthDto.kt`；`NetworkModule`（OkHttp 30 秒超时 + 日志拦截器 + Retrofit + kotlinx.serialization 转换器，地址取自 `BuildConfig.API_BASE_URL`）；`ClJson`（共用 Json 配置）。
  - `ClDataStore`：DataStore 存 token（**内存里额外缓存一份**，因为 OkHttp 拦截器要同步读）、按用户分key的筛选条件、消息提醒开关；`LocalModule` 把 `TokenStore` 绑到它。
  - Room 本地库骨架：`db/ClDatabase.kt`（version 1 + `MIGRATIONS` 数组，从第 1 天就带迁移）、`entity/SyncStateEntity`（同步游标）、`dao/SyncStateDao`、`DatabaseModule`。**`message`/`conversation` 两张表和 v2 迁移留到 T4.5**（Room 要求至少一个实体，所以先用最小的 sync_state 起库）。
- 顺带的**服务端改动**：业务错误的 `data` 从 `[]` 改成 `null`（`ApiResult::error` 与 `ClBusinessException` 的默认值）。原因：客户端按具体 DTO 解析 `data`，空数组会解析失败。已实测错密码返回 `{"code":10001,...,"data":null}`，成功响应不受影响。
- 验收对照（docs/02 第 4.3 节，用 MockWebServer）：
  1. 成功 —— ✅ `ApiResultTest`
  2. 业务错误（10001 / 20001）—— ✅
  3. **401 刷新成功**（刷新后带新 token 重放，共 3 个请求）—— ✅ `AuthInterceptorTest`
  4. **401 刷新失败**（清登录态并返回未登录）—— ✅
  5. 网络错误 —— ✅
  6. 补充：有 token 自动带头 / 无 token 不带头 / 刷新请求不递归刷新 —— ✅
- 自检命令：`bash scripts/check-android.sh` → 通过（BUILD SUCCESSFUL；`testDebugUnitTest` 含上面 9 个用例；`check-ui.sh` 通过）；`bash scripts/check-server.sh` → 通过（**100 测试 / 1160 断言**）。
- 对照设计图：不涉及
- 遗留/疑问：业务错误的 `data` 结构和服务端成功时的 DTO 不一样（例如 20001 带 `balance`/`needed`），所以 `ApiResult.BizError.data` 保留原始 `JsonElement`，需要时自己取。

## 阶段验收记录

<!-- 每个阶段结束写一段：check-all 结果、走查了哪些页面、发现并修复了什么 -->

### P0 准备与骨架 —— ✅ 通过（2026-10-11）
- `bash scripts/check-all.sh` → **`✅✅✅ 全部检查通过`**（rc=0）：server 44 测试 + phpstan + check-money；admin-web lint+build；android testDebugUnitTest+lintDebug+assembleDebug+check-ui。
- 三端均可启动（见 T0.5 记录）。
- 本阶段无 UI 页面，故未做设计图走查。
- 发现并处理的问题（详见各任务记录与「待确认问题」表）：WSL 网络（Clash）、Swoole 编译与 `use_shortname`、测试库隔离、MineAdmin 上游 7 个测试 + 5 个 phpstan 报错、MineAdmin-Vue 上游 lint、AGP 9 内置 Kotlin 的三处适配、Google Maven 不可达改阿里云镜像。
- 遗留：后台「浏览器登录看到菜单」的人工验证未做（无人可用浏览器）；见 T0.3 记录。

### P1 后端基础 —— ✅ 通过（2026-10-11）
- `bash scripts/check-all.sh` → **`✅✅✅ 全部检查通过`**（rc=0）：server **100 测试 / 1160 断言** + phpstan + check-money；admin-web lint+build；android testDebugUnitTest+lintDebug+assembleDebug+check-ui。
- 本阶段只做后端，没有新增页面、也没有后台菜单，故无设计图走查与菜单走查。
- 产出：T1.1 配置中心（`cl_config`/`cl_level_config` + `AppConfigService`）、T1.2 统一响应与错误码（`ApiResult`/`BizCode`/`ClBusinessException`）、T1.3 App 用户与认证（`cl_user` + 两个钱包表 + JWT `api` 场景 + `AppAuthMiddleware`）、T1.4 启动配置与协议（`cl_tag`/`cl_city` + `/app/config`、`/app/agreement/{type}`）、T1.5 图片上传（`StorageInterface`/`LocalStorage` + `/upload/image`）、T1.6 钱包服务（3 张流水表 + 钱包服务 + 对账测试）。
- 发现并处理的问题：见 T1.1~T1.6 各任务记录（注解扫描缓存导致 `ConstantsCollector` 读不到、phpunit 容器里翻译器取不到 key、`FormRequest` 没有 `ip()`、`check-money.sh` 抓到绕过钱包服务、`PACKAGE_MAX_LENGTH` 比图片上限还小等）。

## 页面与设计图对照表（T9.2 填写）

| 页面 | 设计图 | 是否一致 | 差异说明 |
|---|---|---|

---

## 开工前客户确认（2026-10-11）

> 来源：开发方与客户的沟通确认。**这些新增需求已由 T0.6 同步进 `01`/`02`/`04`/`05`/`06` 文档。**
> 按铁律第 15 条，本人未修改需求文档，等经理确认后由经理更新。

| 编号 | 确认内容 | 落地位置 | 与现有文档的冲突 |
|---|---|---|---|
| C0-1 | 免费条数后台可配置：既可设「前 N 条免费」，也可设「第一条就收费」（即 N=0） | 已有支持：`06` 文档 `chat.free_count_per_pair` 默认 3，0 = 不免费。**无需改动** | 无冲突 |
| C0-2 | **聊天消息必须本地化**；服务端只作为数据转发和聊天监管 | `10-客户端缓存与本地化方案.md` 第 1、2 节 | 与 `01` P06「历史消息上拉加载」、`02` 4.3「无本地库」冲突，见 Q0-3 / Q0-4 / Q0-5 |
| C0-3 | **所有实时内容和「广场」都要实现静默刷新 + 持久化存储** | `10` 文档第 3、5 节（stale-while-revalidate） | 「广场」定义待澄清，见 Q0-1 |
| C0-4 | 经理给的 iOS 缓存架构参考，只保留本项目有用的部分 | `10` 文档已按此裁剪，删除阅后即焚 / 闪图 / 撤回 / 多身份 / 换机迁移 / 动态 feed 等 | 无冲突 |

### 影响面提示

C0-2 是**架构级变更**，不是加个配置。它会影响：

- `02` 文档 4.2 依赖清单（新增 Room）、4.3 包结构（新增本地 db 目录）
- `04` 数据库设计（服务端 `cl_message` 的定位变为监管留存，保留策略待定）
- `05` 接口规范（历史消息接口改为增量补拉）
- `01` 文档 P06 页面说明
- `09` 测试（需新增本地库的 DAO 测试与同步逻辑测试）
- 任务清单里 T2.6（网络层与本地存储）的工作量和验收标准

**计费逻辑不受影响**：扣费、分成、余额仍在服务端计算（铁律第 6 条），本地库只存展示用的消息内容。---|
