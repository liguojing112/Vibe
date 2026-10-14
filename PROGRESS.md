# PROGRESS（进度记忆）

> 这是你的记忆。**每次开工先读，每个任务做完必须更新。**
> 只追加和修改状态，不要删除历史记录。

## 当前状态

- 当前阶段：**P7 管理后台**（P6 已完成）
- 当前任务：T7.2 用户管理（男用户、女用户、用户详情抽屉）已完成 → 下一步 T7.3 批量开号
- 任务状态：待开始 <!-- 未开始 / 进行中 / 已完成 -->
- 上次停在哪：T7.2 完成，后台第一个真页面做完了。**男用户/女用户是同一个 `UserPage.vue`**（只有 `gender` 一个参数不同），带搜索、导出 Excel、详情抽屉和 8 个操作；服务端 9 个接口 + 一张 `cl_admin_action_log`（后台对用户的动作流水，封禁记录和调等级日志共用）。**顺手补了一个洞**：`findActiveUser` 原来只挡已注销，**封了号的人拿旧 token 还能刷接口** —— 现在封禁也 401，封号是当场生效的。**新增依赖 `openspout/openspout` 5.3**（你要的真 .xlsx）。浏览器走查抓到 2 处显示问题（`ClStatusTag` 缺 `NORMAL` 文案导致列表显示英文、抽屉里注册来源/实名状态显示原始数字），都已修。封禁→解封全流程从界面跑通并留下了流水。`bash scripts/check-all.sh` → 见 T7.2 记录。

## 任务进度

<!-- 做完一个，把 [ ] 改成 [x]，并在下方「任务记录」里写自检记录 -->

- P0：[x] T0.6 [x] T0.1 [x] T0.2 [x] T0.3 [x] T0.4 [x] T0.5
- P1：[x] T1.1 [x] T1.2 [x] T1.3 [x] T1.4 [x] T1.5 [x] T1.6 [x] T1.7
- P2：[x] T2.1 [x] T2.2 [x] T2.3 [x] T2.4 [x] T2.5 [x] T2.6 [x] T2.7 [x] T2.8 [x] T2.9 [x] T2.10 [x] T2.11
- P3：[x] T3.1 [x] T3.2 [x] T3.3 [x] T3.4 [x] T3.5 [x] T3.6 [x] T3.7 [x] T3.8 [x] T3.9 [x] T3.10
- P4：[x] T4.1 [x] T4.2 [x] T4.3 [x] T4.4 [x] T4.5 [x] T4.6 [x] T4.7 [x] T4.8 [x] T4.9（T4.8 = 阶段验收，见「阶段验收记录」）
- P5：[x] T5.1 [x] T5.2 [x] T5.3 [x] T5.4 [x] T5.5 [x] T5.6 [x] T5.7 [x] T5.8 [x] T5.9（T5.9 = 阶段验收，见「阶段验收记录」）
- P6：[x] T6.1 [x] T6.2 [x] T6.3 [x] T6.4 [x] T6.5 [x] T6.6 [x] T6.7（T6.7 = 阶段验收，见「阶段验收记录」）
- P7：[x] T7.1 [x] T7.2 [ ] T7.3 [ ] T7.4 [ ] T7.5 [ ] T7.6 [ ] T7.7 [ ] T7.8 [ ] T7.9
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

**模拟器连后端（P3 阶段验收时摸清楚的，以后再走查直接照做）**：
1. **模拟器直连 `10.0.2.2:9501` 走不通**（OkHttp 报 `SocketTimeoutException: failed to connect to /10.0.2.2 (port 9501) after 30000ms`），哪怕 Windows 侧 `curl 127.0.0.1:9501` 是 200、模拟器里 `nc` 也装得像能连。原因没深究（后端在 WSL2，`networkingMode=mirrored`；SLIRP 那条路不通），**改成 adb 端口回环**：
   ```bash
   adb reverse tcp:9501 tcp:9501
   cd android && ./gradlew assembleDebug -PapiBaseUrl=http://127.0.0.1:9501
   ```
   `apiBaseUrl` 是 `app/build.gradle.kts` 里给 debug 包加的可覆盖项，**不传就还是 docs/02 写的 `10.0.2.2`**，所以正常构建不受影响；真机调试同样走 `adb reverse` 这条路。
2. 演示数据：`php scripts/seed-demo.php`（在 WSL 里跑，连开发库 `changliao`）。会造 5 个账号，**密码统一 `abc12345`**：`13800000001` 阿宁（男，金币 360）、`13800000002` 小满（女，积分 1200）、`13800000003` 晚晴（女，Lv3）、`13800000004` 星野（女，Lv2）、`13800000005` 林川（男）。已经顺手造好：小满 ↔ 阿宁互关、晚晴关注阿宁、小满今天来访、晚晴昨天来访。
   - 注意脚本用 PHP 的 `date()` 写 `last_active_at`，**CLI 的时区可能是 UTC**，所以造出来的用户「在线」状态不准（服务端按 Asia/Shanghai 比）。要测「在线」标签就手动把 `last_active_at` 改成 `NOW()`。
   - ⚠️ **脚本会先 `DELETE` 这 5 个手机号再重建**，所以**每次重跑 id 都会变**（2026-10-13 跑完是 101~105），
     而且上一次登录拿到的 token 对应的用户已经没了 —— T4.5 真机验证时就踩到：App 里的 token 直接失效。
     **登录 App 之前先跑一次 seed**，别用上次留下的号。
3. **后台账号**（T7.1 起）。`php bin/hyperf.php db:seed` 会建 4 个后台账号，**密码统一 `123456`**：
   `admin`（超级管理员，看全部菜单）、`kefu`（客服）、`caiwu`（财务）、`shenhe`（审核员）。
   角色和菜单在 `server/databases/seeders/zz_cl_menu_seeder_20261014.php` 里配（⚠️ 文件名必须以 `zz_` 开头，原因见该文件头部注释）。
   启后台：`cd admin-web && pnpm dev`（端口 2888，接口直连 `http://127.0.0.1:9501`，见 `admin-web/.env.development`）。
   仓库根有 `.claude/launch.json` 可直接让内置浏览器起这个服务；**但内置预览面板只有 342px 宽**，
   低于 MineAdmin 的 `lg`（1024px）断点，侧边栏会被隐藏 —— 要看菜单观感请用 1024px 以上的窗口自己开。
5. **WebSocket 也要 `adb reverse`**（T4.4 做完了）。后端 WS 服务在 **9502**，地址 `ws://{host}:9502/ws?token={access_token}`
   （`CL_WS_PORT` 在 `server/.env` 里，默认 9502）。模拟器同 9501 一样连不上 `10.0.2.2`，所以：
   ```bash
   adb reverse tcp:9501 tcp:9501
   adb reverse tcp:9502 tcp:9502
   cd android && ./gradlew assembleDebug -PapiBaseUrl=http://127.0.0.1:9501
   ```
   客户端 `WS_URL` 同样要能覆盖（T4.5 做 `ChatSocket` 时按这个来，别再写死 `10.0.2.2`）。
6. **⚠️ 跑后端测试一定要用 `composer test`（或自己带上 `DB_DATABASE=cl_test REDIS_DB=15`）**：
   测试库隔离**只靠 `composer test` 脚本里那几个环境变量**（`phpunit.xml.dist` 的 env 元素不生效，见 `09` 第 1.1 节）。
   直接跑 `vendor/bin/phpunit --filter Xxx` 会用**开发库**，而用例里的 `clearAll()` 会删表数据 ——
   T4.5 时「演示账号突然登录不上」就是这个原因（用户被测试删了）。**血泪教训：一律走 `composer test`。**
7. **⚠️ 新增带注解的类（控制器、命令、定时任务）之后要清一次 `runtime/container` 再启动**：
   注解扫描结果有缓存，不清的话新类**静默不生效**。T4.9 给定时任务踩的这个坑：
   加完 `app/Crontab/CleanExpiredMessagesCrontab.php` 后第一次启动，crontab 一直不触发，
   清掉容器缓存 + 重启（冷启动那一次路由会 404，再启动一次就好，见下一条）之后才正常。
8. **⚠️ 删了 `runtime/container` 之后第一次启动，注解路由会整片 404**（T4.4 排查出来的）：
   `/` 能通、`/api/v1/...` 全 `{"code":500,"message":"Not Found"}`。原因是 Hyperf 的注解扫描在**子进程**里做，
   父进程不等它 —— 冷缓存那一次启动，swagger 注册注解路由时 `AnnotationCollector` 还是空的，于是路由表里一条都没有；
   扫描结果会写进 `runtime/container/scan.cache`，**再启动一次就好了**（热缓存）。
   所以重启后端的正确顺序是：
   ```bash
   pkill -9 -x php
   rm -rf runtime/container          # 只在改了注解/路由后才需要
   php bin/hyperf.php start          # 冷缓存这一次路由是空的
   # 觉得不对劲就再杀一次重启（热缓存），然后 curl 冒烟一句：
   curl -s localhost:9501/api/v1/app/config | head -c 60
   ```

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
| 安卓 | androidx.room:room-runtime / room-ktx / room-compiler | 2.8.5 | 聊天记录本地化的**主数据源**（docs/10 第 1 节）。T2.6 建的骨架，T4.5 落成 message/conversation/sync_state 三张表 | T2.6、T4.5 |
| 安卓 | org.robolectric:robolectric | 4.17 | 本地库的 DAO 与迁移要 Android 环境，用它在 JVM 上跑真 Room 库（Q0-21）。**注意**：本机 compileSdk 是 37，Robolectric 还没有那版预置 SDK，用 `app/src/test/resources/robolectric.properties` 钉 `sdk=34`；它自己下 android-all jar，所以 testOptions 里把仓库指到了阿里云镜像 | T4.6 |
| 安卓 | androidx.test:core | 1.7.0 | Robolectric 测试里取 Application（`ApplicationProvider.getApplicationContext()`） | T4.6 |
| 后端 | openspout/openspout | 5.3.0 | 后台导出 Excel（docs/07 第 3.2、3.3 节）要生成**真的 .xlsx**。纯 PHP、只需要 `ext-zip`（本机已有）。**注意**：最新版 v5.12 要求 PHP 8.4+，本机是 8.3，所以锁在 5.3 —— 以后升 PHP 可以直接放开 | T7.2 |

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
| Q0-22 | **设计图 03 的头像横条第一个是「我的小队」**（一个群组图标），但 `01` P03 的描述只写了「最近联系的 5 个人，点进 P06」，`05` 里也没有任何「小队」接口 | 我按 `01` 做的：横条只放最近联系的 5 个人，没做「我的小队」。如果客户要这个功能，需要补需求（是什么、几个人、在哪建） | T4.6 | ⏳ 待确认 |
| Q0-21 | ~~**Room 的 DAO / 迁移没有自动化测试**~~（**2026-10-13 已解决**：按你的决定加了 Robolectric，`ChatDaoTest` 11 例 + `ChatMigrationTest` 1 例；迁移用例还做了反向验证 —— 故意写错一列，用例立刻红）：`Room.inMemoryDatabaseBuilder` 和 `MigrationTestHelper` 都要 Android 环境，JVM 单测跑不了，目前只靠 `kapt` 在**编译期**校验 SQL 列名 + 我在模拟器上手工跑了一遍 v1→v2（见 T4.5 记录）。要补自动化测试就得加 **Robolectric**（新依赖，还要处理 `compileSdk 37` 对不上 Robolectric 预置 SDK 的问题，可能要钉 `@Config(sdk = [34])`） | 已加，见「新增依赖记录」 | T4.5 起 | ✅ 已解决 |
| Q0-20 | **401 之后不会自动回登录页**：`AuthInterceptor` 已经清了登录态，但没有页面跳转（`MainActivity` 只在启动时按 token 决定起始页），用户会停在一个报错的页面上 | 计划和「退出登录 / 切换账号」一起做：在导航层监听 token 是否变空，空了就回登录页。另外退出登录时还要顺手清掉 `UserRepository.clearCurrentUser()`、`UnreadStore.clear()`、`LocalCache.clearMemory()` | T3.10、T6.x | ⏳ 待做 |
| Q0-19 | 「在线」怎么算？`docs` 没定义多久没活跃算离线（P01 要按在线优先排序、卡片上有在线绿点，P07 有「只看在线」） | 先用 **15 分钟**（`DiscoverRepository::ONLINE_MINUTES`）。这是业务上要调的阈值，客户定了以后要么改常量要么提到 `cl_config` | T3.5 | ⏳ 待确认 |
| Q0-17 | 注册页要不要也放「我已阅读并同意协议」勾选？`docs/01` P00 只在**登录**那条里要求勾选，「注册」那条只列了字段 | 按文档来：**只在登录页勾选**，注册页不放。客户若要求注册也勾，加一个 `ClCheckboxAgreement` 即可 | T3.1 | ⏳ 待确认 |
| Q0-23 | **`01` P27 的「在线接听」开关没有对应接口**：它要控制「自己是否出现在发现页『推荐』里」（`cl_user.show_in_recommend`），但 `05` 第 4.8 节的收益接口清单里没有这个开关 | **2026-10-14 已解决**：用**已有的** `POST /user/settings`（它本来就收 `show_in_recommend`，是 P15 那套设置接口）—— 不用新增接口，T6.5 做 P27 时直接接上了 | T6.5 | ✅ 已解决 |

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

### T3.9 访客、喜欢我的人（P08、P09）—— ✅ 完成（2026-10-12）
- 两个页面**共用一套实现**（`feature/relation/` 的 `RelationList*`）：接口、打码规则、分页都一样，只有标题和几处文案不同，所以用 `RelationKind`（VISITORS / FANS）区分，两个 Route 分别喂给同一个 ViewModel。
- 客户端数据层：`RelationApi` 加 `fans(page)` / `visitors(page)`，`RelationPerson` / `RelationPage`（带 `locked`）模型，`RelationRepository` 加两个列表的**第一页 stale-while-revalidate + 加载更多**；缓存键 `CacheKeys.visitors`（文档里有）+ 新增 `CacheKeys.fans`（docs/10 的表里没列，但它和访客是同一类列表，一并缓存）。
- 页面：顶部提示卡「有 N 人看过你的主页 ✨」（没人就不显示这张卡）、每行头像 + 昵称 + 说明 + 右侧小按钮；P09 没互关时右侧是「回关」，回关成功后变成「查看」。
- **非会员打码这条链路**（服务端 T3.4 已经做好打码）：`locked = true` 时昵称已经是服务端给的「小*」，页面再把文字糊掉，并且**点任何一行都弹「开通会员查看全部」→ P12**，而不是进对方主页 —— 不然等于绕过了打码。
- 顺手给 `TimeFormat` 加了 `dayLabel()`：只说「今天 / 昨天 / MM-dd / yyyy-MM-dd」。P08 的「今天看了你的资料 · 上海」要的是这种，`friendly()` 是带时分的（会变成「09:41 看了你的资料」），不合适。
- 验收对照（docs/08 T3.9 只要求 `D`）：`check-android.sh` → 通过（**207 个单测**，T3.9 新增 17 个：TimeFormat.dayLabel 3 个 + 关系列表 14 个）。
- 单测覆盖：访客列表与总数、喜欢我的人文案不同、没人时不给提示卡、加载失败整页重试、**非会员拿到打码数据且回关被挡去开通会员**、回关成功后按钮变「查看」、回关失败弹服务端文案、上拉加载接下一页、没有更多不再请求、加载更多失败只显示在列表底部、三种说明文字（今天看了你的资料 / 没有时间时 / 关注了你）、`dayLabel` 的今天/昨天/今年/去年/非法值。
- **未做 / 有意为之**：
  - 打码时**头像没有真的糊**（只糊了文字）。`ClMiniProfileRow.blurred` 用的是 `Modifier.blur`，在 **API 31 以下是空操作** —— 这条在 T2.9 就记进 Q0-15 了，设计图 08/09 里头像是糊的。要全机型都糊得换成盖一层遮罩或服务端出缩略图，等客户/后续再定。
  - 页面**没在模拟器上看过**，和 P01/P07/P05 一起留到 T3.10 走查。

### T3.10 P3 阶段验收 —— ✅ 完成（2026-10-12）
- **`bash scripts/check-all.sh`** → **`✅✅✅ 全部检查通过`**（rc=0）：server **194 测试 / 1521 断言** + phpstan `[OK] No errors` + money 通过；后台 `pnpm lint` **0 error**（19 个 warning 是上游自带的老问题，T0.3 已放宽） + `pnpm build` 成功；安卓 **207 单测** + `lintDebug` + `assembleDebug` + `check-ui.sh` 全过。
- **真机走查（模拟器 + 真后端）**：这是本项目**第一次让 App 连上真后端跑通**。走查前先解决了两件事（都记进「环境信息」了）：模拟器连不上 `10.0.2.2` → 改 `adb reverse` + `-PapiBaseUrl=http://127.0.0.1:9501`；造了 5 个演示账号（`scripts/seed-demo.php`）。
- 逐页走查结果（截图在 `docs/screenshots/p3-*.png`）：
  | 页面 | 结果 |
  |---|---|
  | P00 登录 | ✅ 标题、两个输入框、协议勾选、登录按钮、注册链接；**焦点态金色描边**、密码眼睛图标都正常；**连不上后端时按钮转圈到超时后恢复、页面不跳转、表单内容保留** |
  | P01 发现 | ✅ 顶部渐变 + 标题副标题 + 余额胶囊「✦ 360 币」（男号显示金币）；推荐/新人/同城三个 Tab（**速配 Tab 正确隐藏**）；卡片显示已认证/新人标签、`26 岁 · 上海 · 品牌设计师`、简介、`私信 5 币/条`；底部「没有更多了」 |
  | P05 个人详情 | ✅ 封面白字「小满 26」+ meta、真人认证/新人/**互相关注** 标签、聊天价格面板「5 币 私信/条」+ 免费提示「**互相关注，私信免费**」、关于我、基本资料、底部「私信聊天 5 币/条」；⋯ 菜单三项（**互相关注**/拉黑/举报）正常 |
  | P07 筛选 | ✅ 城市 Chip（全部 + 后台 10 个城市）、年龄范围双滑块（18–60）、交友期待、认证状态、只看在线开关、说明卡、底部「重置/查看匹配的人」 |
  | P08 最近访客 | ✅ 「有 2 人看过你的主页 ✨」+「开通会员查看全部」；「小* 今天看了你的资料 · 上海」「晚* 昨天看了你的资料 · 杭州」（**日期文案对**）、头像模糊 |
  | P09 喜欢我的人 | ✅ 「有 2 人喜欢你 ✨」；「晚* / 小*」，未互关时右侧是「**回关**」 |
  | P14 编辑资料 | ✅ 头像 + 「点击头像换一张」、昵称/生日/城市/身高/职业/学历/交友期待/自我介绍，兴趣标签**选中项与数据一致**（音乐、读书被选中） |
- **走查顺手验证的几条链路**（比单测更硬的证据）：
  1. **价格是按女方等级算的**：小满 Lv1 → 5 币、星野 Lv2 → 8 币、晚晴 Lv3 → 10 币，与 `cl_level_config` 一致 ✓
  2. **互关判定 + 免费提示**：小满 ↔ 阿宁 是互关，P05 上直接显示「互相关注，私信免费」✓
  3. **筛选回 P01 生效**：选了城市=杭州后，请求变成 `?city=杭州`，列表只剩晚晴，区块标题变成「按你的筛选 / 改条件」✓
  4. **打码链路**：非会员男号在 P08/P09 拿到的是「小*」+ 需要模糊的头像，且页面标了 locked ✓
  5. 打开他人主页**记了一条访客**（阿宁看过小满 → 数据里能查到）✓
- **走查抓到并修掉的 3 个真 bug**：
  1. **取关后免费提示过时**：P05 上点「互相关注 → 取消关注」后，标签立刻变了，但下面还写着「互相关注，私信免费」（其实已经要收费了）。原因：为了不重复记访客，关注/取关后不重新拉整页，那条提示就留在了旧值。**修法**：关系一变就把 `free_hint` 清掉 —— 新报价只有服务端算得出来，**宁可少显示一句，也不显示一句错的**。
  2. **打码文字被重复模糊**：`ClMiniProfileRow(blurred = true)` 把**文字**糊了，而昵称本来就是服务端打码过的「小*」，再糊一层就成了看不清的灰块。文档的口径是「**昵称打码（服务端做）+ 头像模糊（客户端做）**」，所以改成**只糊头像**，昵称照常显示「小*」✓（截图前后对比很明显）。
  3. **服务端时区不对（影响面最大）**：PHP 默认 UTC，而 MySQL 用的是宿主机的 +08，两边差 8 小时 —— 结果是「星野 2 小时没活跃还显示**在线**」。在 `bin/hyperf.php` 里统一设成 `Asia/Shanghai`（并给 `.env`/`.env.example` 加了 `APP_TIMEZONE`），另加了个用例同时盯住「运行时值」和「入口文件里那行别被删」。**这条不只是显示问题**：docs/06 要求会员每日免费额度按 Asia/Shanghai 自然日重置，T4.2 一旦用上，UTC 下会变成早上 8 点才重置。
- **为了让 P14/P08/P09 能走查，加了一个 debug 专用入口页** `feature/common/DevEntriesPage.kt`：它们的入口页（P04「我的」、P03「消息」）还在后面的阶段，所以先在「我」Tab 的占位页放 4 个跳转（编辑资料/最近访客/喜欢我的人/组件预览页），**只在 debug 包生效**，P04 做完后连同这个文件一起删掉。
- **这次没走到的**：P00 的注册页与协议页（这次直接登录，没点注册）、P14 的**选图上传**（模拟器相册里没有照片，`PickVisualMedia` 是空的；要测得上 `adb push` 一张图再走一遍 —— T3.3 记的「`ImageCompressor` 本体没测」仍然成立）、P05 的**相册与兴趣话题**（演示数据没造相册）。
- 另外**修了一个不是这次引入的老问题**：`network_security_config.xml` 放在 `src/main/res/xml/` 里，对 release 也生效（docs/02 说的是「只对 debug 生效」）。现在没改，先记一笔 —— release 本来就走 HTTPS，不影响，等 T9 收尾时挪到 `src/debug/`。

### T4.1 会话与消息表 —— ✅ 完成（2026-10-12）
- 迁移 `2026_10_12_000007_create_cl_chat_tables.php`（开发库 + `cl_test` 都跑过）：
  - `cl_conversation`：一对用户一条，`user_low_id`/`user_high_id` 唯一 + `male_id`/`female_id` 冗余（计费查询用）+ `male_sent_count`（`06` §3.2 第 2 步要的那个计数）+ 最后一条消息 + **各自的未读数与各自删除时间**。
  - `cl_message`：按 `04` 第 3 节列全（client_msg_id/类型/内容/扣费/免费原因/女方等级与分成/积分/敏感词标记/审核状态/已读）；唯一索引 `(sender_id, client_msg_id)` 管幂等。
  - `cl_sensitive_word`：词库（命中**只标记不拦截**）。
- 模型：`Conversation`（`pair()` 归一化 + `unreadFor()`/`deletedAtColumnFor()`）、`Message`（类型与审核状态常量 + `isNormalMessage()`）、`SensitiveWord`。
- **加了一条 CHECK 约束**（想清楚才加的）：`user_low_id < user_high_id`。
  - 原因：docs 说「按 user_low_id < user_high_id 存」，但**唯一索引挡不住反着写** —— `(3,7)` 和 `(7,3)` 在索引眼里是两行，一旦哪里忘了归一化，同一对人就会出现两条会话、未读数分家、计费计数对不上。CHECK 让这种 bug 在写库那一刻就炸出来。
- 顺手把「DB 默认值不回填内存对象」这个坑按住：三个模型都写了 `$attributes` 默认值（`male_sent_count`/`unread`/`price_coins`/`receiver_points`/`sensitive_hit`/`review_status`/`enabled`）。**T3.6 就是因为这个读出来 `level` 是 null**，与其每次用的时候 `?? 1`，不如在模型上写清楚。
- 验收对照（docs/08 T4.1 只要求 `S`）：`bash scripts/check-server.sh` → 通过（**202 测试 / 1547 断言**，T4.1 新增 8 个；phpstan `[OK] No errors`；money 通过）。
- 单测（`ChatTablesTest`）：`pair()` 与顺序无关、**同一对人只能有一条会话**、**反着写被 CHECK 挡住**、未读/删除列按用户取对、**client_msg_id 在同一个发送者下唯一**（换个人可以复用它）、只有文字和图片算普通消息（礼物不算，`06` 场景 16）、消息的默认值、敏感词唯一且默认启用。
- 给 T4.4 记一笔（**现在没做，别到时候踩**）：`hyperf/websocket-server` 依赖装了、`.env` 里也有 `CL_WS_PORT=9502`，但 **`config/autoload/server.php` 里还没有 ws 服务条目**（只有 9501 的 http）。T4.4 要自己加。另：日志里那个 `9503` 是 **Swagger 文档服务**（`config/autoload/swagger.php`），不是 WebSocket。

### T4.2 发消息计费 —— ✅ 完成（2026-10-12）
- 新增：`Service\Cl\Chat\MessageBillingService`（计费主干）、`SensitiveWordService`（敏感词，Redis 缓存）、`Service\Cl\Level\LevelService`（升级检查）、`ConversationRepository`、`MessageRepository`、`SensitiveWordRepository`、`ChatSendRequest`、`ChatController::send`。
- **严格按 `06` §3.3 的七步走**（一个事务）：
  1. 校验：双方存在且未封禁、**异性**（同性 30003）、**任一方向拉黑都发不出去**（30002）、内容非空且不超 `chat.message_max_length`；
  2. **幂等**：`client_msg_id` 已存在 → 直接返回那条消息，**不重复扣费**；
  3. 报价：`MessagePricingService::quote()`（互关/对子免费/会员额度/收费）；
  4. 收费：`CoinWalletService::decrease`（type=MESSAGE，ref=消息ID）→ 女方积分 `PointWalletService::increase`（type=MESSAGE_INCOME）→ `PlatformIncomeService::record` → 女方累计收益 +、`LevelService::checkUpgrade`；会员额度只在**真的用了** `VIP_FREE` 时才 +1；
  5. 写消息（价格、免费原因、分成比例、积分、敏感词标记全存下来，方便对账）；
  6. 会话：`male_sent_count` +1（**只算男方的普通消息**）、对方未读 +1、更新最后一条消息、**清掉双方「删过」的标记**（新消息让会话重新出现）；
  7. 事务提交后推送 WebSocket —— **T4.4 做**，这里留了口子。
- **先落消息再扣费**：扣费的流水要用消息 id 当 ref，而 `doc` 的步骤顺序是先扣后写。所以实际的顺序是「算报价 → 插消息 → 拿 id 去扣费/记收入」，仍然全在一个事务里，效果一致（余额不足时整笔回滚，消息也不留）。
- 积分公式按 `06` §3.4 **全整数运算**（`intdiv`，先乘后除）：`floor(price × rate × point.per_yuan ÷ (10000 × coin.per_yuan))`，未实名时 `rate = floor(rate × share.unverified_factor ÷ 10000)`。实测：Lv1 已实名 = 15 分、未实名 = 7 分、Lv3 = 40 分，与文档例子完全一致。
- 收费条文案（`fee.strip_text`）与 `fee` 结构按 `docs/05` §4.5 给全，**全由服务端拼**（铁律 6），四种形态都覆盖了。
- 敏感词：命中**只标记不拦截**（消息照常送达、打 `sensitive_hit`），词库缓存在 Redis、后台改完 flush 一次。
- 验收对照（docs/08 T4.2：**`06` 第 10 节场景 1~13 全部通过**）—— ✅ 13 个场景一个不落，另加：
  - 敏感词命中标记（且正常内容不打标）；
  - **场景 17~19**（跨门槛升级、一次跨两级取最高、锁定等级不升）—— 升级检查是计费流程的一环，就顺手做了；**升级的系统通知**要等通知表（P6）；
  - HTTP 层的 5 个用例（`fee` 结构齐全、未登录 401、`type=3` 422、同性 30003、余额不足 20001 且消息不落库）。
- **并发这块踩了两个坑，值得记**：
  1. **并发首次发消息会死锁（MySQL 1213）**：两个协程同时 `insert cl_conversation`，撞唯一索引/间隙锁，MySQL 挑一个当事务回滚。加了事务级重试（最多 3 次，识别 `Deadlock` / `Lock wait timeout` / `Duplicate entry`），第二轮就能读到对方建好的会话继续走。
  2. **文档场景 8 的写法其实证明不了并发安全**：余额 10、单价 5、发 3 条——串行跑也是「成功 2 条、余额 0」，有没有行锁都一样。所以另写了一个**能区分**的用例：只给 **1 条免费额度**再并发发 3 条——没有行锁的话三条会同时读到「还没发过」全部免单；有锁则只能免 1 条、另两条各扣 5。
- 顺手修了一处**测试间污染**：我新加的 HTTP 用例把 `chat.free_count_per_pair` 改成 0 却没在收尾还原，导致 T3.6 的一个用例（`free_hint`）挂了 —— 是测试的问题不是代码退化，收尾补上了还原。
- 自检命令：`bash scripts/check-server.sh` → 通过（**225 测试 / 1659 断言**；phpstan `[OK] No errors`；money 通过 —— 资金动作全部走钱包服务，`check-money.sh` 没报）。
- 未做 / 有意为之：**WebSocket 推送**（步骤 7）在 T4.4；**升级通知**在 P6 通知系统建好后接；礼物计费（场景 14~16）在 P5。

### T4.3 会话、消息查询与已读 —— ✅ 完成（2026-10-13）

- 读：`05` §4.5（8 个接口）、`01` P03/P06、`10`（本地化口径）
- 接口（全部 `#[Middleware(AppAuthMiddleware::class)]`，都在 `ChatController`，路由前缀一样就不另开 Controller）：
  - `GET /chat/conversations` —— 分页外壳（`page_size = 20`），每项 = 对方简要资料 + 最后一条消息 + 未读数
  - `DELETE /chat/conversations/{id}` —— 只对自己隐藏，重复删当成功
  - `GET /chat/conversations/with/{userId}` —— `conversation_id`（没有则 `null`）+ `peer` + `unread` + `fee`
  - `GET /chat/messages` —— `conversation_id` / `before_id` / `limit`（默认 30、上限 100），**倒序**返回 + `has_more`
  - `POST /chat/read` —— 清未读 + 打 `read_at`，**顺便把最新未读总览带回来**（客户端不用再打一次）
  - `GET /chat/unread` —— 会话 / 访客 / 通知分别返回 + `total`
  - `GET /chat/sync` —— 我收到的、`id > after_id` 的消息，升序 + `max_id` 游标 + `has_more` + `unread`
  - `GET /chat/fee-rules` —— 收费说明弹窗条目，按当前配置现算
- **出参形状只写一次**：新增 `ChatPresenter`（`message` / `messages` / `fee` / `peer`）。同一条消息会从
  `/chat/send` 返回值、`/chat/messages` 与 `/chat/sync` 列表、以及 T4.4 的 WS 推送三条路发给客户端，
  三处字段必须一致，所以 `MessageBillingService` 里原来那份私有的 `messagePayload()` 也换成它了。
- **删除会话靠时间戳不靠删数据**：给自己那侧的 `*_deleted_at` 盖时间戳 + 未读清零；`touchConversation()`（T4.2 写的）
  会在对方来新消息时把两侧的时间戳都清空 → 会话重新出现，且**未读从 0 重新数**（不是把删之前的捡回来）。
  删除时不清未读的话，底部角标会指向一个列表里看不见的会话，所以一并清了。
- **拉黑即不可见**：会话列表、未读总数、`with/{userId}` 都过滤任一方向拉黑的人（`with` 直接当对方不存在，返回 30001）。
- 权限口径：`requireParticipant()` 对「会话不存在」和「不是你的会话」返回**同一个** 30001，
  免得拿 id 就能探出别人有没有会话。
- `fee-rules` 按性别给不同条目：男方看「怎么扣钱」（免费条数 / 互关免费 / 会员每日额度 / 各级单价 / 图片同价 / 充值比例），
  女方看「怎么分成」（免费发送 / 各级分成比例 / 未实名系数 / 积分兑换）。后台把 `chat.free_count_per_pair` 改成 0，
  第一句文案跟着变成「每对用户之间不设免费条数，第 1 条即计费」（客户要求的「第一条就收费」可配）。
- **未读数三源**：会话（会话表 `low_unread` / `high_unread` 按我是哪一侧求和）、访客（`cl_visit.read_at` 为空）、
  系统通知。`cl_notice` 表要到 P6 的 T6.6 才建，这里先恒为 0 占住字段位置，**P6 要回来把它接上**。
- 踩坑：加完注解路由后第一次跑 HTTP 测试**全 500**（`testConversationsRequireLogin` 期望 401 拿到 500）——
  是 `runtime/container` 里的注解路由缓存还是旧扫描结果，`rm -rf runtime/container` 后就好了。
  所以在 WSL 里跑测试前，改了 Controller 注解就要清一次容器缓存。
- 自检：`bash scripts/check-server.sh` → **255 测试 / 1799 断言**、phpstan `[OK] No errors`、`check-money.sh` 通过。
- 新增测试：`tests/Feature/Cl/Chat/ChatQueryTest.php`（22 例，服务层）、
  `tests/Feature/Cl/Api/ChatQueryControllerTest.php`（8 例，HTTP 层）。
- 留给后面的：WS 推送 `message.new` / `unread.update` → T4.4；安卓侧 Room 落库与聊天页 → T4.5 / T4.6；
  `cl_notice` 未读数 → T6.6；消息留存清理 → T4.9。

### T4.4 WebSocket 推送 —— ✅ 完成（2026-10-13）

- 读：`05` §5、`02` §2.4
- 做的东西：
  - `config/autoload/server.php` 加 **`ws` 服务（`SERVER_WEBSOCKET`，端口取 `CL_WS_PORT` 默认 9502）**；
    `config/routes.php` 用 `Router::addServer('ws', ...)` 把 `/ws` 指到 `App\WebSocket\ChatWsController`。
  - `ChatWsController` 实现 `OnOpen`/`OnMessage`/`OnClose` 三个接口：
    **onOpen** 拿 `?token=` 鉴权，无效就**先完成握手再发关闭帧踢掉（4001 + reason）**；有效就登记 fd；
    **onMessage** 只认 `{"event":"ping"}` → 回 `pong`（`05` §5：App 只发心跳，发消息一律走 HTTP）；
    **onClose** 注销登记 + 更新 `last_active_at`（还有别的连接就不更新）。
  - `WsAuthenticator`：和 HTTP 的 `AppAuthMiddleware` **同一套 JWT（scene=api）**，黑名单/注销/封禁都算无效；
    这里只返回 null 不抛异常 —— 握手阶段抛异常会变成 400，客户端只知道「连不上」，而我们要的是连上再明确踢掉。
  - `OnlineRegistry`：`ws:fd:{fd} → userId`（由连接反查人）和 `online:{userId} → fd 集合`（由人反查连接）。
    用**集合**是因为同一个人可能开着多台设备；断开时集合空了要顺手删 key，否则 `isOnline()` 永远说人在线。
  - `PushService`：**所有推送的唯一出口**。三条纪律写进了类注释：
    ① 推不出去**绝不抛**（钱扣了、消息落库了，客户端靠 `/chat/sync` 补拉就行），每次 push 都吞异常只记日志；
    ② 不在线就直接返回，不做无谓的 Redis/DB 查询；
    ③ 组包只认 `ChatPresenter`，保证推送里的消息结构和 `/chat/messages` 完全一致。
    `messageReceived()` 推 `message.new` + `unread.update`（未读结构和 `/chat/unread` 一样）。
  - 接进 `MessageBillingService`：**在事务提交之后**推（提前推会出现「客户端先收到推送、再查到消息不存在」）。
- **端口/写法确认**：9503 是 swagger 文档服务，9502 空着；`hyperf/websocket-server` 本来就在 `composer.json` 里。
  Hyperf 会把 ws 服务排在服务器列表最前面，所以主 server 对象是 `Swoole\WebSocket\Server`，
  于是 **HTTP worker 里也能用 `Sender::push()` 跨进程推到 ws 连接**（这正是选 `PushService` 走 `Sender` 的原因）。
- **踩坑（重要，已记进环境信息）**：加完 ws 服务后，HTTP 接口整片 404（`/` 却正常）。
  排查结论：**不是 ws 服务的问题，是「删了 `runtime/container` 后第一次启动」的问题** ——
  Hyperf 的注解扫描在子进程里做、父进程不等，冷缓存那一次启动 swagger 注册注解路由时扫描结果还是空的，
  路由表里就只有 `config/routes.php` 里手写的那几条。**再启动一次（热缓存）就正常**。
  顺手把「重启后端的正确顺序」写进了环境信息，避免以后每次重启都要重新怀疑人生。
- 验收（`scripts/ws-check.php`，**19 项全过**）：两个客户端连上，男方发消息**女方收到 `message.new` + `unread.update`**、
  消息内容与 id 和 HTTP 返回一致、**发送方自己收不到自己的消息**、ping→pong、**无效 token 被以 4001 踢下线**、
  断开后消息照发不误。
  跑法（WSL，后端要先起来）：`cd server && php ../scripts/ws-check.php`
  —— 这个脚本要真进程 + 两个客户端，单元测试里没有真 server 测不出真东西，所以走脚本（`08` 允许「集成测试或脚本」）。
- 单元测试：`tests/Feature/Cl/Push/OnlineRegistryTest.php`（6 例：多设备、断开、重复断开、空集合清理）、
  `tests/Feature/Cl/Push/PushServiceTest.php`（7 例：下发的 JSON 结构、中文不转义、离线不查未读、
  `pong` 的 data 必须是 `{}` 不能是 `[]`、**推送失败被吞掉**）。替身 `SenderSpy` 覆盖 `__call` 记账，不真写 socket。
- phpstan：Hyperf 的 `OnOpenInterface` 签名里引用了 `Swow\Psr7\Server\ServerConnection`（本项目没装 Swow），
  会在所有实现类上报 `class.notFound`，按既有方式在 `phpstan.neon.dist` 加了一条精确豁免。
- 自检：`bash scripts/check-server.sh` → **268 测试 / 1828 断言**、phpstan `[OK] No errors`、`check-money.sh` 通过。
- 留给后面的：安卓侧 `ChatSocket`（心跳 30s、重连 1/2/4…最大 30s、重连后 sync）→ T4.5；
  `cl_notice` 的 `notice.new` 与 `account.banned` 推送 → P6；礼物消息的推送 → P5（现在只推普通消息）。

### T4.5 安卓聊天数据层 —— ✅ 完成（2026-10-13，含模拟器真机验证）

- 读：`02` §4.4、`10`（整份）
- **数据层分层**（`docs/10` §1）：界面只读 Room（`Flow`），网络只负责把数据补进本地库。
  新增 `core/data/repository/ChatRepository`（本地优先）+ `ChatMappers`（DTO↔实体↔模型只写一处）；
  新增 `core/data/model/ChatMessage.kt`（消息/会话/收费/未读的界面模型 + `MessageStatus` 状态机）。
- **Room v2**（`docs/10` §1.2）：`message` / `conversation` / `sync_state` 三张表，`ClDatabase` 从 v1 升到 v2。
  新增 `MessageDao` / `ConversationDao`，`SyncStateDao` 加上 `ownerId` 维度。
  几个必须这么定的点：
  - 消息主键是**本地自增** `localId`：消息发出去之前没有 `serverId`，不能拿它当主键；
  - `clientMsgId` 建**唯一索引**，这是幂等的唯一手段 —— 乐观 UI 先写一条，服务端确认后同一条会再回来，
    WebSocket 推送和 `/chat/sync` 补拉也可能给同一条，`INSERT OR IGNORE` 一次全解决；
  - 会话主键用 **`(ownerId, peerId)`** 而不是服务端会话 id：第一次发消息之前服务端还没有会话，
    本地得先有一条记录把界面撑起来，`serverConversationId` 拿到之后再回填；
  - 三张表都带 **`ownerId`**：同一台设备换账号登录时，两个人的聊天记录、会话列表、同步游标都不能串。
    `sync_state` 因此从「主键 = conversationId」改成「主键 = (ownerId, conversationId)」。
- **MIGRATION_1_2（`docs/10` §8.3 要求从第一天就带迁移）**：
  建两张新表 + `sync_state` 重建（SQLite 改不了主键，走「建新表 → 拷数据 → 删旧表 → 改名」）。
  建表语句是**照 Room 自己导出的 `app/schemas/…/2.json` 抄的**，不是手写的 ——
  在 `app/build.gradle.kts` 里给 kapt 加了 `room.schemaLocation`，Room 导出权威结构，
  写迁移时对着抄（这也是以后补迁移测试的 fixture）。`DatabaseModule` **故意不挂 `fallbackToDestructiveMigration`**：
  宁可升级报错，也不能悄悄把用户的聊天记录清掉。
- **`ChatSocket`**（`docs/02` §4.4）：`ws://{host}:9502/ws?token=…`，**只收不发**（App 只发心跳，发消息一律走 HTTP）。
  30 秒心跳、断线按 **1s/2s/4s…封顶 30s** 重连；`connected` 变 true 就补拉一次
  （首次连上和重连要做的事一样，所以不用分两种情况）。它**不认识数据库也不认识业务接口**，收帧→解析→抛给上层。
  `ChatFrame.parse` 认 `message.new` / `unread.update` / `pong`，**认不出来的一律丢掉**：
  服务端以后加新事件（P6 的 `notice.new`、`account.banned`），现在这版客户端不能因此崩掉或断开。
- **乐观 UI**（`docs/10` §2）：`send()` 先写本地 `sending` 立刻出气泡，成功后**就地替换**（保住 `localId`，界面不闪）；
  失败改判 `failed`。**重发不换 `client_msg_id`**（服务端靠它幂等，换了就重复扣费）。
  一个额外的判断：**余额不足（20001）时把本地那条乐观气泡删掉** —— 聊天页会按 P06 要求把文字放回输入框，
  气泡留着的话用户再点一次发送就重复发了。
- **增量补拉**：`/chat/sync` 是全局的（只给「我收到的」），游标存 `sync_state` 的全局那一行、按用户分开。
  另外两条补数据的路：首次进会话用 `/chat/messages` 补历史（自己发的那半截 `/chat/sync` 给不了），
  上拉用 `before_id`。同一时间只跑一轮同步（`Mutex`）。
- **未读角标以本地库为唯一来源**：`start()` 里订阅本地会话表的未读总数喂给 `UnreadStore`；
  推送里的 `unread.update` **只当「该刷新了」的信号、不直接覆盖**，免得两个来源互相打架。
- 接口层：新增 `ChatApi`（9 个接口）+ `ChatDto`；`NetworkModule` 加 `ChatApi` 与 `@WsUrl`；
  `build.gradle.kts` 的 WS 地址也能构建时覆盖（`-PwsBaseUrl=`），因为模拟器/真机连不到 `10.0.2.2` 时
  HTTP 走 `adb reverse`，WS 得跟着走同一条路。
- **在模拟器上用真后端跑了一遍（这一步最有价值）**：
  1. 造一个 **v1 的 `cl.db`** 塞进 App 私有目录 → 启动 → 拉回来核对：`user_version=2`、
     `message`/`conversation` 建好、`sync_state` 老数据按 `ownerId=0` 保留 `(0,1,5)`、`room_master_table` 写入
     —— **迁移真的跑通了，而且 schema 校验通过**（DDL 不匹配的话 Room 会直接抛 `IllegalStateException`）。
  2. `pm clear` 后重新登录 → 服务端日志出现 **`[ws] fd[38] 用户 101 上线`**（新的 `ChatSocket` 连上了真后端）。
  3. 用 curl 以「小满」的身份给「阿宁」发了 1 条 → 拉库核对：`message` 里 1 行
     （`serverId=54, conversationId=32, peerId=102, outgoing=false, status=SENT`）、
     `conversation` 里 1 行（`unread=1`、`serverConversationId=32` 已回填）—— **推送确实落进了本地库**。
  4. 截图 `docs/screenshots/t45-unread-badge.png`：底部「消息」角标显示 **1**（未读从本地库流到角标）。
- **真机抓到一个 bug（单测发现不了）**：底部角标一开始一直是 0。原因是主界面一出现就 `start()` 了，
  而 `/user/me` 还没回来，未读流绑到了 `ownerId = 0` 上 —— 推送明明写进了库（`ownerId=101`）也看不见。
  改成 `ownerStream().flatMapLatest { … }`（跟着当前用户走）后，切号/退出登录也自动换数据源。
  消息列表和会话列表两个 Flow 一并改成同样的写法。补了两个单测锁住这条链路。
- 单测（+27，共 **234**，`check-android.sh` 全绿）：`ChatSocketTest`（退避序列 1s/2s/4s…封顶 30s、
  token 拼装、心跳帧）、`ChatFrameTest`（三种事件 + 认不出来/坏数据一律丢掉、未知字段不影响解析）、
  `ChatRepositoryTest`（乐观 UI 就地替换、**重发 client_msg_id 不变**、20001 撤气泡、sync 去重不重复计未读、
  游标推进、自己发的不计未读、超 60 秒 sending 改判 failed、会话删除后新消息重现、进聊天页女用户 fee 为 null、
  已读、未读流跟随用户）。假 DAO 放 `FakeChatDaos.kt`（内存实现，因为仓库层是「读改写」，mock 反而不好写）。
- 踩坑：
  - `@WsUrl` 忘了 import，kapt 生成 stub 时报 `NonExistentClass`（Kotlin 那边的报错完全看不出是缺 import）；
  - 单测里 MockWebServer 忘了先 `enqueue`，请求会一直挂着直到超时（20 秒），测试跑得又慢又莫名其妙失败；
  - `assertEquals(消息, 期望, 实际)`：JUnit 的**消息参数在最前面**，和 `kotlin.test` 的顺序相反。
- 自检：`bash scripts/check-android.sh` → 单测 234 / 0 失败、lint 0 error、`assembleDebug` 成功、`check-ui.sh` 通过。
- 留给后面的：
  - P03（T4.6）进入页面时要调一次 `refreshConversations()`：**推送里不带对方昵称头像**，只靠推送建出来的会话行
    昵称是空的；
  - 聊天图片的本地文件（`localMediaPath`）字段已经留好，实际落盘在 T4.7 做图片消息时接上；
  - Room 的 DAO/迁移自动化测试见 Q0-21。

### T4.6 消息页（P03）—— ✅ 完成（2026-10-13，含模拟器真机走查）

- 读：`01` P03、设计图 03
- **先补的 Robolectric（Q0-21，你确认要加）**：本地库的 DAO 与迁移以前只有「编译期校验 + 手工跑一遍」，
  现在有 `ChatDaoTest`（11 例）和 `ChatMigrationTest`（1 例）。两个值得说的点：
  - `compileSdk` 是 37，Robolectric 还没有那版预置 SDK，用 `src/test/resources/robolectric.properties` 钉 `sdk=34`；
  - 它跑测试时要自己下 android-all jar，本机 dl.google / maven central 不通，
    所以 `testOptions` 里用 `robolectric.dependency.repo.url` 指到阿里云镜像（和 Gradle 用同一套源）。
  - **迁移用例做了反向验证**：故意把 `updatedAt` 写成 `updated_at`，用例立刻红（Room 的 schema 校验挡住），
    确认这个测试不是白过的。
- 页面：新增 `feature/message/`（UiState / ViewModel / Screen / Route），`MainScreen` 把 `TAB_MESSAGE` 从占位换成真页面。
  自上而下：标题栏（搜索 / 筛选）→ 最近联系人横条 → 功能入口行 → 私信会话列表。
- **会话列表整体来自本地库**（`ChatRepository.conversations()`）：进页面先显示本地已有的（秒开）、
  同时静默拉一次服务端刷新；**WebSocket 来了新消息，本地库一变，列表和角标自己就更新**，页面不需要手动刷新。
  刷新失败时，只有在**本地一条都没有**的情况下才整页报错 —— 本来就有数据时静默失败不该把列表换成错误页。
- 搜索（按昵称）和「只看未读」都是**本地筛选**（会话本来就没多少条），筛选时收起横条和入口行。
- **功能入口行的文案只用真数据**：`/user/me` 的 `stats` 带回 `fans` / `visitors` / `unread_visitors`，
  所以「看看谁喜欢我」显示「有 N 人喜欢了你」、「最近访客」显示「有 N 人看过你的主页」+ 未读角标；
  **没有数据就不摆文案**（不去写一句「你有新的心动消息」骗人 —— 见全局规则里的毕设假数据诊断）。
  为此给 `UserProfile` 加了 `ProfileStats`。
- **「附近的人」入口要切到发现页的「同城」Tab**（`01` P03），但发现页是底部导航里的一个 Tab、状态在自己 ViewModel 里，
  从别处调不到。新增了一个应用级单例 `DiscoverTabIntent`：P03 写请求 → 切 Tab → 发现页读到请求后切自己的 Tab 并消费掉。
  传字符串而不是枚举，是因为 `core` 不能依赖 `feature`。
- **新增组件 `ClSwipeToDelete`**（`01` P03 要求「左滑可删除会话」，`03` 的组件表里没有对应组件）：
  只允许从右往左滑、无二次确认（删除只是「对自己隐藏」，对方来消息会重新出现）。
  **踩坑**：前景内容一开始没设不透明背景，真机上底下那层红色的「删除」直接透出来盖在会话行上 ——
  截图一眼就看出来了，已加 `background(surface)`。这条单测和 lint 都发现不了，只有真机能看出来。
- 顺手加了 `ClSize.peerStripItem`（头像横条一项的宽度）：`check-ui.sh` 禁止 feature 里出现 `.dp` 字面量，
  尺寸必须来自 token，所以是按规则加到设计系统里而不是就地写死。
- 真机走查（模拟器 + 真后端，截图在 `docs/screenshots/`）：
  1. `t46-p03-message.png`：入口行的数据是真的（「有 2 人喜欢了你」「有 2 人看过你的主页」+ 访客角标 2，来自种子数据）；
  2. 点「系统消息」→ 占位页（P10 还没做，属预期）；
  3. **左滑删除会话** → 列表空 → 显示空状态「还没有聊天 / 去发现页找个人打个招呼吧」+ 「去发现页」按钮，底部角标同时消失；
  4. 用 curl 以小满身份再发一条 → **会话自动重新出现**、摘要变成新消息、未读 1、角标回到 1（`t46-conversation-back.png`）
     —— 「删除后有新消息会重新出现」这条在客户端侧也通了。
- 自检：`bash scripts/check-android.sh` → **259 单测 / 0 失败**、lint 0 error、`assembleDebug` 成功、`check-ui.sh` 通过。
- 留给后面的：搜索/筛选是本地筛选（会话数上万才需要考虑服务端搜索）；「互动通知」暂时和「系统消息」进同一个 P10，
  等 T6.6 做完 P10 的「互动」分类再分开。

### T4.7 私信聊天页（P06）+ 余额不足弹窗（P30）—— ✅ 完成（2026-10-13，含模拟器真机走查）

- 读：`01` P06 / P30、设计图 06 / 30、`09` 第 1.3 节
- 页面：新增 `feature/chat/`（UiState / ViewModel / Screen / Route），`ClNavHost` 把 `Routes.CHAT` 从占位换成真页面。
  自上而下：顶部（返回 + 昵称 + 资料 → P05）→ 收费条（**只有男用户有**）→ 消息列表 → 工具栏 → 输入区。
- **收费条只显示服务端拼好的 `strip_text`**（铁律 6），右侧「收费说明」打开底部弹窗，
  条目来自 `/chat/fee-rules`（服务端按当前配置现算）。发送成功后**用服务端返回的 `fee` 刷新**收费条。
- **消息列表整体来自本地库**：乐观气泡、对方推送、补拉历史都走同一条路进界面。
  列表用 `reverseLayout` 倒着铺（最新的在最下面），往上滚到头自动加载更早的（`/chat/messages` 的 `before_id`）。
  时间分隔是**纯函数** `buildChatRows`：相邻两条间隔超过 5 分钟插一条「今天 09:41」。
- **P30 余额不足**：20001 时弹底部弹窗（当前余额大字 + 「本次操作需要 X 金币，还差 Y 金币」+ 暂不充值 / 去充值），
  数字直接取服务端 20001 的 `data`（`{"balance":4,"needed":5}`）。**输入内容保留**，关掉弹窗后还在。
  两个细节：① 解析不出 `data` 也**必须弹**（用收费条里的余额兜底），静默什么都不做的话用户会以为发出去了；
  ② 仓库层在 20001 时会把本地那条乐观气泡**删掉**（T4.5 的决定），不然用户重发一次就重复发了。
- **图片消息**（docs/10 第 2.5 节）：`ImageUploader` 拆成「先压缩存本地 → 再上传」两步，
  `ChatRepository.sendImage()` 的顺序是**先插一条本地 `sending` 记录（气泡立刻能看到图）→ 上传 → 再走正常发送**。
  上传失败时本地那条变 `failed`，**重发用的是本地那份文件**，不用用户重新选图。
- 工具栏：语音/视频（`feature.call_enabled` 控制）、送礼物（男用户才有）——都没有就整条不显示。
  语音/视频页面在 P8 才做，先按 `01` 第 4 节跳过去。
- 输入区：placeholder 男用户显示单价（**走 `MoneyFormat.coinPrice`**，`check-ui.sh` 禁止页面里手拼「币/条」）、
  女用户只说「说点什么…」。**没挂表情按钮**：`01` P06 写的是「系统 emoji 面板即可」，设计图 06 的输入区也只有输入框 + 发送。
- 单测（+16，共 **275**）：`ChatViewModelTest` 覆盖 `09` 第 1.3 节点名要测的四条里的三条
  （发送成功刷新收费条 / 20001 弹 P30 且输入保留 / 发送失败可重发），
  第四条「`client_msg_id` 重发时不变」是**仓库层**的保证，在 `ChatRepositoryTest` 里断言（页面只把同一个 `localId` 交回仓库）；
  另有 `ChatUiStateTest`（时间分隔规则 + 倒序铺列表）。
- 真机走查（模拟器 + 真后端，截图 `docs/screenshots/t47-*.png`）：
  1. P05「私信聊天 8 币/条」→ P06：收费条「还可免费发送 3 条 · 之后 8 币/条 · 余额 360 币」、空状态「打个招呼吧」、
     工具栏只有「送礼物」（语音视频开关关着）；
  2. 发一条 → 黄色气泡 + 我的头像 + 时间分隔「今天 12:49」，输入清空，
     **收费条刷新成「还可免费发送 2 条 · 之后 8 币/条 · 余额 360 币」**；
  3. 把余额改成 0、把 3 条免费额度用完后发送 → **弹 P30**：「0 金币 / 本次操作需要 8 金币，还差 8 金币」，
     服务端没落这条消息（核对过 `cl_message`）。走查完把余额还原成 360。
- 踩坑：`CoinNotEnough` 忘了加 `@Serializable`，20001 的 `data` 解析不出来 → 弹窗不显示（单测直接报 NPE 抓到了）。
- 自检：`bash scripts/check-android.sh` → **275 单测 / 0 失败**、lint 0 error、`assembleDebug` 成功、`check-ui.sh` 通过。
- 留给后面的：真机上没单独验「断网 → 失败气泡 → 点感叹号重发」（adbd 的 `adb reverse` 走 loopback，
  关 wifi/数据断不掉它，得停后端才行），这条由 `ChatViewModelTest` + `ChatRepositoryTest` 覆盖，
  到 T4.8 阶段验收按 `09` 第 6 节第 4 步再走一遍；图片消息的真机验证要往模拟器相册里 `adb push` 一张图。

### T4.9 消息留存与清理 —— ✅ 完成（2026-10-13）

- 读：`04` 第 3 节（保留策略那一段）、`06` 第 2.1 节
- **配置现成**：`chat.message_retention_months`（默认 6）T1.1 就种下去了，这次只是把它用起来。
  **填 0 = 永久保留**（后台想不清理就这么填）。
- **服务端为什么要留消息**：App 的聊天记录以本地库为主（docs/10），服务端这份只服务于监管与举报取证，
  所以不需要永久保留 —— 超期物理删掉，省空间也少一份数据风险。
- `MessageRetentionService::cleanup()` 三条规则，**写在一条查询里**（`MessageRepository::expiredIds`）：
  1. 只删**普通消息**（文字/图片）—— 礼物牵着钱、系统消息牵着通知，都留着；
  2. `sensitive_hit = 0` —— 敏感词命中的是待审/已判违规的证据，不删；
  3. 没有被 `cl_report.message_id` 关联 —— 举报取证要长期保留。
  用 `NOT EXISTS` 而不是「先查一批 id 再 `NOT IN`」：被举报的消息可能很多，塞进 SQL 又慢又容易踩参数上限。
  删除**分批**（500 条一批、最多 200 批），避免一次锁住整张表、也别让定时任务跑太久。
- **删完修 `last_message_id`**：它可能正好指着被删的那条，不修的话会话列表会显示一条已经不存在的消息。
  `ConversationRepository::refreshLastMessageIds()` 一条 UPDATE 修完（**指向的没了就回退到当前最新的一条**，
  一条都不剩就置 null），只修这次动过的会话。
- **定时任务**：`app/Crontab/CleanExpiredMessagesCrontab.php`，规则 `0 4 * * *`（挑业务低峰期，避免和发消息抢行锁），
  用 Hyperf 自己的 crontab（`02` 第 2.4 节要求，不靠宿主机 cron）。
  另加了 `config/autoload/crontab.php`：**`enable` 必须是 true，缺这个文件时所有 `#[Crontab]` 都静默不注册**
  （`CrontabRegisterListener` 读的就是它），这种「不报错但不生效」的坑值得写一行注释。
  手动跑用命令 `php bin/hyperf.php cl:clean-messages [--months=N]`。
- **顺手建了 `cl_report` 表**：它本来是 P6 的 T6.4 的（`/report` 接口 + 后台举报处理），
  但 T4.9 的保留规则要用它（「被举报关联的消息长期保留」），没这张表这条规则既实现不了也测不了。
  表结构严格按 `04` 第 3 节（reason 五种、status 三种、`message_id` 带索引供清理反查），T6.4 直接在它上面做接口。
- **验收（`09` 四条全过，10 个用例）**：超期普通消息被删 / 未超期的留着 / 敏感词命中的保留 / 被举报的保留 /
  礼物与系统消息不清理 / `last_message_id` 回退到剩下最新的一条 / 全删光时置 null / 没动过的会话保持不变 /
  保留月数从配置读（改成 1 个月立即生效）/ 填 0 时一条不删。
  **反向验证**：把举报保护那句 `NOT EXISTS` 去掉，用例立刻红（确认不是白过的）。
- **定时任务真跑过**：临时把规则改成每分钟，重启后日志里出现
  `[crontab] 私信清理完成：保留 6 个月（2026-04-13 15:02:02 之前），删除 0 条，修正 0 个会话的最后一条消息`，
  然后把规则改回 `0 4 * * *`。
- 自检：`bash scripts/check-server.sh` → **278 测试 / 1852 断言**、phpstan `[OK] No errors`、`check-money.sh` 通过。
- **这次踩到并记进「环境信息」的两个坑**：
  1. **直接跑 `vendor/bin/phpunit` 会连开发库**（测试库隔离只靠 `composer test` 里的环境变量），
     用例的 `clearAll()` 把演示账号删了 —— T4.5 那会儿「演示账号登不上」的真凶就是它。以后一律走 `composer test`。
  2. **新增带注解的类之后要清一次 `runtime/container`**，否则定时任务**静默不注册**（和 T4.4 的路由缓存同一族问题）。
- 另外修了 `scripts/seed-demo.php` 一个复制粘贴 bug：星野那段写成了 `$linchuan = makeUser(...)`，
  于是变量被后一段覆盖、**星野拿不到 id**，钱包那句自然就漏了 —— 这才是 P4 走查时发现的「晚晴/星野没有积分钱包」的根因
  （上次只补了数据、没找到根因，这次一并修掉）。

### T5.1 金币接口（男）—— ✅ 完成（2026-10-13）

- 读：`05` 第 4.7 节、`01` P13/P22/P24、`06` 第 2.6 节
- 新增 `WalletController`（3 个 GET）+ `WalletQueryService`（只读；改钱仍在 `CoinWalletService`）+ `CoinLogsRequest`。
- **只给男用户**：三个接口都先校验性别，女用户调用返回 40001 —— P13/P22/P24 都是男号页面，
  与其返回一个「0 金币」的假钱包让客户端以为正常，不如直接报错暴露调用错误。
- `/wallet/coin`：余额 + **私信单价区间**（Lv1 最低 ~ Lv5 最高，从 `cl_level_config` 现算，
  不写死；后台改 Lv1 单价这里立刻跟着变）+ 开关打开时才有语音/视频单价区间
  （`01` 第 4 节：开关关着时入口都不显示，价格更不该露出来）。
- `/wallet/coin/logs`：P24 的三个 Tab（`all` / `recharge` / `spend`），20 条一页。
  `spend` 把 `MESSAGE`/`GIFT`/`VIP`/**`ADMIN_DEDUCT`** 都算进去（超级管理员手动扣的，用户看到的也是少了一笔）。
  每行给 P24 要的字段：`type`（图标由客户端按类型选）、`title`（「私信 · 小满」这种，写流水时就拼好了）、
  `change`（带正负）、`balance_after`（变动后余额）、`created_at`。`type` 传别的值直接 422，不猜。
- `/wallet/recharge-info`：充值档位（后台 JSON，**逐个挑字段**，配置被写坏时返回空列表而不是让 App 崩）、
  客服三项（微信/二维码/电话）、合规声明、说明文字 + 当前余额。
  - **说明文字是固定文案**（`01` P22 原文「选好档位后联系客服充值，客服确认后金币到账」）：
    `06` 第 2.6 节给充值页列的可配项只有「档位/客服/合规声明」，这句不在其中，所以写在服务端常量里而不是配置里。
  - **合规声明兜底非空**（铁律 13 + `06` 第 2.6 节「不能改成空」）。
- 验收对照（`08` 只要求 `S`）：`bash scripts/check-server.sh` → **289 测试 / 1909 断言**、phpstan `[OK] No errors`、money 通过。
  另有 11 个新用例：余额与区间、区间跟着等级配置变、开关控制语音视频价格、三个接口的 401、女用户 40001、
  明细按类型过滤（充值成正数、消费为负、`balance_after` 正确、倒序）、分页、非法 type 422、
  档位/客服/合规/说明齐全、合规被清空时兜底、档位配置写坏时返回空列表。
- 重启后端 curl 冒烟：`/wallet/coin` → `balance 360 / 5~20 / call_enabled false`；
  `/wallet/recharge-info` → 6 个档位 + 合规声明 + 说明文字；女号访问 → `40001 金币钱包只有男用户有`。
- 踩坑：
  1. **`IRepository` 已经有 `page(array $params, ?int $page, ?int $pageSize)`**，我的同名方法签名不兼容直接致命错误
     → 改名 `pageForUser()`；
  2. 又是**注解缓存**：新加的控制器不清 `runtime/container` 时测试里全是 500（第三次踩，已记在环境信息）；
  3. **测试污染**：我在测试里把 `recharge.packs` 用 **JSON 字符串**写回，而 `AppConfigService::set()` 会按
     value_type(json) 再编码一次 → 变成双重编码，把后面跑的 `AppConfigServiceTest::testGetJson` 弄红了
     （已改成传数组，并在注释里写明原因）。

### T5.2 金币钱包、充值、收支明细页（P13、P22、P24）—— ✅ 完成（2026-10-13，含模拟器真机走查）

- 读：`01` P13/P22/P24、设计图 13/22/24
- 安卓侧新增：`WalletApi` + `WalletDto` + `WalletRepository`（只读）+ `core/data/model/Wallet.kt`；
  `feature/wallet/` 三个页面（各自的 UiState / ViewModel / Screen / Route）；`ClNavHost` 把三个占位路由换成真页面。
- **入口是现成的**：P13 从发现页顶部的余额胶囊进（`Routes.COIN_WALLET` 早就接好了），P22/P24 从 P13 的两个按钮进 ——
  不用再走 debug 的「开发入口页」。
- **金币页不做本地缓存**：`docs/10` 第 3 节列的「静默刷新 + 持久化」不含钱包 —— 余额是钱，
  每次进页面重新拉，宁可多等半秒也不显示一个旧数字。
- P13：余额大卡 + 两个按钮 + 「金币怎么用」（私信显示**区间**「5~20 币/条」，来自 `/wallet/coin` 的 min/max；
  语音/视频两行**只在开关打开时**出现）+ 一行说明。
  - **不显示「查看我的收益」**：设计图 13 上有这个按钮，但 `01` P13 明确写了男用户没有收益、不显示 —— 按文档办。
  - 余额卡的说明文案也跟着开关走（关着时只说「用于私信与礼物」，不提语音视频）。
- P22：余额卡 + 档位网格（**默认选中第一个**，不然「本次到账」空着）+ 「本次到账」行 + 「充值方式」（客服卡：
  微信/电话/二维码 + 「复制客服微信」，用的是系统剪贴板）+ 说明文字 + **合规声明卡**（铁律 13）。
  - **页面里没有任何支付动作**：`docs/06` 第 8 节规定充值由后台客服人工完成。
    设计图 22 上那个「模拟支付」按钮是**原型的演示按钮**，不是要做的东西 —— 照着做就成了假功能。
  - 客服信息全空时整块区域不显示（不会留一个空的「充值方式」标题）。
- P24：三个 Tab（全部/充值/消费）+ 「当前余额 X 金币」+ 列表（图标按流水类型挑、标题用服务端拼好的文案、
  副标题「今天 17:55 · 消费 · 余额 450 币」、金额正数绿负数黑）+ 分页；分页失败只在列表底部提示，不覆盖整页。
- 新增设计系统组件 **`ClQrCode`**（白底方形，二维码不能贴在彩色背景上）+ 组件预览页条目；
  `MoneyFormat.coinPriceRange()`（单价区间文案，页面里不准手拼「币/条」）。
- 真机走查（模拟器 + 真后端，截图 `docs/screenshots/t52-*.png`）：
  1. P13：450 金币、`5~20 币/条`、没有语音视频两行（开关关着）、没有「查看我的收益」；
  2. P22：6 个档位、60 默认选中、「本次到账 60 金币」、客服卡（微信/电话/**二维码正常显示**/复制按钮）、
     说明文字、合规声明；从 P30 跳进来的返回逻辑在 T4.8 已经验过（回聊天页、输入还在）；
  3. P24：两条记录（客服充值 +100 绿、私信 · 晚晴 -10 黑，图标各不相同），切到「消费」Tab 只剩消费那条；
  4. 走查时把后台的客服信息填上（微信/电话/二维码），否则那一整块不显示 —— 顺便验证了「客户端信息」这条链路。
- **走查抓到并修掉一个排版问题**：「充值方式」出现了两次（设置行里一次、客服卡标题一次），
  改成「本次到账」紧跟档位网格、客服卡挂在自己的「充值方式」小节标题下。
- 单测（+15，共 **290**）：三个 ViewModel 各自的加载成功/空/出错/主要操作，
  外加「开关关着时说明文案不提语音视频」「档位默认选中第一个」「没配客服微信时不给复制按钮」
  「切 Tab 会清空旧列表且点同一个 Tab 不重复请求」「分页失败不覆盖整页」。
- 顺带确认了一条对后面有用的约定：**图片地址是绝对 URL**（`StorageInterface::url()` = `APP_URL` + `/uploads/…`），
  所以二维码、聊天图片这些客户端直接拿去给 Coil 用就行，不需要自己拼 base URL（T4.7 的图片消息也依赖这条）。
- 自检：`bash scripts/check-android.sh` → **290 单测 / 0 失败**、lint 0 error、`assembleDebug` 成功、`check-ui.sh` 通过。

### T5.3 礼物 —— ✅ 完成（2026-10-13）

- 读：`06` 第 4 节、`04` 第 5 节、`05` 第 4.6 节
- 迁移 `2026_10_13_000009_create_cl_gift_tables.php`：`cl_gift` + `cl_gift_record`
  （后者把**送出那一刻的礼物名和价格**一起存下来 —— 后台以后调价或下架，历史记录也要还原成当时的样子）。
  唯一索引 `(sender_id, client_order_id)` 管幂等。
- 种子 `cl_gift_seeder_20261013.php`：`04` 第 5 节那 6 个礼物（🌹10 / ☕30 / 🎈60 / 💐120 / 🌠300 / 🏰680），图标用 emoji
  （`04` 允许「图片 URL 或 emoji」，emoji 不用传图也不用审核）。
- `GiftBillingService::send()`：和发私信同一套七步 —— 一个事务里
  扣男方金币 → 女方加积分 → 记平台收入 → 检查升级 → 写赠送记录 → 插一条礼物消息；提交后推 WebSocket。
  三个容易错的点：
  1. **礼物消息 `price_coins = 0`**：礼物不按私信计费（钱已经按礼物价走了）；
  2. **不计入 `male_sent_count`**（`06` 场景 16）—— 算进去的话，用户送个礼物反而白少一次「前 N 条免费」；
  3. **分成用 `share.gift_rate`（3400）而不是女方等级的私信分成**，而且**默认不套未实名系数**
     （`share.unverified_apply_gift` 默认 0；私信那边默认是要套的 —— 两边口径不同，别想当然套用）。
     为此在 `MessagePricingService` 加了 `giftShareRateOf()`（钱的计算仍然只在这一个地方）。
- 校验：**只有男用户能送、只能送给女用户**（自己送自己/男送男 → 30003，女用户送 → 40001）、
  被拉黑 → 30002、礼物不存在或已下架 → 40001 且**什么都不变**、余额不足 → 20001 且整个事务回滚。
- 接口：`GET /gift/list`（只返回上架的，按 sort 排）、`POST /gift/send`（返回礼物消息 + 最新余额）。
  P23 和 P31 用同一个列表，所以只有这一个接口。
- **顺手做的重构**（两处逻辑必须一致，不能各写一份）：
  - `ConversationService`：把「锁住会话 / 没有就建一条 / 落消息后的未读与最后一条维护」抽出来，
    `MessageBillingService` 和 `GiftBillingService` 共用（`male_sent_count` 的规则也只在那一处）；
  - `Concerns\RetriesTransactions`：并发首次建会话会撞死锁（1213）的重试包装，两个计费服务共用。
- 验收（`06` 第 10 节场景 14~16 全过，15 个计费用例 + 6 个接口用例）：
  14. 送 120 金币礼物 → 男方 **-120**（余额 500→380）、女方 **+408 积分**（文档写死的数）、
      赠送记录/金币流水/积分流水/平台收入各一条、礼物消息进会话；
  15. 余额不足 → 20001，余额不变、**记录与消息都没留下**、女方积分不变；
  16. 礼物消息**不计入 `male_sent_count`**（反证：再发一条普通消息才会 +1）；
  另加：同订单号幂等（只扣一次、只加一次积分）、女用户送/男送男/送自己/被拉黑/礼物下架/礼物不存在、
  未实名系数**默认不打折**且开关打开时按 1700（→ 204 积分）打折、礼物积分跨门槛能触发升级。
- 真服务冒烟（重启后端 curl）：`/gift/list` 6 个礼物；`/gift/send` 送花束 →
  `receiver_points 408 / balance 330`；同 `client_order_id` 再送一次返回**同一条消息**；
  库里核对：`cl_gift_record` 一条（rate 3400 / points 408 / message_id 指向那条消息）、
  女方积分 428（20 + 408）、平台收入 `GIFT coins=120 points=408`、
  **会话 `male_sent_count` 仍是 4（礼物没加 1）**。
- 单测：`bash scripts/check-server.sh` → **310 测试 / 1987 断言**（T5.3 新增 21）、phpstan `[OK] No errors`、money 通过。
- 踩到的几个小坑：
  1. **表单校验跑在鉴权之前**（全局 `ValidationMiddleware` 比路由上的 `AppAuthMiddleware` 早），
     所以「未登录 + 空 body」拿到的是 422 不是 401 —— 测未登录要带**合法参数**才走得到鉴权那一步；
  2. `PointWalletService` **没有 `balanceOf()`**（只有 `summaryOf()`），私信那边的 `CoinWalletService` 才有；
  3. 测试里**不能写死礼物 id**：`cl_gift` 清空后自增不重置，所以种完要把 id 记下来用（写死 `giftId: 4` 会随跑的次数漂）；
  4. 顺带把 T5.1 的 `WalletControllerTest` 里 Lv5 语音/视频单价从 300/600 改成 `06` 文档的 **200/400**
     （当时照记忆写的，和真正的种子文件对不上）。

### T5.4 礼物面板与弹窗（P23、P31）—— ✅ 完成（2026-10-13）

- 读：`01` P23/P31；设计图 23、31（自检项 `D` = 设计图走查）
- **P23 和 P31 是同一块内容**（`01` P23 写明「与聊天页底部弹窗共用」），所以只写一套：
  `GiftPanelUiState` + `GiftPanelContent`（`GiftGridSection` 是可滚动那部分、`GiftSendButton` 是钉底那颗），
  P23 用 `ClScaffold(fixedBottom = …)` 把按钮钉住，P31 直接按顺序放进弹窗。两个页面各一个 Route 包一层。
- 状态（`GiftPanelViewModel`）：
  - `enter(receiverId, force)` 幂等（同一个对象再打开面板不重复拉数据）；
  - **订单号 `pendingOrderId` 失败重试时复用、成功之后清空、换礼物时重新生成** —— 和聊天重发不换
    `client_msg_id` 一个道理，服务端靠它幂等，换了就会重复扣钱（单测把这条钉死了）；
  - 20001 走 P30：**和发私信共用同一份弹窗**，为此把它从 `ChatScreen` 的私有实现提到
    `feature/common/CoinNotEnoughSheet.kt`；`data` 解析不出来时用礼物价兜底 —— 静默什么都不做的话用户会以为送出去了。
- 入口三条：P05 底部栏「送礼物」（女号不显示）→ P23；聊天页工具栏「送礼物」→ P31；
  聊天页送完礼物后**收费条要跟着刷新**（`ChatRoute` 用 `LaunchedEffect(giftState.sentCount)` 再调一次 `enterChat`）。
- **走查抓到并修掉 1 个真 bug：送完礼物回聊天页看不到礼物气泡。**
  服务端的推送只发给收礼那一边（`PushService::messageReceived`），而送礼走的是 `/gift/send`、
  **不是**发私信那条乐观落库的路，所以送礼这一端本地库里根本没有这条消息 ——
  聊天页只从 Room 读，自然什么都不显示。修法：`GiftRepository.send()` 成功后调新增的
  `ChatRepository.storeSent(dto)`，把服务端返回的礼物消息补写进本地库（会话摘要顺带变成「[礼物]」）。
  这个 bug 单测和 lint 都看不见，**只有送完礼物不离开聊天页才会暴露**；补了 `GiftRepositoryTest` 6 条锁住。
- 真机走查（模拟器 + 真后端；截图 `docs/screenshots/t54-*.png`）：
  | 步骤 | 结果 |
  |---|---|
  | P05 入口 | 男号底部栏有「私信聊天 10 币/条」与「送礼物」，收费条正常 |
  | P23 整页 | 6 个礼物网格、默认选中第一个、`可用余额 330 金币`、按钮「赠送一朵玫瑰 · 10 金币」；选「浪漫花束」送出 → **余额 330 → 210**，按钮文案跟着换 |
  | P31 弹窗 | 聊天页点「送礼物」→ 标题「送给 晚晴」、同样的网格、`可用余额 210 金币` |
  | 送礼后当场看聊天 | 选「心动气球」送出 → 弹窗里余额 **180 → 120**，关掉弹窗 **礼物气泡已经出现在聊天页**（全程没退出页面），收费条同步刷成「私信 10 币/条 · 余额 120 币」 |
  | 余额不足 | 选 680 的「心动城堡」→ P30「120 金币 / 本次操作需要 680 金币，还差 560 金币」，余额没变、也没留下气泡 |
- 顺带记一笔：聊天页初次进入时会 `loadInitialMessages()` 从服务端补历史，
  所以**光看「气泡最终能显示出来」不足以说明落库路径是对的** —— 那条路会把服务端历史整个补进 Room。
  要验这条链必须**送完不离开页面**再看。
- 单测：`bash scripts/check-android.sh` → **305 单测**（T5.4 新增 15：`GiftPanelViewModelTest` 9 + `GiftRepositoryTest` 6）
  + `lintDebug` + `assembleDebug` + `check-ui.sh` 全过。

### T5.5 会员 —— ✅ 完成（2026-10-13）

- 读：`06` 第 6 节（自检项 `S`）
- 迁移 `2026_10_13_000010_create_cl_vip_tables.php`：`cl_vip_plan` + `cl_vip_order`。
  订单里把**开通前后的到期时间都存下来**（`expire_before` / `expire_after`）——
  续费是在原到期时间上顺延的，只存一个 after 的话事后对不上账（「这次到底加了 30 天还是 45 天」）。
- 种子 `cl_vip_plan_seeder_20261013.php`：月卡 30 天 390、季卡 90 天 990、年卡 365 天 2990 金币（`06` 第 2.4 节）。
- `VipService`：
  - `info()` → `is_vip`、`expire_at`、`plans[]`、`benefits[]`、`balance`、`notice`；
  - `buy()` → **一个事务**里：锁用户行 → 写订单 → 扣金币（`type = VIP`）→ 改 `vip_expire_at`；
  - **只有男用户能开通**（`40001`）：女用户 App 里根本没有会员中心入口。
- 三个容易错的点，都写在代码注释里了：
  1. **订单要先落库再扣钱**：`CoinWalletService::decrease` 要拿订单 id 当 `ref` 做去重，
     所以事务里的实际顺序和文档第 6 节写的不完全一样（文档是「扣钱 → 延期 → 写订单」），
     但**同一个事务**，出错一起回滚；
  2. **必须锁用户行**（新加的 `UserRepository::lockById`）：`vip_expire_at` 是 `cl_user` 上的字段，
     两个并发续费都不锁的话，两边读到同一个旧到期时间、各自加一次天数，后提交的把先提交的覆盖掉
     —— 用户付了两次钱只拿到一次天数；
  3. **幂等查两次**：事务外查一次（网络重试的直接快路，不用开事务），
     拿到用户行锁之后再查一次（并发打同一个订单号时，第二个协程在这里就看见第一条订单了）。
- `cl_vip_order.order_no` 就是客户端的 `client_order_id`（`05` 只写了 `order_no unique`、
  `/vip/buy` 传的是 `client_order_id`，两边合起来只有这一种解释），和 `cl_gift_record` 一个用法。
- `/vip/info` 的权益网格按 `01` P12 的四项（查看访客 / 查看喜欢我的人 / 会员标识 / 每日额外免费私信 N 条），
  **N 从 `vip.daily_free_messages` 取**；图标和文案都由服务端给，客户端只管铺格子。
  顺带记一笔：设计图 12 上的四项是通用模板（优先推荐 / 查看访客 / 更多招呼 / 个性装扮），
  和 `01` 不一致 —— **按 `01` 做**，`01` 是客户确认过的需求文档。套餐另给一个 `price_yuan`，
  对应设计图横幅的「¥39 / 月」（`390 金币 ÷ coin.per_yuan 10`），换算也放服务端（铁律 6）。
- **`06` 第 6 节「会员每日免费条数接入 quote」这一项本来就已经做完了**（T1.6/T4.2 就位）：
  `MessagePricingService::quote()` 第 3 步、`VipFreeQuota`（Redis `vip_free:{userId}:{yyyyMMdd}`）、
  `MessageBillingService` 只在自己真的用了额度时才 `increment`。本次只是核对，没有改。
- 验收（`06` 第 10 节场景 21、22 全过，共 17 个新用例）：
  21. 金币购买月卡 → 扣 390（500→110）、到期 +30 天、订单/金币流水（`type=VIP`、`ref_id` 指向订单）各一条；
      **未过期续费在原到期时间上加 30 天**；**已过期则从现在开始算**（不能从过期那天往后接）；
      季卡/年卡各自的天数与价格、第二单从第一单的到期时间往后接；同一订单号重放只扣一次；
  22. 余额 100 买 390 的月卡 → `20001`，且**余额不变、订单不留下、金币流水不留下、`vip_expire_at` 不变**；
  另加：女用户买 → `40001`、套餐下架/不存在 → `40001` 且余额不变、`/vip/info` 隐藏下架套餐、
  权益文案随配置变、过期后 `expire_at` 返回 null 而不是一个过去的日期。
- 真服务冒烟（重启后端 curl，男号 13800000001）：
  `/vip/info` → 3 个套餐（`price_yuan` 39/99/299）+ 4 条权益 + `notice`；
  买月卡 620 → **230**、`expire_at 2026-11-12 20:32:52`；**同订单号重放返回一模一样的结果、余额还是 230**；
  再买一次（新订单号）→ `expire_at 2026-12-12 20:32:52`（**在原到期时间上加 30 天**）、余额 1230 → 840；
  余额不足时 `20001 {balance:120, needed:390}`，`/vip/info` 仍是 `is_vip=false`。
- 单测：`bash scripts/check-server.sh` → **327 测试 / 2089 断言**（T5.5 新增 17）、phpstan `[OK] No errors`、money 通过。
- 踩到的小坑：新模型 `VipOrder` 的 `@property` 一开始写成 `\DateTimeInterface`，phpstan 立刻报
  「`toDateTimeString()` 不存在」——本项目的约定是 `\Carbon\Carbon`（见 `User`/`Conversation` 的写法）；
  另外测试里别拿两次 `Carbon::now()` 精确比时间戳，跨过一秒就挂（真跑出来过），改成 ±5 秒的窗口。

### T5.6 会员中心页（P12）—— ✅ 完成（2026-10-13）

- 读：`01` P12；设计图 12（自检项 `D`）
- 结构：深色 VIP 横幅（`ClGradients.vipCard`，右下角是**选中套餐**的人民币价 + 天数）→ 会员专属权益 2×2
  → 选择套餐 3 张卡 → 「会员与聊天费用」提示卡 → 「也可联系客服开通」小字 → 钉底按钮。
- 新增 `VipApi`/`VipRepository`/`feature/vip`（UiState + ViewModel + Screen + Route），
  替换掉 `Routes.VIP` 原来的 `placeholder`；P08/P09 的「开通会员」本来就指着它，这次终于有页面了。
- 两个页面级决定：
  1. **横幅和按钮跟着选中套餐变**：切到年卡，横幅写 `¥299 / 365 天`、按钮写「立即开通 年卡 · 2990 金币」——
     设计图只画了月卡价，但 `01` P12 要求套餐可配、价格卡可切；
  2. **已是会员时横幅副标题换成「有效期至 2026-11-12」、按钮写「续费」**（`01` P12：续费到期时间顺延）。
- 订单号策略和礼物/发消息完全一致：**失败重试复用、成功清空、换套餐重新生成**（单测钉死）。
  20001 复用 `feature/common/CoinNotEnoughSheet`（P30），跳充值前先关弹窗（T4.8 那个坑）。
- **真机走查抓到 2 处要改的**（都是单测和 lint 看不见、只有看渲染结果才发现）：
  1. **套餐格子把套餐名塞进了「大数字」位**：`ClRechargePackGrid` 的格子结构是
     「大数字 + 写死的『金币』+ 一行说明」，我先按「金币数」塞了 `月卡`，渲染成「月卡 / 金币」很怪。
     改成 `coins = 价格数字`、`priceText = 月卡 · 30 天`，正好凑成 `01` P12 要的「X 金币 / 30 天」。
  2. **权益第 4 项标题太长被截断**：两列网格的标题**只有一行**、超出打省略号，
     「每日额外免费私信 10 条」被截成「每日额外免费私信 1…」—— 正好把从配置来的那个数字截没了。
     改成标题「每日免费私信」、条数放到能放两行的说明里（「额外 10 条，所有对象合计，每天零点重置」），
     服务端 `benefits()` 和两个服务端用例的断言跟着改。
- 真机走查（模拟器 + 真后端，截图 `docs/screenshots/t56-*.png`）：
  | 步骤 | 结果 |
  |---|---|
  | 入口 | P08 非会员是打码的（`小*`/`晚*` + 头像模糊 + 「开通会员查看全部」）→ 点一行 → 确认弹窗「开通会员查看全部」→ P12 |
  | P12 首屏 | 深色横幅「畅撩交友 VIP / 更高曝光 · 更多匹配机会 · 专属互动权益」+ `¥39 / 30 天`；4 项权益；3 张套餐卡（390/990/2990，默认选中月卡）；提示卡；小字；按钮「立即开通 月卡 · 390 金币」 |
  | 切套餐 | 点年卡 → 横幅变 `¥299 / 365 天`、按钮变「立即开通 年卡 · 2990 金币」 |
  | 余额不足 | 买年卡 → **P30「840 金币 / 本次操作需要 2990 金币，还差 2150 金币」**，余额没变 |
  | 开通成功 | 改选月卡买 → 横幅「有效期至 2026-11-12」、按钮「续费 月卡 · 390 金币」、余额 **840 → 450**（重启后接口核对一致） |
  | 开通后回 P08 | **不再打码**（小满 / 晚晴全名），「开通会员查看全部」消失 |
- 顺带修的一个小毛病：`ClGradientHero` 的文字颜色写死了 `onPrimary`（深色），
  用在深色渐变上会糊在一起；加了 `contentColor` 参数（默认值不变，不影响别处），VIP 横幅传浅色。
- 踩到的两个坑：
  1. **后端进程名是 `MineAdmin.Master` / `MineAdmin.Worker.*`**（Swoole 改了进程名），
     `pkill -f 'hyperf.php start'` 根本匹配不到，端口一直被占（表现为重启报 98 address in use）。
     要么 `pkill -f '[M]ineAdmin'`，要么用 `pgrep -a php` 看真实进程名；
  2. `LocalCache` 有 60 秒节流：改完服务端数据再进页面**还是旧的**（stale-while-revalidate 先给缓存），
     走查时要**下拉刷新**才会重新拉。不是 bug，但不知道的话会误判成「改坏了」。
- 单测：新增 `VipCenterViewModelTest` 11 条（含订单号复用/重生成、P30、到期时间文案）。
  `bash scripts/check-all.sh` → **`✅✅✅ 全部检查通过`**（server 327 测试 + phpstan + money；
  admin lint+build；android **316 单测** + `lintDebug` + `assembleDebug` + `check-ui.sh`）。

### T5.7 每日任务 —— ✅ 完成（2026-10-13）

- 读：`06` 第 7 节（自检项 `S`）
- 迁移 `2026_10_13_000011_create_cl_task_tables.php`：`cl_task` + `cl_task_log` + `cl_sign_in`。
  三张表分工写在迁移注释里：配置 / 领奖流水（唯一索引 `(user_id, task_id, period_key)`）/ 签到流水
  （唯一索引 `(user_id, sign_date)`）。
- 种子 `cl_task_seeder_20261013.php`：4 种任务 × 男/女 = 8 行，名称和说明取自设计图 11
  （每日签到 / 完善照片 / 写一句自我介绍 / 和新朋友打招呼）。
  **男女各一行**是因为奖励单位不一样（男发金币、女发积分）—— `cl_task` 里因此**没有「单位」字段**，
  单位跟着**领奖人的性别**走；同一件事男女奖励不同是两行配置，不是一行里的两个字段。
- `TaskService`：`list()` / `signIn()` / `claim()`。
  - **完成条件全部服务端现算**（`01` P11：App 只负责点按钮）：签到看 `cl_sign_in`、
    照片数看 `cl_user_photo` 里过审的、自我介绍看 `cl_user.bio` 非空（审核通过才会写进这一列）、
    打招呼看今天给多少个**不同的人**发过私信；
  - **只有一处发钱**：私有的 `grantOnce()`，`/task/claim` 和 `/task/sign-in` 都走它，
    所以「同一周期只发一次」这条规则只写了一遍（唯一索引兜底，并发撞了就靠 `RetriesTransactions` 重试）。
- 三个刻意的决定（都写进注释了）：
  1. **签到一次点击就拿到奖励**（设计图 11 的签到卡只有一个「签到」按钮，没有再点一次「领取」）。
     `/task/claim` 会拒绝 SIGN_IN 任务（「签到请点签到」），免得奖励有两条路可拿；
  2. **「和新朋友打招呼」只算文字和图片消息，礼物不算** —— 礼物和私信一直是两套口径
     （`GiftBillingService` 特意不把礼物计进 `male_sent_count`），这里沿用同一条线；
  3. 列表里的**按钮文案和 action 都由服务端给**（`签到/领取/去完成/去认识/已完成` + `sign_in/claim/edit_profile/discover/none`），
     客户端只管按 `action` 走，不自己推断。
- 验收（`06` 第 10 节场景 20 + 任务清单上的四条全过，共 25 个新用例）：
  - **同一周期只能领一次**：领两次 → `40001`，钱包只加了一次，`cl_task_log` 只有一行；
  - **按性别过滤**：男女各看到 4 条、**id 完全不重叠**；下架的任务不出现；异性的任务领不了；
  - **男得金币女得积分**：男的走 `CoinWalletService`（`type=TASK`）、女的走 `PointWalletService`，
    女号的**金币钱包一分不动**；
  - **场景 20**：女号签到 +20 积分，`cl_user.total_earned_points` **不变**（升级只认私信和礼物分成）；
  - 另加：完成条件（照片要过审、3 个**不同的人**才算数、昨天的消息不算今天）、
    每日任务跨天重新可领而一次性任务跨天仍是已完成、没完成领不了、签到卡不能走 claim。
- 真服务冒烟（重启后端 curl，男号阿宁 / 女号晚晴）：
  男号列表 4 条（`写一句自我介绍` 已是「可领取」、`和新朋友打招呼` 进度 1/3）；
  签到 **450 → 455**、再签一次**余额还是 455**；领自我介绍 **+10**（455 → 465）；
  再领 → `40001 这个任务本期已经领过了`；领没完成的照片任务 → `40001 任务还没完成`；
  女号看到的是**另一组任务**（`+20 积分` 文案、id 2/4/6/8），签到 +20 积分。
- 单测：`bash scripts/check-server.sh` → **352 测试 / 2197 断言**（T5.7 新增 25）、
  phpstan `[OK] No errors`、money 通过。

### T5.8 每日任务页（P11）—— ✅ 完成（2026-10-13）

- 读：`01` P11；设计图 11（自检项 `D`）
- 结构：橙黄横幅（`ClGradients.banner`）+ 一条条任务卡（`ClTaskCard`，图标方块 + 名称 + 说明 + 右侧奶黄按钮）。
  页面里**一行完成条件判断都没有** —— 状态、进度、按钮文案、点下去干什么全部来自 `/task/list`。
- 新增 `TaskApi`/`TaskRepository`/`feature/task`（UiState + ViewModel + Screen + Route），
  替换掉 `Routes.DAILY_TASK` 原来的 `placeholder`。
- 两个设计决定：
  1. **签到/领奖成功之后重新拉一次列表**，而不是在本地把状态改掉：
     「已完成 / 可领取」由服务端说了算，本地推一遍等于把判断规则抄了第二份，早晚会跑偏。
     奖励文案（「+5 金币」）用服务端返回的那份做 toast —— 男金币女积分，客户端不用知道区别；
  2. **按钮点下去干什么由服务端给的 `action` 决定**（`sign_in` / `claim` / `edit_profile` / `discover`），
     Route 只做分派：前两个调接口，后两个跳页面。
- 「去认识」的跳法要说明一下：发现页是底部导航里的一个 **Tab**，它的选中状态在主界面的 ViewModel 里，
  从 P11（主界面之外的路由）改不了 —— 所以走的是「把主界面整个重建一次」
  （`navigate(MAIN) { popUpTo(MAIN) { inclusive = true } }`，`MainViewModel` 默认就停在发现 Tab）。
  以后 P04 做完了如果读者觉得重建太重，可以改成和 `DiscoverTabIntent` 一样的单例 intent。
- 顺手把 dev 入口补齐：`我（P04 开发中）` 里加了「会员中心（P12）」「每日任务（P11）」
  （T5.6 做完时 P12 只能从 P08 打码页绕进去，现在直接可点）。
- 真机走查（模拟器 + 真后端，截图 `docs/screenshots/t58-*.png`）：
  | 步骤 | 结果 |
  |---|---|
  | 首屏 | 横幅「今天也有好事发生 ✨ / 完成任务，收集玫瑰，让更多同频的人看见你。」+ 4 张任务卡（📅 每日签到 / 📷 完善照片 / ✍️ 写一句自我介绍 / 💬 和新朋友打招呼），图标和说明都是后台配的 |
  | 四种按钮状态 | 同一屏上同时出现 `签到`（未签到）、`去完成`（照片不够）、`领取`（自我介绍已完成）、`去认识`（还没打够招呼）—— 和 `01` P11 的按钮清单对得上 |
  | 点「签到」 | 卡片立刻变灰「已完成」、余额 **465 → 470**（+5 金币） |
  | 点「领取」 | 「写一句自我介绍」变灰「已完成」、余额 **470 → 480**（+10 金币） |
  | 点「去完成」 | 进 P14 编辑资料（拍照片、写自我介绍都在那一页） |
  | 点「去认识」 | 回到「遇见」Tab，右上角余额角标显示 **480 币**，两笔奖励对上了 |
- 走查用的重置脚本（`runtime/task-reset.php`，清掉演示号的签到与领奖记录好把四种状态都点一遍）**跑完就删了**，
  `runtime/` 本来也不进版本库。
- 单测：新增 `DailyTaskViewModelTest` 12 条（含「签到成功后要重新拉列表」「已领过的是灰按钮」
  「正在操作时忽略重复点击」）。`bash scripts/check-android.sh` → **328 单测**
  （T5.8 新增 12）+ `lintDebug` + `assembleDebug` + `check-ui.sh` 全过。
- 又踩了一次 JUnit 的 `assertEquals(message, expected, actual)` 参数顺序（消息在最前面），
  第一次写成了 `assertEquals("", actual, "说明")`，失败信息正好被当成期望值报出来。
- **注**：T5.9 阶段验收时发现这张卡少了 `01` P11 要求的「奖励」那一行，已在 T5.9 补上 ——
  所以本节的 `t58-*.png` 截图里看不到「+5 金币」，`t59-p11-rewards-*.png` 才是补完后的样子。

### T6.1 等级 —— ✅ 完成（2026-10-13）

- 读：`06` 第 5 节（自检项 `S`）
- **先分清哪些是新的**：`LevelService::checkUpgrade` 在 P1/P4 就写好了（只升不降、一次跨级、
  锁了不升），也早就接进了私信和礼物（场景 17~19 在 `MessageBillingTest` 里有用例）。
  所以本任务真正要做的是两件：**升级通知**（场景 17 那句「并发通知」当时没做，因为通知表还没建）
  和三个只读接口。
- 迁移 `2026_10_13_000012_create_cl_notice_tables.php`：`cl_notice` + `cl_notice_read`。
  两张表是有原因的：升级/实名/提现这类是**一对一**通知，已读时间直接写 `cl_notice.read_at`；
  `user_id = 0` 是**全员公告**，一个人读没读写不到那一行上，所以另开一张「谁读过哪条公告」。
- `NoticeService::send()` / `announce()`：T6.1 只做「发一条」这条路，
  列表、已读、`notice.new` 推送留给 T6.4。
  **刻意不吞异常**：通知和它要通知的那件事（升级）写在同一个事务里，
  所以不会出现「等级升上去了、通知没发出来」；跨进程的推送才需要吞异常（`PushService` 就是那样）。
- `IncomeQueryService` 三个只读方法（改钱仍然是 `PointWalletService` 的事）：
  - `summary()`：可用/冻结积分、约合人民币、今日收益积分、今日收到消息数、今日收到礼物数、底部说明；
  - `logs()`：积分流水（P26 的明细），**不过滤类型** —— P26 要求私信收益、礼物收益、任务奖励、
    提现的三种状态都列出来，客户端按 `type` 挑图标；
  - `level()`：当前等级、累计收益、下一级门槛与进度、我的单价、**等级表里的分成**与**我实际拿的**
    （未实名打折）、礼物分成、全部等级表；`feature.call_enabled` 开着时带上语音/视频单价。
- 三个口径上的决定（都写进注释了）：
  1. **「约合人民币」给的是「分」**（`yuan_fen`）：1162 积分 ÷ 100 = 1162 分 = ¥11.62，
     客户端拿现成的 `MoneyFormat.rmb()` 直接显示。给整元会把 11.62 抹成 11（铁律 6：换算在服务端）；
  2. **「今日收益积分」只算私信和礼物分成**，和 `cl_user.total_earned_points` 一个口径 ——
     任务奖励虽然是进账，但 `06` 第 5 节明说它不算累计收益，所以也不算「收益」；
  3. **单价和分成全部现算**（`cl_level_config` + 配置中心），后台改了比例 P27 立刻跟着变。
- 验收（`06` 第 10 节场景 17~20 全过，共 13 个新用例）：
  - 场景 17 的「并**发通知**」这句现在有断言了：升到 Lv2 时 `cl_notice` 里多一条
    系统通知（标题「等级提升」、`biz_type = LEVEL_UP`、`biz_id` = 新等级、未读）；
  - **只升不降**：后台手动提到 Lv4 之后、累计收益只有 100，检查也不降级、也不发通知；
  - 锁定等级不升、男用户永远不升、一次跨两级只发一条通知、反复检查不会重复通知；
  - 接口：三个接口的数值（余额、约合分、今日三项、进度百分比、实际分成打五折、等级表）、
    分页倒序、顶级没有下一级、男号 `40001`、未登录 `401`。
- 真服务冒烟（重启后端 curl）：见「上次停在哪」。核心几个数：`yuan_fen 1162`（¥11.62）、
  `share_rate 4000 / actual_share_rate 2000`（未实名五折）、`next_threshold 300000`、
  男号 `40001 收益与等级只有女用户有`。
- 顺带发现：演示号晚晴是**种子直接把等级设成 Lv3**、但累计收益只有 1142（远不够 Lv3 的 10 万门槛）——
  也就是说她是个「只升不降」的真实样本：现在跑 `checkUpgrade` 只会算出 Lv1，
  被「不降级」那条挡住。这正好验证了规则，不是数据错。
- 单测：`bash scripts/check-server.sh` → **365 测试 / 2266 断言**（T6.1 新增 13）、
  phpstan `[OK] No errors`、money 通过。
- **待确认（记一笔，不阻塞）**：`01` P27 有一个「在线接听」开关（控制自己是否出现在发现页「推荐」里），
  但 `docs/05` 第 4.8 节的收益接口清单里**没有这个开关的接口** —— 已记进待确认表 **Q0-23**，
  T6.5 做 P27 时定（要么加一个开关接口，要么归到 P15 设置里）。

### T6.2 实名 —— ✅ 完成（2026-10-14）

- 读：`04` 第 1 节 `cl_real_name`、`01` P29（自检项 `S`）
- 迁移 `2026_10_13_000013_create_cl_real_name_table.php`：`cl_real_name`（`user_id` 唯一 ——
  一个人一条，重新提交是**覆盖**不是新增）。
- **身份证号一个字的明文都不落库**（这是本任务的核心）：
  - `id_card_encrypted` = AES-256-**GCM** 密文，存 `base64(IV | TAG | 密文)`。
    选 GCM 而不是 CBC：GCM 自带完整性校验，密文被改了能立刻发现；
  - `id_card_mask` = `110***********1233`（留前 3 后 4，`01` P29 的格式），页面回显用这个；
  - `id_card_hash` = SHA-256，只给后台**提示**「这号别的账号也用过」，**不拦截** ——
    一家人共用一张身份证是合理的，拦了反而是 bug（有专门用例钉住「两个号用同一个号都能提交」）。
- 密钥：新增 `config/autoload/cl.php` 放 `CL_ID_CARD_KEY`（`.env` 里已有）。
  **不直接用配置字符串当密钥**，先 SHA-256 成固定 32 字节 —— `.env` 那串长度不确定
  （示例值是 base64 的一大坨、有人会直接写一句口令），固定长度才不会把加密搞坏。
- 校验位：GB 11643-1999 的加权因子表 + 校验码表，前 17 位加权求和 mod 11 查表得第 18 位
  （余数 2 → `X`）。**格式 + 校验位**两样都查（`01` P29 的要求）。
- 状态机（`cl_real_name.status` 与 `cl_user.real_name_status` 同步维护）：
  未提交 → 审核中（`40002` 挡重复提交）→ 已通过 / 已驳回（**驳回可以重提**，重提会清掉上次的驳回原因）；
  已通过再提交 → `40001`「已通过实名认证，无需重复提交」。
- **顺手把审核动作也做了**（`approve()` / `reject()`）：`docs/08` 里 T6.2 只写「做两个接口」，
  但没有审核动作的话 `real_name_status` 永远到不了 2、P29 的「已通过」和「分成不打折」都没法验；
  T6.3 给提现也是这个路子（「审核三个动作的 Service 方法，后台接口在 P7 接」），所以照着做。
  这两个方法里顺手发了实名结果通知（`NoticeService` T6.1 刚建好）——
  T6.4 的「各业务事件发通知」清单里还有提现、封号、被关注、收到礼物几条。
- 验收（共 20 个新用例）：
  - **格式错 40003**：校验位错、位数不对、含非法字符、空号、姓名为空；
  - **审核中不能重复提交 40002**（接口层也断言了业务码和文案）；
  - **返回打码号**：`/realname` 和对 `/realname/submit` 的响应里都只有 `110***********1233`，
    整个响应 JSON 里搜不到完整号码；
  - **数据库里没有明文**：密文 ≠ 明文、`LIKE '%明文%'` 一条都搜不到、`decrypt()` 能还原一致；
  - 另加：末位是 `X` 的号要收；被驳回可重提且是覆盖（`cl_real_name` 仍只有一行）；
    通过后 `shareRateOf` 从打五折变回原比例；通过/驳回各发一条通知、驳回必须填原因。
- 真服务冒烟（重启后端 curl）：见「上次停在哪」。库里的三个字段实测：
  `id_card_encrypted = GcUyEclpZR3Ow/uvAjax8RLGTHUD+5Oiuoy0cdqZpO6SJmsBYlek6oJIn/buyg==`、
  `id_card_mask = 110***********1233`、明文出现次数 **0**、解密还原一致；
  `approve()` 之后 `/income/level` 的 `actual_share_rate` **2000 → 4000**。
- 踩到一个小坑：curl 从 Git Bash 传中文 body 会变成「Malformed UTF-8」——
  不是服务端的问题，是 shell 编码。把 JSON 写成文件再用 `--data-binary @file` 就正常了。
  （以后再冒烟带中文的接口，都用这招。）
- 单测：`bash scripts/check-server.sh` → **385 测试 / 2345 断言**（T6.2 新增 20）、
  phpstan `[OK] No errors`、money 通过。

### T6.3 提现 —— ✅ 完成（2026-10-14）

- 读：`06` 第 9 节全文、`04` 第 4 节（自检项 `S`）
- 迁移 `2026_10_13_000014_create_cl_withdraw_tables.php`：`cl_withdraw_order` + `cl_withdraw_account`
  （「上次用的收款信息」，下次提现默认带出来）。
- `WithdrawService`：
  - `apply()` 按 `06` 第 9.1 节的八步：实名（开关）→ 档位 → 今日次数 → 需要积分 → 锁钱包判余额
    → 算手续费 → 冻结 → 写单；
  - `info()` / `records()` 两个只读接口；`approve()` / `reject()` / `markPaid()` 三个审核动作
    （后台 HTTP 在 P7 接，和 T6.2 一个路子）。
- 四个刻意的决定（都写进注释了）：
  1. **金额一律用「分」算**：手续费是「金额分 × 万分比 ÷ 10000 向下取整」（`intdiv`），
     用分算才不会出现浮点误差（铁律 6）；
  2. **单据先落库再冻结**：冻结流水的 `ref_id` 要指向单据 id（送礼物、买会员也是这个顺序）；
  3. **收款人一律取实名姓名**（`account_name`），不接受客户端传 —— 免得把钱提到别人账上；
     所以 `cl_withdraw_account` 里**没有姓名字段**；
  4. **审核动作先 `lockForUpdate` 锁单据行**再校验状态：两个人同时点「通过」时，
     第二个协程会等第一个提交、然后看到新状态被拒，不会两个都成功。
- 状态机照 `06` 第 9.2 节的表走：待审核 → 通过 → 已打款；待审核/通过 → 驳回。
  别的组合一律 `40001`（场景 30 的两条：**已打款的不能再驳回、已驳回的不能再打款**）。
  每次状态变化都给女方发系统通知（T6.1 建的 `NoticeService`）。
- 验收（`06` 第 10 节场景 23~30 全过，共 27 个新用例）：
  23 未实名被拒（开关关掉也能提）、24 金额不在档位里被拒、25 积分不足 `20002`（带 `balance`/`needed`，
  整单回滚、没有冻结流水）、26 当天第 2 次被拒（**驳回的也算次数**）、
  27 申请后可用 −5000、冻结 +5000、单据 `PENDING`、收款人是实名姓名、
  28 驳回后可用 +5000、冻结 −5000（`WITHDRAW_REFUND`）、
  29 通过后仍冻结、打款后冻结清零 + `total_withdrawn` +5000（`WITHDRAW_PAID`）、
  30 已打款不能驳回 / 已驳回不能打款；
  另加：手续费按分向下取整（5% → 250 分）、档位列表按积分够不够置 `enabled`、
  收款信息保存与回填、男号 `40001`、收款方式/账号/开户行的校验、`daily_limit = 0` 表示不限。
- 真服务冒烟（重启后端 curl + 一次性脚本）：见「上次停在哪」。库里核对到：
  `approve` 之后**冻结仍是 5000**（不改积分），`markPaid` 之后**冻结 0**，
  `reject` 之后**可用 6200 → 11200**（原路退回）。
- **顺手修了一个测试互相污染的问题（这次最值得记的一条）**：
  跑完整套用例时发现 `ChatQueryControllerTest` / `ChatSendControllerTest` 有 3 个用例挂了，
  但 `composer test` 是绿的。原因是我新加的 `RealNameServiceTest` / `IncomeControllerTest` /
  `LevelServiceTest` 在 `clearAll()` 里 **`LevelConfig::query()->delete()` 把共享的
  `cl_level_config` 整表删了** —— 那 5 行是 `db:seed` 一次性种下的，别的测试类（私信计价要用女方等级）
  要靠它；删了之后**谁的用例先跑谁吃亏**，`composer test` 之所以绿只是因为它在前面先跑了迁移组把库重置了。
  改法照 `GiftBillingTest` 的既有做法：**按 level 覆盖写（upsert），不删整表**。
  改完重跑「不带迁移组」的整套用例，失败数从 10 掉回 7（剩下那 7 个是 MineAdmin 自带的、`composer test` 里排除的已知失败）。
  教训：**测试类只该清理自己造的数据，共享的种子表要用 upsert**。
- 又踩了一次 T6.2 记过的坑：curl 从 Git Bash 传中文 body（`"bank_name":"招商银行"`）报 422/乱码，
  换成 ASCII 就通了 —— 带中文的接口冒烟一律用 `--data-binary @文件`。
- 单测：`bash scripts/check-server.sh` → **412 测试 / 2483 断言**（T6.3 新增 27）、
  phpstan `[OK] No errors`、money 通过。

### T6.4 通知与举报 —— ✅ 完成（2026-10-14）

- 读：`04` 第 6 节 `cl_notice`、`04` 第 3 节 `cl_report`、`05` 第 4.9 节（自检项 `S`）
- 通知这条线现在完整了（表是 T6.1 建的，这里补齐「发 → 列 → 读 → 未读」四件事）：
  - `NoticeService::send()` 是**唯一出口**，并且顺手推一条 `notice.new`；
  - 业务事件的快捷方法：`followed()` / `giftReceived()` / `profileReviewed()` / `banned()`
    （文案统一在服务端拼，别处只传「谁、对谁、什么东西」）；
  - `list()`：某个分类的通知 + **打开即标已读**（`05` 原文），返回的 `read` 是
    「打开**之前**读没读过」，页面据此决定要不要画红点；
  - `unreadCount()`：一对一未读 + **全员公告里我没读过的**（公告的已读状态在 `cl_notice_read`，
    用 `NOT EXISTS` 子查询；这张表存在的意义就在这里 —— 公告只有一行，一个人读没读写不到它身上）。
- **把推送写在 `send()` 里、不等事务提交**，这是个刻意的取舍（代码注释里写了）：
  `notice.new` 的载荷**自包含**（标题正文都在里面），客户端不用回查就能直接显示，
  所以「推送比提交早几毫秒」不会让用户看到错的东西；反过来把推送交给 7 个调用方各自记得推，更容易漏。
- 各业务事件接上了：
  - **被关注** → `RelationService::follow`（`FollowRepository::add` 改成返回「这次是不是真的新建了」，
    **只有新关注才发通知**，重复点关注不该反复打扰对方）；
  - **收到礼物** → `GiftBillingService`（写在礼物那个事务里：礼物送成了通知就一定有）；
  - 升级 / 实名结果 / 提现结果 T6.1~T6.3 已经接了；
  - **资料审核结果、封号**的快捷方法已经备好，但它们的动作方是后台管理员（T7），到 T7 调；
    这里先不硬塞一个假的调用点。
- `/notice/list`：`category` **必填**（1 系统 / 2 互动，P10 就两个 Tab，没有「全部」）。
- `/report`：只做提交（处理在 T7.5）。四条校验：原因只认 5 种、不能举报自己、对象要存在、
  **带上来的 `message_id` 必须是自己参与过的** —— 最后这条不是洁癖：被举报关联的消息
  **不参与定时清理**（`04` 第 3 节的保留策略），不挡的话谁都能拿别人的消息把那条永久钉在库里。
- **顺带做掉了 P4 的一条遗留**：`/chat/unread` 的 `notice` 字段以前写死 0
  （T4.3 建它时通知表还没建，只占了个位），现在真的算了 —— `ChatQueryService` 直接读
  `NoticeRepository::unreadCountFor()`（不走 `NoticeService`，避免
  `NoticeService → PushService → ChatQueryService → NoticeService` 的循环依赖）。
- 验收（22 个新用例）：
  - `NoticeServiceTest` 10 条：写库带分类与 `biz_type`、公告 `user_id = 0`、
    四个事件方法的文案与分类、**关注只发一次**（取关再关注会再发）、
    列表能看到「我的 + 公告」而看不到别人的和另一个分类的、**打开即标已读**（第二次 `read: true`）、
    未读=一对一+未读公告、**公告已读是按人分的**、重复标已读不写重复行、分页倒序；
  - `PushServiceTest` 2 条：`notice.new` 的载荷形状（自包含）与**全员公告不推给任何人**；
  - `NoticeControllerTest` 9 条：列表 + 标已读、`category` 必填/越界 422、
    `/chat/unread` 的 `notice` 数、举报成功（含 `images`）、带自己的消息、带别人的消息被拒、
    举报自己、对象不存在 `30001`、原因写错 422、未登录 401；
  - `GiftBillingTest` 1 条：送礼给收礼人发互动通知、送礼人自己不发。
- 真服务冒烟（重启后端 curl）：见「上次停在哪」。
- 又双叒踩了同一个坑：curl 从 Git Bash 传中文 body 报 `500 Malformed UTF-8`
  （T6.2 记过一次）—— 带中文的接口冒烟**一律**写成 JSON 文件 + `--data-binary @文件`。
- 单测：`bash scripts/check-server.sh` → **434 测试 / 2584 断言**（T6.4 新增 22）、
  phpstan `[OK] No errors`、money 通过。

### T6.5 我的收益 / 等级 / 提现 / 实名四个页面（P26~P29）—— ✅ 完成（2026-10-14）

- 读：`01` P26~P29；设计图 26~29（自检项 `D`）
- 新增安卓侧的网络层与数据层：`IncomeApi`/`WithdrawApi`/`RealNameApi` + 三个 DTO 文件 +
  `Income`/`Withdraw`/`RealName` 三个模型 + `IncomeRepository`/`WithdrawRepository`/`RealNameRepository`；
  四个页面各一套 UiState / ViewModel / Screen / Route，替换掉 `Routes.INCOME`、`LEVEL_SETTING`、
  `WITHDRAW`、`REAL_NAME` 的占位页。
- **Q0-23 顺手解决了**：P27 的「在线接听」开关不用新增接口 —— `POST /user/settings` 本来就收
  `show_in_recommend`（P15 那套设置接口），直接接上，真机上验证过关掉之后库里真的写了 0。
- 复用而不是新建组件：P28 的提现档位用 `ClRechargePackGrid`（P22 充值、P12 会员套餐都用它），
  给它加了两个参数 —— `enabled`（积分不够置灰）和 `unit`（充值/会员是「金币」，提现是「元」）。
- 各页按文档而不是设计图办的地方（都写在页面注释里）：
  P26 的三个统计格从设计图的「收益豆/接通次数/通话分钟」改成 `01` P26 的「今日收益积分/收到消息/收到礼物」；
  P27 从设计图的「接听与收费设置」改成 `01` P27 的**只读展示**；
  P28 从设计图的单个「本次申请金额」改成 `01` P28 的**档位可选 + 不够置灰**；
  P29 的「真人活体检测」按 `01` P29 改成「人工审核」。
- **走查抓到并修掉 2 个真问题（单测和 lint 都看不见）**：
  1. **Toast 从来没显示过 —— 8 个页面都中招。**
     `rememberClToast()` 取的是 `ClScaffold` 通过 `CompositionLocal` 提供的宿主状态，
     而我把 `val toast = rememberClToast()` 写在了 **`ClScaffold` 外面** ——
     那样拿到的是 `rememberClToast()` 里的**兜底实例**，没有任何宿主渲染它，Toast 静默消失。
     编译、单测（断言的是 `state.toast`）、lint 全都不报错，所以 T5.2 的 P22、T5.4 的 P23、
     T5.6 的 P12、T5.8 的 P11 一直是「提示不显示」的 —— 之前几次走查只验了状态变化
     （卡片翻成已完成、余额变了），没验提示本身。
     改法：把这 8 个页面的 `rememberClToast()` + `LaunchedEffect` 挪进 `ClScaffold` 的内容里
     （和 `DiscoverScreen`/`ChatScreen` 这些一直是对的页面保持一致）。
     **并在 `check-ui.sh` 加了一条守卫**：`rememberClToast()` 的行号必须大于同文件第一个
     `ClScaffold(` 的行号 —— 这类 bug 只有肉眼看得见，得让脚本盯着。加完立刻又抓出漏掉的第 8 个（P22）。
  2. **P29 出现「当前状态：已通过」+ 表单同时显示。**
     `cl_user.real_name_status` 和 `cl_real_name.status` 是两个来源：状态取 `cl_user`、
     `can_submit` 取 `cl_real_name` 行。演示数据把星野的 `real_name_status` 手写成 2 却没有对应记录，
     于是报「已通过」还让人提交。
     改法：**有申请记录就以记录为准**，没有才退回 `cl_user`，而 `can_submit` 由**同一个 `$status`** 推出来，
     两个字段再也不可能互相矛盾（服务端加了专门用例钉住）；顺带把演示种子里星野的
     `real_name_status` 从 2 改成 0（她本来就没有实名记录）。
- 真机走查（模拟器 + 真后端，截图 `docs/screenshots/t65-*.png`）：
  | 页面 | 结果 |
  |---|---|
  | P26 我的收益 | 大卡「可提现积分 6264 · 约 ¥62.64 · 冻结中 0 积分」（换算对）；今日三项 `+10102 / 0 / 1`；明细按类型挑图标（礼物/钱包/星星/消息），提现冻结 −5000、提现成功 0 都在 |
  | P27 我的等级与收费 | Lv3 + 累计收益 + 进度条 + 「距离下一级还差…」；私信单价 10 币/条、分成 40%（已实名）、礼物分成 34%；等级表 Lv1~Lv5 门槛/单价/分成；接听设置里的「在线接听」开关 |
  | P27 开关 | 关掉 → 开关变灰 + 提示；**库里 `show_in_recommend = 0`**；再打开恢复 |
  | P28 收益提现 | 大卡 ¥62.00 / 6264 积分；档位 50 亮、100/200/500 **置灰**；收款方式三选一、收款账号**自动带出上次填的**、收款人姓名是实名的「晚晴」且不可改；规则说明「今天的次数已经用完了 · 免手续费 · …」；提现记录「已打款 −¥50.00」 |
  | P28 提交失败 | 点提交 → **toast「今天已经申请过了，请明天再来」**（服务端 40001 原样透出；也是这次修好的 Toast 的验证） |
  | P29 已通过 | 状态「已通过」+ 姓名 + **打码号 `110***********1233`** + 没有表单、没有提交按钮 |
  | P29 未认证 | 状态「未认证」+ 表单 + 三步流程 + 信息安全说明 + 灰着的「提交认证」 |
  | P29 提交 | 校验位写错 → **toast「实名信息格式错误」**、表单还在；改对 → **状态变「审核中」+ 打码号 + toast「已提交，等待审核」** |
  | 男号 | 点这四个页面都被服务端拦住（`收益与等级只有女用户有` / `提现只有女用户有`） |
- 单测：新增 4 个 ViewModel 测试类共 **43 条**（RealName 10 / Income 8 / IncomeLevel 10 / Withdraw 15）。
  `bash scripts/check-all.sh` → **`✅✅✅ 全部检查通过`**（server **435 测试 / 2589 断言** + phpstan + money；
  admin lint+build；android **371 单测** + `lintDebug` + `assembleDebug` + `check-ui.sh`）。
- 又踩了两次 JUnit 的 `assertEquals(message, expected, actual)` 参数顺序（消息在最前，累计第四次了）——
  3 参数的写法一律用「消息在前」，或者干脆不写消息。

### T6.6 系统消息（P10）、我的（P04）、设置（P15）+ 举报接通 + 401 回登录页 —— ✅ 完成（2026-10-14）

- 读：`01` P04/P10/P15；设计图 04/10/15（自检项 `D`）
- 三个页面 + 两处接线，一共动了 30 多个文件。**服务端只加了两个小东西**：
  - `POST /auth/cancel` 注销账号：状态置 `CANCELLED` + 拉黑当前 token。
    **余额/积分不动**（没有「注销清零」这种流水类型，铁律 6；`01` P15 写的是「提交后后台处理」，钱归 T7）；
    顺带补了一个洞：**已注销的账号登录要被挡**（以前 `login` 只挡了封禁，
    注销后能登录成功但每个接口都 401，用户只会看到「登录已失效」循环）；
  - `/user/me` 加 `completeness`（资料完善度）：`01` P04 头部要显示，但文档没说**哪些字段算**，
    这里定了一个说得清的口径（P14 那几项 + 至少一张过审相册，共 9 项），和两个任务的条件口径一致。
- 安卓侧：
  - `NoticeApi`/`NoticeRepository`（通知列表 + 举报）、`RelationApi::blocks` + 仓库方法、
    `AuthRepository::changePassword/cancelAccount`、`UserProfile.completeness`；
  - **P10 系统消息**：两个分类 Tab（系统通知 / 互动）+ 通知卡片 + 上拉加载；
    列表**不缓存**（打开即标已读，缓存住会让「红点清了、列表还是旧的」对不上）；
  - **P04 我的**：头部（头像 + VIP 标识 + ID · 完善度 + 等级徽章 + 编辑）+ 四格统计 + 钱包卡 + 菜单。
    **男女两套菜单在 [MineUiState] 里拼好**，页面只照着画 —— 这样「女号不该出现会员中心」
    这类规则能被单测钉住（8 条菜单用例）；
  - **P15 设置**：消息提醒（本地 DataStore）/ 展示给附近的人（复用 `/user/settings`）/
    黑名单（新页面）/ 修改密码（弹窗）/ 协议 / 关于（版本号 + 连点 7 下进组件预览页，仅 debug）/
    注销二次确认 / 退出登录；
  - **P05 举报接通**：以前是「举报功能还没开放，稍后再试」的桩，现在真的调 `/report`。
- **Q0-20 修好了**（P4 起就记着的遗留）：以前只有「启动时按 token 决定起始页」，
  登录态中途没了（退出登录 / 注销 / 401 之后刷新也失败）就停在报错页面上。
  现在 `ClNavHost` 盯着 `tokenStore.accessTokenFlow()`，token 一空就
  **清本地状态**（断 WebSocket、清当前用户、清未读、清内存缓存）+ **回登录页**（把整棵栈清掉，
  登录页按返回键回不到已登出的页面）。只清内存不清磁盘：聊天记录按 `ownerId` 存在 Room 里，
  换回这个账号还能看到自己的历史。
- **`DevEntriesPage` 删掉了**：它是 P04 没做之前临时放的开发入口（T3.x 起的），
  P04 做完了就该删 —— 现在所有入口都在真实菜单里，组件预览页走「设置 → 关于 → 连点版本号」。
- **走查抓到 1 个真 bug**：改密码弹窗里两个新密码框**一打开就飘红**。
  根因是我在 UiState 里把「没有错误」写成了空串 `""`，而 `ClTextField(error != null)` 就把框画红 ——
  **空串不等于 null**。改成返回 `null`。单元测试断言的是 `hint.isNotEmpty()`，
  看不到这个区别；只有真机截图才看得出来。
  记一笔：**「没有错误」一定要用 `null`，别用空串**。
- 真机走查（模拟器 + 真后端，截图 `docs/screenshots/t66-*.png`）：
  | 步骤 | 结果 |
  |---|---|
  | P04（女） | 头部「晚晴 / ID：113 · 资料完善度 88% / 魅力等级 Lv3 / 编辑」+ 2/2/2/2 统计 + 「6264 积分 · 我的收益 → 去提现」+ 菜单（我的收益 / 我的等级与收费 / 实名认证**已认证** / 每日任务 / 基本资料 / 隐私与设置），**没有会员** |
  | P04（男） | 头部有 VIP 标识 + 「VIP 剩余 31 天」+ 「450 金币 · 消费钱包 → 去充值」+ 菜单（金币收支明细 / 畅撩交友会员**已是会员** / 每日任务 / 基本资料 / 隐私与设置），**没有收益和实名**；`call_enabled=0` 所以也没有通话记录 |
  | P10 系统通知 | 三条卡片：提现已打款 / 提现审核通过 / 实名认证已通过，各带时间 |
  | P10 互动 | 收到礼物「阿宁 送了你「热拿铁」」/ 有人关注了你「阿宁 关注了你，去看看吧」（已读的标题是浅色） |
  | P15 | 通知（消息提醒开关）、隐私（展示给附近的人 + 黑名单）、账号（修改密码 / 注销账号「不可恢复」）、关于（用户协议 / 隐私政策 / 关于畅撩交友「版本 1.0.0」）、退出登录 |
  | P15 黑名单 | 空态「黑名单是空的 / 在别人的资料页右上角「…」里可以拉黑」 |
  | P15 改密码 | 弹窗三个密码框正常（修完 bug 后不再飘红）；填对三项按钮才亮；旧密码故意填错 → 红框 + 「旧密码错误」、弹窗留着可以改 |
  | P05 举报 | 资料页「…」→ 举报 → 选「广告诈骗」→ 提交 → **toast「举报已提交，我们会尽快处理」**（服务端文案） |
  | 退出登录 | 点「退出登录」→ **直接回到登录页**（Q0-20；以前会停在设置页上） |
- 单测：新增 36 条（`MineViewModelTest` 10 条菜单/头部 + `SettingsViewModelTest` 12 + `NoticeViewModelTest` 7 + `BlockListViewModelTest` 6 + 举报 2 条改写在 `UserDetailViewModelTest` 里）。
  服务端新增 1 条（注销）。
  `bash scripts/check-all.sh` → **`✅✅✅ 全部检查通过`**（server **436 测试 / 2595 断言** + phpstan + money；
  admin lint+build；android **407 单测** + `lintDebug` + `assembleDebug` + `check-ui.sh`）。

### T6.7 P6 阶段验收 —— ✅ 完成（2026-10-14）

- 自检项 `ALL`。走查清单见「阶段验收记录」里的 P6 那一段。
- **走查抓到 3 个真缺口，全部修完**（都是「单测和 lint 都看不见、只有按流程真跑一遍才暴露」的那一类）：
  1. **`notice.new` 推送客户端根本没人接。** T6.4 把服务端的 `PushService::EVENT_NOTICE_NEW`
     和 `NoticeService::send()` 里的推送都做了，App 里却**搜不到 `notice.new` 这个字符串** ——
     `ChatFrame` 只认 `message.new` / `unread.update` / `pong`，认不出来的一律丢掉（这是故意的容错），
     于是通知到达时客户端一点反应都没有。
     后果：**收礼 / 被关注时对方 App 完全没有提示**。
     修法：`ChatFrame` 加 `NoticeNew`（只认事件本身，不解载荷）+ `ChatRepository.onFrame`
     把红点 +1；`UnreadStore` 多一个 `notice` 流承载它（原本只有一个「消息 Tab 未读总数」）。
     服务端**不用改** —— `/chat/unread` 一直就返回 `notice`。
  2. **P03「系统消息」入口没有未读数角标。** `01` P03 明写「系统消息 → P10（未读数角标）」，
     而这一行**压根没传 `badge`**（隔壁「最近访客」传了 `state.unreadVisitors`，对比之下很明显）。
     修法：`MessageUiState` 加 `unreadNotices`，进消息页 / 下拉刷新时用 `/chat/unread` 校准
     （`ChatRepository.unread()` 以前是**死代码**，顺手接上）。
  3. **P03「互动通知」点进去落在「系统通知」Tab。** `01` P03 要求落到「互动」分类，
     但两个入口`onClick` 用的是**同一个** `MessageRoutes.notices`，路由上也没带分类。
     修法：新增 `Routes.NOTICE_INTERACT`，`NoticeRoute` 收 `initialCategory`，
     `NoticeViewModel.enter(category)` 支持指定起始分类。
- 另外补了一条**服务端用例**：`checkUpgrade` 的「后台手动调高等级后不会被降回去」。
  演示数据里晚晴就是 `level=3` 而 `total_earned_points=1244`（只有 Lv1 的量），
  一开始以为是 bug，查完 `06` §5 才确认这是文档允许的「后台手动调整等级」，
  `checkUpgrade` 里 `$target->level <= $female->level` 那行就是这个保护 —— 但**没有用例钉着它**。
  顺手在 `seed-demo.php` 写了一句 WHY（免得下次又被当成 bug 查一遍）。
- 顺带修的小东西：P27 的进度文案「距离 **Lv3 的下一级**还差 298756 积分」改成「距离 **Lv4** 还差 …」
  （原来的说法既绕又在**满级时会输出「距离 Lvnull 的下一级」**的隐患，虽然满级走的是另一分支；
  给它补了满级用例）。
- 真机走查（模拟器 + 真后端，男女号各一遍，截图 `docs/screenshots/t67-*.png`）见「阶段验收记录」。
- 单测：新增 8 条（`ChatFrameTest` 1 + `ChatRepositoryTest` 3 + `MessageViewModelTest` 2 +
  `NoticeViewModelTest` 1 + `IncomeLevelViewModelTest` 1），改写 2 条
  （`ChatFrameTest` 原来**断言 `notice.new` 被丢掉**，`MessageViewModelTest` 构造参数）。
  服务端新增 1 条。
  `bash scripts/check-all.sh` → **`✅✅✅ 全部检查通过`**（server **437 测试 / 2597 断言** + phpstan + money；
  admin lint+build；android **414 单测** + `lintDebug` + `assembleDebug` + `check-ui.sh`）。

### T7.2 用户管理（男用户、女用户、用户详情抽屉）—— ✅ 完成（2026-10-14）

- 读：`07` 第 3.2 节、`05` 第 6 节（自检项 `S` `A`）
- **新增一个 composer 依赖 `openspout/openspout` 5.3**（你选的「出真 .xlsx」）：
  `07` 第 3.2 节要求导出 Excel、3.3 节写的是 CSV，而项目里原本**没有任何 Excel 库**。
  见「新增依赖记录」。
- 服务端：
  - 迁移 `2026_10_14_000015_create_cl_admin_action_log_table.php` + 模型 `AdminActionLog`：
    **后台对用户的动作流水**（谁、什么时候、把什么从什么改成了什么）。
    本来只打算记封禁，写的时候发现 `06` §5 还要求「手动调整等级写操作日志（谁、何时、从几级到几级、原因）」，
    而 MineAdmin 自带的 `user_operation_log` **不存请求体也不存目标用户**，两件事都需要同一份东西 ——
    与其开两张表，不如一张通用的，封禁记录按 `action in (BAN, UNBAN)` 过滤即可。
  - `AdminUserRepository`（列表/导出的查询，带两张钱包表的 join）+ `AdminUserService`（列表/详情/改资料/重置密码/封禁/解封/调等级/开会员/改性别/导出）+ `ExcelExporter`。
  - `Http/Admin/Controller/Cl/UserController` 9 个接口 + 5 个请求类，每个都带 `#[Permission(code: 'cl:user:xxx')]`。
  - **顺手补了一个洞**：`AppAuthService::findActiveUser` 原来只挡「已注销」，
    **封了号的人拿着旧 token 照样能刷 App 接口**（封禁检查只在 `login()` 里）。
    现在封禁也返回 null → 中间件回 401 → 客户端清登录态回登录页 → 再登录时
    `assertNotBanned` 会把**原因和解封时间**告诉他。也就是说封号是**当场生效**的，
    而不是「等他下次登录才失效」（这正是 `08` T7.5 要的效果，在这里就落地了）。
  - 会员到期时间算法抽成了 `VipService::extendExpire()`（App 买会员和后台开通共用）——
    各写一份迟早会走岔，后台那份要是误写成「从现在算」，用户会平白丢掉还没用完的会员时间。
    另外它的参数故意收 `DateTimeInterface` 而不是 `?Carbon`：模型上 `vip_expire_at` 的实际类
    会跟着底层 cast 变，钉死具体 Carbon 类两边就传不过来（真踩到了）。
- admin-web：
  - `modules/cl/api/user.ts`（列表/详情/9 个动作/导出）、`modules/cl/status.ts`（状态 → 中文/颜色 token）。
  - `views/user/`：**男女是同一个 `UserPage.vue`**，只有 `gender` 一个参数不同；
    `male/index.vue`、`female/index.vue` 只是两行壳子。搜索项、列定义、各动作弹窗都在里面。
  - 危险动作（封禁/解封/调等级/改性别）统一走 `ClReasonDialog`（给它加了个默认插槽放「时长/等级/性别」这类额外字段），
    原因必填、提交前挡住。
- **走查抓到并修掉 2 处**（都是「页面不报错、但显示的东西不对」）：
  1. `ClStatusTag` 的文案表**漏了 `NORMAL`**，男/女列表的「状态」列直接显示成了英文 `NORMAL`；
  2. 详情抽屉里「注册来源」显示 `1`、「实名状态」显示 `2` —— 列表页翻译了、抽屉漏了。
     根因是同一份映射写了两遍（列表一处、抽屉一处）。改法：抽到 `modules/cl/status.ts`，两边共用。
- **验收（浏览器 + 真后端）**：
  | 检查 | 结果 |
  |---|---|
  | 男用户页 | 标题/副标题、搜索区（ID/账号/手机号/昵称/状态/注册来源/批次号/是否会员/注册时间范围）、导出按钮；表头含**金币余额/累计充值/会员到期**；真实数据（林川 88 金币、阿宁 380 金币 + 累计充值 1,600 + 会员到期 2026-11-12） |
  | 女用户页 | 同一套页面，表头换成**等级/积分余额/冻结积分/累计收益/实名状态**；晚晴 Lv3 6,502 积分 累计收益 1,482 已通过、星野 Lv2 审核中 |
  | 用户详情抽屉 | 基本资料 / 钱包 / 等级·实名·会员 / 相册 / **封禁记录** / **最近 20 条流水**，数据都对；身份证是打码号 `110***********1233` |
  | 按角色的按钮 | 用**客服**登录时，操作里只有「查看 / 编辑资料 / 重置密码 / 封禁」，**没有调整等级和修改性别**（它没这两个权限码） |
  | 封禁（二次确认） | 不填原因点「确认封禁」→ 弹窗留着并提示「请填写原因」；填上原因提交 → 弹窗关闭、列表刷新、该行状态变**已封禁** |
  | 解封 | 同一行「解封」→ 提交后状态恢复「正常」 |
  | 落库 | `cl_admin_action_log` 里留下两条：`BAN 封禁 7 天：走查测试用的临时封禁 by kefu`、`UNBAN 解封：走查结束，解封 by kefu`；账号状态已还原 |
  | 403（接口层） | 客服调 `/admin/cl/user/{id}/level` → `{"code":403,"message":"无权限"}`，`/list` 正常 200 |
  | 导出 | 接口返回的是可读的 xlsx（用例用 openspout 读回来断言），**表头里没有密码和身份证**，第一行数据字段对得上 |
- **`check-money.sh` 在这里拦住了一次**：我一开始把钱包余额**join 进用户列表的 SQL**，还注入了两个钱包仓库去读余额，
  脚本立刻报「以下位置绕过了钱包服务」。它的规矩是**钱包相关的类名/表名只许出现在那 5 个钱包文件里**，
  而且**不区分读写** —— 只是读余额也一样得走 `CoinWalletService` / `PointWalletService`。
  改法：仓库只查 `cl_user`，`AdminUserService` 拿到这一页的 id 之后**批量**补余额
  （给两个钱包服务各加了 `summaryOfMany(ids)`），整页一次查完，不是 N+1。
  **记一笔：这条守卫是「宁可错杀」型的，写涉及余额的查询前先想好该走哪个服务。**
- 测试：服务端新增 **25 条**（`AdminUserServiceTest` 19 + `AdminUserControllerTest` 6），
  覆盖封禁后 App token 失效、永久封禁 `ban_until` 为 null、调等级留前后值、会员在原到期时间上加、
  导出不含密码/身份证、客服调封禁接口 403 且状态不变。
  `bash scripts/check-all.sh` → **`✅✅✅ 全部检查通过`**（server **468 测试 / 2772 断言** + phpstan + money；
  admin lint+build；android **414 单测** + `lintDebug` + `assembleDebug` + `check-ui.sh`）。
- 遗留（不阻塞 T7.3）：
  1. 「开通会员」现在**只能填天数，还不能选套餐** —— 套餐下拉要等 T7.7 的运营配置接口
     （`cl_vip_plan` 目前只有 App 侧 `/vip/info` 能读到）。接口本身已经收了 `plan_id`，T7.7 接上即可。
  2. `07` §3.2 里「超级管理员：金币扣减、积分调整」两个动作**没做** —— 它们在 `05` §6 里是
     `/admin/cl/finance/coin-deduct` / `/point-adjust`，属于**资金管理（T7.6）**，届时在同一个页面上加按钮。

### T7.1 后台公共组件、菜单与角色种子 —— ✅ 完成（2026-10-14）

- 读：`07` 第 1、2、4 节（自检项 `S` `A`）
- 服务端新增 `server/databases/seeders/zz_cl_menu_seeder_20261014.php`：`07` §1 那棵「畅撩交友」菜单树
  （**27 个页面 + 21 个只做权限判断的按钮码**，共 48 个节点）+ 客服/财务/审核员 3 个角色和 3 个登录账号
  （`kefu` / `caiwu` / `shenhe`，密码和 admin 一样都是 `123456`）。
- **踩到一个只有真跑 `db:seed` 才会发现的坑：种子是「按文件名排序」执行的。**
  `Seed::getSeederName()` 返回的是**文件名的 basename**（不是类名 —— 虽然它长得像类名，
  因为 `Str::studly()` 正好能把 `menu_seeder_20240926` 变成 `MenuSeeder20240926`，容易看错），
  然后 `sortBy` 排序。而：
  - `menu_seeder_20240926.php` 里有 `Menu::truncate()`，
  - `user_seeder_20240926.php` 里有 `Role::truncate()` + `User::truncate()`。
  `cl_*` 开头的名字排在 `menu_seeder_*` **前面**，所以按 `07` §1 写的 `cl_menu_seeder.php` 命名的话，
  这里建好的菜单和角色会被后面两个种子**清掉**（而且 `db:seed` 全部报「Seeded」，一声不吭）。
  改法：文件名前面加 `zz_`（`z` 比 `m`/`u` 都大，排在最后跑），并在文件头把原因写清楚。
  上游自己后加菜单用的 `menu_update_*` 也是同一个思路。
- 另一个坑：**`$this->create()` 不是 `Seeder` 基类的方法**，是 `menu_seeder_20240926.php` 里
  **自己定义**的一个递归插入辅助方法（在文件第 360 行，我只读了前 80 行就被它坑了一次：
  照着写 `$this->create($this->data())` 直接 `Call to undefined method`）。
  各菜单种子各带一份，这里也照抄一份。
- **验证方式是新建一个空库跑完整的 `migrate` + `db:seed`**（`cl_seedtest`，也是安装脚本走的那条路），
  而不是在开发库上试 —— 这样既验证了从零安装，又不会把开发库的菜单/角色弄坏。
- admin-web 新增 `src/modules/cl/`：
  - `components/`：`ClUserCell`、`ClUserDrawer`、`ClAmount`、`ClStatusTag`、`ClImagePreview`、
    `ClReasonDialog`、`ClConfigForm` 七个（`07` §2）。`ClStatusTag` 的颜色口径按 `03` §8 第 5 条写死
    （待处理橙 / 成功绿 / 驳回红 / 关闭灰），英文枚举名和中文都认，认不出来按灰处理 ——
    后端以后加新状态不会画错颜色。
  - `api/user.ts`（详情接口，`ClUserDrawer` 用）、`types.ts`（跨页面共用的两个类型）。
  - **27 个占位页**（`views/*/index.vue`）：菜单里的 `component` 路径必须指向真实文件，
    否则 `useRouteStore` 拿不到组件，点进去是**空白**（它只是不注册路由，不会报错）。
    占位页写明「页面待建（T7.x）」，各任务做到时替换掉。
  - 组件**不会被自动导入**（`vite/components.ts` 里 `dirs` 只有 `src/components`），页面里要显式 `import`。
- 验收（docs/08 要求的两条）——**后台前端第一次真的在浏览器里跑起来**（T0.3 那次只跑了构建，
  没验成「浏览器登录看到菜单」，这次补上了）：
  | 账号 | 结果 |
  |---|---|
  | `admin`（超管） | 侧边栏出现「畅撩交友」，展开是**数据看板 / 用户管理 / 审核中心 / 聊天管理 / 资金管理 / 运营配置**；运营配置下 11 个子页全在；点「数据看板」→ 路由 `#/cl/dashboard`、内容区渲染出占位页（说明 `component` 路径映射是通的） |
  | `kefu`（客服） | **只有** 数据看板 / 用户管理 / 聊天管理 / 资金管理 / 运营配置，**没有「审核中心」**；用户管理下只有男用户、女用户（**没有批量开号**）；聊天管理下只有举报处理；资金管理下只有后台充值/充值记录/金币流水/积分流水（**没有提现审核和平台收入**）；运营配置下只有系统通知 |
  | `kefu` 看不到的 | 「权限管理 / 日志管理 / 数据中心」三个基础菜单也没露出来 —— 说明是按角色过滤的，不是把所有菜单都发下来 |
- **这一轮的走查方式有个限制**：内置预览面板只有 342px 宽，低于 MineAdmin 的 `lg`（1024px）断点，
  侧边栏被整个隐藏（不是抽屉），所以**没截到能看清菜单树的图**。上面那张表是用
  `preview_eval` 读渲染后的 DOM 得到的（比截图更准，能确切看到每一项）。
  要看观感的话，把浏览器窗口拉到 1024px 以上自己开一次即可（`cd admin-web && pnpm dev`，默认 `admin/123456`）。
- 顺手建了 `.claude/launch.json`（在 `C:\Workspace\android\vibe\.claude\`，内置浏览器按这个路径找），
  里面是后台前端的启动配置，以后 P7 各任务走查直接用。
- 服务端新增 6 条用例（`tests/Feature/Cl/Admin/ClMenuSeederTest.php`）：3 个角色各自**该有的有、不该有的没有**、
  父级分组跟着子菜单一起给（少给的话前端建不出树）、权限码不重名。种子文件在全局命名空间、
  没进 composer autoload，所以用例里 `require_once` 一次再跑 —— 和 `db:seed` 是同一份代码。
  `bash scripts/check-all.sh` → **`✅✅✅ 全部检查通过`**（server **443 测试 / 2657 断言** + phpstan + money；
  admin lint+build；android **414 单测** + `lintDebug` + `assembleDebug` + `check-ui.sh`）。

## 阶段验收记录

### P6 女用户收益、等级、实名、提现、通知、举报（T6.7 阶段验收）—— ✅ 通过（2026-10-14）

- **`bash scripts/check-all.sh`** → **`✅✅✅ 全部检查通过`**（rc=0）：
  server **437 测试 / 2597 断言** + phpstan `[OK] No errors` + `check-money.sh` 通过；
  后台 `pnpm lint` + `pnpm build` 通过；安卓 **414 单测** + `lintDebug` + `assembleDebug` + `check-ui.sh` 通过。
- 产出（7 个任务）：
  - **服务端**：T6.1 等级接入私信/礼物 + `/income/summary|logs|level` + 通知表；
    T6.2 实名（AES-256-GCM 存证、GB 11643 校验位、打码）；T6.3 提现（申请/记录/审核三动作）；
    T6.4 `NoticeService` 各业务事件发通知 + `/notice/list` + `/report` + `notice.new` 推送。
  - **安卓**：T6.5 P26/P27/P28/P29；T6.6 P10/P04/P15 + P05 举报接通 + 401 回登录页。
- **按 `09` 第 5 节走查（模拟器 + 真后端，男号女号各走一遍）**
  （截图 `docs/screenshots/t65-*`、`t66-*`、`t67-*`）：
  | 页面 | 结果 |
  |---|---|
  | P01（男号） | 晚晴 **10 币/条**、星野 **8 币/条**、小满 **5 币/条** —— 等级 → 单价这条链在真机上看得到 |
  | P01（女号） | 右上角是「**6264 积分**」不是金币；男号卡片只写「私信」不带价 |
  | P04（男） | VIP 标识 + 「VIP 剩余 30 天」+ 「450 金币 · 消费钱包 → 去充值」+ 菜单（金币收支明细 / 畅撩交友会员**已是会员** / 每日任务 / 基本资料 / 隐私与设置），**没有收益和实名** |
  | P04（女） | 「晚晴 / ID：113 · 资料完善度 88% / 魅力等级 Lv3」+ 「6264 积分 · 我的收益 → 去提现」+ 菜单（我的收益 / 我的等级与收费 / 实名认证**已认证** / 每日任务 / 基本资料 / 隐私与设置），**没有会员** |
  | P26 我的收益 | 可提现 6264 积分 · 约 ¥62.64 · 冻结中 0；今日 +10102 / 0 / 1；明细按类型挑图标 |
  | P27 我的等级与收费 | Lv3 + 累计收益 + 进度条 + 等级表 Lv1~Lv5 门槛/单价/分成；接听设置里的「在线接听」开关 |
  | P28 收益提现 | ¥62.00 / 6264 积分；档位 50 亮、100/200/500 置灰；收款人姓名是实名的「晚晴」且不可改；规则说明含「今天的次数已经用完了」；提现记录「已打款 −¥50.00」 |
  | P29 实名认证 | 状态「已通过」+ 姓名 + **打码号 `110***********1233`** + 没有表单、没有提交按钮（T6.5 修的「已通过还让人提交」没复发） |
  | P10 系统通知 | 提现已打款 / 提现审核通过 / 实名认证已通过，各带时间 |
  | P10 互动 | 收到礼物 / 有人关注了你（已读的标题是浅色） |
  | P03 入口 | 「系统消息」红点、「互动通知」分落两个 Tab（**T6.7 修的**，见下） |
  | P15 设置 | 三个分组全在（通知 / 隐私 / 账号 / 关于）+ 黑名单空态 + 改密码（旧密码错 →「旧密码错误」）+ 「退出登录」**直接回登录页** |
  | 男号点收益四页 | 菜单里根本没有入口（T6.5 验过服务端也会拦：`收益与等级只有女用户有`） |
- **后台菜单：本阶段没有**。`docs/08` 的 P6 七个任务全是 App 侧；实名审核（T7.4 审核中心）、
  提现审核三动作（T7.6 资金管理）、举报处理（T7.5 聊天管理）都在 **P7 管理后台** ——
  **服务端方法 T6.3/T6.4 已经写好**，P7 只管接后台接口和页面。
- **走查抓到并修掉的 3 个真缺口 + 1 条补的用例 + 1 处文案**，全部写在 T6.7 的记录里，摘要：
  1. `notice.new` 推送**客户端从来没接**（App 里搜不到这个字符串）→ 收礼/被关注时对方毫无提示；
  2. P03「系统消息」入口**没有未读数角标**（`01` P03 明写要有）；
  3. P03「互动通知」点进去落在**「系统通知」Tab**（应落「互动」）；
  4. 补用例：后台手动调高等级后**不会被降回去**（演示数据正好处在这个状态）；
  5. P27 文案「距离 Lv3 的下一级还差…」改成「距离 **Lv4** 还差…」。
- **这几条的共同点值得记一笔**：**推送和角标这类"跨模块接线"最不容易被发现** ——
  服务端把事件发出去了、单测覆盖了服务端一侧、页面单独看也都对，
  但**没有任何一个单元测试会去问「这条推送客户端认不认」**。
  只有把两个端同时跑起来、从外面造一个事件，才会看到「对面什么都不动」。
  T6.4 当时也是按「发了推送 + 有单测」就算过了，实际上少了一半。
- 遗留（不阻塞 P7）：
  1. **通知未读是「会话未读 + 通知未读」分开显示的**：底部「消息」Tab 角标只算会话
     （`01` 第 3 节的写法），「系统消息」入口单独一个红点。服务端的 `/chat/unread.total`
     是三者之和，客户端没用它 —— 如果有别的页面要「总未读」，先看这条。
     「系统消息」的红点是**两个分类合起来**的数（服务端 `/chat/unread` 只给一个 `notice`），
     所以收到互动通知时「系统消息」也会亮 —— 与 `01` P03「互动通知不带角标」的写法一致，
     但不完全精确；要分开得让服务端按分类返回两个数。
  2. 演示库里还有一条**我自己冒烟脚本留下的流水**（`收益明细` 里那条「冒烟收益 +10000」，
     `ref_type = smoke…`）。它**撑起了后面的提现**（删了余额会变成负数），所以不能删；
     它也是晚晴 `total_earned_points = 1244` 却 `level = 3` 的第二个成因（该脚本直接写流水表、
     没走 `PointWalletService`，计数器没跟着加）。**教训和 T5.9 那次时区问题一样：
     造演示数据要走接口 / 服务层，别直接 INSERT 流水表。**
  3. P4 记的两条仍有效：`network_security_config.xml` 到 T9 挪 `src/debug/`；
     `ClRangeSlider` 观感（Q0-16）、`ClSoftButton` 配色（Q0-14）等 UI 待确认项。


### P5 金币、礼物、会员、任务（T5.9 阶段验收）—— ✅ 通过（2026-10-13）

- **`bash scripts/check-all.sh`** → **`✅✅✅ 全部检查通过`**（rc=0）：
  server **352 测试 / 2197 断言** + phpstan `[OK] No errors` + `check-money.sh` 通过；
  后台 `pnpm lint` + `pnpm build` 通过；安卓 **328 单测** + `lintDebug` + `assembleDebug` + `check-ui.sh` 通过。
- 产出（8 个任务）：
  - **服务端**：T5.1 金币三个接口；T5.3 礼物（`cl_gift`/`cl_gift_record` + `GiftBillingService`）；
    T5.5 会员（`cl_vip_plan`/`cl_vip_order` + `VipService`）；
    T5.7 每日任务（`cl_task`/`cl_task_log`/`cl_sign_in` + `TaskService`）。
  - **安卓**：T5.2 P13/P22/P24；T5.4 P23 礼物面板 + P31 聊天礼物弹窗；T5.6 P12 会员中心；T5.8 P11 每日任务。
- **按 `09` 第 5 节走查（模拟器 + 真后端，男号女号各走一遍）**
  （截图 `docs/screenshots/t52-*`、`t54-*`、`t56-*`、`t58-*`、`t59-*`）：
  | 页面 | 结果 |
  |---|---|
  | P13 金币钱包 | 余额 480 · 充值/收支明细两个入口 ·「金币怎么用」的私信 **5~20 币/条**（区间是等级配置现算的） |
  | P22 金币充值 | 6 个档位 + 本次到账 + 客服微信/电话/二维码 + 说明文字 + 合规声明 |
  | P24 金币收支明细 | 三个 Tab；流水覆盖**礼物 / 会员 / 任务 / 充值**四类（-120/-30/-60、-390×3、+5/+10、+500/+1000），每条的「余额」都对得上 |
  | P05 → P23 礼物面板 | 6 个礼物、默认选中第一个、可用余额 480 |
  | P06 → P31 礼物弹窗 | 「送给 晚晴」+ 6 个礼物 + 可用余额 480 |
  | P12 会员中心（会员态） | 横幅「有效期至 2026-11-12」、按钮「续费 月卡 · 390 金币」、3 个套餐、4 项权益 |
  | P11 每日任务（男） | 4 条任务 + 奖励「+5 / +20 / +10 / +5 金币」、四种按钮状态（签到 / 去完成 / 领取 / 去认识）都在 |
  | P11 每日任务（女） | 同一页给的是**另一组任务**，奖励是「+20 / +50 / +30 / +20 积分」 |
  | P06 收费条（开会员后） | 「**会员今日还剩 10 条免费** · 余额 480 币」—— T5.5 的会员每日免费额度确实进了 quote |
  | 女号 P05/P06/P09 | 「遇见」右上角是 **1162 积分**（不是金币）、卡片只写「私信」不带价格、聊天页没有收费条（T4.8 记过的女号行为没被 P5 改坏） |
  | 女号点会员中心 | 服务端拦住：`会员只有男用户可以开通` —— 入口本身是 debug 开发页（见「遗留」），但**钱和权限的闸在服务端** |
- **后台菜单：本阶段没有**。`docs/08` 的 P5 八个任务全是 App 侧，礼物的上下架改价、会员套餐、
  任务配置这些菜单在 **P7 管理后台**（T7.x）才做，本阶段它们只能靠种子文件配（见「遗留」）。
- **走查抓到并修掉 1 处与文档不符：任务卡上没有显示奖励。**
  `01` P11 明确要求卡片有「奖励（男显示『+X 金币』，女显示『+X 积分』）」，
  而 `ClTaskCard`（T2.9 建的）没有这个位置、设计图 11 上也没画，所以 T5.8 做完时**两边都看不到奖励数**。
  给 `ClTaskItem` 加了 `reward` 字段、卡片上补了一行金色小字，男女两组奖励现在都看得见
  （组件预览页也补了这个字段）。**这正是阶段验收这一步的价值** —— 单测和 lint 都不会说
  「文档要求的字段没显示」。
- 顺带发现一处**测试脚本的时区问题**（不是产品 bug）：P24 上有两条「冒烟充值」显示成 12:32/12:33，
  原因是我那两个**一次性脚本没走 `bin/hyperf.php`**，PHP 默认时区是 UTC，写进去的 `created_at` 差了 8 小时。
  App 自己永远走入口文件（`TimezoneTest` 专门盯着这一行），所以真实数据不受影响 ——
  记一笔：**以后写一次性脚本，要么走 `bin/hyperf.php`，要么自己设 `Asia/Shanghai`**。
- 遗留（不阻塞 P6）：
  1. 礼物上下架/改价、会员套餐、任务配置**还没有后台菜单**（P7 做），现在只能改种子文件；
  2. `DevEntriesPage` 是 debug 包临时的开发入口，**没有按性别过滤**（女号也能点到「会员中心」）；
     P04「我的」做完后连它一起删掉，那时的菜单才是 `01` P04 里那份按性别分的；
  3. P4 记过的两条仍然有效：`network_security_config.xml` 到 T9 挪 `src/debug/`、
     P6 要把 `notice` 未读接进 `/chat/unread`。

### P4 私信与计费（T4.8 阶段验收）—— ✅ 通过（2026-10-13）

- **`bash scripts/check-all.sh`** → **`✅✅✅ 全部检查通过`**（rc=0）：
  server **268 测试 / 1828 断言** + phpstan `[OK] No errors` + `check-money.sh` 通过；
  后台 `pnpm lint` + `pnpm build` 通过；安卓 **275 单测** + `lintDebug` + `assembleDebug` + `check-ui.sh` 通过。
- 产出（9 个任务）：
  - **服务端**：T4.1 会话/消息/敏感词三张表（含 `user_low_id < user_high_id` 的 CHECK）；T4.2 发消息计费
    （`MessageBillingService` 按 `06` §3.3 七步，先落消息再扣费、死锁重试、积分纯整数）；T4.3 会话/消息查询与已读 8 个接口；
    T4.4 WebSocket 推送（`ws` 服务 9502 + `PushService`，只推不收）。
  - **安卓**：T4.5 聊天数据层（Room v2 三张表 + `MIGRATION_1_2` + `ChatSocket` + `ChatRepository` 本地优先、乐观 UI）；
    T4.6 消息页 P03；T4.7 聊天页 P06 + 余额不足弹窗 P30。
- **按 `09` 第 6 节走查（模拟器 + 真后端，男号女号各走一遍）**：
  | 步骤 | 结果 |
  |---|---|
  | 3. 3 条免费 → 第 4 条扣币 → 女号实时收到 → 女号加积分 | ✅ 3 条都是 `PAIR_FREE / price=0 / points=0`；第 4 条 `price=10`（晚晴 Lv3）、阿宁 **360 → 350**、晚晴积分 **0 → 20**。**晚晴全程用 WS 客户端听着**：四条 `message.new` 依次到达，第 4 条payload 里就写着 `price=10 points=20`，每条后面跟一条 `unread.update` |
  | 4. 余额不足 → 弹 P30 → 去充值 → 回来输入还在 | ✅ 把余额改成 5（需要 10）→ 发送 → P30「5 金币 / 本次操作需要 10 金币，还差 5 金币」→ 去充值进 P22 → 返回后**输入框里的字还在**、弹窗已关 |
  | 5. 互关后发消息不扣费 | ✅ 把余额**故意改成 1 金币**再给小满发 —— 发出去了（`MUTUAL_FOLLOW / price=0 / points=0`），余额还是 1。收费条显示「互相关注，私信免费」 |
- **女号视角也走了一遍**（`09` §5 要求）：
  - P05：**没有**「聊天价格」面板，底部按钮只有「私信聊天」（不带价格）；
  - P06：**没有收费条**、输入框提示是「说点什么…」、**工具栏整条不显示**（女号没有送礼物，语音视频开关也关着）；
  - 女号发消息：`FEMALE_SEND / price=0 / points=0`，男号金币一分没动（林川还是 88）；
  - P03：入口行的数字是**她自己的**（「有 1 人喜欢了你」，最近访客没人看过所以不显示说明文案和角标），
    会话列表按最后消息时间倒序（林川 13:40 / 阿宁 13:32），未读角标 1。
- **走查抓到并修掉 1 个真 bug**：P30 点「去充值」跳到 P22，**返回时弹窗又冒出来了** —— 因为跳转前没把
  `coinNotEnough` 清掉，用户会以为余额没充上。修法：跳转前先关弹窗（`onRecharge` 里先 dismiss 再导航）。
  这个 bug 单测和 lint 都看不见，只有按流程走一遍才会遇到。
- **走查顺带修的数据问题**：`scripts/seed-demo.php` 只给 3 个号建了钱包，晚晴/星野**没有积分钱包** ——
  注册接口会给新用户建钱包，但种子脚本是自己 INSERT 用户的，漏了谁谁就收不了付费私信
  （`PointWalletService` 找不到钱包会报错）。已改成**每个号都建**（男号金币、女号积分）。
- **这次没走到的**：
  - 「断网 → 失败气泡 → 点感叹号重发」：adbd 的 `adb reverse` 走 loopback，关 wifi/数据断不掉它，
    要停后端才能造出网络失败；这条由 `ChatViewModelTest` + `ChatRepositoryTest` 覆盖（发送失败 → failed 可重发、
    重发用同一个 `client_msg_id`），留到 P9 全流程走查时再在真机上过一遍。
  - 图片消息的真机验证：模拟器相册是空的（`PickVisualMedia` 显示 No photos or videos），
    要往相册 `adb push` 一张图才能走（T3.10 记的「选图上传没测」仍然成立，这次至少确认了**选图器能正常拉起**）。
  - 后台：P4 没有对应的后台菜单（消息记录、敏感词、举报都在 P7），所以「后台逐个打开本阶段菜单」这项为空。
  - P22 金币充值、P10 系统消息、P25 通话记录还是占位页（分别在 P5/P6/P8 做），走查时确认跳转正确、页面是「开发中」。


<!-- 每个阶段结束写一段：check-all 结果、走查了哪些页面、发现并修复了什么 -->
### P3 登录、资料、发现、关系 —— ✅ 通过（2026-10-12）
- `bash scripts/check-all.sh` → **`✅✅✅ 全部检查通过`**（rc=0）：server **194 测试 / 1521 断言** + phpstan `[OK] No errors` + money 通过；后台 lint（0 error）+ build；安卓 **207 单测** + lint + assemble + check-ui。
- 产出（10 个任务）：
  - **服务端**：T3.1 登录注册；T3.2 `/user/me`、`/user/profile`、`/user/photos`、`/user/settings` + 审核表；T3.4 关系 9 个接口（关注/拉黑/访客/粉丝/好友/黑名单 + 非会员打码）；T3.5 `/discover`、`/discover/search`；T3.6 `/user/{id}` + `MessagePricingService::quote()`（`06` §3.2 四步判断）。
  - **安卓**：T3.3 编辑资料页（P14）、T3.7 发现页（P01）+ 筛选页（P07）、T3.8 个人详情页（P05）、T3.9 访客（P08）+ 喜欢我的人（P09）；设计系统新增 `ClProfileHero`、`ClDatePickerDialog`，`ClPhotoGrid` 加长按拖动排序，`ClPullRefreshList` 加 header 插槽。
- **真机走查**（模拟器 + 真后端，7 个页面逐页对照设计图与文档）：P00 登录 / P01 发现 / P05 个人详情 / P07 筛选 / P08 最近访客 / P09 喜欢我的人 / P14 编辑资料 —— 全部渲染正确，细节见 T3.10 记录里的表。截图留在 `docs/screenshots/p3-*.png`。
- **走查抓到并修掉 3 个真 bug**（详见 T3.10）：取关后免费提示过时、打码文字被重复模糊、**服务端时区 UTC/MySQL +08 不一致导致「在线」判断偏 8 小时**（最后这条影响 T4.2 的会员每日免费额度按自然日重置，属于早晚要炸的那类）。
- **这次验收最大的价值不是"页面都出来了"，而是第一次把 App 连上真后端**：发现模拟器连不上 `10.0.2.2`（改用 `adb reverse`）、发现 `pending` 字段 PHP 空数组编码成 `[]` 客户端解析失败、发现时区不一致 —— 这三个都是**单测和 lint 抓不到、只有真跑起来才会暴露**的问题。
- 遗留（不阻塞 P4，已记录）：`network_security_config.xml` 放错目录（release 也生效，T9 收尾时挪到 `src/debug/`）；**401 之后不会自动回登录页**（`AuthInterceptor` 清了登录态，但没有页面跳转，用户会停在报错页）—— 这条要跟"退出登录/切号"一起做，见下方 Q0-20。
- 本阶段的页面：**P08/P09/P14 在 P3 结束时还没有正式入口**（入口页 P03「消息」、P04「我的」在后面的阶段），走查时靠 debug 专用的「开发入口」页进的；P4/P5 做好入口后要再走一遍，并把 `DevEntriesPage` 删掉。

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
