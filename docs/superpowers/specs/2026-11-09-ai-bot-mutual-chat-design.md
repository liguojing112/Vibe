# AI 男女互聊（机器人男号 → 指定女号）设计

日期：2026-11-09
状态：待评审

## 一、背景与目标

客户要求：后台可指定一批「机器人男号」，让它们主动给「指定女号」发私信，把女号的消息页面填满；后续运营能自己增删目标女号。

需求经过多轮澄清后，最终形态是**双向 AI 互聊**：

- 男号（机器人）主动发消息给指定女号；
- 目标女号若已开启「AI 代聊」，则由女号 AI 自动回复，形成男女 AI 互聊；未开启则只发开场白（单向）；
- 男号发消息**不扣金币**（免费通道）；
- 女号按现有分成公式产生**虚拟收益**（只计数，**不写入钱包**、不影响可提现余额、不计入等级累计收益）；
- 每个女号每天虚拟收益累计达到 **15 元**，该女号当天全部停止，次日自动重置；
- 所有节奏参数（每对每天轮数、发送间隔、每日时段、上下文条数）均在后台配置，代码中不写死业务数字。

### 明确不做

- 不给女号真实加积分，不产生平台真实支出；
- 不修改现有 `MessageBillingService` 的计费逻辑（真实用户付费链路保持原样）；
- 不做图片、礼物、语音等非文字消息；
- 不做客户端改动（Android App 不感知机器人）。

## 二、现状与约束

### 2.1 现有相关能力

| 能力 | 位置 | 说明 |
|---|---|---|
| 付费私信 | `server/app/Service/Cl/Chat/MessageBillingService.php` | 唯一的发消息入口，男→女必扣金币并给女方积分 |
| 会话维护 | `server/app/Service/Cl/Chat/ConversationService.php` | `lockOrCreate` / `touch`，负责未读和 `male_sent_count` |
| 报价与分成 | `server/app/Service/Cl/Pricing/MessagePricingService.php` | `quote()` / `pointsFor()` / `shareRateOf()` |
| 女号 AI 代聊 | `server/app/Service/Cl/Ai/AiChatService.php` | 男发消息 → 女号自动回，触发点在 Controller |
| 模型调用 | `server/app/Service/Cl/Ai/DeepSeekClient.php` | OpenAI 兼容协议，失败返回 null |
| 推送 | `server/app/Service/Cl/Push/PushService.php` | `message.new` + `unread.update` |
| 机器人男号池 | `server/app/Service/Cl/Admin/AdminBatchService.php` | 批量开号，`cl_user.source=2` 且带 `batch_no` |

### 2.2 三条硬约束

1. **计费链路不能动**。`MessageBillingService` 是全站最敏感的方法（钱只在服务端算），免费通道必须另开路径，不许加 `freeMode` 之类的分支参数。
2. **AI 代聊触发点在 Controller**（`ChatController::send` 第 55 行）。机器人消息如果绕过 Controller 直接落库，天然不会触发女号 AI 回复。但本需求**需要**女号 AI 回复，所以必须显式调用，并在调用前加规则层校验。
3. **`AiChatService::deliver()` 内含 `sleep(2~7 秒)`**（模拟真人打字）。批量场景下会拖慢定时任务，需要评估每轮耗时。

### 2.3 关键换算

- `coin.per_yuan = 10`，`point.per_yuan = 100`
- 女号虚拟收益 = `pointsFor(女号等级 message_price, shareRate)`
- 例：Lv1 女号 `message_price=5`、`message_share_rate=3000`；已实名 `shareRate=3000` → `5×3000×100/(10000×10) = 15` 积分/条；未实名 `shareRate=1500`（×5000/10000）→ 7 积分/条
- 15 元 = 1500 积分 → 已实名约 100 条/天，未实名约 214 条/天

## 三、数据模型

新增 3 张表 + 扩展 1 张现有表。

### 3.1 `cl_bot_chat_rule`（互聊规则）

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint | 主键 |
| name | varchar(64) | 规则名称 |
| enabled | tinyint | 1 启用 0 停用 |
| male_user_ids | json | 手动指定的男号 id 列表 |
| pool_male_count | int | 从机器人号池随机补足的男号数，0 表示不补 |
| window_start | tinyint | 每日时段开始小时（0-23） |
| window_end | tinyint | 每日时段结束小时（0-23），与 start 相同表示全天 |
| round_limit_per_pair | int | 每对男女每天最多几个来回 |
| interval_seconds | int | 同一对之间最小发送间隔（秒） |
| daily_amount_limit_fen | int | 每个女号每天虚拟收益上限（分），默认 1500 |
| context_messages | int | 生成时带多少条历史，默认 10 |
| operator_id | bigint | 创建人 |
| created_at / updated_at | | |

**说明**：`round_limit_per_pair` 与 `interval_seconds` 是节奏参数，`daily_amount_limit_fen` 是终止条件，`context_messages` 控制 AI 上下文成本。

### 3.2 `cl_bot_chat_target`（目标女号）

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint | 主键 |
| rule_id | bigint | 所属规则 |
| female_id | bigint | 女号 id |
| enabled | tinyint | 是否参与 |
| stat_date | date | 统计日期（跨天重置依据） |
| virtual_points_today | int | 今日累计虚拟积分 |
| rounds_today | int | 今日全规则已完成来回数（冗余，便于列表展示） |
| stopped_reason | varchar(32) | daily_limit / null |
| last_sent_at | datetime | 最后一条发送时间 |
| created_at / updated_at | | |

唯一索引 `(rule_id, female_id)`。

### 3.3 `cl_bot_chat_pair`（每对男女状态）

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint | 主键 |
| rule_id | bigint | 所属规则 |
| male_id | bigint | 男号 id |
| female_id | bigint | 女号 id |
| stat_date | date | 统计日期 |
| rounds_today | int | 今日已完成来回数 |
| last_sent_at | datetime | 最后一条发送时间 |
| created_at / updated_at | | |

唯一索引 `(rule_id, male_id, female_id, stat_date)`。

### 3.4 `cl_bot_chat_log`（发送记录）

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint | 主键 |
| rule_id | bigint | 规则 |
| male_id | bigint | 男号 |
| female_id | bigint | 女号 |
| message_id | bigint | 落库后的 `cl_message.id` |
| role | varchar(16) | male_bot / female_ai |
| virtual_points | int | 本条产生的虚拟积分（女 AI 回复为 0） |
| status | tinyint | 1 成功 2 失败 |
| fail_reason | varchar(255) | 失败原因 |
| created_at | datetime | |

索引 `(rule_id, id)`、`(female_id, created_at)`。

### 3.5 复用 `cl_ai_persona`（扩展）

现有表只服务女号（`user_id` 唯一，注释写死「女用户」）。男号人设**复用同一张表**，规则：

- 不加性别字段，靠 `cl_user.gender` 判断归属；
- `uniq_user_id` 已满足「每个男号一套人设」；
- `proactive` / `active_start` / `active_end` / `daily_limit` / `replied_today` 对男号**不生效**（男号的节奏完全由 `cl_bot_chat_rule` 决定），仅保留 `persona` / `tone` / `taboos` / `examples`、`enabled`、token 累计；
- 迁移里更新表注释说明「男女通用，男号只用人设字段」。

## 四、服务设计

### 4.1 新增 `BotChatService`（`server/app/Service/Cl/Ai/BotChatService.php`）

职责：执行一条规则的一次「男号发一条消息」动作。

```
sendOne(rule, target, pair) -> bool
  1. 校验：全局开关、规则启用、时段内、该女号未达 15 元上限
  2. 校验：pair.rounds_today < rule.round_limit_per_pair
  3. 校验：pair.last_sent_at + interval_seconds <= now
  4. 选男号：手动列表 + 号池（首次解析后缓存到规则）
  5. 生成内容：
     - 该对无历史 → 开场白（用男号人设，参考女号资料）
     - 有历史 → 带最近 context_messages 条生成接话
  6. 落库：ConversationService::lockOrCreate + 写 cl_message
       price_coins = 0
       free_reason = 'BOT_MALE'
       receiver_points = 0
       share_rate = 该女号实际分成（仅记录，不结算）
  7. ConversationService::touch
  8. 计算虚拟收益：pointsFor(女号等级 message_price, shareRate)
     累加 target.virtual_points_today
     上限换算：limitPoints = daily_amount_limit_fen × point.per_yuan ÷ 100
     （默认 1500 分 × 100 积分/元 ÷ 100 = 1500 积分 = 15 元）
     若 virtual_points_today >= limitPoints → stopped_reason = daily_limit
  9. 更新 pair.rounds_today / last_sent_at
 10. 写 cl_bot_chat_log
 11. 触发女号 AI 回复（若女号有启用的 AI 人设）
```

**不调用**：`CoinWalletService`、`PointWalletService`、`PlatformIncomeService`、`LevelService`。

### 4.2 女号 AI 回复的约束

现有 `AiChatService::replyTo()` 需要增加一道规则校验，避免绕过轮数和金额上限：

```
新增 AiChatService::replyTo 前置判断
  若这条消息来自机器人规则（cl_message.free_reason = 'BOT_MALE'）
     → 查该会话对应的 rule / target / pair
     → pair.rounds_today >= round_limit_per_pair 时跳过
     → target.virtual_points_today >= 上限 时跳过
```

**注意**：这里必须用 `free_reason` 或新增字段标记来源，不能靠 `sender_id` 是否在号池里推断（号池会变）。

### 4.3 定时任务

新增 `server/app/Crontab/BotChatCrontab.php`，每分钟执行：

```
1. 读全局开关 bot_chat.enabled（cl_config），关闭则退出
2. 取 enabled 且在时段内的规则
3. 遍历规则 → 遍历目标女号（enabled 且未停止）
   a. 跨天重置 stat_date / virtual_points_today / rounds_today
   b. 调 BotChatService::sendOne
4. 每批最多处理 N 对，避免单轮过长
5. 全部完成后统一调用一次 PushService::unreadUpdate（每女号一次）
```

**性能考量**：`AiChatService::deliver()` 内有 2~7 秒 `sleep`。若一轮处理 20 条，最坏 140 秒，超过 1 分钟的调度间隔。处理方式：

- 定时任务只负责**入队**（写 `cl_bot_chat_pending`），由独立协程消费；或
- 直接在任务里发，但限制每轮条数（如 10 条），接受任务执行时间 > 调度间隔（Hyperf crontab 默认不会重叠执行同一任务）。

本设计采用**限制每轮条数**的简单方案，每轮最多 10 条。

### 4.4 全局开关

`cl_config` 新增 `bot_chat.enabled`（group=`ai`，默认 0）。关闭后定时任务立即停止产生新消息，已有消息不受影响。

## 五、后台页面

位置：内容管理 → 新增独立菜单「AI 互聊」（挂在现有「AI 代聊」同级）。

权限：`cl:content:ai:bot:list` / `cl:content:ai:bot:edit`。

### 界面风格约定（沿用现有后台）

新页面必须复用现有组件和写法，不引入新风格：

| 用途 | 复用 |
|---|---|
| 列表页 | `MaProTable` + `MaProTableSchema`（同 `views/user/batch/index.vue`） |
| 用户展示 | `ClUserCell`（头像 + 昵称 + 性别图标 + ID） |
| 状态标签 | `ClStatusTag` / `el-tag` |
| 金额展示 | `ClAmount` |
| 用户详情 | `ClUserDrawer` |
| API 封装 | `src/modules/cl/api/*.ts`，`useHttp()` + `ResponseStruct` |
| 提示 | `useMessage()` + `ResultCode` |
| 按钮鉴权 | `v-auth="['cl:content:ai:bot:edit']"` / `hasAuth()` |

### 5.1 规则列表

列：名称 / 状态 / 男号数 / 目标女号数 / 每对轮数 / 发送间隔 / 时段 / 今日已发 / 操作（启动、暂停、编辑、删除、记录）

### 5.2 新建/编辑弹窗（分 4 步）

用 `el-steps` 分步，避免一屏塞太多。

**步骤 1 · 基本信息**
- 规则名称（`el-input`，必填）
- 启用开关（`el-switch`）

**步骤 2 · 男号与人设**
- 男号多选：`el-select` + `remote` 搜索（按昵称/账号/ID），多选标签展示
- 「从机器人号池随机补足」`el-input-number`（0 = 不补），旁边提示「号池 = 批量开号生成的男号」
- **男号人设**：选中男号后，下方出现可展开的面板列表，每个男号一行：
  - `persona`（`el-input type=textarea rows=3`，1000 字）
  - `tone`（`el-input`，300 字）
  - `taboos`（`el-input`，300 字）
  - `examples`（`el-input type=textarea rows=5`，2000 字，用 `---` 分隔对话）
  - 写入 `cl_ai_persona`（按 `user_id` upsert）

**步骤 3 · 目标女号**
- `el-select` + `remote` 搜索，多选，随时可增删
- 展示已选女号的 `ClUserCell` 列表（带头像），支持单独移除
- 提示「只显示状态正常的女号」

**步骤 4 · 节奏与限制**
- 每对每天轮数：`el-input-number`，默认 20
- 发送间隔：`el-input-number`（秒），默认 30
- 每日时段：两个 `el-select`（0~23 时），相同表示全天
- 每女号每日虚拟收益上限：`el-input-number`（元，两位小数），默认 15
- AI 上下文条数：`el-input-number`，默认 10

底部：上一步 / 下一步 / 保存。

### 5.3 目标女号明细

点规则行的「目标」进入抽屉（`el-drawer`，宽 900px）：

- 顶部统计：目标女号数 / 今日产生消息数 / 今日虚拟收益合计
- 表格列：
  - 女号：`ClUserCell`（点击打开 `ClUserDrawer`）
  - 今日虚拟收益：`ClAmount`（元）
  - 今日轮数
  - 状态：`el-tag`（进行中 / 已达上限）
  - 停止原因
  - 最后发送时间
  - 操作：移除该女号

### 5.4 发送记录

点规则行的「记录」进入抽屉（`el-drawer`，宽 1000px）：

- 筛选：女号（搜索）、日期范围、状态
- 表格列：时间 / 男号（`ClUserCell`）/ 女号（`ClUserCell`）/ 内容摘要（截断 40 字）/ 类型（男号发起 / 女 AI 回复）/ 虚拟收益 / 状态

### 5.5 交互细节

- 列表页顶部：`el-alert` 说明「男号发消息免费，女号收益只做统计不进入钱包」
- 保存前前端校验：至少 1 个男号、至少 1 个女号
- 删除规则：二次确认，提示「历史记录保留，不再产生新消息」
- 所有写操作按钮加 `v-auth`

## 六、风控与安全

1. **性别与状态**：目标女号必须 `gender=2 && status=1`；男号必须 `gender=1 && status=1`
2. **拉黑**：`BlockRepository::blockedEitherWay` 命中则跳过
3. **敏感词**：复用 `SensitiveWordService::hit`，命中照常标记 `sensitive_hit` / `review_status`
4. **上限保护**：单规则男号数、目标女号数、轮数、间隔下限均在服务端校验并设定硬上限
5. **钱包零影响**：`BotChatService` 不注入任何钱包服务。测试中断言：执行后女号 `cl_point_wallet`、`cl_point_log`、`cl_coin_log`、`cl_platform_income`、`cl_user.total_earned_points` 全部无变化
6. **审计**：所有后台写操作记 `cl_admin_action_log`
7. **幂等**：定时任务使用 Redis 分布式锁（`bot_chat:rule:{id}`），避免多 worker 重复执行

## 七、测试

### 7.1 单元/功能测试（`server/tests/Feature/Cl/`）

| 用例 | 断言 |
|---|---|
| 创建规则 | 性别校验、号池补足数量、参数入库、非法值被拒 |
| 时段判断 | 时段外不产生消息；跨零点时段正确 |
| 轮数限制 | 每对达到 `round_limit_per_pair` 后停止；次日重置 |
| 间隔限制 | 未到 `interval_seconds` 不发送 |
| 虚拟收益计算 | 与 `pointsFor` 结果一致；未实名系数生效 |
| 15 元终止 | 达到上限后该女号当天全部停止；其他女号不受影响 |
| 免费通道 | `cl_message.price_coins=0`、`receiver_points=0`、`free_reason=BOT_MALE` |
| 钱包零影响 | `cl_point_wallet` / `cl_point_log` / `cl_coin_log` / `cl_platform_income` / `total_earned_points` 无变化 |
| 会话与未读 | 会话正确建立、`low_unread/high_unread` +1、`last_message_id` 更新 |
| AI 隔离 | 女号未开 AI 时代聊时不产生回复；开启时回复受轮数上限约束 |
| 全局开关 | 关闭后不产生任何消息 |
| 暂停/删除规则 | 不再产生新消息，历史记录保留 |

### 7.2 回归

- 现有 `MessageBillingService` 的 618 个测试保持全绿
- `phpstan` 无错误
- 后台 `lint` 0 error、`build` 成功

## 八、实施顺序建议

1. 迁移：3 张新表 + `cl_ai_persona` 注释更新 + `bot_chat.enabled` 配置项
2. 模型与仓储：`BotChatRule` / `BotChatTarget` / `BotChatPair` / `BotChatLog`
3. `BotChatService`：免费发送 + 虚拟收益 + 轮数控制
4. `AiChatService` 增加机器人来源判断（轮数与上限校验）
5. `BotChatCrontab` + 全局开关 + 分布式锁
6. 后台 API（`AdminBotChatService` + `BotChatController`）
7. 后台 API 前端封装：`admin-web/src/modules/cl/api/botChat.ts`
8. 后台页面：`admin-web/src/modules/cl/views/content/botChat/index.vue`
   + 规则编辑弹窗组件 `BotChatRuleDialog.vue`
   + 明细抽屉 `BotChatTargetDrawer.vue` / 记录抽屉 `BotChatLogDrawer.vue`
9. 菜单与权限迁移（含按钮权限节点）
10. 测试与回归

## 九、待确认事项

无。所有业务口径已确认：

- 15 元按女号虚拟收益累加（已实名约 100 条/天，未实名约 214 条/天）
- 达到上限后该女号当天全部停止，次日重置
- 男号人设每个单独配置
- 上下文带最近 10 条
- 目标女号未开 AI 代聊时只发单向开场白
- 女号收益只计数，不写入钱包、不计入等级
