# PROGRESS（进度记忆）

> 这是你的记忆。**每次开工先读，每个任务做完必须更新。**
> 只追加和修改状态，不要删除历史记录。

## 当前状态

- 当前阶段：P0
- 当前任务：T0.5 README
- 任务状态：进行中 <!-- 未开始 / 进行中 / 已完成 -->
- 上次停在哪：T0.6 / T0.1 / T0.2 / T0.3 / T0.4 全部完成，**`scripts/check-all.sh` 全绿通过**。安卓工程已建好并能打 debug 包（AGP 9.4.1 + Gradle 9.8.1 + Kotlin 2.4.21 + Compose BOM 2026.09.00，compileSdk/targetSdk 37、minSdk 26），6 个检查脚本已建。下一步只剩 T0.5 README（只写已验证过的命令）。

## 任务进度

<!-- 做完一个，把 [ ] 改成 [x]，并在下方「任务记录」里写自检记录 -->

- P0：[x] T0.6 [x] T0.1 [x] T0.2 [x] T0.3 [x] T0.4 [ ] T0.5
- P1：[ ] T1.1 [ ] T1.2 [ ] T1.3 [ ] T1.4 [ ] T1.5 [ ] T1.6 [ ] T1.7
- P2：[ ] T2.1 [ ] T2.2 [ ] T2.3 [ ] T2.4 [ ] T2.5 [ ] T2.6 [ ] T2.7 [ ] T2.8 [ ] T2.9 [ ] T2.10
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

## 阶段验收记录

<!-- 每个阶段结束写一段：check-all 结果、走查了哪些页面、发现并修复了什么 -->

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
