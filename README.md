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
| 后端 | **WSL2 Ubuntu 24.04**：PHP 8.3.6 + Swoole 5.1.8 + Redis 7 + Composer；MySQL 8 装在 **Windows 宿主**（服务 `MySQL80`，账号 `root` / 密码 `root123`） |
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
cd android && ./gradlew assembleDebug -PapiBaseUrl=http://127.0.0.1:9501 -PwsBaseUrl=ws://127.0.0.1:9502/ws
```

`apiBaseUrl` / `wsBaseUrl` 是 `app/build.gradle.kts` 里给 debug 包加的可覆盖项，**不传就还是 `docs/02` 写的 `10.0.2.2`**，所以正常构建不受影响。

---

## 四、演示数据与账号

| 账号 | 密码 | 怎么来的 |
|---|---|---|
| 后台 `admin` / `kefu` / `caiwu` / `shenhe` | 统一 `123456` | `php bin/hyperf.php db:seed`（角色与菜单见 `server/databases/seeders/zz_cl_menu_seeder_20261014.php`） |
| App 演示号 5 个（`13800000001` 阿宁 男 / `13800000002` 小满 女 / `13800000003` 晚晴 女 / `13800000004` 星野 女 / `13800000005` 林川 男） | 统一 `abc12345` | WSL 里跑 `php scripts/seed-demo.php`（⚠️ **每次重跑 id 都会变**，App 里旧的 token 会失效，登录前先跑一次） |

跑完 seed 就有一套能走通全流程的数据（互关、访客、金币积分、礼物、会员、任务、待审资料……）。

---

## 五、跑检查

三端一起跑：

```bash
bash scripts/check-all.sh
```

也可以单独跑：`check-server.sh`（后端测试 + phpstan + 资金扫描，**会自动转发进 WSL**）、`check-admin.sh`（lint + build）、`check-android.sh`（单测 + lint + 打包 + UI 扫描）、`check-money.sh`、`check-ui.sh`。

每个任务收尾必须跑通对应检查并写进 `PROGRESS.md`，详见 [docs/09-测试与自检清单.md](docs/09-测试与自检清单.md)。

两个脚本是**铁律的守卫**，别绕过：`scripts/check-money.sh`（余额变动必须走钱包服务、账本行必须落库）和 `scripts/check-ui.sh`（UI 只能用 `core/designsystem` 里的组件与颜色）。
