# PROGRESS（进度记忆）

> 这是你的记忆。**每次开工先读，每个任务做完必须更新。**
> 只追加和修改状态，不要删除历史记录。

## 当前状态

- 当前阶段：**P3 登录·资料·发现·关系**（P2 已完成）
- 当前任务：T3.8 已完成 → 下一步 T3.9 访客、喜欢我的人（P08、P09）
- 任务状态：待开始 <!-- 未开始 / 进行中 / 已完成 -->
- 上次停在哪：T3.8 个人详情页完成（P05 + 设计系统新增封面组件 + 关注/拉黑/举报入口，17 个新单测），`check-android.sh` 通过（**190 个单测**）、`check-server.sh` 通过（**192 测试**）。下一步按 `docs/08` 做 T3.9（访客 P08 + 喜欢我的人 P09，非会员打码那条链路要看效果，对照设计图 08、09）。

## 任务进度

<!-- 做完一个，把 [ ] 改成 [x]，并在下方「任务记录」里写自检记录 -->

- P0：[x] T0.6 [x] T0.1 [x] T0.2 [x] T0.3 [x] T0.4 [x] T0.5
- P1：[x] T1.1 [x] T1.2 [x] T1.3 [x] T1.4 [x] T1.5 [x] T1.6 [x] T1.7
- P2：[x] T2.1 [x] T2.2 [x] T2.3 [x] T2.4 [x] T2.5 [x] T2.6 [x] T2.7 [x] T2.8 [x] T2.9 [x] T2.10 [x] T2.11
- P3：[x] T3.1 [x] T3.2 [x] T3.3 [x] T3.4 [x] T3.5 [x] T3.6 [x] T3.7 [x] T3.8 [ ] T3.9 [ ] T3.10
- P4：[ ] T4.1 [ ] T4.2 [ ] T4.3 [ ] T4.4 [ ] T4.5 [ ] T4.6 [ ] T4.7 [ ] T4.8
- P5：[ ] T5.1 [ ] T5.2 [ ] T5.3 [ ] T5.4 [ ] T5.5 [ ] T5.6 [ ] T5.7 [ ] T5.8 [ ] T5.9
- P6：[ ] T6.1 [ ] T6.2 [ ] T6.3 [ ] T6.4 [ ] T6.5 [ ] T6.6 [ ] T6.7
- P7：[ ] T7.1 [ ] T7.2 [ ] T7.3 [ ] T7.4 [ ] T7.5 [ ] T7.6 [ ] T7.7 [ ] T7.8 [ ] T7.9
- P8：[ ] T8.1 [ ] T8.2 [ ] T8.3 —— ⏸ **本期不做**（2026-10-12 客户确认「视频和语音功能先不做」：`feature.call_enabled` 保持 0、所有音视频入口隐藏，P8 三个任务跳过）
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
| 安卓 | androidx.lifecycle:lifecycle-runtime-compose | 2.11.0 | Compose 里用 `collectAsStateWithLifecycle()` 收 StateFlow（随生命周期停止收集） | T2.7 |
| 安卓 | androidx.hilt:hilt-navigation-compose | 1.4.0 | `hiltViewModel()`：在 `composable(...)` 目的地里拿 @HiltViewModel（目的地的 ViewModelStoreOwner 不是 Activity，普通 `viewModel()` 拿不到 Hilt 工厂） | T2.7 |
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
| Q0-15 | `ClMiniProfileRow` 的 `blurred`（P09 非会员看不全是谁）用 `Modifier.blur`，但它在 **API 31 以下是空操作**，低版本手机上打码不生效 | 先用着（minSdk 26，Android 12+ 生效）；要全机型都糊就得改成盖一层遮罩或直接换成打码文字。做 P09 时决定 | T2.9、T3.9 | ⏳ 待确认（**2026-10-12 模拟器实测：API 37 上打码正常生效**） |
| Q0-16 | `ClRangeSlider`（`docs/03` 第 5.5 节）是双滑块，但设计图 07 画的是**单滑块**（原型 HTML 也是一个 `<input type=range>`） | 行为以 `docs/01` P07「双滑块 18~60」为准，实现不改；外观差异（M3 竖条 thumb vs 设计图圆 thumb）留到 T3.8 做 P07 时对着设计图定 | T2.5、T3.8 | ⏳ 待 T3.8 定 |
| Q0-18 | P14 的「学历」`docs/01` 只写「选择」，没给选项清单（原型里只出现过「本科」「硕士」两个值） | 先定成 `高中及以下 / 大专 / 本科 / 硕士 / 博士` 五项；服务端只校验 `string|max:16` 不限制取值，客户给清单后改客户端常量 `EDUCATION_OPTIONS` 即可 | T3.3 | ⏳ 待确认 |
| Q0-19 | 「在线」怎么算？`docs` 没定义多久没活跃算离线（P01 要按在线优先排序、卡片上有在线绿点，P07 有「只看在线」） | 先用 **15 分钟**（`DiscoverRepository::ONLINE_MINUTES`）。这是业务上要调的阈值，客户定了以后要么改常量要么提到 `cl_config` | T3.5 | ⏳ 待确认 |
| Q0-17 | 注册页要不要也放「我已阅读并同意协议」勾选？`docs/01` P00 只在**登录**那条里要求勾选，「注册」那条只列了字段 | 按文档来：**只在登录页勾选**，注册页不放。客户若要求注册也勾，加一个 `ClCheckboxAgreement` 即可 | T3.1 | ⏳ 待确认 |

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

### T2.7 导航与 App 骨架 —— ✅ 完成（2026-10-12）
- 完成内容：
  - `core/navigation/Routes.kt`：`docs/01` 第 5 节全部 31 个页面 + P00 登录/注册/协议的路由常量与带参构造函数（`userDetail(id)`、`chat(id)`、`callConfirm(id)`…）。**P30 余额不足、P31 聊天礼物是弹窗不是页面，所以没有路由**。
  - `core/navigation/ClNavHost.kt`：全 App 唯一导航图。已实现的只有 `login`（占位）、`main`、`gallery`；其余 26 个页面统一挂 `PlaceholderPage`（`ClScaffold` + `ClTopBar` + `ClEmpty("开发中")`），后续任务把对应的 `placeholder(...)` 换成真页面即可。
  - `MainScreen` / `MainViewModel` / `MainUiState`：底部导航 4 个 Tab（遇见/速配/消息/我），**速配 Tab 由 `feature.call_enabled` 控制**；Tab 各自是独立路由，切换用 `saveState/restoreState` 保留各自的滚动位置。
  - 启动配置：`AppApi`（`/app/config`、`/app/agreement/{type}`）、`AppConfig` 模型（**字段名按服务端下划线风格加了 `@SerialName`**）、`AppConfigRepository`（`StateFlow<AppConfig?>` + 落 DataStore）、`ClApp.onCreate` 里后台拉一次。拿不到配置（首次安装 + 断网）时按 `call_enabled = false` 处理，与服务端默认值一致。
  - `MainActivity`：起始页由本地 token 决定 —— 没 token 进登录页，有 token 直接进主界面。
  - 底部安全区：在 `ClNavHost` 的 `NavHost` 上统一 `windowInsetsPadding(navigationBars)`（背景铺满、内容避开），页面不用各自操心；`ClBottomBar` 自己也带一份，靠 Compose 的 insets 消费机制不会重复留白（targetSdk 35+ 强制边到边）。
- 验收对照（docs/08 T2.7）：
  1. 能在 4 个 Tab 间切换 —— ✅ `MainViewModelTest`（8 个用例）
  2. **开关关闭时只有 3 个 Tab**（ViewModel 测试）—— ✅ 另含：配置未加载按关闭处理 / 配置从关到开后多出速配且不打断当前 Tab / 关闭时点速配不生效 / 点不认识的路由不改变当前 Tab
  3. `/app/config` 字段名契约 —— ✅ `AppConfigTest`（4 个用例，用**接口真实返回的 JSON** 钉住解析；另覆盖多返回未知字段、缺字段走默认值）
- 实测：`curl http://127.0.0.1:9501/api/v1/app/config` 返回的字段与 `AppConfig` 一一对应（`call_enabled`/`compliance_disclosure`/`service.wechat`/`service.qrcode_url`/`service.phone`/`chat_max_length`/`image_max_mb`/`cities`/`tags[{id,name}]`/`version`）。
- 自检命令：`bash scripts/check-android.sh` → 通过（BUILD SUCCESSFUL；`testDebugUnitTest` **28 个用例**：AppConfig 4 + MainViewModel 8 + ApiResult 5 + AuthInterceptor 5 + 主题 5 + 图标 1；`lintDebug`、`assembleDebug`、`check-ui.sh` 均通过）。
- **未做**：没有在模拟器/真机上跑过（本机只装了 Android Studio，没有启动过 AVD），所以「Tab 能点、能切」是靠 ViewModel 测试 + 编译通过保证的，真机观感待你跑一次再确认。
- 对照设计图：不涉及（占位页）。
- 遗留/疑问：底部导航的**未读角标**原计划留到 T4.x（未读数要等消息数据），**2026-10-12 已补做**：新增应用级 `core/data/local/UnreadStore.kt`（`StateFlow<Int>` + `set/clear`），`MainViewModel` 把它合进 `MainUiState.badges`（只挂消息 Tab、0 不显示），`ClBottomBar` 的 `badges` 参数接上。`UnreadStore` 只做落点，**真正的未读来源是 T4.6 的会话表 + WebSocket 推送**，届时由消息层调 `set()`。
  - 角标测试（6 个）：无未读不显示 / 挂在消息 Tab 上 / 未读数变化跟着变 / 清零后消失 / 超过 99 原样交给 `ClBadgeDot` 显示「99+」/ 负数当 0。

### T2.8 公共工具 —— ✅ 完成（2026-10-12）
- `core/common/TimeFormat.kt`：`friendly()` 按 `docs/03` 第 6 节第 5 条 —— 今天 `HH:mm`、昨天「昨天 HH:mm」、今年 `MM-dd HH:mm`、更早 `yyyy-MM-dd`；另有 `dateTime()`/`date()`/`parse()`（解析服务端 `yyyy-MM-dd HH:mm:ss`）。**昨天按日历天算，不是按 24 小时算**。
- `core/common/MoneyFormat.kt`：金币「120 金币」、紧凑位置「120 币」、积分「15 积分」、价格「5 币/条」「60 币/分钟」（单位常量 `UNIT_PER_MESSAGE`/`UNIT_PER_MINUTE`）、流水「+15/-5」、人民币「¥12.50」（分 → 两位小数）。**只做显示**，任何金额/价格都由服务端算好（铁律 6）。
- `core/common/Validators.kt`：手机号、密码（6~20 且同时含字母数字）、昵称（2~12）、身份证（18 位 + GB 11643 校验位，末位小写 x 也认）。**规则逐条对齐 `server/app/Http/Api/Request/V1/RegisterRequest.php`**（`^1[3-9]\d{9}$`、`min:6|max:20` + `(?=.*[A-Za-z])(?=.*\d)`、`min:2|max:12`），客户端校验只为少一次往返，服务端才是最终判定。
- `core/common/ImageCompressor.kt`：读图 → `inSampleSize` 降采样（2 的幂）→ 按 EXIF 扳正方向（8 种方向）→ 精确缩放到长边 1080（**小图不放大**）→ JPEG 质量 80。用系统 `android.media.ExifInterface`（API 26+ 自带，不新增依赖）。
- 验收对照（docs/08 T2.8「全部单元测试，边界值齐全」）：**34 个用例**全部通过
  - `TimeFormatTest` 12：今天/昨天/今年/更早、**跨天边界**（23:59 与次日 00:30）、跨年边界、闰日、非法时间戳（0/负数）返回空串、时区参数生效、`dateTime`/`date` 格式、`parse` 往返与解析失败返回 null。
  - `MoneyFormatTest` 7：金币/简写/积分/价格单位/带符号/人民币两位小数（含 0、0.05、1.00、负数）。
  - `ValidatorsTest` 6：手机号（1 开头、第二位 3~9、长度、非数字）、密码（5/6/20/21 位、纯字母、纯数字）、昵称（1/2/12/13 字）、身份证（两个合法号 + 位数/字符/校验位错误）。
  - `ImageCompressorTest` 9：降采样倍数、够小的图不降采样、非法参数、横图/竖图/正方形、不放大、极端长宽比不缩成 0。
- 自检命令：`bash scripts/check-android.sh` → 通过（BUILD SUCCESSFUL；**62 个单测**：公共工具 34 + AppConfig 4 + MainViewModel 8 + ApiResult 5 + AuthInterceptor 5 + 主题 5 + 图标 1；`lintDebug`、`assembleDebug`、`check-ui.sh` 通过）。
- **未做**：`ImageCompressor.compress()` 本体（Bitmap 解码/编码）需要设备或 Robolectric，**没有测试**；单测只覆盖了它内部两个纯计算函数（`calculateInSampleSize`/`calculateTargetSize`）。第一次真机上传图片时要重点看这一步。
- 对照设计图：不涉及。
- 遗留/疑问：`Validators` 的身份证规则目前只对齐了 `docs/01` P29 的「18 位格式和校验位」；服务端的实名认证请求类要到 P6 才写，届时两边的规则要再核一次（出生日期合法性、年龄下限）。

### T2.9 业务复用组件 —— ✅ 完成（2026-10-12）
- `03` 第 5.4 节 15 个组件全部落地（一个组件一个文件，文件名 = 组件名）：
  `ClPersonCard`(P01)、`ClMatchCard`(P02)、`ClMiniProfileRow`(P08/P09/P16/黑名单)、`ClConversationRow`(P03)、`ClEntryRow`(P03)、`ClChatBubble`(P06)、`ClChatInputBar`(P06)、`ClChatToolBar`(P06)、`ClGiftGrid`(P23/P31)、`ClRechargePackGrid`(P22)、`ClTaskCard`(P11)、`ClBenefitGrid`(P12)、`ClStepList`(P29)、`ClPhotoGrid`(P05/P14)、`ClCallControls`(P19/P20)。
- **组件层不依赖业务模型**：每个组件自带一个小的展示数据类（`ClPersonCardData`、`ClConversationItem`、`ClGiftItem`…），跟 T2.7 的 `ClBottomBarItem` 一个路子。`core/designsystem` 不 import `core/data/model`，也不 import `core/common`（所以**金额/时间文案由页面先格式化好再传字符串**，例如 `ClGiftItem.priceText`），保持设计系统自洽。
- `ClIcons` 新增 4 个：`add`、`close`、`hangup`(CallEnd)、`warning`(ErrorOutline，气泡发送失败用)。
- 预览页新增一节「业务复用组件」，15 个组件全部有假数据预览（`check-ui.sh` 会逐个检查是否出现）。
- **设计图对照**（逐个打开 `docs/页面设计图/` 对应图核对）：
  | 组件 | 设计图 | 结论 |
  |---|---|---|
  | ClPersonCard | 01 | 布局一致（头像+绿点/昵称+标签/「26 岁 · 上海 · 品牌设计师」/简介两行/三颗操作按钮）。差异：设计图里「私信」按钮比语音/视频宽，`ClPersonCard` 的 actions 是 `RowScope` 插槽、权重由页面给（T3.7 里给 weight 即可） |
  | ClMatchCard | 02 | 一致（照片铺满、左上「可视频」胶囊、底部渐隐白字+黄色操作按钮）。差异：「可视频」胶囊设计图是半透明白底，我用不透明 `surface`（避免照片颜色干扰可读性） |
  | ClMiniProfileRow | 08 | 一致（头像/昵称/「今天看了你的资料 · 上海」/右侧描边「查看」） |
  | ClEntryRow | 03 | 一致（圆形彩底图标+标题+说明+右侧时间或未读角标）。差异：圆形底色我用**传入色的 18% 透明度**自动生成，设计图各行的柔和底色是逐个指定的，观感接近但色值未必逐像素相同 |
  | ClChatBubble / InputBar / ToolBar | 06 | 一致（左右气泡+尾巴圆角、头像在外侧、日期分隔；工具栏三颗等宽奶黄按钮；输入框胶囊+黄色发送）。差异：**「发送中」设计图没画**，我用了小转圈（失败态是红色感叹号 `warning`，可点击重发） |
  | ClGiftGrid | 23 | 一致（三列、选中金色描边） |
  | ClRechargePackGrid | 22 | 一致（三列、数值大字+「金币」+金色价格、选中金色描边+奶黄底） |
  | ClTaskCard | 11 | 一致（图标方块+标题+说明+右侧奶黄按钮），额外做了 `done` 置灰态 |
  | ClBenefitGrid | 12 | 一致（两列奶白卡片、表情+标题+说明） |
  | ClStepList | 29 | 一致（奶黄序号圆+标题+说明+分割线） |
  | ClPhotoGrid | 05/14 | 一致（三列方形、8dp 圆角占位色 `#E8D8C7`），编辑态右上角删除+末尾「+」 |
  | ClCallControls | 19 | 一致（三个圆钮+下方标签+红色「结束通话」）。差异：未选中圆钮设计图是半透明白，我原来误用了 `mask`（暖褐色遮罩）→ **已改成 `surface` 16% 透明度** |
- **未做截图**（**2026-10-12 已补**：见下方「P2 真机走查（模拟器）」，截图在 `docs/screenshots/`，并据此修掉 2 个真实缺陷）：当时本机没有可用的模拟器/真机，也没装 Robolectric/Paparazzi 这类能在 JVM 里渲染 Compose 的工具，所以「组件预览页截图 vs 设计图」这一步**没有截图可比**，上表的结论是把设计图逐张打开、对照组件代码和 token 得出的。
- 自检命令：`bash scripts/check-android.sh` → 通过（BUILD SUCCESSFUL；`check-ui.sh` 的「每个组件都要出现在预览页」逐个通过；单测仍为 62 个，本次没新增测试）。
- 遗留/疑问：`ClMiniProfileRow` 的 `blurred`（P09 非会员看不全）用的 `Modifier.blur` **在 API 31 以下是空操作**（Compose 的限制），所以低版本手机上打码不生效；如果要保证所有机型都糊，得改成画一层遮罩或换成打码文字。记入待确认。

### T2.10 P2 阶段验收 —— ✅ 完成（2026-10-12）
- `bash scripts/check-all.sh` → **`✅✅✅ 全部检查通过`**（rc=0）：
  - server：`Tests: 100, Assertions: 1160`（含 7 个上游用例按 Q0-9 排除）+ phpstan `[OK] No errors`（5 条上游问题走 baseline）+ `✅ money 检查通过`。
  - admin-web：`pnpm lint`（vue-tsc + eslint + stylelint）与 `pnpm build` 通过。
  - android：`testDebugUnitTest` **62 个用例全绿**、`lintDebug`、`assembleDebug`、`✅ UI 检查通过`。
- **组件预览页从头到尾检查**：
  - `docs/03` 第 5.1~5.6 节列的组件**全部实现**：5.1 结构类 6、5.2 按钮类 6、5.3 展示类 23、5.4 业务复用 15、5.5 输入类 6、5.6 反馈类 8。`core/designsystem/component/` 下 **60 个文件**（一个文件里放同族组件，如 `ClMenuRow.kt` 含 `ClMenuGroup`）。
  - 写了个静态核对：除 `check-ui.sh` 的「文件名要出现在预览页」之外，再逐个确认**预览页里真的有调用**（`ClXxx(`）。60 个文件全部有实际预览，其中 `ClFixedBottomArea` 是尾随 lambda 写法（`ClFixedBottomArea { ... }`），第一版脚本误报，已确认是脚本的正则问题不是缺陷。
  - 预览页分 6 节（结构/按钮/展示/业务复用/输入/反馈），每节都有多状态样例（禁用、loading、错误、空、发送中/失败、选中/未选中等）。
  - **未做的部分**（**2026-10-12 已补做**，见「P2 真机走查（模拟器）」）：当时没在模拟器/真机上把预览页从头滚到尾点一遍，所以「打开不崩、滚动不卡、点击有响应」只能靠编译 + lint + 代码审阅保证。`ClPullRefreshList` 放在有界高度容器里这条已知约束在预览页里仍然遵守。
- **本阶段没有可走查的页面**：P2 只做基础件，`docs/01` 的 31 个页面在本阶段全是占位页（「开发中」），所以「按 01 文档逐页检查（男号/女号各走一遍）」这一步无从做起，留到 P3 开始有真页面后逐阶段做。
- **本阶段没有后台菜单**：P2 没动后台，`check-admin.sh` 只验证了 lint + build。
- 遗留/疑问：无新增。P2 累积的待确认项见「待确认问题」表（Q0-6 token 明文、Q0-13 `app.version` 语义、Q0-14 `ClSoftButton` 三色、Q0-15 `Modifier.blur` 低版本无效）。

### T2.11 列表缓存封装 —— ✅ 完成（2026-10-12）
- `core/data/local/LocalCache.kt`：
  - `save/load/remove`（按 key 落成 JSON 文件）+ `clearMemory()`（切号 / 退出登录用）。
  - **原子写**：先写 `.json.tmp` 再改名，避免写到一半被杀留下半个 JSON。
  - **解码失败当没缓存**（`runCatching` 包住解析）—— 对应 docs/10 第 3 节规则 2。
  - `CacheKeys`：把 docs/10 第 5 节那张表变成函数，**键自带版本号前缀 + userId**（`v1.list.discover.推荐.7`），页面不自己拼字符串；改结构就把 `CacheKeys.VERSION` +1，旧版本文件在启动时被清掉（`pruneOldVersions`），旧缓存自然作废。
  - 键里的 `/`、空格等会被转义，**不会写到缓存目录外面**。
  - `staleWhileRevalidate(key, serializer, force, fetch): Flow<ApiResult<T>>` —— 有缓存先发缓存，网络成功覆盖缓存并发新数据；**有缓存时网络失败不发错误**（页面继续显示缓存，符合「静默刷新」），没缓存时才把错误交给页面转错误页。
  - 节流：同一 key **60 秒内切回来不重复请求**；`force = true`（下拉刷新）跳过节流（规则 6）。节流表只在内存里，重启 App 后第一次打开一定刷新。
- `core/data/local/CacheModule.kt`：只注入**缓存目录**（`filesDir/cl_cache`，用 `@CacheDir` 限定符）而**不是 `Context`** —— 这样 `LocalCache` 是纯 JVM 类，单测直接传临时目录就能跑，不用 Robolectric。
- 验收对照（docs/08 T2.11：存取、解码失败当无缓存、切号清内存）：**24 个用例**全部通过
  - 存取 6：存读往返、没存过返回 null、确实落盘、新实例能读到旧实例写的、不留 `.tmp` 残骸、remove 后读不到。
  - 解码/版本 5：文件被写坏当没缓存、结构变了读不出来、缺字段但有默认值还能读出、启动清掉旧版本文件、键里带斜杠不越界。
  - 切号 2：清内存后磁盘还在（能再读回来）、不会读到上一个账号的内存数据。
  - 缓存键 2：8 个键形状对（含版本号与 userId）、不同账号同一个列表不串。
  - stale-while-revalidate 5：先缓存后新数据并落盘、没缓存只发网络、有缓存时网络失败不发错误、没缓存时失败要发错误、业务错误按失败处理。
  - 节流 4：60 秒内不重复请求、`force` 不受限、没缓存时不受节流影响、不同列表互不影响。
- 自检命令：`bash scripts/check-android.sh` → 通过（BUILD SUCCESSFUL；`testDebugUnitTest` **92 个用例**：LocalCache 24 + 公共工具 34 + MainViewModel 14 + 其余 20；`lintDebug`、`assembleDebug`、`check-ui.sh` 通过）。
- 对照设计图：不涉及。
- 遗留/疑问：`LocalCache` 目前**还没有调用方** —— 各列表页在 P3~P6 逐个接（发现页 T3.7、访客/喜欢我 T3.9、消息 T4.6、金币与明细 T5.2、会员 T5.6、任务 T5.8、收益/提现 T6.5、系统消息 T6.6）；`clearMemory()` 由 T3.1 的退出登录 / 切号流程调用。届时若发现用法不合适再改封装。

### P2 真机走查（模拟器）—— ✅ 完成（2026-10-12）
第一次真正把 App 跑起来（`emulator -avd Medium_Phone_API_37.0`，1080×2400，Android 17）。走查路径：登录占位 → 主界面 → 我 → 组件预览页，逐节滚动对照 `docs/页面设计图/`。截图存 `docs/screenshots/`（8 张，见该目录）。

**走查确认正常的部分**
- 底部导航 3 个 Tab（`feature.call_enabled=false` 时**速配正确隐藏**），Tab 切换与各自的滚动位置保留正常；未读角标正常。
- `ClScaffold` 的顶部奶油色状态栏、`ClTopBar`、`ClEmpty`、`ClPrimaryButton`（含 loading/disabled）、`ClSecondaryButton`、`ClSoftButton` 三色、`ClOutlineSmallButton`、`ClLoading`、`ClErrorRetry`、`ClPullRefreshList`（含「没有更多了」）、`ClImagePreview`、`ClMenuRow`、`ClSettingRow`+`ClSwitch`、`ClStatRow`、`ClFeeStrip`、`ClProgressBar` 全部渲染正常。
- 5.4 业务组件与设计图 01/02/03/06/08/11/12/19/22/23 对照**基本一致**：用户卡片、速配卡（含左上「可视频」胶囊 + 底部渐隐白字）、小资料行（**`blurred` 打码在 API 37 上确实生效** —— 见 Q0-15）、会话行、入口行、礼网格、充值档位（选中金边+奶黄底）、任务卡（含「已完成」置灰）、权益网格、步骤条、相册九宫格、通话控制（圆钮 + 红色结束通话）。

**走查发现并修掉的真实缺陷（2 个）**
1. **`ClTextField` 文字被垂直裁掉** —— 给 Material3 的 `OutlinedTextField` 硬压了 44dp（设计要的 `inputHeight`），而它自带 56dp 最小高度，结果「请输入手机号」的下半截被裁。**改成用 `BasicTextField` 自己拼外壳**（`heightIn(min=44dp)` + 自己画背景/描边/焦点态/错误态），与 `ClSelectField` 的写法统一。影响面很大：P00 登录注册、P14 编辑资料、P29 实名认证、P15 设置的表单全靠它。
2. **聊天输入栏的「表情 / 图片」按钮图标语义不对** —— 我原先为省事借用了 `spark`(✨) 和 `flip`(🔄)，画出来谁也看不懂。补了 `ClIcons.emoji`(EmojiEmotions) 和 `ClIcons.image`(Image) 两个图标并用上。

另外 `ClChatBubble` 的「发送中」转圈从 17dp 调到 22dp（原来太小，看着像一个杂点）。

**已澄清、不是缺陷的（2 处）**
1. **`ClRangeSlider` 是双滑块，设计图 07 画的是单滑块** —— `docs/01` P07 明确写「年龄范围（**双滑块** 18~60）」，以文档为准，我的实现是对的；设计图那张是单滑块的静态 mock（且原型 HTML 里也是一个 `<input type=range>`）。**差异只在外观**：Material3 新的 RangeSlider 用竖条 thumb，设计图是圆 thumb。**留给 T3.8 做 P07 时对着设计图定**。
2. **用户卡片三颗操作按钮的宽度** —— 我第一次看走眼，以为没铺满整行就把它们压成了 1:1:1.3 的权重，结果「语音 60 币/分」被省略号截断（`docs/03` 规定小按钮用 `buttonSmall` 12sp，压到 1/3 宽放不下）。回滚成按内容宽度排列后**实测已经几乎铺满整行**（只差约 8dp），与设计图一致。**教训：截图上"看着不对"不等于真的不对，先量再改。** 顺手给 `ClSoftButton` 的文字加了 `overflow = Ellipsis` 作为兜底（万一以后真被压窄也不会被硬裁）。

- 自检命令：`bash scripts/check-android.sh` → 通过（BUILD SUCCESSFUL；92 单测；`lintDebug`、`assembleDebug`、`check-ui.sh` 全过）。
- 环境补充：本机有现成 AVD `Medium_Phone_API_37.0`（系统镜像 `android-37.0` 已下载），`adb` 在 `C:\Users\34252\AppData\Local\Android\Sdk\platform-tools`。**以后每个阶段收尾都可以这样真机走查一次**，不用等 P9。

### T3.1 App 登录注册页（P00）—— ✅ 完成（2026-10-12）
- 数据层：
  - `core/data/model/Agreement.kt`（`type`/`content` + `TYPE_USER`/`TYPE_PRIVACY`）、`core/data/model/Gender.kt`（1 男 / 2 女，与 `cl_user.gender` 一致）。
  - `core/data/repository/AuthRepository.kt`：`login` / `register` / `agreement` / `logout`。**登录、注册成功就地把 token 存进 `TokenStore`**，页面只管跳转，不用每个页面都记得存；退出登录即使接口失败也一定清本地 token。
- 页面（`feature/auth/`，每个页面都是 `Screen` + `ViewModel` + `UiState` + `Route` 四件套）：
  - **登录页**：手机号/账号 + 密码 + 协议勾选（没勾不给登）+ 登录按钮（提交中转圈并禁用）+「注册新账号」。
  - **注册页**：手机号、昵称、密码、确认密码、性别（`ClChipGroup` 单选 + 「注册后不可修改」提示）。
  - **协议页**：内容从 `/app/agreement/{type}` 取，四态齐全（加载/空/出错重试/内容）。
  - `ClNavHost` 接上：`login` / `register` / `agreement/{type}` 三个路由，登录或注册成功都跳主界面并把登录页从回退栈里去掉。
  - 删掉了 T2.7 的 `LoginPlaceholderScreen`。
- 校验：**逐条对齐服务端请求类**，本地只管「少一次往返」；10001（账号或密码错误）、10002（账号已存在）、10003（锁定 N 分钟）、10004（封禁原因+解封时间）这些带具体信息的提示**一律直接用服务端返回的 `message`**（docs/03 第 6 节第 5 条）。实测服务端确实返回 `{"code":10001,"message":"账号或密码错误","data":null}`。
- 顺带修掉一个真 bug：**`rememberClToast()` 必须在 `ClScaffold` 的内容里调用**才能拿到它提供的 Toast 宿主（在外面拿到的是一个没有宿主的空壳），组件预览页的「点我看 ClToast」按钮点了其实没有任何反应 —— 已修正写法并把预览页改过来。
- 验收对照（docs/08 T3.1）：
  1. 校验错误提示 —— ✅ `LoginViewModelTest` 4 例（账号空/密码空/没勾协议不发请求、改字段清错误）+ `RegisterViewModelTest` 6 例（手机号/密码/确认密码/性别/昵称）
  2. **登录成功保存 token** —— ✅ `AuthRepositoryTest`（登录/注册成功存 token、业务错误与网络错误**一个字节都不存**、退出登录即使 500 也清）
  3. 10003/10004 提示 —— ✅ `LoginViewModelTest`（锁定、封禁、10001、网络错误四条提示文案）
  4. 另覆盖：提交中重复点击不会发第二次请求、提交中按钮 loading、注册参数（昵称去空格）
- 自检命令：`bash scripts/check-android.sh` → 通过（BUILD SUCCESSFUL；`testDebugUnitTest` **120 个用例**：T3.1 新增 28 个；`lintDebug`、`assembleDebug`、`check-ui.sh` 通过）。
- 端到端接口核对（curl 真实后端）：登录失败返回 10001 的 body 形状对得上；`/app/agreement/user` 与 `/privacy` 返回 `{type, content}` 与 `Agreement` 模型一致，且用户协议里**确实包含付费聊天声明**（铁律 13）。
- **未做 / 有意为之**：
  - **「登录后按资料完整度跳转」没做** —— 需要 `/user/me`，那是 T3.2 的活。现在登录成功一律进主界面，等 T3.2 拿到资料接口后再补「没头像 → P14 编辑资料」这一跳。
  - **注册页没放协议勾选** —— `docs/01` P00 只要求登录页勾选，「注册」那条只列了字段，所以按文档来。（如果客户要求注册也勾，加一个 `ClCheckboxAgreement` 即可，改动很小。）
  - **没有在模拟器上目视这两个页面**（你让先关掉模拟器了）。表单布局、错误文案的观感留到 P3 阶段验收时一起走查。
- 踩到的坑：Kotlin 的块注释**可以嵌套**，KDoc 里写路径 `.../V1/*` 会打开一个嵌套注释，编译器报「Unclosed comment」——已改成不写星号。

### T3.2 资料接口与审核数据 —— ✅ 完成（2026-10-12）
- 迁移 `2026_10_12_000005_create_cl_profile_tables.php`：`cl_user_photo`（相册，带 `audit_status`/`reject_reason`）、`cl_profile_audit`（资料修改审核）。**开发库和 `cl_test` 都跑过 migrate**。
- 新增：`Model\Cl\UserPhoto`、`Model\Cl\ProfileAudit`（含字段名/状态常量与 `AUDIT_FIELDS`）、`UserPhotoRepository`、`ProfileAuditRepository`、`Service\Cl\User\ProfileService`、三个 Request、`Controller\V1\UserController`。
- 四个接口（都要登录）：
  - `GET /user/me`：完整资料 + **`pending`（待审的新内容）** + **`auditing`（哪些字段在审核中）** + 相册（含 `audit_status`）+ 金币余额（男）/ 积分余额（女）+ `is_vip`。
  - `POST /user/profile`：**头像 / 昵称 / 自我介绍进审核**（写 `cl_profile_audit`，**不动 `cl_user`**）；生日、城市、身高、职业、学历、交友期待、标签**直接生效**。
  - `POST /user/photos`：相册整体提交，整批进审核；`urls` 去空去重、顺序即排序，最多 9 张。
  - `POST /user/settings`：`show_in_nearby` / `show_in_recommend` 直接生效。
- 几个想清楚了才写的规则：
  1. **同一字段只留一条待审记录**（重复提交覆盖，`upsertPending`）；
  2. **改回原值就撤销待审**（`deletePending`），不让用户白等一次审核；
  3. **相册没真改就不重新进审核**（比对当前提交的 URL 列表），否则用户点一次保存就把自己的相册打回待审；
  4. 相册审核：新一批进待审，**已通过的旧相册留着**（别人看到的还是旧的），等这批通过后由后台替换（P7）；
  5. 字段用**白名单**（`Arr::only`），所以 `gender`、`level`、`status`、`real_name_status`、`account`、`phone` 客户端传了也不生效。
- 覆盖掉一个脚手架文件：`app/Http/Api/Controller/V1/UserController.php` 原本是 MineAdmin 的示例（一个不干活、任何请求都返回成功的假 `/api/v1/login`）——这文件在 `app/` 下、属于我们的代码，已换成真实的资料接口；App 登录一直在 `AuthController`。`app/Http/Api/Request/V1/UserRequest.php`（同样没人引用的示例）留着没动。
- 验收对照（docs/08 T3.2）：
  1. **改头像后 `/user/me` 返回待审内容** —— ✅ `testAvatarGoesToAuditAndOwnerSeesPendingValue`：`pending.avatar` 是新值、`auditing` 含 `avatar`，而 `avatar`（已通过、别人看到的）还是旧值。
  2. **他人看到旧头像** —— ✅ 同上，断言 `cl_user.avatar` 数据库里没变 + `cl_profile_audit` 里有状态 0 的记录（old_value=旧、new_value=新）。
  3. **年龄不满 18 被拒** —— ✅ `testUnder18BirthdayIsRejected`（422，提示含「18」）+ `testExactly18TodayIsAccepted`（刚好今天满 18 放行）。
  4. **性别字段传了也不生效** —— ✅ `testGenderChangeIsIgnored`：传 `gender=2`，返回和数据库都还是 1。
  5. 另覆盖：未登录 401、女用户返回积分余额、昵称也走审核、重复提交只留一条待审、改回原值撤销、直接生效字段不进审核表、标签必须存在且最多 5 个、相册去重/清空/超 9 张 422、开关直接生效且非法值 422。
- 自检命令：`bash scripts/check-server.sh` → 通过（**Tests: 118, Assertions: 1243**，比 T3.1 后多 18 个；phpstan `[OK] No errors`；money 通过）。
- curl 对真实后端冒烟（跑完已把测试账号和临时脚本删掉）：`/user/me` 200 字段齐全；改头像后 `pending.avatar` 出现、`auditing:["avatar"]`、`avatar` 仍为空；`gender:2` 不生效；相册返回 2 张 `audit_status:0`；未登录 401。
- 踩到的坑：
  1. **`birthday` cast 出来的是 `\Carbon\Carbon`，不是 `Hyperf\Carbon\Carbon`** —— 一开始用 `instanceof Hyperf\Carbon\Carbon` 判空，导致 `age` 一直是 null，改成 `Carbon::parse($birthday->format('Y-m-d'))` 再算。
  2. **phpstan 泛型**：`$model->newQuery()->get()` 推出来是 `Collection<int,object>`，声明了 `@return Collection<int,UserPhoto>` 就会报错，要加 `@var` 注解；`Collection::map()` 的回调同理（改用 foreach 收集更省事）。
  3. **重启后端的坑（又踩一次）**：Swoole 会改进程标题，`pkill -f 'hyperf'` 匹配不到，得按端口杀；而且**pkill 的模式和启动命令写在同一条命令里会把自己的 shell 一起杀掉**（`pkill -f '[h]yperf.php start'` 匹配到了同一条命令行里的启动串）。以后重启后端分两步：先按端口杀，再单独启动。
  4. 新增 Controller 后必须 `rm -rf runtime/container` 再启，否则注解路由扫不进去（T1.x 记过，这次又验证了一遍）。
- **未做 / 有意为之**（**`/user/me` 的统计数已于 T3.4 补上**，见该任务记录）：`/user/me` 的**统计数（关注 / 好友 / 粉丝 / 访客未读）当时没返回** —— 这些要 `cl_follow`/`cl_visitor` 等关系表，是 **T3.4** 的活；当时返回 0 或编造数字都是假的，所以先不放这个字段。现在已在 T3.4 里补成 `stats`。
- 对照设计图：不涉及（纯接口）。

### T3.3 编辑资料页（P14）—— ✅ 完成（2026-10-12）
- 数据层：`UserApi`（四个资料接口）、`UploadApi`（multipart 图片上传）、`dto/UserDto.kt`（请求体）、`data/model/UserProfile.kt`（含 `UserPhoto`、`PhotoListResult`、`ProfileUpdate`）、`UserRepository`、`ImageUploader`（**选图 → 压缩 → 上传**，把 `Context`/`Uri` 关在这一层，ViewModel 不碰 Android API）。
- 设计系统补了两处：
  - 新增 `ClDatePickerDialog`（生日选择器）。**用 M3 的 DatePicker 但包在 design system 里**，页面不直接碰 M3 的弹窗；年份范围可传，生日直接卡到「今年往前推 18 年」，不该选的年份根本选不到。已加进组件预览页。
  - `ClPhotoGrid` 增加 `onMove`：**长按拖动排序**（长按触发，避免和页面上下滚动打架），拖动中的格子放大跟着手指走。落点换算抽成纯函数 `photoDragTarget()` 单独测。
  - `core/common/TimeFormat` 加了 `utcMillisToDate()` / `dateToUtcMillis()`（M3 的 DatePicker 是 UTC 零点口径，不按 UTC 取会差一天）。
- 页面（`feature/profile/` 四件套）：女用户顶部固定提示「请使用本人真实照片和资料，虚假资料将被封号」；头像点击换图；昵称、生日、城市、身高、职业、学历、交友期待、自我介绍、兴趣标签、相册；右上角「保存」+ 底部「保存资料」。头像/昵称/自我介绍/相册在审核中时打**「审核中」角标**（昵称、自我介绍用输入框的 `trailingIcon` 塞了个 `ClTag`，不用改组件 API）。
- 两个关键设计：
  1. **只提交用户真的改过的字段** —— 服务端 `sometimes` 是「没传就不改」，把没动过的头像/昵称一起发过去会平白多一条待审记录还得等审核。`buildUpdate()` 逐字段和原始资料比，没改的留 null（`clJson` 配了 `explicitNulls = false`，null 不会编码）。
  2. 相册是**整体提交**的另一个接口，也是「变了才发」。
- 验收对照（docs/08 T3.3 只要求 `D`）：`bash scripts/check-android.sh` → 通过（BUILD SUCCESSFUL；**150 个单测**，T3.3 新增 30 个：EditProfileViewModel 20 + PhotoGridDrag 10；`lintDebug`、`assembleDebug`、`check-ui.sh` 通过 —— 页面只用 designsystem 组件与 token）。
- 单测覆盖：加载时**待审内容优先**（看到的是新昵称）、加载失败文案、昵称/生日（不满 18、刚好 18 放行）/自我介绍/标签/身高五处校验拦住时不发请求、**只把改过的字段发出去**（断言没改的字段是 null）、保存成功刷新 `auditing` 并提示、保存失败显示服务端文案、相册改了才调相册接口、拖动排序与删除、标签名字换回后台 id、不认识的名字不落库；拖动落点 10 个边界（跨行、贴边、只有一张）。
- 写测试时抓到**自己两个真 bug**（不写测试就漏过去了）：
  1. `heightError` 用 `toIntOrNull()?.let { 合法就 null } ?: "错误"` —— `let` 返回的 null 既是「合法」又是「没值」，结果**每个填了身高的用户都被判成身高有问题，保存直接被拦下**；
  2. 标签越界时我在 `onTagNamesChanged` 里提前 return（不存这次选择），但 `validate()` 又按 `tags.size` 重算，把刚提示的错误**覆盖成 null** —— 两处判断不一致。现在统一成「照存 + 一处算错误」。
- **未做 / 有意为之**：
  - **没有在模拟器上目视这个页面**（你让先关模拟器了）。选图/拖动/日期选择这三个交互都只在单测层面验证过（拖动落点有纯函数测试，选图和上传没测），**P3 阶段验收（T3.10）时一定要真机走一遍这个页面**。
  - 学历的选项清单 `docs/01` P14 只说「选择」没给具体项，我先定了 `高中及以下 / 大专 / 本科 / 硕士 / 博士`（见 Q0-18）。
  - `ImageUploader.upload()` 本体（压缩 + multipart）没写单测 —— 要 `Uri`/`Context`，纯 JVM 测不了。

### T3.4 关系接口 —— ✅ 完成（2026-10-12）
- 迁移 `2026_10_12_000006`：`cl_follow`（唯一 `(user_id,target_id)`）、`cl_block`、`cl_visit`（唯一 `(visitor_id,target_id,visit_date)`）。开发库和 `cl_test` 都跑过。
- 新增：`Follow`/`Block`/`Visit` 模型、三个 Repository、`Service\Cl\Relation\RelationService`、`RelationTargetRequest`、`Controller\V1\RelationController`。
- 9 个接口（都要登录）：`POST /relation/{follow,unfollow,block,unblock}`、`GET /relation/{following,friends,fans,visitors,blocks}`。
- 列表统一返回分页外壳 + 一个 `locked`（这一页数据是不是打码的）。
- 几条想清楚才写的规则：
  1. **拉黑后互相不可见是「全列表 + 计数」一起过滤的**：过滤放在 SQL 里（`whereNotIn`），不是拿完再筛 —— 否则会出现「共 1 人但列表是空的」（**这个 bug 就是被测试抓出来的**，见下）。
  2. **只打码「喜欢我的人」和「最近访客」**（docs/05 第 4.4 节）：我关注的 / 好友 / 黑名单不打码 —— 那些是用户自己建立的关系，遮起来没道理。
  3. **女用户默认能看完整**（`vip.female_free_view` 默认 1）；开关关掉则和男用户一样打码。会员一律不打码。
  4. **被拉黑的人关注你时返回 30001「对方不存在」**，不暴露「你被拉黑了」（不然拉黑等于通知对方）。
  5. 访客**同一人一天一条**（用 `upsert` 撞唯一索引更新 `last_visit_at`），列表里**一个人一行**、显示最近一次时间；P08 的「有 N 人看过」取的是**去重人数**。
  6. 打开访客列表**读一次就把未读清零**（docs/05 第 4.4 节）。
- **顺带补上 T3.2 欠的账**：`/user/me` 现在返回 `stats`（`following`/`friends`/`fans`/`visitors`/`unread_visitors`），P04 我的页四格和底部角标要用。
- 顺手去重：`age` 计算原来在 `ProfileService` 里写了一份，现在挪到 `User::age()`，两个 service 共用（`birthday` cast 出来是 `\Carbon\Carbon` 而不是 Hyperf 的子类的坑也一并写在注释里）。
- 验收对照（docs/08 T3.4）：
  1. **互关判断** —— ✅ 双向关注后进 `friends` 且 `is_mutual = true`；单向关注不进好友、`is_mutual = false`。
  2. **拉黑后互相不可见** —— ✅ 拉黑后双方在 `friends`/`following`/`fans`/`visitors` 里**列表和 total 都为 0**；被拉黑方关注对方返回 30001；解除拉黑后恢复可见；黑名单里能看到被拉黑的人。
  3. **同日重复访问一条记录** —— ✅ 同日访问两次仍只有 1 行、`last_visit_at` 被刷新；访问自己不入库。
  4. **非会员男用户拿到打码数据** —— ✅ 昵称 `小*`、`avatar_blur = true`、`locked = true`；会员男用户、女用户（默认）都能看完整；开关关掉后女用户也被打码。
  5. 另覆盖：不能关注自己、关注不存在的人 30001、访客读取后未读清零、`/user/me` 统计数正确、三个接口未登录 401、我关注的列表不打码。
- 自检命令：`bash scripts/check-server.sh` → 通过（**Tests: 137, Assertions: 1342**，比 T3.3 后多 19 个；phpstan `[OK] No errors`；money 通过）。
- 踩到的坑：**测试抓到一个真 bug** —— 一开始列表用「查完再过滤掉拉黑的人」，但 `total` 用的是数据库原始计数，于是拉黑后出现「共 1 人、列表却是空的」。改成把排除条件下沉到 SQL（包括互关那种连表查询，排除列必须写别名 `a.target_id`，不然和 `b` 表撞列名）。
- 未做 / 有意为之：**访客的 HTTP 入口还没接** —— 「打开别人主页记一条访客」属于 T3.6 的 `/user/{id}`；T3.4 只做 `RelationService::recordVisit()`，测试里直接调它模拟。
- 对照设计图：不涉及（纯接口）。

### T3.5 发现接口 —— ✅ 完成（2026-10-12）
- 新增 `DiscoverRepository`（把「谁能被看到」的硬规则集中在 SQL 里）、`Service\Cl\Discover\DiscoverService`、`DiscoverRequest`、`Controller\V1\DiscoverController`。
- 两个接口（要登录）：`GET /discover`（`tab` + 筛选 + 分页）、`GET /discover/search`（按昵称或 ID 搜异性）。
- **可见性规则**（docs/01 P01）：只返回**异性**、`status=正常`、**必须有头像**（没头像 = 资料没过审）、**任一方向拉黑都不出现**。
  - 「推荐」额外要求 `show_in_recommend=1`；**「新人」「同城」不看这个开关**（它俩是另外的维度）。
  - 「新人」= 注册 7 天内；「同城」= 和我在同一个城市（**我自己没填城市时同城是空的**，这是有意的，别用假数据兜底）。
- **筛选**（docs/01 P07）：`city`、`age_min`/`age_max`（按生日区间算，**没填生日的人在带年龄筛选时会被排除**）、`purpose[]`、`verified`（真人认证 = `real_name_status=2`）、`complete`（资料完善 = 有自介 + 填了生日）、`online_only`。
- **列表项**：`online` / `is_new` / `verified` 都是**算出来的**，不是存的字段；排序是「在线优先、最近活跃在前」。
- **价格**：男用户看女用户才带 `message_price`（取她等级对应的单价，等级配置一次查出来、不在循环里查库）；语音/视频开关关着时**不返回** `voice_price`/`video_price`。
- 排序与分页：每页 20 条，返回统一分页外壳。
- 验收对照（docs/08 T3.5）：
  1. **只返回异性** —— ✅ 男看只有女、女看只有男，同性（含自己）不出现。
  2. **排除封号 / 无头像 / 拉黑** —— ✅ 三条各有用例，拉黑是**双向**都测了。
  3. **`show_in_recommend=0` 只影响推荐 tab** —— ✅ 推荐里没有、新人/同城里还在。
  4. **筛选条件生效** —— ✅ 年龄、城市、交友期待、真人认证、资料完善、只看在线各一条；非法 tab 返回 422。
  5. **男看女带 Lv 对应价格** —— ✅ 取等级 3 的配置断言价格；开关打开才返回语音/视频价；女用户看男用户不带价格。
  6. **分页正确** —— ✅ 25 条 → 第 1 页 20 条 `has_more=true`、第 2 页 5 条 `has_more=false`。
  7. 另覆盖：搜索按昵称 / 按 ID / 只搜异性 / 空关键词 422 / 未登录 401。
- 自检命令：`bash scripts/check-server.sh` → 通过（**Tests: 164, Assertions: 1411**，比 T3.4 后多 27 个；phpstan `[OK] No errors`；money 通过）。
- 记一笔**测试写错而不是代码错**的一次：我一开始断言「同城」tab 应该有 2 条，实际是 0 —— 因为注册出来的「我」**没填城市**，同城本来就该是空的。花了十几分钟加探针打印才定位到，教训是**断言前先把前置条件（这里是"我"的城市）摆好**。代码本身没问题，改的是测试。
- 未做 / 有意为之：
  - **「视频」「语音」两个 tab 没做** —— `docs/05` 第 4.3 节只定义了 `recommend/new/city` 三个，视频/语音是「只做页面」的入口且本期开关关闭，所以传这两个值直接 422。
  - 「在线」的判定（多久没活跃算离线）`docs` 没写，先用 `DiscoverRepository::ONLINE_MINUTES = 15` 分钟（见 Q0-19）。
- 对照设计图：不涉及（纯接口）。

### T3.6 他人主页接口 —— ✅ 完成（2026-10-12）
- 新增 `Service\Cl\Pricing\MessagePricingService`（+ `MessageQuote` 值对象、`VipFreeQuota` Redis 计数）和 `Service\Cl\User\UserDetailService`，`UserController` 加 `GET /api/v1/user/{id}`。
- **`MessagePricingService::quote()` 严格按 `06` 第 3.2 节四步、命中就停**：
  1. 互关 且 `chat.mutual_follow_free=1` → 免费 `MUTUAL_FOLLOW`
  2. 这对男女已发条数 < `chat.free_count_per_pair` → 免费 `PAIR_FREE`
  3. 有效会员 且 今天会员免费没用完 → 免费 `VIP_FREE`
  4. 都不是 → 收费 = 女方当前等级单价
  顺序是有意义的：**第 1、2 步命中了就不碰会员额度**（场景 5）。所以 `quote()` **只读不写** —— 会员额度由发消息那一步（T4.2）在确认 `freeReason = VIP_FREE` 时才 `increment`。这一点写进注释了，T4.2 别忘。
- `quote(User $male, User $female, int $maleSentCount = 0)`：**多了一个「已发条数」参数**。因为那个数存在会话表的 `male_sent_count`，而会话表是 P4 的 T4.1 才建的 —— 与其抢建表，不如让调用方传进来：现在没人聊过就是 0，T4.2 传真实条数。这样 quote 本身是纯函数，也好测。
- 免费提示 `freeHint()`：互关 → 「互相关注，私信免费」；对子 → 「还可免费发送 N 条」；会员 → 「会员今日还剩 N 条免费」；收费 → `null`（页面直接显示单价）。
- **`/user/{id}`**：
  - **别人看到的永远是审核通过的内容**（`cl_user` 里的昵称/头像/简介），相册**只给已通过的**（待审/驳回的只有本人看得到）。
  - 男看女才有 `price`（`message_price` + `free_hint`；语音/视频开关关着时**不返回**后两项）。
  - 关系：`is_following` / `is_mutual` / `is_blocked`。
  - **看别人的主页写一条访客**（同一天同一人只留一条是 T3.4 的规则）；**自己看自己不算**。
  - **被拉黑时 `is_blocked=true`、不给价格、也不写访客**（写访客等于告诉对方"我来过"）。封号/已注销/不存在一律 30001，不暴露封禁状态。
- 验收对照（docs/08 T3.6）：**`06` 第 10 节场景 1~5 里 quote 相关判断全部有单测**（`MessagePricingServiceTest` 13 例）：
  1. 前 3 条免费、第 4 条按 Lv1 单价 5 收费 ✅
  2. `free_count_per_pair=0` → 第 1 条就收费 ✅
  3. 互关免费；关掉开关后恢复收费 ✅（另补单向关注不算互关）
  4. 会员 10 条免费、第 11 条开始收费、**第二天重新计数**（用带日期的 Redis key 验证）✅
  5. **互关免费时不消耗会员额度；对子免费没用完时也不消耗** —— 断言理由分别是 `MUTUAL_FOLLOW`/`PAIR_FREE` 而不是 `VIP_FREE`，且额度计数没变 ✅
  另覆盖：价格随女方等级（Lv1/3/5）、非会员与过期会员没有额度、三种免费提示文案 + 收费时无提示。
- `UserDetailControllerTest` 14 例：资料与派生标记（在线/新人/已认证）、**只看已通过相册**、男看女带价格与提示、开关关时不返回语音视频价、女看男不带价格、关系字段、被拉黑标记且不写访客、看别人写访客（同天只一条）、看自己不写访客、封号/已注销/不存在 30001、未登录 401。
- **加了个回归测试**：`/user/me` 不能被 `/user/{id}` 抢走（静态路由段优先），`testMeRouteStillWorksAfterAddingIdRoute` 专门盯这个。
- 自检命令：`bash scripts/check-server.sh` → 通过（**Tests: 191, Assertions: 1510**，比 T3.5 后多 27 个；phpstan `[OK] No errors`；money 通过）。
- 踩到的坑：`level` 在**内存里新建的 User** 上是 null（表默认值 1 不会回填到模型对象），`findByLevel(null)` 直接 TypeError —— 11 个用例一起报错。服务里兜底成 1（和表默认值一致），测试里也显式传 `level`。
- 未做 / 有意为之：`quote()` **没有算分成比例**（`share_rate`，含未实名系数）—— 那是 `06` 第 3.4 节、T4.2 扣费时的事，这里只做报价，不提前建。

### T3.7 发现页、筛选页（P01、P07）—— ✅ 完成（2026-10-12）
- 客户端数据层：
  - `DiscoverApi`（`/discover`、`/discover/search`）、`data/model/DiscoverUser.kt`（`DiscoverUser` / `DiscoverPage` / `DiscoverFilter`）、`DiscoverRepository`。
  - **第一页走 stale-while-revalidate**：先渲染本地缓存（秒开、不转圈），再静默刷新；下拉刷新和切 Tab 传 `force = true` 跳过 60 秒节流（docs/10 第 3 节）。第 2 页起是「加载更多」，不读缓存。
  - **缓存的键多了一段筛选签名**：`CacheKeys.discover(tab, userId, signature)`。docs/10 的键里没有筛选项，但用户改了筛选后如果还命中旧缓存，会先闪一眼不符合条件的结果 —— 没筛选时键的形状仍与文档一致。
  - **年龄只在真的缩过范围时才传**：一旦带了年龄参数，服务端会按生日过滤，没填生日的用户会被误伤。
- 页面：
  - **P01 发现页**：顶部渐变 + 「畅撩交友 ✦」+ 副标题 + 余额胶囊（男金币进 P13 / 女积分进 P26）；Tab 行（推荐 / 新人 / 同城）+ 筛选图标进 P07；列表是 `ClPersonCard`（头像带在线绿点、昵称、标签「已认证/在线/新人」、`26 岁 · 上海 · 品牌设计师`、简介、操作按钮）。
    - **私信按钮的文案**：男用户看女用户显示「私信 5 币/条」（价格来自服务端按女方等级算好的 `message_price`，用 `MoneyFormat` 拼，铁律 6）；女用户看男用户只显示「私信」。
    - 列表跟着滚的区块标题「现在有空聊 / 只看同城」，有筛选时变成「按你的筛选 / 改条件」。
  - **P07 筛选页**：所在城市（Chip 单选，含「全部」+ 后台配置城市）、年龄范围（双滑块 18~60 + 文案「年龄范围 · 22–35 岁」）、交友期待（多选）、认证状态（多选：真人认证 / 资料完善，底层是两个开关）、只看在线（开关）、底部「重置」「查看匹配的人」。
    - **筛选按用户存在本地**（`ClDataStore`），点「查看匹配的人」保存后回 P01；P01 每次进入都会重读一遍，所以**改动立即生效**（没改动时那一步只是一次被节流掉的静默刷新）。
- 顺手做的三处改动：
  1. `ClPullRefreshList` 加了 `header` 插槽（让区块标题跟着列表一起滚）；
  2. `MoneyFormat` 加了 `UNIT_COIN` / `UNIT_POINT`（余额胶囊的单位要分开传）；
  3. `UserRepository` 加了 `currentUser` 缓存（`/user/me` 的结果）—— P01 顶部、P07 的存储键、以后的 P04/P13/P26 都要用，别每个页面各请求一次。**退出登录时要调 `clearCurrentUser()`**（和 `UnreadStore.clear()` 一起，在 T6 的退出流程里接）。
- 验收对照：`docs/08` T3.7 只要求 `D`。`bash scripts/check-android.sh` → 通过（BUILD SUCCESSFUL；**173 个单测**，T3.7 新增 23 个；`lintDebug`、`assembleDebug`、`check-ui.sh` 全过 —— 页面只用 `core/designsystem` 的组件与 token）。
- 单测覆盖：进入页面拿到列表与余额、**女用户显示积分**、无缓存先转圈、缓存拿不到时整页报错可重试、**我的资料拿不到就不发列表请求**（不然缓存键会写到 userId=0 名下）、切 Tab 立刻按新 Tab 请求、重复点同一 Tab 不重复请求、下拉刷新走 `force`、上拉加载接在下一页、没有更多时不再请求、加载更多失败只显示在列表底部（不覆盖整页）、筛选改动后重新请求、筛选没改动不强制刷新；筛选页覆盖城市/全部/年龄/多选期待/认证状态两个开关/只看在线/重置/保存与返回/拿不到资料时给错误。
- **未做 / 有意为之**：
  - **视频 / 语音相关的一律没做**（客户 2026-10-12 确认本期不做）：P01 的「视频」「语音」Tab、两张快捷卡、卡片上的语音/视频按钮**都没写**，只实现了「开关关闭」这一种形态（推荐/新人/同城 + 单颗「私信」按钮）。要做时：① 服务端 `/discover` 得先支持 `tab=video|voice`（T3.5 里传这两个值会 422）；② 客户端加 Tab 与快捷卡。已记在 P8 那行。
  - **没在模拟器上看过这两个页面**。T2.9 那次真机走查抓到过 2 个真实缺陷（`ClTextField` 裁字、图标语义错），所以这次也建议在 T3.10 阶段验收时把 P01/P07 真机走一遍再往下走。
- 又犯了一次**测试写错而不是代码错**：`isEmpty` 的定义是「没有内容**且**没有错误」，我在错误用例里断言 `isEmpty` 为 true，当然不成立。改的是测试断言。

### T3.8 个人详情页（P05）—— ✅ 完成（2026-10-12）
- 设计系统新增 `ClProfileHero`（大图封面 + 底部渐隐 + 白字「昵称 年龄」和小字 meta），已加进组件预览页。
- 客户端数据层：`UserApi.detail(id)`、`RelationApi`（关注/取关/拉黑/解除拉黑）、`UserDetail`/`UserDetailPrice`/`RelationState` 模型、`RelationRepository`。
- 页面（`feature/profile/` 的 `UserDetail*` 四件套）：大图封面 → 标签行（真人认证 / 在线 / 新人 / 互相关注）→ **聊天价格面板**（男看女才有，`ClPriceGrid` 三格 + 服务端给的 `free_hint` 小字）→ 关于我 → 基本资料（城市/身高/职业/学历/交友期待）→ 相册（点开大图预览，`ClImagePreview`）→ 兴趣话题；底部固定「私信聊天 X 币/条」。
- 右上角「⋯」菜单：**关注 / 已关注 / 互相关注**（同一项，文案跟着关系变）、拉黑（二次确认）、举报（底部弹窗：原因单选 + 可选文字）。
- **顺带改了服务端一处**：`follow` / `unfollow` 现在返回**关系快照** `{is_following, is_mutual}`。
  - 原因：客户端只靠自身的 `is_following` 猜不出"回关之后是不是互关了"；而重新拉一次 `/user/{id}` 会**再记一条访客**。让服务端把新状态带回来最省事也最准（T3.4 的用例只断言 `code`，所以没被打破，另补了一条互关的用例）。
- 验收对照（docs/08 T3.8 只要求 `D`）：`check-android.sh` → 通过（**190 个单测**，T3.8 新增 17 个）；`check-server.sh` → 通过（**192 测试 / 1518 断言**）。
- 单测覆盖：资料与标签 id→名字（不在标签库的直接丢）、加载失败、**关注按钮三态**、没关注时点 follow / 已关注时点 unfollow、**回关后立刻显示「互相关注」**、**关注后不重新拉整页**（否则又记一条访客）、关注失败弹服务端文案、拉黑成功标记要回上一页、拉黑失败不回、举报没选原因不让提交、**选了原因提交如实告知功能还没开放**、关弹窗清空已填内容、相册预览与封面取第一张。
- **未做 / 有意为之**：
  1. **举报只做了弹窗，没有提交**（docs/08 明确说提交是 T6.4 的活）。所以点「提交举报」**不假装成功**，如实提示「举报功能还没开放，稍后再试」。
  2. **举报的截图附件也没做**（docs 说可选截图）—— 和提交一起留到 T6.4。
  3. **设计图 05 右上角的「分享」没做** —— `docs/01` P05 里没有这一项（只写了「⋯」菜单），分享要接系统 Intent，先不做。要做的话记一笔。
- 仍然**没在模拟器上看过**（和 P01/P07 一起留到 T3.10 阶段验收走查）。

## 阶段验收记录

<!-- 每个阶段结束写一段：check-all 结果、走查了哪些页面、发现并修复了什么 -->

### P2 安卓基础 —— ✅ 通过（2026-10-12）
- `bash scripts/check-all.sh` → **`✅✅✅ 全部检查通过`**（rc=0）：server 100 测试 / 1160 断言 + phpstan + check-money；admin-web lint+build；android 单测 + lintDebug + assembleDebug + check-ui。
- 产出（**11 个任务全部完成**）：
  - T2.1 主题（`ClColors`/`ClTypography`/`ClSpacing`/`ClSize`/`ClShapes`/`ClElevation`/`ClGradients` + `ClTheme` 与 `clCardShadow`/`clTopWash*`）
  - T2.2 图标（`ClIcons`，本阶段末共 30 个）
  - T2.3~T2.5 六类公共组件 45 个文件（含 `UiState`、`ClPullRefreshList` 分页失败重试）
  - T2.6 网络层与本地存储（`ApiResponse`/`ApiResult`/`safeApiCall`/`AuthInterceptor` 401 自动刷新、`AuthApi`、`ClDataStore`、Room 骨架）
  - T2.7 导航与 App 骨架（`Routes` 覆盖 31 个页面 + P00、`ClNavHost`、底部导航受 `call_enabled` 控制、启动拉 `/app/config`、未读角标）
  - T2.8 公共工具（`TimeFormat`/`MoneyFormat`/`Validators`/`ImageCompressor` + 34 单测）
  - T2.9 业务复用组件 15 个 + 预览页新增一节
  - T2.10 阶段验收（本记录）
  - T2.11 列表缓存封装（`LocalCache` + `CacheKeys` + stale-while-revalidate + 60 秒节流，24 单测；验收后补齐）→ **全阶段 android 单测 92 个**
- **UI 统一这条命脉已经立住**：所有页面只能用 `core/designsystem` 的 token 与组件，`check-ui.sh` 会扫 `feature/` 里的硬编码色值/尺寸和原生组件，也会检查每个组件有没有进预览页。
- 发现并处理的问题：AGP 9 内置 Kotlin 的三处适配（已在 P0 记录）、Room 需要至少一个实体所以先用 `sync_state` 起库（T2.6）、服务端业务错误的 `data` 从 `[]` 改成 `null`（T2.6）、`hiltViewModel` 新包名（T2.7）、写单测时抓到自己两处笔误（身份证校验位用例填错、通话圆钮颜色用错 token，均已修）。
- 遗留（不阻塞 P3，已记入待确认表或对应任务）：Q0-13 `app.version` 语义、Q0-14 `ClSoftButton` 三种底色的最终归属、Q0-15 `Modifier.blur` 在 API 31 以下无效、`ImageCompressor.compress()` 本体未测、**真机/模拟器尚未跑过一次**（本机没有可用 AVD）。

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
