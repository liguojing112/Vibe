# 畅撩交友（ChangLiao）

一对一**付费私信**交友 App，三端：

| 端 | 目录 | 技术 |
|---|---|---|
| 安卓 App | [android/](android/) | Kotlin + Jetpack Compose + Material3 |
| 后端 | [server/](server/) | MineAdmin 3.2（Hyperf 3.2 + PHP 8.3 + Swoole 5 + MySQL 8 + Redis） |
| 管理后台 | [admin-web/](admin-web/) | MineAdmin-Vue 3.2（Vue 3 + TS + Element Plus） |

**开发前必读**：[docs/00-必读-总规则.md](docs/00-必读-总规则.md) 与 [docs/08-开发任务清单.md](docs/08-开发任务清单.md)。
**进度与已知问题**：[PROGRESS.md](PROGRESS.md)（每个任务做完必须更新）。

---

## 一、交付状态（2026-10-15）

- **`docs/01` 第 5 节的 31 个页面全部实现**（P00~P31，其中 P30/P31 是弹窗不是独立页面），
  `docs/08` 的 T0.1~T9.3 全部完成。
- **P8 语音/视频按 `docs/01` 第 4 节只做页面、不做功能**：`feature.call_enabled` 关闭时所有入口隐藏；
  打开后能进 P02/P16~P21/P25，点「确认拨打」只提示「功能暂未开放」，**不发请求、不扣币**。
  通话计费服务端没实现。
- 三端检查全绿：`bash scripts/check-all.sh` → `✅✅✅ 全部检查通过`。
- **完成情况、已知限制、待确认问题**汇总在 [PROGRESS.md](PROGRESS.md) 的「最终总结」一节。

---

## 二、本机环境（已装好，细节见 PROGRESS.md「环境信息」）

| 端 | 依赖 |
|---|---|
| 后端 | **WSL2 Ubuntu 24.04**：PHP 8.3.6 + Swoole 5.1.8 + Redis 7 + Composer；MySQL 8 装在 **Windows 宿主**（服务 `MySQL80`）；账号密码见 [server/.env.example](server/.env.example)（真实值填在 `server/.env`，不提交） |
| 管理后台 | Node 24 + pnpm 10 |
| 安卓 | **JDK 17** + Android SDK（`C:\Users\34252\AppData\Local\Android\Sdk`，平台 android-37） |

> 两个环境要点（改之前先看 PROGRESS.md）：
> 1. **后端跑在 WSL 里**——Windows 没有原生 PHP/Swoole。`C:\Users\34252\.wslconfig` 开了 mirrored 网络，WSL 才能走通本机的 Clash 代理（否则外网域名会被解析成 127.0.0.1）。
> 2. 本机 `dl.google.com` / `maven.google.com` 不可达，安卓构建统一走**阿里云 Maven 镜像**（已配在 `android/settings.gradle.kts`）。

数据库：MySQL 里已有 `changliao`（开发库）与 `cl_test`（测试库），均为 utf8mb4。后端连接信息在 `server/.env`（不提交；照着 [server/.env.example](server/.env.example) 建，里面标了哪些必填）。

---

## 三、启动三端

### 1. 后端（在 WSL 终端里执行）

打开 WSL 终端（`wsl -d Ubuntu`），然后：

```bash
cd /mnt/c/Workspace/android/vibe/changliao/server
php bin/hyperf.php start
```

看到 `Server started` 即成功。三个端口：**9501**（HTTP，App 和后台都打它）、**9502**（WebSocket）、**9503**（Swagger：<http://127.0.0.1:9503/swagger>）。**Ctrl+C 停止**（也可以后台跑：`setsid nohup php bin/hyperf.php start > /root/build/server.log 2>&1 < /dev/null &`）。

首次安装（换机器时才需要）：

```bash
cd /mnt/c/Workspace/android/vibe/changliao/server
composer install
cp .env.example .env      # 再把里面标「必填」的填上（MySQL / Redis / 三把密钥 / CL_WS_PORT）
php bin/hyperf.php mine:install
composer require hyperf/websocket-server:"3.2.*"
php bin/hyperf.php db:seed        # 建后台账号和业务菜单
```

> 注意几条（详见 PROGRESS.md「环境信息」，都是踩过的坑）：
> - Hyperf 的 `start` **没有** `-d` 参数；Swoole 需要 `swoole.use_shortname=Off`（已写进 WSL 的 `/etc/php/8.3/cli/conf.d/20-swoole.ini`）。
> - **加了带注解的新类（控制器、命令、定时任务）**：先 `rm -rf runtime/container` 再启动，且**启动两次**（第一次冷缓存时注解路由是空的）。
> - **跑后端测试一律用 `composer test`**，别直接跑 `vendor/bin/phpunit` —— 测试库隔离只靠这个脚本里的 `DB_DATABASE=cl_test REDIS_DB=15`，直接跑会连开发库并清表。

### 2. 管理后台

```bash
cd admin-web
pnpm install     # 首次
pnpm dev
```

浏览器打开 **<http://localhost:2888/>**（注意 dev 服务器绑的是 IPv6，用 `localhost` 而不是 `127.0.0.1`），
用默认账号 **`admin` / `123456`** 登录。接口地址已在 `.env.development` 里指向 `http://127.0.0.1:9501`，无需改。

### 3. 安卓

```bash
cd android
export JAVA_HOME="C:/Program Files/Eclipse Adoptium/jdk-17.0.19.10-hotspot"   # 必须 JDK 17
./gradlew assembleDebug
```

产物在 `android/app/build/outputs/apk/debug/app-debug.apk`。
（`local.properties` 里的 `sdk.dir` 指向本机 Android SDK，该文件不提交。）

**模拟器/真机连后端必须走 `adb reverse`**（直接连 `10.0.2.2` 走不通，原因见 PROGRESS.md）：

```bash
adb reverse tcp:9501 tcp:9501
adb reverse tcp:9502 tcp:9502
```

**debug 包的默认地址已经就是 `127.0.0.1:9501` / `ws://127.0.0.1:9502/ws`**（`app/build.gradle.kts`），
所以 `./gradlew assembleDebug` **不带任何参数**也能连上 —— Android Studio 点 ▶ 直接能跑。
真机也一样，插上线跑一次 `adb reverse` 即可。

（历史包袱：`10.0.2.2` 是 `docs/02` 里写的地址，但后端在 WSL2 里时**这条路从来就没通过** ——
模拟器 `SocketTimeoutException`，同一时刻 Windows 侧 `curl 127.0.0.1:9501` 是 200。T9.5 把默认值改掉了。
真要换地址仍可覆盖：`./gradlew assembleDebug -PapiBaseUrl=http://xxx`。）

> ⚠️ **发现页显示「网络不太好，请稍后重试」就是这条**：那是 App 自己 P01 的错误态，**不是 Android Studio 报的**。
> 按顺序查：① `adb reverse --list` 看 9501/9502 在不在（拔线、重启模拟器、冷启 adb 都会掉）；
> ② 后端在不在（WSL 里 `ss -ltn | grep 9501`）；③ APK 是不是带参数/新版构建的（老的写死 `10.0.2.2`）。

**在 Android Studio 里跑**：打开 `android/` 这个目录（不是仓库根，根目录没有 `settings.gradle.kts`，Gradle 不认 Gradle 脚本），等右下角 Sync 完，选 `Medium Phone API 37`，点 ▶。

**IDE 两个已知毛病**（都不是工程问题，别去改代码）：
1. **偶发崩溃**（`NullPointerException: peer` 在 `WInputMethod.openCandidateWindow`）—— 输入法和 Swing 的老 bug，
   换微软拼音或在 `Settings → Editor` 里关掉自定义字体可规避。
2. 内置 AI（Gemini）在本机连不上（Clash 把 Studio 的内建服务也代理了，日志里
   `BuiltInServer - Channel will be closed due to error` 连出 4 次）—— 用不到就关掉：
   `File → Settings → Tools → AI Assistant`。它不影响构建、同步和跑模拟器。

> ⚠️ **注意别把这两件事混起来**：App 里显示「网络不太好」是 **P01 的错误态**（真连不上后端），
> Studio 的 AI 连不上是**弹窗**，两者字面都带「网络」但完全是两码事。
> App 报这个的排查顺序见上面第三节。

---

## 四、演示数据与账号

| 账号 | 密码 | 怎么来的 |
|---|---|---|
| 后台 `admin` / `kefu` / `caiwu` / `shenhe` | 统一 `123456` | `php bin/hyperf.php db:seed`（角色与菜单见 `server/databases/seeders/zz_cl_menu_seeder_20261014.php`） |
| App 演示号 5 个（`13800000001` 阿宁 男 / `13800000002` 小满 女 / `13800000003` 晚晴 女 / `13800000004` 星野 女 / `13800000005` 林川 男） | 统一 `abc12345` | WSL 里跑 `php scripts/seed-demo.php`（⚠️ **每次重跑 id 都会变**，App 里旧的 token 会失效，登录前先跑一次） |

跑完 seed 就有一套能走通全流程的数据（互关、访客、金币积分、礼物、会员、任务、待审资料……）。

**给客户演示前，再跑一次这个**（不新增账号，只把「客户看了会误会」的几处掰正）：

```bash
wsl -d Ubuntu -u root -- bash -lc 'cd /mnt/c/Workspace/android/vibe/changliao/server && php ../scripts/demo-prepare.php'
```

它做四件事：**① 取消阿宁→小满的互关**（互关后发消息全免费，客户会以为计费坏了）、
**② 发现页只留 5 个演示号**（库里还有 E2E/WS/批量号等测试残留）、
**③ 头像换成能加载的图**（seed 用的 `cdn.example.com` 域名不存在，模拟器里全是裂图）、
**④ 确保阿宁是会员**。结尾会打印当前状态和「怎么让客户看到扣费」——会员每天免 10 条消息，
连发 14 条才扣币，所以要提前想好走哪条路（脚本里列了 A/B/C 三种）。

---

## 五、给客户演示的路线（一页看完）

**登录**：`13800000001` 阿宁（男，476 金币）/ `13800000002` 小满（女，11215 积分），密码 `abc12345`。

按这个顺序讲，客户能自己看出「这是个怎么运转的产品」：

| 顺序 | 页面 | 讲什么 | 看他点什么 |
|---|---|---|---|
| 1 | P01 发现 | 只推异性；三个女号单价不同（5/8/10 币/条） | 切「城市 / 视频 / 语音」Tab |
| 2 | P05 她的主页 | 相册、资料、等级、认证状态 | 点「关注」→ 变成「已关注」 |
| 3 | P06 私信 | **前 3 条免费**，第 4 条开始扣；女方按等级拿分成 | 连发几条，看余额往下掉 |
| 4 | P30 余额不足 | 服务端算「还差多少」，弹窗可跳充值 | 把金币发到不够 |
| 5 | P23 礼物 | 6 档礼物，分成走配置 | 送一朵 10 币的 |
| 6 | P26~P29 收益 | 女方视角：积分怎么来、等级、提现、实名 | **换小满登录**走一遍 |
| 7 | P04 会员 | 权益、档位、每日免费条数 | 看月卡多少钱 |
| 8 | P15 设置 | 黑名单、改密码、注销、安全提醒 | 底部那段防骗提示 |
| 9 | 后台 | 改配置 App 立刻生效 | 把免费条数改成 0 或调价，回 App 刷新 |

**收费链路（服务端算，客户端只显示）**：男号付 5 币 → 小满拿 15 积分（Lv1 分成 30%）
→ 100 积分 = 1 元。这三步都在 T9.1 用真后端对过账。

**语音/视频**：`feature.call_enabled` 现在是 1（开着），页面都能进，但**点「确认拨打」只提示「功能暂未开放」**，
不发请求、不扣币 —— 这是 `docs/01` 第 4 节定的范围，跟客户说清楚这是本期的边界。

---

## 六、跑检查

三端一起跑：

```bash
bash scripts/check-all.sh
```

也可以单独跑：`check-server.sh`（后端测试 + phpstan + 资金扫描，**会自动转发进 WSL**）、`check-admin.sh`（lint + build）、`check-android.sh`（单测 + lint + 打包 + UI 扫描）、`check-money.sh`、`check-ui.sh`。

每个任务收尾必须跑通对应检查并写进 `PROGRESS.md`，详见 [docs/09-测试与自检清单.md](docs/09-测试与自检清单.md)。

两个脚本是**铁律的守卫**，别绕过：`scripts/check-money.sh`（余额变动必须走钱包服务、账本行必须落库）和 `scripts/check-ui.sh`（UI 只能用 `core/designsystem` 里的组件与颜色）。
