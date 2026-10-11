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

## 一、本机环境（已装好，细节见 PROGRESS.md「环境信息」）

| 端 | 依赖 |
|---|---|
| 后端 | **WSL2 Ubuntu 24.04**：PHP 8.3 + Swoole 5.1 + Redis 7 + Composer；MySQL 8 装在 **Windows 宿主**（服务 `MySQL80`，账号 `root` / 密码 `root123`） |
| 管理后台 | Node 24 + pnpm 10 |
| 安卓 | **JDK 17** + Android SDK（`C:\Users\34252\AppData\Local\Android\Sdk`，平台 android-37） |

> 两个环境要点（改之前先看 PROGRESS.md）：
> 1. **后端跑在 WSL 里**——Windows 没有原生 PHP/Swoole。`C:\Users\34252\.wslconfig` 开了 mirrored 网络，WSL 才能走通本机的 Clash 代理（否则外网域名会被解析成 127.0.0.1）。
> 2. 本机 `dl.google.com` / `maven.google.com` 不可达，安卓构建统一走**阿里云 Maven 镜像**（已配在 `android/settings.gradle.kts`）。

数据库：MySQL 里已有 `changliao`（开发库）与 `cl_test`（测试库），均为 utf8mb4。后端连接信息在 `server/.env`（不提交）。

---

## 二、启动三端

### 1. 后端（在 WSL 终端里执行）

打开 WSL 终端（`wsl -d Ubuntu`），然后：

```bash
cd /mnt/c/Workspace/android/vibe/changliao/server
php bin/hyperf.php start
```

看到 `Server started` 即成功，监听 `http://127.0.0.1:9501`。**Ctrl+C 停止**（也可以后台跑：`setsid nohup php bin/hyperf.php start > /root/build/server.log 2>&1 < /dev/null &`）。

首次安装（换机器时才需要）：

```bash
cd /mnt/c/Workspace/android/vibe/changliao/server
composer install
cp .env.example .env      # 再按 02 文档填 MySQL / Redis
php bin/hyperf.php mine:install
composer require hyperf/websocket-server:"3.2.*"
```

> 注意：Hyperf 的 `start` **没有** `-d` 参数；Swoole 需要 `swoole.use_shortname=Off`（已写进 WSL 的 `/etc/php/8.3/cli/conf.d/20-swoole.ini`）。

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

---

## 三、跑检查

三端一起跑：

```bash
bash scripts/check-all.sh
```

也可以单独跑：`check-server.sh`（后端测试 + phpstan + 资金扫描，**会自动转发进 WSL**）、`check-admin.sh`（lint + build）、`check-android.sh`（单测 + lint + 打包 + UI 扫描）、`check-money.sh`、`check-ui.sh`。

每个任务收尾必须跑通对应检查并写进 `PROGRESS.md`，详见 [docs/09-测试与自检清单.md](docs/09-测试与自检清单.md)。
